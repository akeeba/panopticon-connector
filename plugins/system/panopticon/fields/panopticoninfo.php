<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('_JEXEC') or die;

class JFormFieldPanopticoninfo extends JFormField
{
	protected $type = 'Panopticoninfo';

	protected function getInput()
	{
		JLoader::registerNamespace('Akeeba\\PanopticonConnector', __DIR__ . '/../src', false, false, 'psr4');

		$secret = (new \Akeeba\PanopticonConnector\Authentication())->getSecret();

		@ob_start();

		require_once JPluginHelper::getLayoutPath('system', 'panopticon');

		return @ob_get_clean();
	}

}