<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Tests\Unit\Library;

use Akeeba\Component\Panopticon\Api\Library\JRegistryForAPIWorkaround;
use PHPUnit\Framework\TestCase;

/**
 * JRegistryForAPIWorkaround extends the real Joomla\Registry\Registry, which this suite deliberately
 * does not load (see tests/unit/bootstrap.php); tests/unit/stubs/JoomlaRegistryStub.php provides a
 * minimal stand-in with the same get()/set()/__get()/__set() surface, plus a `$magicSetCalls` counter
 * (test-only instrumentation, not present on the real class) used below to observe whether
 * parent::__set() was actually reached.
 *
 * This suite's bootstrap defines JVERSION as '5.1.0' (see tests/unit/bootstrap.php), which is < the
 * 5.999.999 threshold in JRegistryForAPIWorkaround::__set() — so only that branch (both $this->set()
 * AND parent::__set() being called) is exercised here, per the plan for this test.
 */
class JRegistryForAPIWorkaroundTest extends TestCase
{
	public function testSetAndGetRoundTrip(): void
	{
		$registry = new JRegistryForAPIWorkaround();

		$registry->set('foo', 'bar');

		$this->assertSame('bar', $registry->get('foo'));
	}

	public function testGetReturnsTheDefaultWhenTheKeyIsMissing(): void
	{
		$registry = new JRegistryForAPIWorkaround();

		$this->assertNull($registry->get('missing'));
		$this->assertSame('fallback', $registry->get('missing', 'fallback'));
	}

	public function testMagicSetAlsoRoundTripsThroughGet(): void
	{
		$registry = new JRegistryForAPIWorkaround();

		$registry->foo = 'baz';

		$this->assertSame('baz', $registry->get('foo'));
	}

	public function testMagicSetOnPreJoomla6AlsoInvokesTheParentMagicSetter(): void
	{
		// JVERSION is '5.1.0' in this suite (< 5.999.999), so __set() should call BOTH $this->set()
		// (always) AND parent::__set() (only on pre-Joomla-6). We can observe the latter via the
		// stub's $magicSetCalls counter, which only the stub's own __set() increments.
		$registry = new JRegistryForAPIWorkaround();

		$this->assertSame(0, $registry->magicSetCalls);

		$registry->someKey = 'someValue';

		$this->assertSame(1, $registry->magicSetCalls);
		$this->assertSame('someValue', $registry->get('someKey'));
	}
}
