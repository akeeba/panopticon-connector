<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;

defined('_JEXEC') || die;

use Akeeba\PanopticonConnector\Controller\Mixit\ElementToExtensionIdTrait;
use Akeeba\PanopticonConnector\Controller\Mixit\JoomlaUpdateTrait;

class CoreUpdate extends AbstractController
{
	use ElementToExtensionIdTrait;
	use JoomlaUpdateTrait;

	public function __invoke(\JInput $input): object
	{
		$force = $input->getInt('force', 0) === 1;

		$updateInfo     = $this->getJoomlaUpdateInfo($force);
		$updateInfo->id = $this->coreExtensionID ?: 0;

		return $this->asSingleItem('', $updateInfo);
	}
}