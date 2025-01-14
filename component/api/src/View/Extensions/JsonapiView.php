<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\View\Extensions;

defined('_JEXEC') || die;

use Joomla\CMS\MVC\View\JsonApiView as BaseJsonApiView;

class JsonapiView extends BaseJsonApiView
{
	protected $fieldsToRenderList = [
		"extension_id",
		"package_id",
		"type",
		"folder",
		"element",
		"client_id",
		"client_translated",
		"type_translated",
		"folder_translated",
		"state",
		"enabled",
		"access",
		"protected",
		"locked",
		"name",
		"description",
		"author",
		"authorUrl",
		"authorEmail",
		"version",
		"new_version",
		"detailsurl",
		"infourl",
		"changelogurl",
		"updatesites",
		"downloadkey",
		"naughtyUpdates"
	];

	protected $fieldsToRenderItem = [
		"extension_id",
		"package_id",
		"type",
		"folder",
		"element",
		"client_id",
		"client_translated",
		"type_translated",
		"folder_translated",
		"state",
		"enabled",
		"access",
		"protected",
		"locked",
		"name",
		"description",
		"author",
		"authorUrl",
		"authorEmail",
		"version",
		"new_version",
		"detailsurl",
		"infourl",
		"changelogurl",
		"updatesites",
		"downloadkey",
	];
}