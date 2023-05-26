<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\View\Updatesites;

defined('_JEXEC') || die;

class JsonapiView extends \Joomla\CMS\MVC\View\JsonApiView
{
	protected $fieldsToRenderList = [
		'update_site_id',
		'update_site_name',
		'update_site_type',
		'location',
		'enabled',
		'checked_out',
		'checked_out_time',
		'extra_query',
		'extension_id',
		'name',
		'type',
		'element',
		'folder',
		'client_id',
		'state',
		'manifest_cache',
		'editor',
		'downloadKey',
	];

	protected $fieldsToRenderItem = [
		'update_site_id',
		'update_site_name',
		'update_site_type',
		'location',
		'enabled',
		'checked_out',
		'checked_out_time',
		'extra_query',
		'extension_id',
		'name',
		'type',
		'element',
		'folder',
		'client_id',
		'state',
		'manifest_cache',
		'editor',
		'downloadKey',
	];
}