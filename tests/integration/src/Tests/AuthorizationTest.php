<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Tests\Integration\Tests;

use Akeeba\Panopticon\Tests\Integration\AbstractApiTestCase;

/**
 * Verifies that guarded endpoints reject unauthenticated / garbage-token
 * requests, and that a valid token is accepted.
 */
class AuthorizationTest extends AbstractApiTestCase
{
	public static function guardedEndpointProvider(): array
	{
		return [
			'POST admintools/tempsuperuser' => ['POST', 'v1/panopticon/admintools/tempsuperuser'],
			'POST update'                   => ['POST', 'v1/panopticon/update'],
			'GET akeebabackup/info'         => ['GET', 'v1/panopticon/akeebabackup/info'],
			'GET extensions'                => ['GET', 'v1/panopticon/extensions'],
		];
	}

	/**
	 * @dataProvider guardedEndpointProvider
	 */
	public function testGuardedEndpointRejectsRequestsWithNoToken(string $method, string $path): void
	{
		$response = $this->api($method, $path, null, null);

		$this->assertGreaterThanOrEqual(
			400,
			$response['status'],
			sprintf('%s %s should reject an unauthenticated request. Body: %s', $method, $path, $response['raw'])
		);
	}

	/**
	 * @dataProvider guardedEndpointProvider
	 */
	public function testGuardedEndpointRejectsAGarbageToken(string $method, string $path): void
	{
		$response = $this->api($method, $path, null, 'this-is-not-a-valid-token');

		$this->assertGreaterThanOrEqual(
			400,
			$response['status'],
			sprintf('%s %s should reject a garbage token. Body: %s', $method, $path, $response['raw'])
		);
	}

	public function testExtensionsSucceedsWithAValidToken(): void
	{
		$response = $this->api('GET', 'v1/panopticon/extensions');

		$this->assertJsonApiSuccess($response);
	}
}
