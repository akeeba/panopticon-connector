<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Library;

defined('_JEXEC') || die;

/**
 * Helper methods to detect and describe the stability of a version string.
 *
 * Extracted from CoreModel so it can be tested (and reused) without depending on the Joomla update model machinery.
 *
 * @since   2.0.0
 */
class VersionStability
{
	/**
	 * Detects the stability of a version string, e.g. "1.2.3-beta1" => "beta".
	 *
	 * @param   string  $versionString  The version string to parse, e.g. "1.2.3-beta1".
	 *
	 * @return  string  One of stable, alpha, beta, rc, dev.
	 * @since   2.0.0
	 */
	public static function detectStability(string $versionString): string
	{
		$version = \z4kn4fein\SemVer\Version::parse($versionString, false);
		$tag     = strtolower($version->getPreRelease() ?: '');

		if ($tag === '')
		{
			return 'stable';
		}

		if (strpos($tag, 'alpha') === 0)
		{
			return 'alpha';
		}

		if (strpos($tag, 'beta') === 0)
		{
			return 'beta';
		}

		if (strpos($tag, 'rc') === 0)
		{
			return 'rc';
		}

		return 'dev';
	}

	/**
	 * Converts a numeric stability level (as used by Joomla's Updater class) to its string representation.
	 *
	 * @param   int  $stability  The numeric stability level: 0=dev, 1=alpha, 2=beta, 3=rc, 4=stable.
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	public static function stabilityToString(int $stability): string
	{
		switch ($stability)
		{
			case 0:
				return "dev";

			case 1:
				return "alpha";

			case 2:
				return "beta";

			case 3:
				return "rc";

			case 4:
			default:
				return "stable";
		}
	}
}
