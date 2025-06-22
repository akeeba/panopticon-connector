<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Model;

defined('_JEXEC') || die;

use Joomla\Database\ParameterType;

class UpdatesitesModel extends \Joomla\Component\Installer\Administrator\Model\UpdatesitesModel
{
	public function getItems()
	{
		$this->setState('filter.supported', $this->getState('filter.supported') ?? '');
		$this->setState('filter.enabled', $this->getState('filter.enabled') ?? '');
		$this->setState('filter.folder', $this->getState('filter.folder') ?? '');

		return array_map(
			function (object $item) {
				$item->id = $item->update_site_id;

				return $item;
			},
			parent::getItems()
		);
	}

	protected function getListQuery()
	{
		$query = parent::getListQuery();
		$eid   = $this->getState('filter.eid', []);

		if (!empty($eid))
		{
			$query->whereIn($query->quoteName('se.extension_id'), $eid, ParameterType::INTEGER);
		}

		return $query;
	}


}