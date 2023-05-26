<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\View\Templatechanged;

defined('_JEXEC') || die;

class JsonapiView extends \Joomla\CMS\MVC\View\JsonApiView
{
	protected $fieldsToRenderList = [
		'id',
		'template',
		'extension_id',
		'state',
		'action',
		'created_date',
		'modified_date',
	];
}