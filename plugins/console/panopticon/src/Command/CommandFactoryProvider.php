<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Plugin\Console\Panopticon\Command;

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * Class CommandFactoryProvider
 *
 * This class implements the ServiceProviderInterface and is responsible for registering
 * the CommandFactoryInterface with the DI container.
 *
 * @since   1.0.3
 */
class CommandFactoryProvider implements ServiceProviderInterface
{
	/**
	 * @inheritDoc
	 */
	public function register(Container $container)
	{
		$container->set(
			CommandFactoryInterface::class,
			function (Container $container) {
				$factory = new CommandFactory();

				$factory->setMVCFactory($container->get(MVCFactoryInterface::class));
				$factory->setDatabase($container->get(DatabaseInterface::class));
				$factory->setApplication(Factory::getApplication());

				return $factory;
			}
		);
	}
}