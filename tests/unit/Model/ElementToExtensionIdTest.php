<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Tests\Unit\Model;

use Akeeba\Component\Panopticon\Api\Model\ElementToExtensionIdTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A trivial class that `use`s the trait under test, exposing its (now `protected static`)
 * `extensionNameToCriteria()` through a public static wrapper.
 */
final class TestableElementToExtensionId
{
	use ElementToExtensionIdTrait;

	public static function callExtensionNameToCriteria(string $extensionName): array
	{
		return self::extensionNameToCriteria($extensionName);
	}
}

class ElementToExtensionIdTest extends TestCase
{
	public static function extensionNameProvider(): iterable
	{
		yield 'package' => [
			'pkg_foo',
			['type' => 'package', 'element' => 'pkg_foo'],
		];

		yield 'component' => [
			'com_foo',
			['type' => 'component', 'element' => 'com_foo'],
		];

		yield 'plugin' => [
			'plg_system_foo',
			['type' => 'plugin', 'folder' => 'system', 'element' => 'foo'],
		];

		yield 'plugin whose element itself contains an underscore' => [
			'plg_system_foo_bar',
			['type' => 'plugin', 'folder' => 'system', 'element' => 'foo_bar'],
		];

		yield 'site module' => [
			'mod_foo',
			['type' => 'module', 'element' => 'mod_foo', 'client_id' => 0],
		];

		yield 'admin module' => [
			'amod_foo',
			['type' => 'module', 'element' => 'mod_foo', 'client_id' => 1],
		];

		yield 'core "files_" pseudo-extension' => [
			'files_joomla',
			['type' => 'file', 'element' => 'joomla'],
		];

		yield 'file extension' => [
			'file_foo',
			['type' => 'file', 'element' => 'file_foo'],
		];

		yield 'library' => [
			'lib_foo',
			['type' => 'library', 'element' => 'foo'],
		];

		yield 'unrecognised prefix' => [
			'xyz_foo',
			[],
		];

		yield 'no underscore at all' => [
			'joomla',
			[],
		];
	}

	#[DataProvider('extensionNameProvider')]
	public function testExtensionNameToCriteria(string $extensionName, array $expected): void
	{
		$this->assertSame($expected, TestableElementToExtensionId::callExtensionNameToCriteria($extensionName));
	}
}
