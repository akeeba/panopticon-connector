<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller\Mixit;


use Akeeba\PanopticonConnector\Version\Version as VersionParser;
use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Updater\Updater;
use Joomla\CMS\Version;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Throwable;

defined('_JEXEC') || die;

trait JoomlaUpdateTrait
{
	private $coreExtensionID = null;

	private $coreUpdateSiteIDs = null;

	public function getJoomlaUpdateInfo(bool $force = false): object
	{
		// Get the update parameters from the com_installer configuration
		$params           = ComponentHelper::getComponent('com_installer')->getParams();
		$cacheHours       = (int) $params->get('cachetimeout', 6);
		$cacheTimeout     = 3600 * $cacheHours;
		$minimumStability = (int) $params->get('minimum_stability', Updater::STABILITY_STABLE);

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
			'panopticon'          => [
				'version' => $version,
				'date'    => $date,
				'api'     => $apiLevel,
			],
			'admintools'          => $this->getAdminToolsInformation(),
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

		// TODO Mama mia!

		// Update updateSiteUrl and lastUpdateTimestamp from the first update site for the core pseudo-extension
		/** @var \JTableUpdatesite $updateSiteTable */
		$updateSiteTable = Table::getInstance('Updatesite');

		if ($updateSiteTable->load(
			array_reduce(
				$updateSiteIDs,
				function (int $carry, int $item) {
					return min($carry, $item);
				},
				PHP_INT_MAX
			)
		))
			/** @noinspection PhpUndefinedFieldInspection */
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

		$db    = Factory::getDbo();
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
			->where($db->quoteName('extension_id') . ' = ' . $eid)
			->order($db->quoteName('update_id') . ' DESC');

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

	private function detectStability(string $versionString): string
	{
		$version = VersionParser::create($versionString);

		if ($version->isStable())
		{
			return 'stable';
		}

		if ($version->isAlpha())
		{
			return 'alpha';
		}

		if ($version->isBeta())
		{
			return 'beta';
		}

		if ($version->isRC())
		{
			return 'rc';
		}

		return 'dev';
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
		$ret->renamed                = !@file_exists(JPATH_PLUGINS . '/system/admintools/admintools/main.php');
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
		$db    = Factory::getDbo();
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

		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('update_site_id'))
			->from($db->quoteName('#__update_sites_extensions'))
			->where($db->quoteName('extension_id') . ' = ' . $eid);

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

		$coreUpdateSiteIDs = ArrayHelper::toInteger($this->coreUpdateSiteIDs);
		$coreUpdateSiteIDs = array_map([$db, 'quote'], $coreUpdateSiteIDs);
		$coreUpdateSiteIDs = implode(',', $coreUpdateSiteIDs);

		// Get enabled core update sites.
		$query = $db->getQuery(true)
			->select($db->quoteName('update_site_id'))
			->from($db->quoteName('#__update_sites'))
			->where($db->quoteName('update_site_id') . 'IN(' . $coreUpdateSiteIDs . ')')
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
		$db = Factory::getDbo();

		// Clear update records
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__updates'))
			->where($db->quoteName('extension_id') . ' = ' . $eid);

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Throwable $e)
		{
			// Swallow the exception.
		}

		$coreUpdateSiteIDs = ArrayHelper::toInteger($updateSiteIDs);
		$coreUpdateSiteIDs = array_map([$db, 'quote'], $coreUpdateSiteIDs);
		$coreUpdateSiteIDs = implode(',', $coreUpdateSiteIDs);

		// Reset last check timestamp on update site
		$query = $db->getQuery(true)
			->update($db->quoteName('#__update_sites'))
			->set($db->quoteName('last_check_timestamp') . ' = 0')
			->where($db->quoteName('update_site_id') . 'IN(' . $coreUpdateSiteIDs . ')');
		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Throwable $e)
		{
			// Swallow the exception.
		}
	}

}