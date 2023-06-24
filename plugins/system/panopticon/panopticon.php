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
use Joomla\CMS\Input\Input;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class plgSystemPanopticon extends CMSPlugin
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
		$basePath    = trim(Uri::base(true), '/');
		$currentPath = trim(Uri::getInstance()->getPath(), '/');

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

	private function getRouter(Input $input): Router
	{
		$router = new Router($input);

		$router->addRoute(new Route(
			'GET',
			self::API_PREFIX . 'core/update',
			function (Input $input) {
				return (object) [
					'foo' => 'bar'
				];
			}
		));

		// TODO Add routes

		return $router;
	}
}