<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Tests\Unit\Library;

use Akeeba\Component\Panopticon\Api\Library\VersionStability;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use z4kn4fein\SemVer\VersionFormatException;

class VersionStabilityTest extends TestCase
{
	// ---------------------------------------------------------------------------------------------
	// detectStability()
	// ---------------------------------------------------------------------------------------------

	public static function detectStabilityProvider(): iterable
	{
		yield 'plain stable version' => ['1.2.3', 'stable'];
		yield 'another stable version' => ['2.0.0', 'stable'];
		yield 'alpha pre-release' => ['1.2.3-alpha1', 'alpha'];
		yield 'alpha, dotted pre-release, mixed case' => ['1.2.3-ALPHA.1', 'alpha'];
		yield 'beta pre-release' => ['1.2.3-beta2', 'beta'];
		yield 'release candidate' => ['1.2.3-rc1', 'rc'];
		yield 'explicit dev tag' => ['1.2.3-dev', 'dev'];
		yield 'dev tag with dotted suffix' => ['1.2.3-dev.20240101', 'dev'];
		yield 'unrecognised pre-release tag falls back to dev' => ['1.2.3-nightly1', 'dev'];
	}

	#[DataProvider('detectStabilityProvider')]
	public function testDetectStability(string $version, string $expected): void
	{
		$this->assertSame($expected, VersionStability::detectStability($version));
	}

	public static function malformedVersionProvider(): iterable
	{
		yield 'not a version at all' => ['not-a-version'];
		yield 'missing patch component' => ['1.2'];
		yield 'too many components' => ['1.2.3.4'];
	}

	#[DataProvider('malformedVersionProvider')]
	public function testDetectStabilityThrowsOnMalformedVersionString(string $version): void
	{
		// z4kn4fein\SemVer\Version::parse() throws on malformed input, and detectStability() does not
		// catch it — this is existing (pre-refactor) behaviour, preserved verbatim.
		$this->expectException(VersionFormatException::class);

		VersionStability::detectStability($version);
	}

	public function testDetectStabilityThrowsOnEmptyVersionString(): void
	{
		$this->expectException(VersionFormatException::class);

		VersionStability::detectStability('');
	}

	// ---------------------------------------------------------------------------------------------
	// stabilityToString()
	// ---------------------------------------------------------------------------------------------

	public static function stabilityToStringProvider(): iterable
	{
		yield 'dev' => [0, 'dev'];
		yield 'alpha' => [1, 'alpha'];
		yield 'beta' => [2, 'beta'];
		yield 'rc' => [3, 'rc'];
		yield 'stable' => [4, 'stable'];
		yield 'unknown positive value defaults to stable' => [5, 'stable'];
		yield 'negative value defaults to stable' => [-1, 'stable'];
	}

	#[DataProvider('stabilityToStringProvider')]
	public function testStabilityToString(int $stability, string $expected): void
	{
		$this->assertSame($expected, VersionStability::stabilityToString($stability));
	}
}
