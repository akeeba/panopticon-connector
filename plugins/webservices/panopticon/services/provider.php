<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('_JEXEC') || die;

use Akeeba\Plugin\WebServices\Panopticon\Extension\Panopticon;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function register(Container $container)
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$pluginsParams = (array) PluginHelper::getPlugin('webservices', 'panopticon');
				$dispatcher    = $container->get(DispatcherInterface::class);
				$plugin        = new Panopticon($dispatcher, $pluginsParams);

				// Joomla 4.2 and later
				if (method_exists($plugin, 'setApplication'))
				{
					$plugin->setApplication(Factory::getApplication());
				}

				return $plugin;
			}
		);
	}
};
