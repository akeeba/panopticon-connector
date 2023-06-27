<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;

use Akeeba\PanopticonConnector\Model\CoreUpdateModel;

defined('_JEXEC') || die;

class CoreUpdateActivate extends AbstractController
{
	public function __invoke(\JInput $input): object
	{
		$basename = $input->getRaw('basename', null);

		/** @var CoreUpdateModel $model */
		$model = new CoreUpdateModel(['ignore_request' => true]);

		if (!$model->createRestorationFile($basename))
		{
			throw new \RuntimeException('Cannot create the administrator/components/com_joomlaupdate/restoration.php file.');
		}

		$result = (object) [
			'id'       => 0,
			'password' => $model->getState('password'),
			'filesize' => $model->getState('filesize'),
			'file'     => $model->getState('file'),
		];

		return $this->asSingleItem('coreupdateactivate', $result);
	}
}