<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller\Mixit;

defined('_JEXEC') || die;

trait AdminToolsTrait
{
	/**
	 * @return  \FOF40\Container\Container|\FOF30\Container\Container
	 * @since   1.0.0
	 */
	protected function getAdminToolsContainer()
	{
		// Is the component enabled?
		if (!\JComponentHelper::isEnabled('com_admintools'))
		{
			throw new \RuntimeException('Admin Tools is not installed on this site.', 500);
		}

		// Which version of FOF do I need?
		$fofXml = @file_get_contents(JPATH_ADMINISTRATOR . '/components/com_admintools/fof.xml');

		if (empty($fofXml))
		{
			throw new \RuntimeException('Your Admin Tools installation is missing some necessary files.', 500);
		}

		$fofVersion = strpos($fofXml, 'FOF40\\') ? 4 : 3;

		if ($fofVersion === 3)
		{
			if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
			{
				throw new \RuntimeException('Akeeba Framework-on-Framework 3.x is not installed on this site.', 500);
			}

			$container = \FOF30\Container\Container::getInstance('com_admintools', [
				'factoryClass' => \FOF30\Factory\SwitchFactory::class
			]);

			$container->factoryClass = \FOF30\Factory\SwitchFactory::class;

			return $container;
		}

		if (!defined('FOF40_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof40/include.php'))
		{
			throw new \RuntimeException('Akeeba Framework-on-Framework 4.x is not installed on this site.', 500);
		}

		$container = \FOF40\Container\Container::getInstance('com_admintools', [
			'factoryClass' => \FOF40\Factory\SwitchFactory::class
		]);

		$container->factoryClass = \FOF40\Factory\SwitchFactory::class;

		return $container;
	}

	private function getScannerState(): array
	{
		$scannerSession = \Akeeba\AdminTools\Admin\Model\Scanner\Util\Session::getInstance();
		$session        = \JFactory::getApplication()->getSession();
		$ret            = [];

		foreach ($scannerSession->getKnownKeys() as $key)
		{
			$key       = 'com_admintools.filescanner.' . $key;
			$ret[$key] = $session->get($key, null);
		}

		return $ret;
	}

	private function setScannerState(array $sessionData): void
	{
		$session = \JFactory::getApplication()->getSession();

		foreach ($sessionData as $k => $v)
		{
			$session->set($k, $v);
		}
	}
}