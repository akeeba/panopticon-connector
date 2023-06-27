<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;

defined('_JEXEC') || die;

use Akeeba\PanopticonConnector\Controller\Mixit\AdminToolsTrait;

class AdmintoolsHtaccessDisable extends AbstractController
{
	use AdminToolsTrait;

	public function __invoke(\JInput $input): object
	{
		// TODO: Implement __invoke() method.
	}
}