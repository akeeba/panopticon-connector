<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * Bootstrap for the integration test suite.
 *
 * Unlike the unit suite, no Joomla application is booted in this process: the
 * integration tests are plain HTTP clients driving a real Joomla + Panopticon
 * Connector install running in Docker (see tests/integration/run-tests.sh /
 * tests/integration/docker). This file only wires up autoloading and reads the
 * environment variables the test cases need.
 */

// phpcs:disable PSR1.Files.SideEffects

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Reuse the component's vendored Composer autoloader (no dev dependencies are
// required for HTTP-only tests, but this keeps things consistent with the unit
// suite and gives us access to any vendored helper libraries).
$vendorAutoload = __DIR__ . '/../../component/backend/vendor/autoload.php';

if (is_file($vendorAutoload))
{
	require_once $vendorAutoload;
}

// Minimal PSR-4 autoloader for the integration test source tree; no Composer
// ClassLoader is guaranteed to be present (the vendored autoloader above ships
// only production dependencies), so register a small loader by hand.
spl_autoload_register(function (string $class): void {
	$prefix = 'Akeeba\\Panopticon\\Tests\\Integration\\';

	if (strncmp($class, $prefix, strlen($prefix)) !== 0)
	{
		return;
	}

	$relative = substr($class, strlen($prefix));
	$path     = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

	if (is_file($path))
	{
		require $path;
	}
});

// Read the environment once; AbstractApiTestCase::setUpBeforeClass() consumes
// these via getenv() as well, this is just an early sanity log.
$baseUrl = getenv('PANOPTICON_BASE_URL') ?: '';
$token   = getenv('PANOPTICON_API_TOKEN') ?: '';

if ($baseUrl === '' || $token === '')
{
	fwrite(
		STDERR,
		"NOTE: PANOPTICON_BASE_URL and/or PANOPTICON_API_TOKEN are not set. " .
		"Integration tests will be skipped. Run them via tests/integration/run-tests.sh.\n"
	);
}
