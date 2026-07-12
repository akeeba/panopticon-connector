<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Tests\Integration\Tests;

use Akeeba\Panopticon\Tests\Integration\AbstractApiTestCase;

/**
 * Exercises the "updates" refresh endpoint with a valid token.
 */
class UpdatesTest extends AbstractApiTestCase
{
	public function testRefreshUpdatesReturnsNoContent(): void
	{
		$response = $this->api('POST', 'v1/panopticon/updates');

		$this->assertStatus(204, $response);
	}
}
