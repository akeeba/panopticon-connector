<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Model;


use Joomla\CMS\MVC\Model\AdminModel;

defined('_JEXEC') || die;

class ExtensionModel extends AdminModel
{
	public function getForm($data = [], $loadData = true)
	{
		// We are not actually using this
	}

	public function getItem($pk = null)
	{
		$pk = $pk ?: $this->getState('extension.id', null);

		/** @var ExtensionsModel $otherModel */
		$otherModel = new ExtensionsModel([], $this->getMVCFactory());
		$otherModel->setState('filter.id', $pk);
		$otherModel->setState('filter.protected', -1);

		$items = $otherModel->getItems();

		return !empty($items) ? array_shift($items) : null;
	}
}