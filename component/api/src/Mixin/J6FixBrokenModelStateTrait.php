<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Mixin;

defined('_JEXEC') || die;

use Akeeba\Component\Panopticon\Api\Library\JRegistryForAPIWorkaround;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;

trait J6FixBrokenModelStateTrait
{
	public function __construct(
		$config = [], MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null
	)
	{
		parent::__construct($config, $factory, $app, $input);

		if (version_compare(JVERSION, '5.999.999', 'gt'))
		{
			$this->modelState = new JRegistryForAPIWorkaround();
		}
	}
}