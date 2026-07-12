<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * A minimal stand-in for `Joomla\Registry\Registry`, good enough to exercise
 * `Akeeba\Component\Panopticon\Api\Library\JRegistryForAPIWorkaround` in isolation, without loading the
 * real Joomla Framework (which is intentionally absent from this unit test suite; see
 * tests/unit/bootstrap.php).
 *
 * It only implements the small subset of behaviour the workaround class and its test rely on: a
 * dot-path `get()`/`set()` pair, and the `__get()`/`__set()` magic methods. `$magicSetCalls` is an
 * extra bit of test-only instrumentation (the real Registry class has no such property) that lets the
 * test observe whether `parent::__set()` was actually reached.
 *
 * Loaded unconditionally from tests/unit/bootstrap.php, but guarded by a `class_exists()` check so it
 * never clobbers the real class if this suite is ever run alongside a full Joomla Framework.
 */

namespace Joomla\Registry;

if (!class_exists(Registry::class, false))
{
	class Registry
	{
		private const SEPARATOR = '.';

		/** @var \stdClass */
		protected $data;

		/**
		 * Test-only instrumentation: counts how many times the magic __set() below has run.
		 *
		 * @var int
		 */
		public $magicSetCalls = 0;

		public function __construct($data = null)
		{
			$this->data = new \stdClass();

			if (is_array($data))
			{
				foreach ($data as $key => $value)
				{
					$this->set((string) $key, $value);
				}
			}
			elseif (is_object($data))
			{
				foreach (get_object_vars($data) as $key => $value)
				{
					$this->set((string) $key, $value);
				}
			}
			elseif (is_string($data) && $data !== '')
			{
				$decoded = json_decode($data, true);

				if (is_array($decoded))
				{
					foreach ($decoded as $key => $value)
					{
						$this->set((string) $key, $value);
					}
				}
			}
		}

		public function get(string $path, $default = null)
		{
			$node = $this->data;

			foreach (explode(self::SEPARATOR, $path) as $n)
			{
				if (is_object($node) && isset($node->$n))
				{
					$node = $node->$n;

					continue;
				}

				return $default;
			}

			return $node;
		}

		public function set(string $path, $value)
		{
			$nodes = explode(self::SEPARATOR, $path);
			$last  = array_pop($nodes);
			$node  = $this->data;

			foreach ($nodes as $n)
			{
				if (!isset($node->$n) || !is_object($node->$n))
				{
					$node->$n = new \stdClass();
				}

				$node = $node->$n;
			}

			$node->$last = $value;

			return $value;
		}

		public function __get($name)
		{
			return $this->get($name);
		}

		public function __set($name, $value)
		{
			$this->magicSetCalls++;

			$this->set($name, $value);
		}
	}
}
