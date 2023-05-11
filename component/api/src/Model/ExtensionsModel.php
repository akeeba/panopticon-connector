<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Api\Model;

defined('_JEXEC') || die;

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Object\CMSObject;
use Joomla\Component\Installer\Administrator\Helper\InstallerHelper;
use Joomla\Database\ParameterType;
use stdClass;

class ExtensionsModel extends ListModel
{
	use ElementToExtensionIdTrait;

	public function __construct($config = [], MVCFactoryInterface $factory = null)
	{
		$config['filter_fields'] = $config['filter_fields'] ?? [
			'updatable',
			'protected',
			'id',
			'core',
		];

		parent::__construct($config, $factory);
	}

	protected function getListQuery()
	{
		$protected = $this->getState('filter.protected', 0);
		$protected = ($protected !== '' && is_numeric($protected)) ? intval($protected) : null;

		$db    = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('e.extension_id', 'id'),
				$db->quoteName('e') . '.*',
				$db->quoteName('u.version', 'new_version'),
				$db->quoteName('u.detailsurl'),
				$db->quoteName('u.infourl'),
				$db->quoteName('u.changelogurl'),
			])
			->from($db->quoteName('#__extensions', 'e'))
			->join(
				'LEFT OUTER',
				$db->quoteName('#__updates', 'u'),
				$db->quoteName('u.extension_id') . ' = ' . $db->quoteName('e.extension_id')
			)
			->where(
				[
					$db->quoteName('e.package_id') . ' = 0',
					'(' .
						$db->quoteName('e.locked') . ' != 1 OR ' .
						'(' . $db->quoteName('e.type') . ' = ' . $db->quote('file') . ' AND ' . $db->quoteName('e.element') . ' = ' . $db->quote('joomla') . ')'
					. ')'
				]
			)
			;

		if (is_int($protected) && $protected >= 0)
		{
			$protected = boolval($protected) ? 1 : 0;
			$query->where($db->quoteName('protected') . ' = :protected')
				->bind(':protected', $protected, ParameterType::INTEGER);
		}

		$updatable = $this->getState('filter.updatable', '');

		if ($updatable !== '' && $updatable)
		{
			$query->where($db->quoteName('u.version') . ' IS NOT NULL');
		}

		$eid = $this->getState('filter.id', '');
		$eid = ($eid !== '' && is_numeric($eid)) ? intval($eid) : null;

		if (is_int($eid) && $eid > 0)
		{
			$query->where($db->quoteName('e.extension_id') . ' = :eid')
				->bind(':eid', $eid, ParameterType::INTEGER);
		}

		return $query;
	}

	protected function _getList($query, $limitstart = 0, $limit = 0)
	{
		// Get all items from the database. We deliberately don't apply any limits just yet.
		$items = parent::_getList($query);

		// Pull information from the manifest cache
		$items = array_map(
			function (object $item): object {
				try
				{
					$manifestCache = @json_decode($item->manifest_cache ?? '{}') ?? new stdClass();
				}
				catch (Exception $e)
				{
					$manifestCache = new stdClass();
				}

				$item->author      = $manifestCache->author ?? '';
				$item->authorUrl   = $manifestCache->authorUrl ?? '';
				$item->authorEmail = $manifestCache->authorEmail ?? '';
				$item->version     = $manifestCache->version ?? '0.0.0';
				$item->description = $manifestCache->description ?? '';

				return $item;
			},
			$items
		);

		// Apply the filter.core filter, if requested
		$coreFilter = $this->getState('filter.core', '');
		$coreFilter = ($coreFilter !== '' && is_numeric($coreFilter)) ? intval($coreFilter) === 1 : null;

		if (!is_null($coreFilter))
		{
			$items = array_filter(
				$items,
				function ($item) use ($coreFilter) {
					return !$coreFilter xor str_contains($item->authorUrl, 'www.joomla.org');
				}
			);
		}

		// Translate some information
		$jLang = Factory::getApplication()->getLanguage();
		// -- Load the com_installer language files; they are used below
		$jLang->load('com_installer', JPATH_API, null);
		$jLang->load('com_installer', JPATH_ADMINISTRATOR, null);

		$items = array_map(
			function (object $item) use ($jLang): object {
				// Translate the client, extension type, and folder
				$item->client_translated = Text::_([
					0 => 'JSITE', 1 => 'JADMINISTRATOR', 3 => 'JAPI',
				][$item->client_id] ?? 'JSITE');
				$item->type_translated   = Text::_('COM_INSTALLER_TYPE_' . strtoupper($item->type));
				$item->folder_translated = @$item->folder ? $item->folder : Text::_('COM_INSTALLER_TYPE_NONAPPLICABLE');

				// Load an extension's language files (if applicable)
				$path = $item->client_id ? JPATH_ADMINISTRATOR : JPATH_SITE;

				switch ($item->type)
				{
					case 'component':
						$extension = $item->element;
						$source    = JPATH_ADMINISTRATOR . '/components/' . $extension;
						$jLang->load("$extension.sys", JPATH_ADMINISTRATOR) || $jLang->load("$extension.sys", $source);
						break;

					case 'file':
						$extension = 'files_' . $item->element;
						$jLang->load("$extension.sys", JPATH_SITE);
						break;

					case 'library':
						$parts     = explode('/', $item->element);
						$vendor    = (isset($parts[1]) ? $parts[0] : null);
						$extension = 'lib_' . ($vendor ? implode('_', $parts) : $item->element);

						if (!$jLang->load("$extension.sys", $path))
						{
							$source = $path . '/libraries/' . ($vendor ? $vendor . '/' . $parts[1] : $item->element);
							$jLang->load("$extension.sys", $source);
						}
						break;

					case 'module':
						$extension = $item->element;
						$source    = $path . '/modules/' . $extension;
						$jLang->load("$extension.sys", $path) || $jLang->load("$extension.sys", $source);
						break;

					case 'plugin':
						$extension = 'plg_' . $item->folder . '_' . $item->element;
						$source    = JPATH_PLUGINS . '/' . $item->folder . '/' . $item->element;
						$jLang->load("$extension.sys", JPATH_ADMINISTRATOR) || $jLang->load("$extension.sys", $source);
						break;

					case 'template':
						$extension = 'tpl_' . $item->element;
						$source    = $path . '/templates/' . $item->element;
						$jLang->load("$extension.sys", $path) || $jLang->load("$extension.sys", $source);
						break;

					case 'package':
					default:
						$extension = $item->element;
						$jLang->load("$extension.sys", JPATH_SITE);
						break;
				}

				// Translate the extension name, if applicable
				$item->name = Text::_($item->name);

				// Translate the description, if applicable
				$item->description = empty($item->description) ? '' : Text::_($item->description);

				return $item;
			},
			$items
		);

		// Apply limits
		$limitstart = $limitstart ?: 0;
		$limit = $limit ?: 0;

		if ($limitstart !== 0 && $limit === 0)
		{
			$items = array_slice($items, $limitstart);
		}
		elseif ($limitstart !== 0 && $limit !== 0)
		{
			$items = array_slice($items, $limitstart, $limit);
		}

		// Add Update Site information for each extension
		$updateSites = $this->getUpdateSitesForExtensions(
			array_map(
				function ($item) {
					return $item->extension_id;
				},
				$items
			)
		);

		$items = array_map(
			function(object $item) use ($updateSites): object {
				$item->updatesites = $updateSites[$item->extension_id] ?? [];

				// This is needed by InstallerHelper::getDownloadKey
				$item->extra_query = array_reduce(
					$item->updatesites,
					function (string $carry, object $item) {
						return $carry ?: $item->extra_query;
					},
					''
				);

				$item->downloadkey = InstallerHelper::getDownloadKey(new CMSObject($item));

				return $item;
			},
			$items
		);

		return $items;
	}

	protected function _getListCount($query)
	{
		return count($this->_getList($query, 0, 0));
	}

	private function getUpdateSitesForExtensions(array $eids): array
	{
		$db    = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from('#__update_sites_extensions')
			->whereIn($db->quoteName('extension_id'), $eids, ParameterType::INTEGER);

		try
		{
			$temp = $db->setQuery($query)->loadObjectList() ?: [];
		}
		catch (Exception $e)
		{
			return [];
		}

		$updateSitesPerEid = [];
		$updateSiteIDs = [];

		foreach ($temp as $item)
		{
			$updateSitesPerEid[$item->extension_id] = $updateSitesPerEid[$item->extension_id] ?? [];
			$updateSitesPerEid[$item->extension_id][] = $item->update_site_id;
			$updateSiteIDs[] = $item->update_site_id;
		}

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__update_sites'))
			->whereIn($db->quoteName('update_site_id'), $updateSiteIDs);

		try
		{
			$temp = $db->setQuery($query)->loadObjectList('update_site_id') ?: [];
		}
		catch (Exception $e)
		{
			return [];
		}

		$ret = [];

		foreach ($updateSitesPerEid as $eid => $usids)
		{
			$ret[$eid] = array_filter(
				array_map(
					function (int $usid) use ($temp) {
						return $temp[$usid] ?? null;
					},
					$usids
				)
			);
		}

		$ret = array_filter($ret);

		return $ret;
	}
}