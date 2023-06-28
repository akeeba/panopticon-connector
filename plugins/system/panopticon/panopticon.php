<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('_JEXEC') || die;

use Akeeba\PanopticonConnector\AcceptHeaderMatch;
use Akeeba\PanopticonConnector\Authentication;
use Akeeba\PanopticonConnector\Route\Route;
use Akeeba\PanopticonConnector\Route\Router;

class plgSystemPanopticon extends \JPlugin
{
	/**
	 * Joomla database object
	 *
	 * @var   JDatabaseDriver
	 * @since 1.0.0
	 */
	protected $db;

	/**
	 * Application object.
	 *
	 * @var    JApplicationCms
	 * @since  1.0.0
	 */
	protected $app;

	private const PATH_PREFIX = 'panopticon_api';

	private const API_PREFIX = 'v1/panopticon/';

	public function onAfterInitialise()
	{
		if (!$this->app->isClient('site'))
		{
			return;
		}

		// Get the relative path
		$basePath    = trim(\JUri::base(true), '/');
		$currentPath = trim(\JUri::getInstance()->getPath(), '/');

		if (strlen($basePath) > 0 && substr($currentPath, 0, strlen($basePath)) === $basePath)
		{
			$currentPath = ltrim(substr($currentPath, strlen($basePath)), '/');
		}

		// Remove index.php from the path (deals with index.php/api/... or index.php?/api...
		if (substr($currentPath, 0, 10) === 'index.php/' || substr($currentPath, 0, 10) === 'index.php?')
		{
			$currentPath = ltrim(substr($currentPath, 10),'/');
		}

		// The remaining path must start with self::PATH_PREFIX followed by a slash
		$commmonPrefix = self::PATH_PREFIX . '/';

		if (substr($currentPath, 0, strlen($commmonPrefix)) !== $commmonPrefix)
		{
			return;
		}

		$currentPath = substr($currentPath, strlen($commmonPrefix));

		// This might be followed by index.php and a slash. We need to remove that
		if (substr($currentPath, 0, 10) === 'index.php/')
		{
			$currentPath = ltrim(substr($currentPath, 10),'/');
		}

		// At this point, we have an API path we are supposed to handle. First, register the PSR-4 autoloader.
		JLoader::registerNamespace('Akeeba\\PanopticonConnector', __DIR__ . '/src', false, false, 'psr4');

		require_once __DIR__ . '/version.php';

		try
		{
			$input = $this->app->input;

			// Route. Returns a callable or null
			/** @noinspection PhpParamsInspection */
			$callable = $this->getRouter($input)->parseRoute($currentPath);

			// Detect routing errors
			if (empty($callable) || !is_callable($callable))
			{
				throw new RuntimeException('Resource not found', 404);
			}

			// Authentication
			if (!(new Authentication())->isAuthenticated())
			{
				throw new RuntimeException('Forbidden', 401);
			}

			// Check the Accept header. We can only do "application/vnd.api+json".
			$accept = new AcceptHeaderMatch('application/vnd.api+json');

			if (!$accept($this->app->input->server->getString('HTTP_ACCEPT')))
			{
				throw new RuntimeException('Could not match accept header', 406);
			}

			// Execute the callable and respond
			$this->respond($callable($input));
		}
		catch (Throwable $e)
		{
			// HTTP 406 is a special case
			if ($e->getCode() === 406)
			{
				$this->respondRawStatus($e);
			}

			// For anything else, respond with an error array
			$errors = [];
			$throwable = $e;

			do {
				$error      = [
					'title' => $e->getMessage()
				];

				if (is_int($e->getCode()) && $e->getCode() > 0)
				{
					$error['code'] = (int) $e->getCode();
				}

				$errors[] = $error;
			} while ($throwable = $throwable->getPrevious());

			$this->respond((object) ['errors' => $errors], $e->getCode());
		}
	}

	#[\JetBrains\PhpStorm\NoReturn]
	private function respond(object $data, int $code = 200): void
	{
		@ob_end_clean();

		$this->app->setHeader('status', $code);
		// Set the Content-Type
		$this->app->setHeader('Content-Type', 'application/vnd.api+json; charset=utf-8');
		// No caching (HTTP/1.1 and later)
		$this->app->setHeader('Expires', 'Wed, 17 Aug 2005 00:00:00 GMT', true);
		$this->app->setHeader('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT', true);
		$this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0', false);
		// No caching (HTTP/1.0)
		$this->app->setHeader('Pragma', 'no-cache');

		$this->app->sendHeaders();

		echo json_encode($data);

		$this->app->close();
	}

	#[\JetBrains\PhpStorm\NoReturn]
	private function respondRawStatus(Throwable $e)
	{
		@ob_end_clean();

		$this->app->setHeader('status', $e->getCode());
		// Set the Content-Type
		$this->app->setHeader('Content-Type', 'text/html; charset=utf-8');
		// No caching (HTTP/1.1 and later)
		$this->app->setHeader('Expires', 'Wed, 17 Aug 2005 00:00:00 GMT', true);
		$this->app->setHeader('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT', true);
		$this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0', false);
		// No caching (HTTP/1.0)
		$this->app->setHeader('Pragma', 'no-cache');

		$this->app->sendHeaders();

		echo $e->getMessage();

		$this->app->close();
	}

	private function getRouter(\JInput $input): Router
	{
		$router = new Router($input);

		$router->addRoute(new Route(
			'GET',
			self::API_PREFIX . 'core/update',
			new \Akeeba\PanopticonConnector\Controller\CoreUpdate()
		));

		$router->addRoute(new Route(
			'POST',
			self::API_PREFIX . 'core/update',
			new \Akeeba\PanopticonConnector\Controller\CoreUpdatePost()
		));

		// The former is required for the connection test.
		$router->addRoute(new Route(
			'GET',
			'v1/extensions',
			new \Akeeba\PanopticonConnector\Controller\Extensions()
		));

		$router->addRoute(new Route(
			'GET',
			self::API_PREFIX . 'extensions',
			new \Akeeba\PanopticonConnector\Controller\Extensions()
		));

		$router->addRoute(new Route(
			'POST',
			self::API_PREFIX . 'updates',
			new \Akeeba\PanopticonConnector\Controller\ExtensionsUpdatesRefresh()
		));

		$router->addRoute(new Route(
			'POST',
			self::API_PREFIX . 'update',
			new \Akeeba\PanopticonConnector\Controller\ExtensionsUpdateApply()
		));

		$router->addRoute(new Route(
			'POST',
			self::API_PREFIX . 'core/update/download',
			new \Akeeba\PanopticonConnector\Controller\CoreUpdateDownload()
		));

		// TODO Chunked downloads (future featur)
		// GET core/update/chunk_start
		// GET core/update/chunk_step

		$router->addRoute(new Route(
			'POST',
			self::API_PREFIX . 'core/update/activate',
			new \Akeeba\PanopticonConnector\Controller\CoreUpdateActivate()
		));

		$router->addRoute(new Route(
			'POST',
			self::API_PREFIX . 'core/update/postupdate',
			new \Akeeba\PanopticonConnector\Controller\CoreUpdatePostupdate()
		));

		$router->addRoute(new Route(
			'GET',
			self::API_PREFIX . 'akeebabackup/info',
			new \Akeeba\PanopticonConnector\Controller\AkeebaBackupInfo()
		));

		$router->addRoute(new Route(
			'POST',
			self::API_PREFIX . 'admintools/unblock',
			new \Akeeba\PanopticonConnector\Controller\AdmintoolsUnblock()
		));

		$router->addRoute(new Route(
			'POST',
			self::API_PREFIX . 'admintools/plugin/disable',
			new \Akeeba\PanopticonConnector\Controller\AdmintoolsPluginDisable()
		));

		$router->addRoute(new Route(
			'POST',
			self::API_PREFIX . 'admintools/plugin/enable',
			new \Akeeba\PanopticonConnector\Controller\AdmintoolsPluginEnable()
		));

		// TODO POST admintools/htaccess/disable
//		$router->addRoute(new Route(
//			'POST',
//			self::API_PREFIX . 'admintools/htaccess/disable',
//			new \Akeeba\PanopticonConnector\Controller\AdmintoolsHtaccessDisable()
//		));

		// TODO POST admintools/htaccess/enable
//		$router->addRoute(new Route(
//			'POST',
//			self::API_PREFIX . 'admintools/htaccess/enable',
//			new \Akeeba\PanopticonConnector\Controller\AdmintoolsHtaccessEnable()
//		));

		// TODO POST admintools/htaccess/tempsuperuser
//		$router->addRoute(new Route(
//			'POST',
//			self::API_PREFIX . 'admintools/tempsuperuser',
//			new \Akeeba\PanopticonConnector\Controller\AdmintoolsTempsuperuser()
//		));

		// TODO POST admintools/scanner/start
//		$router->addRoute(new Route(
//			'POST',
//			self::API_PREFIX . 'admintools/scanner/start',
//			new \Akeeba\PanopticonConnector\Controller\AdmintoolsScannerStart()
//		));

		// TODO POST admintools/scanner/step
//		$router->addRoute(new Route(
//			'POST',
//			self::API_PREFIX . 'admintools/scanner/step',
//			new \Akeeba\PanopticonConnector\Controller\AdmintoolsScannerStep()
//		));

		// TODO POST admintools/scans
//		$router->addRoute(new Route(
//			'GET',
//			self::API_PREFIX . 'admintools/scans',
//			new \Akeeba\PanopticonConnector\Controller\AdmintoolsScans()
//		));

		return $router;
	}
}