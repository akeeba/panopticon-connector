<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Tests\Integration\Tests;

use Akeeba\Panopticon\Tests\Integration\AbstractApiTestCase;

/**
 * Exercises the "akeebabackup/info" endpoint with a valid token. Akeeba
 * Backup is not installed on the integration test site, so the connector is
 * expected to report the documented "not installed" shape rather than fail.
 */
class BackupTest extends AbstractApiTestCase
{
	public function testAkeebaBackupInfoReportsNotInstalled(): void
	{
		$response = $this->api('GET', 'v1/panopticon/akeebabackup/info');

		$this->assertJsonApiSuccess($response);

		$attributes = $response['json']['data']['attributes'] ?? null;

		$this->assertIsArray($attributes, 'Missing data.attributes in response: ' . $response['raw']);
		$this->assertArrayHasKey('installed', $attributes);
		$this->assertFalse($attributes['installed'], 'Expected Akeeba Backup to be reported as not installed.');
		$this->assertArrayHasKey('version', $attributes);
	}
}
