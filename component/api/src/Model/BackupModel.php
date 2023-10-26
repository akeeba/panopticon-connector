<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Model;

defined('_JEXEC') || die;

use Akeeba\Engine\Platform;
use Exception;
use InvalidArgumentException;
use JConfig;
use Joomla\Application\AbstractApplication;
use Joomla\Application\ConfigurationAwareApplicationInterface;
use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\CallbackController;
use Joomla\CMS\Cache\Exception\CacheExceptionInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use Joomla\Registry\Registry;
use RuntimeException;
use Throwable;
use UnexpectedValueException;
use function get_class;
use function is_array;
use function is_null;

class BackupModel extends BaseModel
{
	public static function clearCacheGroups(
		array $clearGroups, array $cacheClients = [
		0, 1,
	], ?string $event = null
	): void
	{
		// Early return on nonsensical input
		if (empty($clearGroups) || empty($cacheClients))
		{
			return;
		}

		// Make sure I have an application object
		try
		{
			$app = Factory::getApplication();
		}
		catch (Exception $e)
		{
			return;
		}

		// If there's no application object things will break; let's get outta here.
		if (!is_object($app))
		{
			return;
		}

		$isJoomla4 = version_compare(JVERSION, '3.9999.9999', 'gt');

		// Loop all groups to clean
		foreach ($clearGroups as $group)
		{
			// Groups must be non-empty strings
			if (empty($group) || !is_string($group))
			{
				continue;
			}

			// Loop all clients (applications)
			foreach ($cacheClients as $client_id)
			{
				$client_id = (int) ($client_id ?? 0);

				$options = $isJoomla4
					? self::clearCacheGroupJoomla4($group, $client_id, $app)
					: self::clearCacheGroupJoomla3($group, $client_id, $app);

				// Do not call any events if I failed to clean the cache using the core Joomla API
				if (!($options['result'] ?? false))
				{
					return;
				}

				/**
				 * If you're cleaning com_content and you have passed no event name I will use onContentCleanCache.
				 */
				if ($group === 'com_content')
				{
					$cacheCleaningEvent = $event ?: 'onContentCleanCache';
				}

				/**
				 * Call Joomla's cache cleaning plugin event (e.g. onContentCleanCache) as well.
				 *
				 * @see BaseDatabaseModel::cleanCache()
				 */
				if (empty($cacheCleaningEvent))
				{
					continue;
				}

				if ($isJoomla4)
				{
					self::triggerEvent($cacheCleaningEvent, $options);
				}
				else
				{
					$app = Factory::getApplication();
					$app->triggerEvent($cacheCleaningEvent, $options);
				}
			}
		}
	}

	private static function triggerEvent($eventName, $args = [])
	{
		$app = Factory::getApplication();

		try
		{
			$dispatcher = $app->getDispatcher();
		}
		catch (UnexpectedValueException $exception)
		{
			$app->getLogger()->error(sprintf('Dispatcher not set in %s, cannot trigger events.', get_class($app)));

			return [];
		}

		if ($args instanceof Event)
		{
			$event = $args;
		}
		elseif (is_array($args))
		{
			$className = Event::class;
			$event     = new $className($eventName, $args);
		}
		else
		{
			throw new InvalidArgumentException('The arguments must either be an event or an array');
		}

		$result = $dispatcher->dispatch($eventName, $event);

		return !isset($result['result']) || is_null($result['result']) ? [] : $result['result'];
	}

	private static function clearCacheGroupJoomla3(string $group, int $client_id, object $app): array
	{
		$options = [
			'defaultgroup' => $group,
			'cachebase'    => ($client_id) ? self::getAppConfigParam($app, 'cache_path', JPATH_SITE . '/cache')
				: JPATH_ADMINISTRATOR . '/cache',
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

	private static function clearCacheGroupJoomla4(string $group, int $client_id, object $app): array
	{
		// Get the default cache folder. Start by using the JPATH_CACHE constant.
		$cacheBaseDefault = JPATH_CACHE;
		$appClientId      = 0;

		if (method_exists($app, 'getClientId'))
		{
			$appClientId = $app->getClientId();
		}

		// -- If we are asked to clean cache on the other side of the application we need to find a new cache base
		if ($client_id != $appClientId)
		{
			$cacheBaseDefault = (($client_id) ? JPATH_SITE : JPATH_ADMINISTRATOR) . '/cache';
		}

		// Get the cache controller's options
		$options = [
			'defaultgroup' => $group,
			'cachebase'    => self::getAppConfigParam($app, 'cache_path', $cacheBaseDefault),
			'result'       => true,
		];

		try
		{
			$container = Factory::getContainer();

			if (empty($container))
			{
				throw new RuntimeException('Cannot get Joomla 4 application container');
			}

			/** @var CacheControllerFactoryInterface $cacheControllerFactory */
			$cacheControllerFactory = $container->get('cache.controller.factory');

			if (empty($cacheControllerFactory))
			{
				throw new RuntimeException('Cannot get Joomla 4 cache controller factory');
			}

			/** @var CallbackController $cache */
			$cache = $cacheControllerFactory->createCacheController('callback', $options);

			if (empty($cache) || !property_exists($cache, 'cache') || !method_exists($cache->cache, 'clean'))
			{
				throw new RuntimeException('Cannot get Joomla 4 cache controller');
			}

			$cache->cache->clean();
		}
		catch (CacheExceptionInterface $exception)
		{
			$options['result'] = false;
		}
		catch (Throwable $e)
		{
			$options['result'] = false;
		}

		return $options;
	}

	private static function getAppConfigParam(?object $app, string $key, $default = null)
	{
		/**
		 * Any kind of Joomla CMS, Web, API or CLI application extends from AbstractApplication and has the get()
		 * method to return application configuration parameters.
		 */
		if (is_object($app) && ($app instanceof AbstractApplication))
		{
			return $app->get($key, $default);
		}

		/**
		 * A custom application may instead implement the Joomla\Application\ConfigurationAwareApplicationInterface
		 * interface (Joomla 4+), in which case it has the get() method to return application configuration parameters.
		 */
		if (is_object($app)
			&& interface_exists('Joomla\Application\ConfigurationAwareApplicationInterface', true)
			&& ($app instanceof ConfigurationAwareApplicationInterface))
		{
			return $app->get($key, $default);
		}

		/**
		 * A Joomla 3 custom application may simply implement the get() method without implementing an interface.
		 */
		if (is_object($app) && method_exists($app, 'get'))
		{
			return $app->get($key, $default);
		}

		/**
		 * At this point the $app variable is not an object or is something I can't use. Does the Joomla Factory still
		 * has the legacy static method getConfig() to get the application configuration? If so, use it.
		 */
		if (method_exists(Factory::class, 'getConfig'))
		{
			try
			{
				$jConfig = Factory::getConfig();

				if (is_object($jConfig) && ($jConfig instanceof Registry))
				{
					$jConfig->get($key, $default);
				}
			}
			catch (Throwable $e)
			{
				/**
				 * Factory tries to go through the application object. It might fail if there is a custom application
				 * which doesn't implement the interfaces Factory expects. In this case we get a Fatal Error which we
				 * can trap and fall through to the next if-block.
				 */
			}
		}

		/**
		 * When we are here all hope is nearly lost. We have to do a crude approximation of Joomla Factory's code to
		 * create an application configuration Registry object and retrieve the configuration values. This will work as
		 * long as the JConfig class (defined in configuration.php) has been loaded.
		 */
		$configPath = defined('JPATH_CONFIGURATION')
			? JPATH_CONFIGURATION
			:
			(defined('JPATH_ROOT') ? JPATH_ROOT : null);
		$configPath = $configPath ?? (__DIR__ . '/../../..');
		$configFile = $configPath . '/configuration.php';

		if (!class_exists('JConfig') && @file_exists($configFile) && @is_file($configFile) && @is_readable($configFile))
		{
			require_once $configFile;
		}

		if (class_exists('JConfig'))
		{
			try
			{
				$jConfig      = new Registry();
				$configObject = new JConfig();
				$jConfig->loadObject($configObject);

				return $jConfig->get($key, $default);
			}
			catch (Throwable $e)
			{
				return $default;
			}
		}

		/**
		 * All hope is lost. I can't find the application configuration. I am returning the default value and hope stuff
		 * won't break spectacularly...
		 */
		return $default;
	}

	public function getVersion(): object
	{
		$ret = (object) [
			'id'        => 0,
			'installed' => false,
			'version'   => '0.0.0',
			'api'       => null,
			'secret'    => null,
			'endpoints' => [],
		];

		$component = $this->getComponentElement();

		if (empty($component))
		{
			return $ret;
		}

		$ret->id        = ComponentHelper::getComponent($component)->id;
		$ret->installed = true;
		$ret->version   = $this->getComponentVersion($component);
		$ret->api       = $this->getMaxApiVersion($component);
		$ret->secret    = $this->getSecret($component);
		$ret->endpoints = $this->getEndpoints($component);

		return $ret;
	}

	private function getEndpoints(string $component): ?object
	{
		$maxVersion = $this->getMaxApiVersion($component);
		$baseUri    = rtrim(Uri::base(), '/') . '/';
		$rootUri    = substr($baseUri, 0, -4);

		if ($maxVersion >= 3)
		{
			return (object) [
				'v3' => [
					$baseUri . 'index.php/v3/akeebabackup',
				],
				'v2' => [
					$baseUri . 'index.php/v2/akeebabackup/index.php',
					$rootUri . sprintf('index.php?option=%s&view=Api&format=raw', $component),
				],
			];
		}

		if ($maxVersion == 2)
		{
			return (object) [
				'v2' => [
					$rootUri . sprintf('index.php?option=%s&view=Api&format=raw', $component),
				],
				'v1' => [
					$rootUri . sprintf('index.php?option=%s&view=json', $component),
				],
			];
		}

		return (object) [
			'v1' => [
				$rootUri . sprintf('index.php?option=%s&view=json', $component),
			],
		];
	}

	private function getSecret(string $component): ?string
	{
		$this->ensureFrontendApiEnabled($component);

		return $this->getDecodedSecret($component) ?: $this->createSecret($component);
	}

	private function getComponentElement(): ?string
	{
		return ComponentHelper::isEnabled('com_akeebabackup')
			? 'com_akeebabackup'
			: (ComponentHelper::isEnabled('com_akeeba') ? 'com_akeeba' : null);
	}

	private function getComponentVersion(?string $component): string
	{
		$component = empty($component) ? $this->getComponentElement() : $component;

		return $this->getVersionFromManifest($component)
			?: $this->getVersionFromExtensionsTable($component)
				?: '0.0.0';
	}

	private function getVersionFromExtensionsTable(string $component): ?string
	{
		/** @var DatabaseDriver $db */
		$db    = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true)
			->select($db->quoteName('manifest_cache'))
			->from($db->quoteName('#__extensions'))
			->where(
				[
					$db->quoteName('element') . ' = :component',
					$db->quoteName('type') . ' = ' . $db->quote('component'),
				]
			)
			->bind(':component', $component, ParameterType::STRING);
		$json  = $db->setQuery($query)->loadResult() ?: '{}';

		try
		{
			$manifest = @json_decode($json);

			if (!isset($manifest->version))
			{
				throw new RuntimeException('No cached version');
			}

			return $manifest->version ?: null;
		}
		catch (Exception $e)
		{
			return null;
		}
	}

	private function getVersionFromManifest(string $component): ?string
	{
		$manifestPath = rtrim(JPATH_ADMINISTRATOR, DIRECTORY_SEPARATOR . '/') .
			'/components/' . $component . '/' . substr($component, 4) . '.xml';

		if (!file_exists($manifestPath) || !is_readable($manifestPath))
		{
			return null;
		}

		$contents = @file_get_contents($manifestPath);

		if ($contents === false)
		{
			return null;
		}

		$hasMatch = preg_match('#<version>\s*([\d.]+)\s*</version>#is', $contents, $matches);

		if (!$hasMatch)
		{
			return null;
		}

		return $matches[1] ?: null;
	}

	private function getMaxApiVersion(string $component): ?string
	{
		$apiPrefix = rtrim(JPATH_API, DIRECTORY_SEPARATOR . '/') . '/components/' . $component;
		$prefix    = rtrim(JPATH_SITE, DIRECTORY_SEPARATOR . '/') . '/components/' . $component;

		$apiFiles = [
			$apiPrefix . '/src/Controller/ApiController.php' => '3',
			$prefix . '/src/Controller/ApiController.php'    => '2',
			$prefix . '/Controller/Api.php'                  => '2',
			$prefix . '/Controller/Json.php'                 => '1',
			$prefix . '/controllers/json.php'                => '1',
		];

		foreach ($apiFiles as $filePath => $version)
		{
			if (@file_exists($filePath) && is_file($filePath) && is_readable($filePath))
			{
				return $version;
			}
		}

		return null;
	}

	private function getDecodedSecret(string $component): ?string
	{
		$params = ComponentHelper::getParams($component);
		$secret = $params->get('frontend_secret_word', null);

		// No secret set
		if (empty($secret))
		{
			return null;
		}

		// A secret is set, and it's under 12 characters. Not enough space for encryption. Return verbatim.
		if (strlen($secret) < 12)
		{
			return $secret;
		}

		// If the secret does not have the encryption prefix, return verbatim.
		$prefix = substr($secret, 0, 12);

		if (!in_array($prefix, ['###AES128###', '###CTR128###']))
		{
			return $secret;
		}

		// Do I have the correct engine location?
		switch ($component)
		{
			// Akeeba Backup 5.6 to 8.x inclusive
			case 'com_akeeba':
				$engineRoot   = JPATH_ADMINISTRATOR . '/components/com_akeeba/BackupEngine';
				$platformRoot = JPATH_ADMINISTRATOR . '/components/com_akeeba/BackupPlatform/Joomla3x';
				break;

			// Akeeba Backup 9.x and later
			case 'com_akeebabackup':
				$engineRoot   = JPATH_ADMINISTRATOR . '/components/com_akeebabackup/engine';
				$platformRoot = JPATH_ADMINISTRATOR . '/components/com_akeebabackup/platform/Joomla';
				break;
		}

		// Yeah, well, no idea what is going on. Let me create a new secret key instead.
		if (!@is_dir($engineRoot))
		{
			return null;
		}

		// Necessary defines for Akeeba Engine
		if (!defined('AKEEBAENGINE'))
		{
			define('AKEEBAENGINE', 1);
			define('AKEEBAROOT', $engineRoot);
		}

		if (!defined('AKEEBA_BACKUP_ORIGIN'))
		{
			define('AKEEBA_BACKUP_ORIGIN', 'json');
		}

		// Make sure we have a profile set throughout the component's lifetime
		$app        = Factory::getApplication();
		$profile_id = $app->getSession()->get('akeebabackup.profile', null);

		if (is_null($profile_id))
		{
			$app->getSession()->set('akeebabackup.profile', 1);
		}

		// Load Akeeba Engine
		require_once AKEEBAROOT . '/Factory.php';

		// Tell the Akeeba Engine where to load the platform from
		Platform::addPlatform('joomla', $platformRoot);

		if (class_exists(Platform\Joomla::class))
		{
			// !!! IMPORTANT !!! DO NOT REMOVE! This triggers Akeeba Engine's autoloader. Without it the next line fails!
			$DO_NOT_REMOVE = Platform::getInstance();

			// Set the DBO to the Akeeba Engine platform for Joomla
			Platform\Joomla::setDbDriver(Factory::getContainer()->get('DatabaseDriver'));
		}

		// Load the configuration
		$akeebaEngineConfig = \Akeeba\Engine\Factory::getConfiguration();

		Platform::getInstance()->load_configuration();

		unset($akeebaEngineConfig);

		// Decrypt the encrypted setting
		return \Akeeba\Engine\Factory::getSecureSettings()->decryptSettings($secret) ?: '';
	}

	private function createSecret(string $component): ?string
	{
		$newSecret = UserHelper::genRandomPassword(32);
		$params    = ComponentHelper::getParams($component);
		$params->set('frontend_secret_word', $newSecret);

		self::saveParams($params, $component);

		return $newSecret;
	}

	private function ensureFrontendApiEnabled(string $component): void
	{
		// Which maximum API version do I have?
		$maxApiVersion = $this->getMaxApiVersion($component);

		// APIv3: Make sure the plugin is enabled
		if ($maxApiVersion >= 3 && !PluginHelper::isEnabled('webservices', 'akeebabackup'))
		{
			$this->enableWebservicesPlugin();
		}

		$params = ComponentHelper::getParams($component);

		// APIv3 (AB 9+) and APIv2 (AB 7.4+): jsonapi_enabled must be 1
		if ($maxApiVersion >= 2)
		{
			if ($params->get('jsonapi_enabled') != 1)
			{
				$params->set('jsonapi_enabled', 1);

				$this->saveParams($params, $component);
			}
		}

		// APIv1: jsonapi_enabled (AB 7.0 to 7.3) or frontend_enable (AB 3, 4, 5, 6) must be 1
		$version = $this->getComponentVersion($component);

		if (version_compare($version, '6.999.999', 'lt'))
		{
			if ($params->get('frontend_enable') != 1)
			{
				$params->set('frontend_enable', 1);

				$this->saveParams($params, $component);
			}
		}
		elseif ($params->get('jsonapi_enabled') != 1)
		{
			$params->set('jsonapi_enabled', 1);

			$this->saveParams($params, $component);
		}
	}

	private function enableWebservicesPlugin()
	{
		/** @var DatabaseDriver $db */
		$db    = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('element') . ' = 1')
			->where(
				[
					// type = 'plugin' and folder = 'webservices' and element = 'akeebabackup'
					$db->quoteName('type') . ' = ' . $db->quote('plugin'),
					$db->quoteName('folder') . ' = ' . $db->quote('webservices'),
					$db->quoteName('element') . ' = ' . $db->quote('akeebabackup'),
				]
			);
		$db->setQuery($query)->execute();
	}

	private function saveParams(Registry $params, string $component)
	{
		/** @var DatabaseDriver $db */
		$db   = Factory::getContainer()->get('DatabaseDriver');
		$data = $params->toString();

		$query = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('params') . ' = :params')
			->where(
				[
					$db->quoteName('element') . ' = :component',
					$db->quoteName('type') . ' = ' . $db->quote('component'),
				]
			)
			->bind(':params', $data, ParameterType::STRING)
			->bind(':component', $component, ParameterType::STRING);

		$db->setQuery($query)->execute();

		self::clearCacheGroups(['_system', 'system']);
	}
}
