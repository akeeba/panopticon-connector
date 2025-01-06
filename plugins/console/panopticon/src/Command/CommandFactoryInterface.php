<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Plugin\Console\Panopticon\Command;

defined('_JEXEC') || die;

use Joomla\Console\Command\AbstractCommand;

/**
 * Interface CommandFactoryInterface
 *
 * This interface defines the contract for a Command Factory, which is responsible for creating
 * command objects based on the provided command name.
 *
 * @since   1.0.3
 */
interface CommandFactoryInterface
{
	public function getCLICommand(string $commandName): AbstractCommand;
}