<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Tests\Unit\Library;

use Akeeba\Plugin\Console\Panopticon\Command\GetToken;
use PHPUnit\Framework\TestCase;

/**
 * Tests GetToken::computeApiToken(), the pure HMAC computation extracted out of the private
 * getApiToken() method (see plugins/console/panopticon/src/Command/GetToken.php).
 *
 * Loading Akeeba\Plugin\Console\Panopticon\Command\GetToken requires a couple of Joomla Framework
 * symbols to exist (its class declaration extends/implements/uses them) — see
 * tests/unit/stubs/JoomlaConsoleCommandStub.php for the minimal stand-ins used here. Only
 * computeApiToken() itself (a plain static method using scalar types only) is exercised.
 */
class ApiTokenTest extends TestCase
{
	public function testComputeApiTokenMatchesTheHandComputedHmac(): void
	{
		$userId     = 123;
		$tokenSeed  = base64_encode('a-fairly-random-32-byte-seed...');
		$siteSecret = 'the-secret';

		$expected = base64_encode(
			'sha256:' . $userId . ':' . hash_hmac('sha256', base64_decode($tokenSeed), $siteSecret)
		);

		$this->assertSame($expected, GetToken::computeApiToken($userId, $tokenSeed, $siteSecret));
	}

	public function testComputeApiTokenIsDeterministic(): void
	{
		$userId     = 42;
		$tokenSeed  = base64_encode(random_bytes(32));
		$siteSecret = 'another-secret';

		$this->assertSame(
			GetToken::computeApiToken($userId, $tokenSeed, $siteSecret),
			GetToken::computeApiToken($userId, $tokenSeed, $siteSecret)
		);
	}

	public function testComputeApiTokenDiffersWhenTheUserIdChanges(): void
	{
		$tokenSeed  = base64_encode('same-seed-same-seed-same-seed..');
		$siteSecret = 'same-secret';

		$this->assertNotSame(
			GetToken::computeApiToken(1, $tokenSeed, $siteSecret),
			GetToken::computeApiToken(2, $tokenSeed, $siteSecret)
		);
	}

	public function testComputeApiTokenDiffersWhenTheSiteSecretChanges(): void
	{
		$userId    = 7;
		$tokenSeed = base64_encode('same-seed-same-seed-same-seed..');

		$this->assertNotSame(
			GetToken::computeApiToken($userId, $tokenSeed, 'secret-one'),
			GetToken::computeApiToken($userId, $tokenSeed, 'secret-two')
		);
	}

	public function testComputeApiTokenEncodesTheAlgorithmAndUserIdInPlainSight(): void
	{
		$userId     = 999;
		$tokenSeed  = base64_encode('yet-another-seed-value-here....');
		$siteSecret = 'shhh';

		$decoded = base64_decode(GetToken::computeApiToken($userId, $tokenSeed, $siteSecret));

		$this->assertStringStartsWith('sha256:999:', $decoded);
	}
}
