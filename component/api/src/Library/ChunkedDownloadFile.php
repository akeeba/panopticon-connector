<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Library;

defined('_JEXEC') || die;

use RuntimeException;

/**
 * Opens the output file of a chunked download, ready to append the next chunk.
 *
 * Extracted from CoreModel so it can be tested without depending on the Joomla update model machinery.
 *
 * @since   1.2.0
 */
class ChunkedDownloadFile
{
	/**
	 * Opens the file for appending, positioned right after the last byte written so far.
	 *
	 * @param   string  $path    The file to open.
	 * @param   int     $offset  Zero-based index of the last byte written so far; -1 (or 0) for a fresh download.
	 *                           The file therefore holds `$offset + 1` bytes.
	 *
	 * @return  resource
	 * @throws  RuntimeException
	 * @since   1.2.0
	 */
	public static function openForResume(string $path, int $offset)
	{
		$expectedSize = max($offset + 1, 0);

		if ($offset > 0)
		{
			clearstatcache(false, $path);

			if (!file_exists($path))
			{
				throw new RuntimeException(
					sprintf('The Joomla update package file %s went away', basename($path))
				);
			}

			$filesize = @filesize($path) ?: 0;

			if ($filesize < $expectedSize)
			{
				throw new RuntimeException(
					sprintf(
						'The Joomla update package file %s is smaller than expected (expected: %d bytes; current: %d bytes)',
						basename($path), $expectedSize, $filesize
					)
				);
			}
		}

		$fp = @fopen($path, 'ab');

		if ($fp === false)
		{
			throw new RuntimeException('Cannot open output file for writing.');
		}

		// Drop anything beyond the last byte we know was written, and get ready to append data.
		@ftruncate($fp, $expectedSize);
		@fseek($fp, $expectedSize);

		return $fp;
	}
}
