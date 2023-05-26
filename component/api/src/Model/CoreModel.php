<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Model;

defined('_JEXEC') || die;

use Exception;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Updater\Updater;
use Joomla\CMS\User\UserHelper;
use Joomla\CMS\Version;
use Joomla\Component\Installer\Administrator\Table\UpdatesiteTable;
use Joomla\Component\Joomlaupdate\Administrator\Model\UpdateModel;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use ReflectionClass;
use Throwable;

class CoreModel extends UpdateModel
{
	private $coreExtensionID = null;

	private $coreUpdateSiteIDs = null;

	use ElementToExtensionIdTrait;

	public function getItem(): ?object
	{
		$mode = $this->getState('panopticon_mode');

		if ($mode === 'core.update')
		{
			$force          = $this->getState('panopticon_force', false);
			$updateInfo     = $this->getJoomlaUpdateInfo($force);
			$updateInfo->id = $this->coreExtensionID ?: 0;

			return $updateInfo;
		}

		return null;
	}

	public function getJoomlaUpdateInfo(bool $force = false): object
	{
		// Get the update parameters from the com_installer configuration
		$params           = ComponentHelper::getComponent('com_installer')->getParams();
		$cacheHours       = (int)$params->get('cachetimeout', 6);
		$cacheTimeout     = 3600 * $cacheHours;
		$minimumStability = (int)$params->get('minimum_stability', Updater::STABILITY_STABLE);

		if (!defined('AKEEBA_PANOPTICON_VERSION'))
		{
			@include_once JPATH_ADMINISTRATOR . '/components/com_panopticon/version.php';
		}

		$version  = defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : '0.0.0-dev1';
		$date     = defined('AKEEBA_PANOPTICON_DATE') ? AKEEBA_PANOPTICON_DATE : gmdate('Y-m-d');
		$apiLevel = defined('AKEEBA_PANOPTICON_API') ? AKEEBA_PANOPTICON_API : 100;

		$jVersion   = new Version();
		$updateInfo = (object)[
			'current'             => $jVersion->getShortVersion(),
			'currentStability'    => $this->detectStability($jVersion->getShortVersion()),
			'latest'              => $jVersion->getShortVersion(),
			'latestStability'     => $this->detectStability($jVersion->getShortVersion()),
			'needsUpdate'         => false,
			'details'             => null,
			'info'                => null,
			'changelog'           => null,
			'extensionAvailable'  => true,
			'updateSiteAvailable' => true,
			'maxCacheHours'       => $cacheHours,
			'minimumStability'    => $this->stabilityToString($minimumStability),
			'updateSiteUrl'       => null,
			'lastUpdateTimestamp' => null,
			'phpVersion'          => PHP_VERSION,
			'panopticon'          => [
				'version' => $version,
				'date'    => $date,
				'api'     => $apiLevel,
			],
		];

		// Get the file_joomla pseudo-extension's ID
		$eid = $this->getCoreExtensionID();

		if ($eid === 0)
		{
			$updateInfo->extensionAvailable  = false;
			$updateInfo->updateSiteAvailable = false;

			return $updateInfo;
		}

		// Get the IDs of the update sites for the core
		$updateSiteIDs = $this->getCoreUpdateSiteIDs();

		if (empty($updateSiteIDs))
		{
			$updateInfo->updateSiteAvailable = false;

			return $updateInfo;
		}

		// Update updateSiteUrl and lastUpdateTimestamp from the first update site for the core pseudo-extension
		/** @var MVCFactory $comInstallerFactory */
		$comInstallerFactory = Factory::getApplication()->bootComponent('com_installer')->getMVCFactory();
		/** @var UpdatesiteTable $updateSiteTable */
		$updateSiteTable = $comInstallerFactory->createTable('Updatesite');
		if ($updateSiteTable->load(
			array_reduce(
				$updateSiteIDs,
				function (int $carry, int $item)
				{
					return min($carry, $item);
				},
				PHP_INT_MAX
			)
		))
		{
			$updateInfo->updateSiteUrl       = $updateSiteTable->location;
			$updateInfo->lastUpdateTimestamp = $updateSiteTable->last_check_timestamp ?: 0;
		}

		// The $force flag tells us to remove update information before fetching the core update status
		if ($force)
		{
			$this->forceCoreUpdateRefresh();
		}

		// This populates the #__updates table records, if necessary
		Updater::getInstance()->findUpdates($eid, $cacheTimeout, $minimumStability, true);

		$db    = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();
		$query = $db->getQuery(true)
					->select($db->quoteName([
						'version',
						'detailsurl',
						'infourl',
						'changelogurl',
					]))
					->from($db->quoteName('#__updates'))
					->where($db->quoteName('extension_id') . ' = :eid')
					->order($db->quoteName('update_id') . ' DESC')
					->bind(':eid', $eid, ParameterType::INTEGER);

		try
		{
			$allLatest = $db->setQuery($query)->loadObjectList() ?: null;
		}
		catch (Throwable $e)
		{
			$allLatest = null;
		}

		$latest = array_reduce(
			$allLatest ?: [],
			function ($carry, $item)
			{
				return is_null($carry)
					? $item
					: (version_compare($carry->version, $item->version, 'lt') ? $item : $carry);
			},
			null
		);

		if (is_object($latest))
		{
			$updateInfo->latest          = $latest->version;
			$updateInfo->latestStability = $this->detectStability($latest->version);
			$updateInfo->details         = $latest->detailsurl;
			$updateInfo->info            = $latest->infourl;
			$updateInfo->changelog       = $latest->changelogurl;
			$updateInfo->needsUpdate     = version_compare($latest->version, $updateInfo->current, 'gt');
		}

		return $updateInfo;
	}

	public function changeUpdateSource(string $updateSource, ?string $updateURL): void
	{
		// Sanity check
		if (!in_array($updateSource, ['nochange', 'next', 'testing', 'custom']))
		{
			return;
		}

		// Get the current parameters
		$params = ComponentHelper::getParams('com_joomlaupdate');

		$currentUpdateSource = $params->get('updatesource');
		$currentUrl          = $params->get('customurl');

		// If there is no change, take no action
		if (
			($currentUpdateSource === $updateSource && $updateSource != 'custom')
			|| ($currentUpdateSource === $updateSource && $updateSource === 'custom' && $currentUrl === $updateURL)
		)
		{
			return;
		}

		// Update the component parameters
		$params->set('updatesource', $updateSource);

		if ($updateSource === 'custom')
		{
			$params->set('customurl', $updateURL);
		}

		// Save the parameters to the database
		$this->saveComponentParameters('com_joomlaupdate', $params);
	}

	/**
	 * This method had to be forked to avoid the use of JPATH_ADMINISTRATOR_COMPONENT
	 *
	 * @param   string|null  $basename
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function createUpdateFile($basename = null): bool
	{
		// Load overrides plugin.
		PluginHelper::importPlugin('installer');

		// Get a password
		$password = UserHelper::genRandomPassword(32);
		$app      = Factory::getApplication();

		// Trigger event before joomla update.
		$app->triggerEvent('onJoomlaBeforeUpdate');

		// Get the absolute path to site's root.
		$siteroot = JPATH_SITE;

		// If the package name is not specified, get it from the update info.
		if (empty($basename))
		{
			$updateInfo = $this->getUpdateInformation();
			$packageURL = $updateInfo['object']->downloadurl->_data;
			$basename   = basename($packageURL);
		}

		if (empty($basename))
		{
			throw new \RuntimeException('The update package\'s file name cannot be determined automatically.', 400);
		}

		// Get the package name.
		$config  = $app->getConfig();
		$tempdir = $config->get('tmp_path');
		$file    = $tempdir . '/' . $basename;

		$filesize = @filesize($file);
		$this->setState('password', $password);
		$this->setState('filesize', $filesize);
		$this->setState('file', $basename);
		$app->setUserState('com_joomlaupdate.password', $password);
		$app->setUserState('com_joomlaupdate.filesize', $filesize);

		if (version_compare(JVERSION, '4.0.4', 'lt'))
		{
			$data = "<?php\ndefined('_AKEEBA_RESTORATION') or die('Restricted access');\n";
			$data .= '$restoration_setup = array(' . "\n";
			$data .= <<<ENDDATA
	'kickstart.security.password' => '$password',
	'kickstart.tuning.max_exec_time' => '5',
	'kickstart.tuning.run_time_bias' => '75',
	'kickstart.tuning.min_exec_time' => '0',
	'kickstart.procengine' => 'direct',
	'kickstart.setup.sourcefile' => '$file',
	'kickstart.setup.destdir' => '$siteroot',
	'kickstart.setup.restoreperms' => '0',
	'kickstart.setup.filetype' => 'zip',
	'kickstart.setup.dryrun' => '0',
	'kickstart.setup.renamefiles' => array(),
	'kickstart.setup.postrenamefiles' => false
ENDDATA;

			$data .= ');';

			$configpath = JPATH_ADMINISTRATOR . '/components/com_joomlaupdate/restoration.php';
		}
		else
		{
			$data = "<?php\ndefined('_JOOMLA_UPDATE') or die('Restricted access');\n";
			$data .= '$extractionSetup = [' . "\n";
			$data .= <<<ENDDATA
	'security.password' => '$password',
	'setup.sourcefile' => '$file',
	'setup.destdir' => '$siteroot',
ENDDATA;

			$data .= '];';

			// Remove the old file, if it's there...
			$configpath = JPATH_ADMINISTRATOR . '/components/com_joomlaupdate/update.php';
		}


		if (File::exists($configpath))
		{
			if (!File::delete($configpath))
			{
				File::invalidateFileCache($configpath);
				@unlink($configpath);
			}
		}

		// Write new file. First try with File.
		$result = File::write($configpath, $data);

		// In case File used FTP but direct access could help.
		if (!$result)
		{
			if (function_exists('file_put_contents'))
			{
				$result = @file_put_contents($configpath, $data);

				if ($result !== false)
				{
					$result = true;
				}
			}
			else
			{
				$fp = @fopen($configpath, 'wt');

				if ($fp !== false)
				{
					$result = @fwrite($fp, $data);

					if ($result !== false)
					{
						$result = true;
					}

					@fclose($fp);
				}
			}
		}

		return $result;
	}

	public function removeExtractPasswordFile()
	{
		$basePath = JPATH_ADMINISTRATOR . '/components/com_joomlaupdate';

		if (File::exists($basePath . '/update.php'))
		{
			File::delete($basePath . '/update.php');
		}

		if (File::exists($basePath . '/restoration.php'))
		{
			File::delete($basePath . '/restoration.php');
		}
	}

	/**
	 * This method had to be forked to avoid the use of JPATH_ADMINISTRATOR_COMPONENT
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function cleanUp()
	{
		$basePath = JPATH_ADMINISTRATOR . '/components/com_joomlaupdate';

		// Load overrides plugin.
		PluginHelper::importPlugin('installer');

		$app = Factory::getApplication();

		// Trigger event after joomla update.
		$app->triggerEvent('onJoomlaAfterUpdate');

		// Remove the update package.
		$tempdir = $app->get('tmp_path');
		$file    = $app->getUserState('com_joomlaupdate.file', null) ?: $this->getZipFilenameFromPasswordFile();

		if (!empty($file))
		{
			File::delete($tempdir . '/' . $file);
		}

		// Remove the update.php file used in Joomla 4.0.3 and later.
		if (File::exists($basePath . '/update.php'))
		{
			File::delete($basePath . '/update.php');
		}

		// Remove the legacy restoration.php file (when updating from Joomla 4.0.2 and earlier).
		if (File::exists($basePath . '/restoration.php'))
		{
			File::delete($basePath . '/restoration.php');
		}

		// Remove the legacy restore_finalisation.php file used in Joomla 4.0.2 and earlier.
		if (File::exists($basePath . '/restore_finalisation.php'))
		{
			File::delete($basePath . '/restore_finalisation.php');
		}

		// Remove joomla.xml from the site's root.
		if (File::exists(JPATH_ROOT . '/joomla.xml'))
		{
			File::delete(JPATH_ROOT . '/joomla.xml');
		}

		// Unset the update filename from the session.
		$app = Factory::getApplication();
		$app->setUserState('com_joomlaupdate.file', null);
		$oldVersion = $app->getUserState('com_joomlaupdate.oldversion');

		// Trigger event after joomla update.
		$app->triggerEvent('onJoomlaAfterUpdate', [$oldVersion]);
		$app->setUserState('com_joomlaupdate.oldversion', null);
	}


	private function getCoreExtensionID(): int
	{
		return $this->coreExtensionID =
			$this->coreExtensionID ?? (
		$this->getExtensionIdFromElement('files_joomla') ?: 0
		);
	}

	private function getCoreUpdateSiteIDs(): array
	{
		if ($this->coreUpdateSiteIDs !== null)
		{
			return $this->coreUpdateSiteIDs;
		}

		$eid = $this->getCoreExtensionID();

		if ($eid === 0)
		{
			$this->coreUpdateSiteIDs = [];

			return $this->coreUpdateSiteIDs;
		}

		$db    = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();
		$query = $db->getQuery(true)
					->select($db->quoteName('update_site_id'))
					->from($db->quoteName('#__update_sites_extensions'))
					->where($db->quoteName('extension_id') . ' = :eid')
					->bind(':eid', $eid, ParameterType::INTEGER);

		try
		{
			$this->coreUpdateSiteIDs = $db->setQuery($query)->loadColumn() ?: [];
		}
		catch (Throwable $e)
		{
			$this->coreUpdateSiteIDs = [];
		}

		if (empty($this->coreUpdateSiteIDs))
		{
			return $this->coreUpdateSiteIDs;
		}

		// Get enabled core update sites.
		$query = $db->getQuery(true)
					->select($db->quoteName('update_site_id'))
					->from($db->quoteName('#__update_sites'))
					->whereIn($db->quoteName('update_site_id'), $this->coreUpdateSiteIDs, ParameterType::INTEGER)
					->where($db->quoteName('enabled') . ' = 1');

		try
		{
			$this->coreUpdateSiteIDs = $db->setQuery($query)->loadColumn() ?: [];
		}
		catch (Exception $e)
		{
			$this->coreUpdateSiteIDs = [];
		}

		return $this->coreUpdateSiteIDs;
	}

	private function forceCoreUpdateRefresh(): void
	{
		// Get the core extension ID
		$eid = $this->getCoreExtensionID();

		if ($eid === 0)
		{
			return;
		}

		// Get the core update site IDs
		$updateSiteIDs = $this->getCoreUpdateSiteIDs();

		if (empty($updateSiteIDs))
		{
			return;
		}

		// Get a database object
		$db = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();

		// Clear update records
		$query = $db->getQuery(true)
					->delete($db->quoteName('#__updates'))
					->where($db->quoteName('extension_id') . ' = :eid')
					->bind(':eid', $eid, ParameterType::INTEGER);

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Throwable $e)
		{
			// Swallow the exception.
		}

		// Reset last check timestamp on update site
		$query = $db->getQuery(true)
					->update($db->quoteName('#__update_sites'))
					->set($db->quoteName('last_check_timestamp') . ' = 0')
					->whereIn($db->quoteName('update_site_id'), $updateSiteIDs, ParameterType::INTEGER);

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Throwable $e)
		{
			// Swallow the exception.
		}
	}

	private function stabilityToString(int $stability): string
	{
		switch ($stability)
		{
			case 0:
				return "dev";

			case 1:
				return "alpha";

			case 2:
				return "beta";

			case 3:
				return "rc";

			case 4:
			default:
				return "stable";
		}
	}

	private function detectStability(string $versionString): string
	{
		$version = \z4kn4fein\SemVer\Version::parse($versionString, false);
		$tag     = strtolower($version->getPreRelease() ?: '');

		if ($tag === '')
		{
			return 'stable';
		}

		if (str_starts_with($tag, 'alpha'))
		{
			return 'alpha';
		}

		if (str_starts_with($tag, 'beta'))
		{
			return 'beta';
		}

		if (str_starts_with($tag, 'rc'))
		{
			return 'rc';
		}

		return 'dev';
	}

	private function saveComponentParameters(string $component, Registry $params): void
	{
		/** @var DatabaseDriver $db */
		$db           = Factory::getContainer()->get('DatabaseDriver');
		$paramsString = $params->toString('JSON');
		$query        = $db->getQuery(true)
						   ->update($db->quoteName('#__extensions'))
						   ->set($db->quoteName('params') . ' = :params')
						   ->where(
							   [
								   $db->quoteName('element') . ' = :component',
								   $db->quoteName('type') . ' = ' . $db->quote('component'),
							   ]
						   )
						   ->bind(':params', $paramsString, ParameterType::STRING)
						   ->bind(':component', $component, ParameterType::STRING);

		$db->setQuery($query)->execute();

		// Clear the _system cache
		$this->clearCacheGroup('_system');

		// Update internal Joomla data
		$refClass = new ReflectionClass(ComponentHelper::class);
		$refProp  = $refClass->getProperty('components');
		$refProp->setAccessible(true);

		$components                     = $refProp->getValue();
		$components[$component]->params = $params;

		$refProp->setValue($components);
	}

	private function clearCacheGroup(string $group): void
	{
		$app = Factory::getApplication();

		$options = [
			'defaultgroup' => $group,
			'cachebase'    => $app->get('cache_path', JPATH_CACHE),
			'result'       => true,
		];

		$cacheControllerFactory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);

		try
		{
			$cacheControllerFactory
				->createCacheController('callback', $options)
				->cache
				->clean();
		}
		catch (Throwable $e)
		{
			// Do nothing
		}
	}

	private function getZipFilenameFromPasswordFile(): ?string
	{
		// Joomla 4.0.0 to 4.0.3 — using the old version of Akeeba Restore I contributed to the core back in 2011.
		if (version_compare(JVERSION, '4.0.4', 'lt'))
		{
			if (!defined('_AKEEBA_RESTORATION'))
			{
				define('_AKEEBA_RESTORATION', 1);
			}

			$configPath = JPATH_ADMINISTRATOR . '/components/com_joomlaupdate/restoration.php';

			if (is_file($configPath) && is_readable($configPath))
			{
				try
				{
					@include_once $configPath;
				}
				catch (Throwable $e)
				{
					return null;
				}
			}

			return $restoration_setup['kickstart.setup.sourcefile'] ?? null;
		}

		// Joomla 4.0.4 or later — using the modern extract.php I contributed to the core back in late 2020.
		if (!defined('_JOOMLA_UPDATE'))
		{
			define('_JOOMLA_UPDATE', 1);
		}

		$configPath = JPATH_ADMINISTRATOR . '/components/com_joomlaupdate/update.php';

		if (is_file($configPath) && is_readable($configPath))
		{
			try
			{
				@include_once $configPath;
			}
			catch (Throwable $e)
			{
				return null;
			}
		}

		return $extractionSetup['setup.sourcefile'] ?? null;
	}
}