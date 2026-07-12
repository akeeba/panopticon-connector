<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Base class for HTTP end-to-end tests against a real, Docker-provisioned
 * Joomla + Panopticon Connector install (see tests/integration/run-tests.sh).
 *
 * Pure HTTP client: no Joomla application is booted in this process.
 */
abstract class AbstractApiTestCase extends TestCase
{
	/**
	 * Sentinel default for the $token parameter of self::api(): means "use the
	 * valid token read from the environment". Pass null (or an empty string)
	 * explicitly to send NO Authorization header (the unauthenticated case), or
	 * any other string to send it verbatim as the bearer token (e.g. a garbage
	 * token, to exercise authorization failures).
	 */
	protected const SELF_TOKEN = "\0__panopticon_self_token__\0";

	protected static string $baseUrl = '';

	protected static string $token = '';

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$baseUrl = getenv('PANOPTICON_BASE_URL') ?: '';
		$token   = getenv('PANOPTICON_API_TOKEN') ?: '';

		if ($baseUrl === '' || $token === '')
		{
			self::markTestSkipped(
				'PANOPTICON_BASE_URL and/or PANOPTICON_API_TOKEN are not set. ' .
				'Run the integration suite via tests/integration/run-tests.sh.'
			);
		}

		self::$baseUrl = rtrim($baseUrl, '/');
		self::$token   = $token;
	}

	/**
	 * Perform an HTTP request against the Panopticon Connector API.
	 *
	 * @param   string       $method  HTTP method (GET, POST, PATCH, DELETE, ...).
	 * @param   string       $path    Route relative to the base URL, e.g. "v1/panopticon/core/update".
	 * @param   array|null   $body    Optional request body; JSON-encoded and sent with a
	 *                                Content-Type: application/json header when provided.
	 * @param   string|null  $token   Bearer token to send. Defaults to the valid token read
	 *                                from the environment. Pass null or '' to send NO
	 *                                Authorization header (unauthenticated request); pass any
	 *                                other string (e.g. a garbage value) to send it verbatim.
	 *
	 * @return  array{status:int,headers:string,json:?array,raw:string}
	 */
	protected function api(string $method, string $path, ?array $body = null, ?string $token = self::SELF_TOKEN): array
	{
		if ($token === self::SELF_TOKEN)
		{
			$token = self::$token;
		}

		$url = self::$baseUrl . '/' . ltrim($path, '/');

		$headers = [
			'Accept: application/vnd.api+json',
			'Content-Type: application/json',
			'User-Agent: panopticon/test',
		];

		if (!empty($token))
		{
			$headers[] = 'Authorization: Bearer ' . $token;
		}

		$ch = curl_init($url);

		$options = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST  => strtoupper($method),
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_HEADER         => true,
			CURLOPT_HTTPHEADER     => $headers,
		];

		if ($body !== null)
		{
			$options[CURLOPT_POSTFIELDS] = json_encode($body);
		}

		curl_setopt_array($ch, $options);

		$raw = curl_exec($ch);

		if ($raw === false)
		{
			$error = curl_error($ch);
			curl_close($ch);

			$this->fail(sprintf('cURL request %s %s failed: %s', $method, $url, $error));
		}

		$status     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		curl_close($ch);

		$rawHeaders = substr((string) $raw, 0, $headerSize);
		$rawBody    = substr((string) $raw, $headerSize);

		return [
			'status'  => $status,
			'headers' => $rawHeaders,
			'json'    => json_decode($rawBody, true),
			'raw'     => $rawBody,
		];
	}

	/**
	 * Assert that an api() response has a 2xx status and a decodable JSON:API
	 * body without an "errors" member.
	 *
	 * @param   array{status:int,headers:string,json:?array,raw:string}  $response
	 */
	protected function assertJsonApiSuccess(array $response, string $message = ''): void
	{
		$this->assertGreaterThanOrEqual(
			200,
			$response['status'],
			$message !== '' ? $message : 'Expected a successful HTTP status. Body: ' . $response['raw']
		);
		$this->assertLessThan(
			300,
			$response['status'],
			$message !== '' ? $message : 'Expected a successful HTTP status. Body: ' . $response['raw']
		);
		$this->assertIsArray($response['json'], 'Response body is not valid JSON: ' . $response['raw']);
		$this->assertArrayNotHasKey(
			'errors',
			$response['json'],
			'Response contains JSON:API errors: ' . $response['raw']
		);
	}

	/**
	 * Assert that an api() response has the expected HTTP status code.
	 *
	 * @param   array{status:int,headers:string,json:?array,raw:string}  $response
	 */
	protected function assertStatus(int $expected, array $response, string $message = ''): void
	{
		$this->assertSame(
			$expected,
			$response['status'],
			$message !== '' ? $message : sprintf(
				'Expected HTTP status %d, got %d. Body: %s',
				$expected,
				$response['status'],
				$response['raw']
			)
		);
	}
}
