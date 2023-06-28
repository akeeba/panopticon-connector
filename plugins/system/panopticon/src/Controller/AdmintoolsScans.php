<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;

defined('_JEXEC') || die;

use Akeeba\AdminTools\Site\Model\Scans;
use Akeeba\PanopticonConnector\Controller\Mixit\AdminToolsTrait;

class AdmintoolsScans extends AbstractController
{
	use AdminToolsTrait;

	public function __invoke(\JInput $input): object
	{
		/** @var Scans $model */
		$container = $this->getAdminToolsContainer();
		$model     = $container->factory->model('Scans')->tmpInstance();

		$pages      = $input->get('pages', [], 'raw');
		$pages      = is_array($pages) ? $pages : [];
		$limit      = $pages['limit'] ?? 10;
		$limitStart = $pages['offset'] ?? 0;

		$items      = $model->get(false, $limitStart, $limit);
		$pagination = new \JPagination($model->count(), $limitStart, $limit);

		return $this->asItemsList('admintools.scans', $items->toArray(), $pagination);
	}
}