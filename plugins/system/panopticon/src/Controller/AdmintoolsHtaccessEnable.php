<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;

defined('_JEXEC') || die;

use Akeeba\PanopticonConnector\Controller\Mixit\AdminToolsTrait;

class AdmintoolsHtaccessEnable extends AbstractController
{
	use AdminToolsTrait;

	public function __invoke(\JInput $input): object
	{
		$ret = (object) [
			'id'       => 0,
			'exists'   => false,
			'restored' => false,
		];

		$from = JPATH_SITE . '/.htaccess.admintools';
		$to   = JPATH_SITE . '/.htaccess';

		if (!file_exists($from) && file_exists($to))
		{
			$ret->exists   = false;
			$ret->restored = true;
		}
		elseif (!file_exists($from))
		{
			$ret->exists   = false;
			$ret->restored = false;
		}
		elseif (@rename($from, $to))
		{
			$ret->exists   = true;
			$ret->restored = true;
		}
		else
		{
			$ret->exists   = true;
			$ret->restored = false;
		}

		return $this->asSingleItem('admintools', $ret);
	}
}