<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;

use Akeeba\PanopticonConnector\Model\CoreUpdateModel;

defined('_JEXEC') || die;

class CoreUpdateDownload extends  AbstractController
{
	public function __invoke(\JInput $input): object
	{
		/** @var CoreUpdateModel $model */
		$model = new CoreUpdateModel(['ignore_request' => true]);
		$result = $model->download();

		return $this->asSingleItem('coreupdatedownload', $result);
	}
}