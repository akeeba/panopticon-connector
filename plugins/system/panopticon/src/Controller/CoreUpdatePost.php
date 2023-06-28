<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;

defined('_JEXEC') || die;

use Akeeba\PanopticonConnector\Controller\Mixit\ElementToExtensionIdTrait;
use Akeeba\PanopticonConnector\Controller\Mixit\JoomlaUpdateTrait;
use Akeeba\PanopticonConnector\Controller\Mixit\SaveComponentParamsTrait;

class CoreUpdatePost extends AbstractController
{
	use ElementToExtensionIdTrait;
	use JoomlaUpdateTrait;
	use SaveComponentParamsTrait;

	public function __invoke(\JInput $input): object
	{
		// Change the update site if necessary
		$updateSource = $input->post->getCmd('updatesource', '');

		if (!empty($updateSource))
		{
			$updateURL = $input->post->getRaw('updateurl', null);

			$this->changeUpdateSource($updateSource, $updateURL);
		}

		// Make sure there is a core update record
		$this->affirmCoreUpdateRecord();

		// Apply the update source
		// Apply the update source
		if (!class_exists(\JoomlaupdateModelDefault::class))
		{
			require_once JPATH_ADMINISTRATOR . '/components/com_joomlaupdate/models/default.php';
		}

		/** @var \JoomlaupdateModelDefault $jUpdateModel */
		$jUpdateModel = \JModelLegacy::getInstance('Default', 'JoomlaupdateModel', ['ignore_request' => true]);

		$jUpdateModel->applyUpdateSite();

		// Reload the update information
		$this->getJoomlaUpdateInfo(true);

		return new \stdClass();
	}

	public function changeUpdateSource(string $updateSource, ?string $updateURL): void
	{
		// Sanity check
		if (!in_array($updateSource, ['nochange', 'next', 'testing', 'custom']))
		{
			return;
		}

		// Get the current parameters
		$params = \JComponentHelper::getParams('com_joomlaupdate');

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

	public function affirmCoreUpdateRecord()
	{
		$id = $this->getExtensionIdFromElement('files_joomla');

		if (empty($id))
		{
			return;
		}

		$db = \JFactory::getDbo();

		// Is there an update site record?
		$query = $db
			->getQuery(true)
			->select($db->quoteName('us.update_site_id'))
			->from($db->quoteName('#__update_sites_extensions', 'map'))
			->join(
				'INNER',
				$db->quoteName('#__update_sites', 'us') . 'ON(' .
				$db->quoteName('us.update_site_id') . ' = ' . $db->quoteName('map.update_site_id')
				. ')'
			)
			->where($db->quoteName('map.extension_id') . ' = ' . (int) $id);

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

}