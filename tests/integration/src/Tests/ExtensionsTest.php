<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Tests\Integration\Tests;

use Akeeba\Panopticon\Tests\Integration\AbstractApiTestCase;

/**
 * Exercises the "extensions" JSON:API list/item endpoints with a valid token.
 */
class ExtensionsTest extends AbstractApiTestCase
{
	public function testListExtensionsReturnsAJsonApiCollection(): void
	{
		$response = $this->api('GET', 'v1/panopticon/extensions');

		$this->assertJsonApiSuccess($response);
		$this->assertArrayHasKey('data', $response['json'], 'Missing "data" member: ' . $response['raw']);
		$this->assertIsArray($response['json']['data']);
		$this->assertNotEmpty($response['json']['data'], 'Expected at least one installed extension.');
	}

	public function testGetExtensionByElementResolvesTrackedExtension(): void
	{
		// The connector only lists/resolves extensions it tracks for updates. Core
		// extensions such as com_content have no update site and are (correctly) not
		// resolvable here, so we resolve the connector's own package element, which is
		// always installed and tracked. This exercises the same element -> id -> item
		// resolution path as any other tracked extension.
		$response = $this->api('GET', 'v1/panopticon/extension/pkg_panopticon');

		$this->assertJsonApiSuccess($response);
		$this->assertArrayHasKey('data', $response['json'], 'Missing "data" member: ' . $response['raw']);

		$data = $response['json']['data'];

		$this->assertIsArray($data);
		$this->assertArrayHasKey('attributes', $data, 'Missing data.attributes: ' . $response['raw']);
		$this->assertSame(
			'pkg_panopticon',
			$data['attributes']['element'] ?? null,
			'Resolved the wrong extension: ' . $response['raw']
		);
	}
}
