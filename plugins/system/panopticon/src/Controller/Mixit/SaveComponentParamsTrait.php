<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller\Mixit;

defined('_JEXEC') || die;

use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use ReflectionClass;
use Throwable;

trait SaveComponentParamsTrait
{
	private function saveComponentParameters(string $component, Registry $params): void
	{
		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('params') . ' = ' . $db->quote($params->toString('JSON')))
			->where(
				[
					$db->quoteName('element') . ' = ' . $db->quote($component),
					$db->quoteName('type') . ' = ' . $db->quote('component'),
				]
			);

		$db->setQuery($query)->execute();

		// Clear the _system cache
		$this->clearCacheGroupJoomla3('_system', 0);
		$this->clearCacheGroupJoomla3('_system', 1);

		// Update internal Joomla data
		$refClass = new ReflectionClass(ComponentHelper::class);
		$refProp  = $refClass->getProperty('components');
		$refProp->setAccessible(true);

		$components                     = $refProp->getValue();
		$components[$component]->params = $params;

		$refProp->setValue($components);
	}

	private function clearCacheGroupJoomla3(string $group, int $client_id, object $app = null): array
	{
		$app = $app ?? Factory::getApplication();

		$options = [
			'defaultgroup' => $group,
			'cachebase'    => ($client_id) ? $app->get('cache_path', JPATH_SITE . '/cache') : JPATH_ADMINISTRATOR . '/cache',
			'result'       => true,
		];

		try
		{
			$cache = Cache::getInstance('callback', $options);
			/** @noinspection PhpUndefinedMethodInspection Available via __call(), not tagged in Joomla core */
			$cache->clean();
		}
		catch (Throwable $e)
		{
			$options['result'] = false;
		}

		return $options;
	}

}