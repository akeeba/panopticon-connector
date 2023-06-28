<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Route;

defined('_JEXEC') || die;

class Route
{
	/**
	 * Applicable HTTP verb, e.g. GET, POST, PUT, ...
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	private $method;

	/**
	 * Routing pattern e.g. /foo/:bar/:baz
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	private $pattern;

	/**
	 * The callable which should be executed when this route matches
	 *
	 * @var   callable
	 * @since 1.0.0
	 */
	private $callable;

	/**
	 * Regular expressions for each named route variable
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private $rules;

	/**
	 * Regular expression for the routing pattern (takes into account the rules)
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	private $regex = '';

	/**
	 * Variables known to the route
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private $routeVariables = [];

	public function __construct(
		string $method, string $pattern, callable $callable, array $rules = []
	)
	{
		$this->method   = strtoupper($method);
		$this->pattern  = $pattern;
		$this->callable = $callable;
		$this->rules    = $rules;

		$this->makeRegexAndRouteVars();
	}

	/**
	 * Get the applicable HTTP verb
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * Get the callable corresponding to this route
	 *
	 * @return  callable
	 * @since   1.0.0
	 */
	public function getCallable(): callable
	{
		return $this->callable;
	}

	/**
	 * Get the regular expression for this route
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	public function getRegex(): string
	{
		return $this->regex;
	}

	/**
	 * Get the variables for this route
	 *
	 * @return  array
	 * @since   1.0.0
	 */
	public function getRouteVariables(): array
	{
		return $this->routeVariables;
	}

	private function makeRegexAndRouteVars()
	{
		$regex = [];

		// Loop on each segment
		foreach (explode('/', trim($this->pattern, '/')) as $segment)
		{
			// `*` -> match anything and don't store into variables
			if ($segment == '*')
			{
				$regex[] = '.*';

				continue;
			}

			// `*something` -> match anything into variable 'something'
			if (strpos($segment, '*') === 0)
			{
				$this->routeVariables[] = substr($segment, 1);
				$regex[]                = '(.*)';

				continue;
			}

			// `:` -> unnamed variable (match segment but do not store into a variable)
			if ($segment === ':')
			{
				$regex[] = '([^/]*)';
				continue;
			}

			// `:something` -> named variable
			if (strpos($segment, ':') === 0)
			{
				$varName                = substr($segment, 1);
				$this->routeVariables[] = $varName;
				$regex[]                = $this->rules[$varName] ?? '([^/]*)';

				continue;
			}

			// Literal match
			$regex[] = preg_quote($segment);
		}

		// Using 0x01 as the regular expression delimiter to avoid conflicts
		$this->regex = chr(1) . '^' . implode('/', $regex) . '$' . chr(1);
	}
}