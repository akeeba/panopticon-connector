<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Model;

defined('_JEXEC') || die;

use Akeeba\Component\Panopticon\Api\Library\ServerInfo;
use Exception;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Updater\Updater;
use Joomla\CMS\User\UserHelper;
use Joomla\CMS\Version;
use Joomla\Component\Installer\Administrator\Table\UpdatesiteTable;
use Joomla\Component\Joomlaupdate\Administrator\Model\UpdateModel;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Http\HttpFactory;
use Joomla\Registry\Registry;
use ReflectionClass;
use RuntimeException;
use Throwable;

class CoreModel extends UpdateModel
{
	private const DEBUG_CHUNKED_DOWNLOAD = false;

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
		$cParams       = ComponentHelper::getComponent('com_panopticon')->getParams();
		$sysInfoToggle = $cParams->get('sysinfo', 1);

		// Get the update parameters from the com_installer configuration
		$params           = ComponentHelper::getComponent('com_installer')->getParams();
		$cacheHours       = (int) $params->get('cachetimeout', 6);
		$cacheTimeout     = 3600 * $cacheHours;
		$minimumStability = (int) $params->get('minimum_stability', Updater::STABILITY_STABLE);

		if (!defined('AKEEBA_PANOPTICON_VERSION'))
		{
			@include_once JPATH_ADMINISTRATOR . '/components/com_panopticon/version.php';
		}

		$version  = defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : '0.0.0-dev1';
		$date     = defined('AKEEBA_PANOPTICON_DATE') ? AKEEBA_PANOPTICON_DATE : gmdate('Y-m-d');
		$apiLevel = defined('AKEEBA_PANOPTICON_API') ? AKEEBA_PANOPTICON_API : 100;

		$jVersion   = new Version();
		$updateInfo = (object) [
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
			'overridesChanged'    => $this->getNumberOfTemplateOverridesChanged(),
			'panopticon'          => [
				'version' => $version,
				'date'    => $date,
				'api'     => $apiLevel,
			],
			'admintools'          => $this->getAdminToolsInformation(),
			'serverInfo'          => $sysInfoToggle ? (new ServerInfo($this->getDatabase()))() : null,
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
				function (int $carry, int $item) {
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
			->select(
				$db->quoteName(
					[
						'version',
						'detailsurl',
						'infourl',
						'changelogurl',
					]
				)
			)
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
			function ($carry, $item) {
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

	public function useAdminToolsResetJoomlaUpdate(): bool
	{
		// Make sure the component is installed
		$adminToolsExtensionRecord = ComponentHelper::getComponent('com_admintools', true);

		if (!$adminToolsExtensionRecord->enabled)
		{
			return false;
		}

		// Try to get the JupdateModel model
		try
		{
			$model = Factory::getApplication()
				->bootComponent('com_admintools')
				->getMVCFactory()
				->createModel('Jupdate', 'Administrator');
		}
		catch (Throwable $e)
		{
			return false;
		}

		if (!$model instanceof BaseModel)
		{
			return false;
		}

		if (!method_exists($model, 'resetJoomlaUpdate'))
		{
			return false;
		}

		try
		{
			$model->resetJoomlaUpdate();
		}
		catch (Throwable $e)
		{
			return false;
		}

		return true;
	}

	public function affirmCoreUpdateRecord()
	{
		$coreExtension = ExtensionHelper::getExtensionRecord('joomla', 'file');

		if (empty($coreExtension) || !is_object($coreExtension))
		{
			return;
		}

		$id = $coreExtension->extension_id;

		if (empty($id))
		{
			return;
		}

		$db = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();

		// Is there an update site record?
		$query = $db
			->getQuery(true)
			->select($db->quoteName('us.update_site_id'))
			->from($db->quoteName('#__update_sites_extensions', 'map'))
			->join(
				'INNER',
				$db->quoteName('#__update_sites', 'us'),
				$db->quoteName('us.update_site_id') . ' = ' . $db->quoteName('map.update_site_id')
			)
			->where($db->quoteName('map.extension_id') . ' = :id')
			->bind(':id', $id, ParameterType::INTEGER);

		$usId = $db->setQuery($query)->loadResult();

		if (!empty($usId))
		{
			return;
		}

		// Create an update site record.
		$o = (object) [
			'update_site_id'       => null,
			'name'                 => 'Joomla! Core',
			'type'                 => 'collection',
			'location'             => '',
			'enabled'              => 1,
			'last_check_timestamp' => 0,
			'extra_query'          => '',
		];

		$db->insertObject('#__update_sites', $o, 'update_site_id');

		// Delete old map records
		$query = $db
			->getQuery(true)
			->delete($db->quoteName('#__update_sites_extensions'))
			->where($db->quoteName('update_site_id') . ' = :extension_id')
			->bind(':extension_id', $id);
		$db->setQuery($query)->execute();

		// Create an update site to extension ID map record
		$o2 = (object) [
			'update_site_id' => $o->update_site_id,
			'extension_id'   => $id,
		];

		$db->insertObject('#__update_sites_extensions', $o2);
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
			throw new RuntimeException('The update package\'s file name cannot be determined automatically.', 400);
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

	/**
	 * @return array{basename:string|false, url:string|null, size:int, offset:int, chunk_index:int, done:bool, error:
	 *                                      string|null}
	 * @throws Exception
	 * @since  1.0.0
	 */
	public function downloadChunkedUpdateByPanopticon(): array
	{
		/**
		 * Tobias Zulauf needlessly undermined \Joomla\CMS\Updater\Update to no longer return the downloadSources, as
		 * he could not correctly troubleshoot the code (hint: he should be emptying $this->downloadSources every time
		 * _startElement is called with $name === 'UPDATE').
		 *
		 * So, instead of using $this->>getUpdateInformation() we have to REINVENT THE WHEEL.
		 */

		$chunkSizes = [51200, 153600, 262144, 524288, 1048576, 2097152, 5242880, 10485760];
		$totalStart = microtime(true);

		$url        = $this->getState('download.url') ?: $this->getURLForChunkedDownloads();
		$size       = $this->getState('download.size')
			?: call_user_func(
				function (?string $url) {
					if (empty($url))
					{
						return -1;
					}

					try
					{
						$version    = new Version;
						$httpOption = new Registry;
						$httpOption->set('userAgent', $version->getUserAgent('Joomla', true, false));
						$http     = (new HttpFactory())->getHttp($httpOption);
						$response = $http->head($url);

						if ($response->code != 200)
						{
							return -1;
						}

						$headers = $response->getHeaders();

						$contentType   = $headers['content-type'] ?? [];
						$contentType   = is_array($contentType) ? $contentType : [$contentType];
						$acceptRanges  = $headers['accept-ranges'] ?? [];
						$acceptRanges  = is_array($acceptRanges) ? $acceptRanges : [$acceptRanges];
						$contentLength = $headers['content-length'] ?? [-1];
						$contentLength = is_array($contentLength) ? $contentLength : [$contentLength];

						if (!in_array('application/zip', $contentType) || !in_array('bytes', $acceptRanges))
						{
							return -1;
						}

						$contentLength = array_shift($contentLength);

						return (int) ($contentLength ?: -1);
					}
					catch (Exception $e)
					{
						return -1;
					}
				}, $url
			);
		$offset     = (int) $this->getState('download.offset') ?: -1;
		$chunkIndex = (int) $this->getState('download.chunk_index') ?: 1;
		$maxTime    = (float) $this->getState('download.max_time', 10);
		$chunkIndex = max(0, min($chunkIndex, count($chunkSizes) - 1));
		$basename   = empty($url) ? false : basename($url);

		$result = [
			'basename'    => $basename,
			'url'         => $url,
			'size'        => $size,
			'offset'      => $offset,
			'chunk_index' => $chunkIndex,
			'done'        => true,
			'error'       => null,
		];

		if ($result['size'] <= 0)
		{
			$result['error'] = 'Could not find a file to download.';

			return $result;
		}

		// Update the timer
		$totalElapsed = microtime(true) - $totalStart;

		// Update the basename
		$result['basename'] = basename($url);

		// Create an HTTP client
		$version    = new Version;
		$httpOption = new Registry;
		$httpOption->set('userAgent', $version->getUserAgent('Joomla', true, false));
		$http = (new HttpFactory())->getHttp($httpOption);

		// Open the output file.
		$tempDir = Factory::getApplication()->get('tmp_path');
		$outFile = $tempDir . '/' . $basename;

		try
		{
			if ($offset > 0)
			{
				clearstatcache(false, $outFile);

				if (!file_exists($outFile))
				{
					throw new RuntimeException(
						sprintf(
							'The Joomla update package file %s went away',
							basename($outFile)
						)
					);
				}

				$filesize = @filesize($outFile) ?: 0;

				if ($filesize < $offset)
				{
					throw new RuntimeException(
						sprintf(
							'The Joomla update package file %s is smaller than expected (expected: %d bytes; current: %d bytes)',
							basename($outFile), $offset, $filesize
						)
					);
				}
			}

			$fp = @fopen($outFile, 'ab');

			if ($fp === false)
			{
				throw new RuntimeException('Cannot open output file for writing.');
			}

			// If there's an offset make sure the file size isn't beyond that offset and get ready to append data.
			@ftruncate($fp, max($offset, 0));
			@fseek($fp, max($offset, 0));

		}
		catch (Exception $e)
		{
			$result['error'] = $e->getMessage();

			if ($fp !== false && $fp !== null)
			{
				@fclose($fp);
			}

			return $result;
		}

		// Indicate download is not done
		$result['done'] = false;

		$debug = [];

		// Start downloading chunks while we have some time
		while ($totalElapsed < $maxTime)
		{
			$chunkSize = $chunkSizes[$chunkIndex];

			// Calculate the current start / end byte
			$from = max(0, $offset + 1);
			$to   = min($from + $chunkSize, $size);

			// If the start byte is beyond the content-length we read from the HEAD: break
			if ($from > $size)
			{
				$result['done'] = true;

				break;
			}

			$startTime = microtime(true);

			// Download the chunk and append to file
			try
			{
				$response = $http->get(
					$url, [
						'Range' => sprintf('bytes=%d-%d', $from, $to),
					]
				);

				if ($response->code != 200 && $response->code != 206)
				{
					throw new RuntimeException(sprintf('Invalid HTTP response code: %d', $response->code));
				}

				$chunk = $response->getBody();

				if (empty($chunk))
				{
					throw new RuntimeException(
						sprintf('No data returned from the remote URL (byte range %d-%d)', $from, $to)
					);
				}

				$nominalLength = strlen($chunk);
				$written       = fwrite($fp, $chunk);
				$chunk         = null;

				if ($written < $nominalLength)
				{
					throw new RuntimeException(
						sprintf(
							'File write failed. Expected to write %d bytes, written %d bytes instead. Check if the server has enough disk space.',
							$nominalLength, $written ?: 0
						)
					);
				}

				$offset           += $written;
				$result['offset'] = $offset;

				/**
				 * We use $result['size'] - 1 because the first byte written is the byte with offset zero.
				 *
				 * Simply put, if I read two bytes, my offset is 1, not 2.
				 */
				if ($offset >= $result['size'] - 1)
				{
					$result['done'] = true;

					break;
				}
			}
			catch (Exception $e)
			{
				fclose($fp);

				@unlink($outFile);

				$result['error'] = $e->getMessage();

				return $result;
			}

			// Get the timing information
			$endTime           = microtime(true);
			$timeElapsed       = $endTime - $startTime;
			$totalElapsed      = $endTime - $totalStart;
			$projectedNextTime = $timeElapsed;

			$debugEntry = [
				'from'       => $from,
				'to'         => $to,
				'elapsed'    => $timeElapsed,
				'chunkSize'  => $chunkSize,
				'chunkIndex' => $chunkIndex,
				'decision'   => 'hold',
			];

			if ($timeElapsed < 2.0 && $chunkIndex >= count($chunkSizes) - 1)
			{
				// We are using the maximum chunk size, but it's still too fast. Slow down to prevent CloudFlare blocking us.
				$sleepTime = 2.0 - $timeElapsed;

				if (function_exists('usleep'))
				{
					try
					{
						usleep($timeElapsed * 1000000);
					}
					catch (Throwable $e)
					{
						// Ignore.
					}
				}

				$debugEntry['decision']  = 'sleep';
				$debugEntry['sleepTime'] = $sleepTime;
			}
			elseif ($timeElapsed < 2.0)
			{
				// Try to increase the chunk size so that the next chunk takes around 2 to 4 seconds to complete.
				$addFactor         = max(1, ceil(sqrt(2.0 / $timeElapsed)));
				$chunkIndex        = ($addFactor + $chunkIndex) >= count($chunkSizes)
					? (count($chunkSizes) - 1)
					: ($addFactor + $chunkIndex);
				$projectedNextTime = pow(2.0, $addFactor) * $timeElapsed;

				$debugEntry['decision']      = 'increase';
				$debugEntry['addFactor']     = $addFactor;
				$debugEntry['projectedTime'] = $projectedNextTime;
			}
			elseif ($timeElapsed > 4.0)
			{
				// The download was too slow. Halve the chunk size.
				$chunkIndex        = max(0, $chunkIndex - 1);
				$projectedNextTime = $projectedNextTime / 2.0;

				$debugEntry['decision']      = 'decrease';
				$debugEntry['projectedTime'] = $projectedNextTime;
			}

			if (self::DEBUG_CHUNKED_DOWNLOAD)
			{
				$debug[] = $debugEntry;
			}

			// Update the return array's `chunk_index`
			$result['chunk_index'] = $chunkIndex;

			// If we might time out in the next step: break early.
			if ($totalElapsed + $projectedNextTime > $maxTime)
			{
				break;
			}
		}

		@fclose($fp);

		if (self::DEBUG_CHUNKED_DOWNLOAD)
		{
			$result['debug'] = $debug;
		}

		return $result;
	}

	private function getURLForChunkedDownloads(): ?string
	{
		// Joomla! 5.1.0 and later uses TUF, therefore we have to go through the model and hope for the best
		if (version_compare(JVERSION, '5.0.999999', 'gt'))
		{
			$updateInfo = $this->getUpdateInformation();

			if (!is_array($updateInfo) || !isset($updateInfo['object'])
			    || !is_object($updateInfo['object'])
			    || !isset($updateInfo['object']->downloadurl)
			    || !is_object($updateInfo['object']->downloadurl)
			    || !isset($updateInfo['object']->downloadurl->_data))
			{
				return null;
			}

			return $updateInfo['object']->downloadurl->_data;
		}

		/**
		 * On Joomla! 4.0 through 5.0 we implement our own thing which reads the XML update file, extracts the download
		 * URLs, removes the GitHub URL, and returns the first URL remaining which is the CDN URL.
		 */

		// Fetch the update information from the database.
		$id    = ExtensionHelper::getExtensionRecord('joomla', 'file')->extension_id;
		$db    = version_compare(JVERSION, '4.2.0', 'lt') ? $this->getDbo() : $this->getDatabase();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__updates'))
			->where($db->quoteName('extension_id') . ' = :id')
			->bind(':id', $id, ParameterType::INTEGER);
		$db->setQuery($query);
		$updateObject = $db->loadObject();

		// No update? No joy.
		if (is_null($updateObject))
		{
			return null;
		}

		// Load the XML file from the `detailsurl` of the update object.
		try
		{
			$version    = new Version;
			$httpOption = new Registry;
			$httpOption->set('userAgent', $version->getUserAgent('Joomla', true, false));
			$http     = (new HttpFactory())->getHttp($httpOption);
			$response = $http->get($updateObject->detailsurl);
		}
		catch (RuntimeException $e)
		{
			return null;
		}

		if ($response->code !== 200)
		{
			return null;
		}

		// Use SimpleXML to parse the raw XML data
		try
		{
			$xml = new \SimpleXMLElement($response->body);
		}
		catch (Exception $e)
		{
			return null;
		}

		// Extract the download URLs into an array
		$expression      = sprintf(
			'//update[version="%s"]/downloads/downloadsource[@type="full" and @format="zip"]', $updateObject->version
		);
		$downloadSources = $xml->xpath($expression);

		if (!count($downloadSources))
		{
			$expression      = sprintf(
				'//update[version="%s"]/downloads/downloadurl[@type="full" and @format="zip"]', $updateObject->version
			);
			$downloadSources = $xml->xpath($expression);
		}

		if (!count($downloadSources))
		{
			return null;
		}

		$urls = [];

		foreach ($downloadSources as $derp)
		{
			$urls[] = (string) $derp;
		}

		// Keep unique values and filter what is left
		$urls = array_unique($urls);
		$urls = array_filter(
			$urls,
			function ($url) {
				return !empty($url) && stripos($url, 'update') !== false && stripos($url, '/github.com/') === false;
			}
		);

		if (empty($urls))
		{
			return null;
		}

		return array_shift($urls);
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

		if (strpos($tag, 'alpha') === 0)
		{
			return 'alpha';
		}

		if (strpos($tag, 'beta') === 0)
		{
			return 'beta';
		}

		if (strpos($tag, 'rc') === 0)
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

	private function getAdminToolsInformation(): ?object
	{
		$ret = (object) [
			'enabled'      => false,
			'renamed'      => false,
			'secret_word'  => null,
			'admindir'     => 'administrator',
			'awayschedule' => (object) [
				'timezone' => 'UTC',
				'from'     => null,
				'to'       => null,
			],
		];

		if (!ComponentHelper::isEnabled('com_admintools'))
		{
			return $ret;
		}

		$ret->enabled                = true;
		$ret->renamed                = !@file_exists(JPATH_PLUGINS . '/system/admintools/services/provider.php');
		$registry                    = $this->getAdminToolsConfigRegistry();
		$ret->secret_word            = $registry->get('adminpw') ?: null;
		$ret->admindir               = $registry->get('adminlogindir', 'administrator') ?: 'administrator';
		$ret->awayschedule->timezone = Factory::getApplication()->get('offset', 'UTC');
		$ret->awayschedule->from     = $registry->get('awayschedule_from') ?: null;
		$ret->awayschedule->to       = $registry->get('awayschedule_from') ?: null;

		return $ret;
	}

	private function getAdminToolsConfigRegistry(): ?Registry
	{
		$db    = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('value'))
			->from($db->quoteName('#__admintools_storage'))
			->where($db->quoteName('key') . ' = ' . $db->quote('cparams'));
		try
		{
			$json = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			return new Registry();
		}

		if (empty($json))
		{
			return new Registry();
		}

		try
		{
			return new Registry($json);
		}
		catch (Exception $e)
		{
			return new Registry();
		}
	}

	private function getNumberOfTemplateOverridesChanged(): int
	{
		$db       = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();
		$subQuery = $db->getQuery(true)
			->select('1')
			->from($db->quoteName('#__extensions', 'e'))
			->where($db->quoteName('e.extension_id') . ' = ' . $db->quoteName('o.extension_id'));
		$query    = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__template_overrides', 'o'))
			->where(
				[
					$db->quoteName('o.state') . ' = 0',
					$db->quoteName('o.client_id') . ' = 0',
					'EXISTS(' . $subQuery . ')',
				]
			);

		try
		{
			return $db->setQuery($query)->loadResult() ?: 0;
		}
		catch (Exception $e)
		{
			return 0;
		}
	}
}