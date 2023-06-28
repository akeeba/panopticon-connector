<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Route;

defined('_JEXEC') || die;

class Router
{
	/**
	 * Known routes
	 *
	 * @var   Route[]
	 * @since 1.0.0
	 */
	protected $routes = [];

	/**
	 * The input object where variables will be set
	 *
	 * @var   \JInput
	 * @since 1.0.0
	 */
	protected $input;

	/**
	 * Constructor
	 *
	 * @param   \JInput  $input  The input object where variables will be set
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function __construct(\JInput $input)
	{
		$this->input = $input;
	}

	/**
	 * Add a route
	 *
	 * @param   Route  $route  The route to add
	 *
	 * @return  $this
	 * @since   1.0.0
	 */
	public function addRoute(Route $route): self
	{
		if (!in_array($route, $this->routes))
		{
			$this->routes[] = $route;
		}

		return $this;
	}

	/**
	 * Add multiple routes
	 *
	 * @param   Route[]  $routes  An array of routes
	 *
	 * @return  $this
	 * @since   1.0.0
	 */
	public function addRoutes(array $routes): self
	{
		foreach ($routes as $route)
		{
			if (!$route instanceof Route)
			{
				continue;
			}

			$this->addRoute($route);
		}

		return $this;
	}

	/**
	 * Parse the route, returning the callable to use (or null if we failed to parse the route)
	 *
	 * @param   string       $path    The path to parse
	 * @param   string|null  $method  The HTTP method
	 *
	 * @return  callable|null
	 * @throws  \Exception
	 * @since   1.0.0
	 */
	public function parseRoute(string $path, ?string $method = null): ?callable
	{
		$method = $method ?? strtoupper($this->input->getMethod());
		$path   = ltrim($path, '/');

		foreach ($this->routes as $route)
		{
			if ($route->getMethod() !== $method)
			{
				continue;
			}

			if (!preg_match($route->getRegex(), $path, $matches))
			{
				continue;
			}

			foreach ($route->getRouteVariables() as $i => $key)
			{
				$this->input->set($key, $matches[$i + 1]);
			}

			return $route->getCallable();
		}

		return null;
	}
}