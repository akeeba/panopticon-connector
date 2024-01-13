<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Plugin\Console\Panopticon\Command;

defined('_JEXEC') || die;

use Joomla\Application\ApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use RuntimeException;

/**
 * Implements the CommandFactoryInterface and DatabaseAwareInterface interfaces.
 *
 * It is responsible for creating CLI commands based on a given class name.
 *
 * @since   1.0.3
 */
class CommandFactory implements CommandFactoryInterface, DatabaseAwareInterface
{
	use MVCFactoryAwareTrait;
	use DatabaseAwareTrait;

	/**
	 * @var   ApplicationInterface
	 * @since 1.0.3
	 */
	private $app;

	/**
	 * Sets the application object for the current instance.
	 *
	 * @param   ApplicationInterface  $app  The application to set.
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	public function setApplication(ApplicationInterface $app)
	{
		$this->app = $app;
	}

	/**
	 * Retrieves an instance of the specified CLI command class.
	 *
	 * @param   string  $classFQN  The fully qualified name of the CLI command class.
	 *
	 * @return  AbstractCommand  An instance of the CLI command class.
	 * @throws  RuntimeException If the specified CLI command class does not exist.
	 * @since   1.0.3
	 */
	public function getCLICommand(string $classFQN): AbstractCommand
	{
		if (!class_exists($classFQN))
		{
			throw new RuntimeException(sprintf('Unknown Akeeba Panopticon CLI command class ‘%s’.', $classFQN));
		}

		$o = new $classFQN;

		if (method_exists($classFQN, 'setMVCFactory'))
		{
			$o->setMVCFactory($this->getMVCFactory());
		}

		if ($o instanceof DatabaseAwareInterface)
		{
			$o->setDatabase($this->getDatabase());
		}

		if (method_exists($o, 'setApplication'))
		{
			$o->setApplication($this->getApplication());
		}

		return $o;
	}

	/**
	 * Retrieves the application instance.
	 *
	 * @return  ApplicationInterface The application instance.
	 * @since   1.0.3
	 */
	private function getApplication(): ApplicationInterface
	{
		return $this->app;
	}
}