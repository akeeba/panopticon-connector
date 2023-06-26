<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;


use Akeeba\PanopticonConnector\Model\ExtensionsModel;
use Joomla\CMS\Input\Input;

defined('_JEXEC') || die;

class Extensions extends AbstractController
{
	public function __invoke(Input $input): object
	{
		$updatable  = $input->getBool('updatable', null);
		$protected  = $input->getBool('protected', null);
		$core       = $input->getBool('core', null);
		$force      = $input->getBool('force', false);
		$pageParams = $input->getInt('page', []);
		$pageParams = is_array($pageParams) ? $pageParams : [];
		$limit      = $pageParams['limit'] ?? 10000;
		$limitStart = $pageParams['offset'] ?? 0;

		if (!class_exists(\InstallerModel::class))
		{
			require_once JPATH_ADMINISTRATOR . '/components/com_installer/models/extension.php';
		}
		if (!class_exists(\InstallerModelManage::class))
		{
			require_once JPATH_ADMINISTRATOR . '/components/com_installer/models/manage.php';
		}

		\JModelLegacy::addIncludePath(realpath(__DIR__ . '/../Model'));

		$model = new ExtensionsModel(['ignore_request' => true]);

		$model->setState('list.limitstart', $limitStart);
		$model->setState('list.limit', $limit);
		$model->setState('filter.updatable', $updatable);
		$model->setState('filter.protected', $protected);
		$model->setState('filter.core', $core);
		$model->setState('filter.force', $force);

		$ret = $this->asItemsList('extension', $model->getItems() ?: [], $model->getPagination());

		if ($model->getError())
		{
			throw new \RuntimeException($model->getError());
		}

		return $ret;
	}
}