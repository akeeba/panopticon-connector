<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Library;

defined('_JEXEC') || die;

/**
 * Verifies a downloaded update package against the size and hashes advertised by the update source.
 *
 * Mirrors Joomla's own UpdateModel::isChecksumValid() (which is private), adding a file size check.
 *
 * @since   1.2.0
 */
class PackageChecksum
{
	/**
	 * Hash algorithms Joomla update sources may advertise, in the order Joomla checks them.
	 *
	 * @since 1.2.0
	 */
	public const ALGORITHMS = ['sha256', 'sha384', 'sha512'];

	/**
	 * Is the file the expected size, and does it match every hash we were given?
	 *
	 * @param   string               $file          The file to check.
	 * @param   int                  $expectedSize  Expected size in bytes; zero or less skips the size check.
	 * @param   array<string,string> $hashes        Algorithm => expected lowercase hex digest. Unknown algorithms are
	 *                                              ignored. No hashes at all means only the size is checked.
	 *
	 * @return  bool
	 * @since   1.2.0
	 */
	public static function verify(string $file, int $expectedSize, array $hashes): bool
	{
		clearstatcache(false, $file);

		if (!is_file($file))
		{
			return false;
		}

		if ($expectedSize > 0 && filesize($file) !== $expectedSize)
		{
			return false;
		}

		foreach (self::ALGORITHMS as $algo)
		{
			$expected = $hashes[$algo] ?? '';

			if ($expected === '')
			{
				continue;
			}

			if (!hash_equals(strtolower(trim($expected)), (string) hash_file($algo, $file)))
			{
				return false;
			}
		}

		return true;
	}
}
