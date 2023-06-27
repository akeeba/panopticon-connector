<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;

defined('_JEXEC') || die;

use Akeeba\AdminTools\Admin\Model\UnblockIP;
use Akeeba\PanopticonConnector\Controller\Mixit\AdminToolsTrait;

class AdmintoolsUnblock extends AbstractController
{
	use AdminToolsTrait;

	public function __invoke(\JInput $input): object
	{
		/** @var UnblockIP $model */
		$ip        = $input->post->get('ip', null, 'raw');
		$container = $this->getAdminToolsContainer();
		$model     = $container->factory->model('UnblockIP')->tmpInstance();

		$model->unblockIP($ip);

		return $this->asSingleItem('admintools', (object) [
			'status' => true,
		]);
	}
}