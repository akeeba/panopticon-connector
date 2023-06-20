<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\View\Admintools;

defined('_JEXEC') || die;

use Joomla\CMS\MVC\View\JsonApiView as BaseJsonApiView;

class JsonapiView extends BaseJsonApiView
{
	protected $fieldsToRenderList = [
		'id',
		'comment',
		'scanstart',
		'scanend',
		'status',
		'origin',
		'totalfiles',
		'files_new',
		'files_modified',
		'files_suspicious',
	];

	protected $scanAlertsFieldsToRenderList = [
		'id',
		'newfile',
		'suspicious',
		'filestatus',
		'threatindex',
		'path',
		'threat_score',
		'acknowledged',
		'scan_id',
		'scandate',
	];

	protected $fieldsToRenderItem = [
		'id',
		'path',
		'scan_id',
		'diff',
		'threat_score',
		'acknowledged',
	];

	public function displayList(array $items = null)
	{
		if ($this->type === 'admintools.scanalerts')
		{
			$this->fieldsToRenderList = $this->scanAlertsFieldsToRenderList;
		}

		return parent::displayList($items);
	}
}