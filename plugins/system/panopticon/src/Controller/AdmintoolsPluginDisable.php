<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;

defined('_JEXEC') || die;

use Akeeba\AdminTools\Admin\Model\ControlPanel;
use Akeeba\PanopticonConnector\Controller\Mixit\AdminToolsTrait;

class AdmintoolsPluginDisable extends AbstractController
{
	use AdminToolsTrait;

	public function __invoke(\JInput $input): object
	{
		/** @var ControlPanel $model */
		$container = $this->getAdminToolsContainer();
		$model = $container->factory->model('ControlPanel');

		$ret   = (object) [
			'id'      => 0,
			'renamed' => true,
			'name'    => null,
		];

		if ($model->isMainPhpDisabled())
		{
			$ret->name = $model->getRenamedMainPhp();

			return $this->asSingleItem('admintools', $ret);
		}

		$from = JPATH_PLUGINS . '/system/admintools/admintools/main.php';
		$to   = JPATH_PLUGINS . '/system/admintools/admintools/main-disable.php';

		if (@rename($from, $to) || \JFile::move($from, $to))
		{
			$ret->name = basename($to);
		}
		else
		{
			$ret->renamed = false;
		}

		return $this->asSingleItem('admintools', $ret);
	}
}