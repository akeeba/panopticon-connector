<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * Bootstrap for the unit test suite.
 *
 * No Joomla framework is loaded here on purpose: these tests exercise pure,
 * behaviour-preserving refactors extracted out of Joomla-coupled classes, so
 * only the vendored Composer dependencies (z4kn4fein/php-semver) are needed.
 */

// phpcs:disable PSR1.Files.SideEffects

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('_JEXEC', 1);

defined('JVERSION') || define('JVERSION', '5.1.0');

$vendorAutoload = __DIR__ . '/../../component/backend/vendor/autoload.php';

if (!is_file($vendorAutoload))
{
	fwrite(
		STDERR,
		"Vendored Composer autoloader not found at $vendorAutoload. Run `composer install` in the repository root first.\n"
	);

	exit(1);
}

/** @var \Composer\Autoload\ClassLoader $classLoader */
$classLoader = require $vendorAutoload;

// The vendored autoloader only ships production dependencies (no dev autoload map), so register the
// namespaces under test, and the unit test namespace itself, by hand.
$classLoader->addPsr4('Akeeba\\Component\\Panopticon\\Api\\', __DIR__ . '/../../component/api/src');
$classLoader->addPsr4('Akeeba\\Plugin\\Console\\Panopticon\\', __DIR__ . '/../../plugins/console/panopticon/src');
$classLoader->addPsr4('Akeeba\\Panopticon\\Tests\\Unit\\', __DIR__);

// A couple of production classes are declared with a Joomla Framework base class/interface/trait that
// this suite deliberately does not load (see the file-level docblock). Provide minimal stand-ins so
// the handful of tests that need it can load those classes.
require_once __DIR__ . '/stubs/JoomlaRegistryStub.php';
require_once __DIR__ . '/stubs/JoomlaConsoleCommandStub.php';
