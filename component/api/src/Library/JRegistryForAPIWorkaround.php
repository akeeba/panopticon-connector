<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Library;

defined('_JEXEC') || die;

use Joomla\Registry\Registry;

class JRegistryForAPIWorkaround extends Registry
{
	public function __set($name, $value)
	{
		$this->set($name, $value);

		if (version_compare(JVERSION, '5.999.999', 'lt'))
		{
			parent::__set($name, $value);
		}
	}
}