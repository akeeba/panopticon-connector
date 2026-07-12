<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Tests\Integration\Tests;

use Akeeba\Panopticon\Tests\Integration\AbstractApiTestCase;

/**
 * Proves the harness can reach a live Panopticon Connector install and that
 * it reports the expected API level (AKEEBA_PANOPTICON_API in
 * component/backend/version.php).
 */
class SmokeTest extends AbstractApiTestCase
{
	public function testCoreUpdateEndpointReportsTheExpectedApiLevel(): void
	{
		$response = $this->api('GET', 'v1/panopticon/core/update');

		$this->assertJsonApiSuccess($response);

		$attributes = $response['json']['data']['attributes'] ?? null;

		$this->assertIsArray($attributes, 'Missing data.attributes in response: ' . $response['raw']);
		$this->assertArrayHasKey('panopticon', $attributes, 'Missing "panopticon" attribute: ' . $response['raw']);

		$panopticon = $attributes['panopticon'];

		$this->assertIsArray($panopticon);
		$this->assertArrayHasKey('api', $panopticon);
		$this->assertSame(101, $panopticon['api'], 'Unexpected AKEEBA_PANOPTICON_API level: ' . $response['raw']);
		$this->assertArrayHasKey('version', $panopticon);
		$this->assertArrayHasKey('date', $panopticon);
	}
}
