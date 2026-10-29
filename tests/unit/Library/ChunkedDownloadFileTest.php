<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Tests\Unit\Library;

use Akeeba\Component\Panopticon\Api\Library\ChunkedDownloadFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ChunkedDownloadFileTest extends TestCase
{
	private string $file;

	protected function setUp(): void
	{
		$this->file = tempnam(sys_get_temp_dir(), 'pnptest');
	}

	protected function tearDown(): void
	{
		@unlink($this->file);
	}

	public static function sizeProvider(): iterable
	{
		yield '1 byte' => [1, 1];
		yield '2 bytes, chunks of 1' => [2, 1];
		yield '1,000 bytes' => [1000, 300];
		yield '51,201 bytes' => [51201, 51201 - 1];
		yield '51,202 bytes' => [51202, 51201];
		yield 'multiple resumes' => [5000, 700];
	}

	/**
	 * Simulates the chunked download loop in CoreModel: $offset is the zero-based index of the last byte written.
	 */
	#[DataProvider('sizeProvider')]
	public function testResumedDownloadIsByteIdentical(int $size, int $chunkSize): void
	{
		$source = random_bytes($size);
		$offset = -1;

		while ($offset < $size - 1)
		{
			$fp    = ChunkedDownloadFile::openForResume($this->file, $offset);
			$from  = $offset + 1;
			$chunk = substr($source, $from, $chunkSize);

			fwrite($fp, $chunk);
			fclose($fp);

			$offset += strlen($chunk);
		}

		$this->assertSame($size, filesize($this->file));
		$this->assertSame(md5($source), md5_file($this->file));
	}

	public function testRefusesFileSmallerThanExpected(): void
	{
		file_put_contents($this->file, str_repeat('x', 10));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('expected: 12 bytes; current: 10 bytes');

		ChunkedDownloadFile::openForResume($this->file, 11);
	}

	public function testRefusesMissingFileOnResume(): void
	{
		@unlink($this->file);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('went away');

		ChunkedDownloadFile::openForResume($this->file, 5);
	}

	public function testFreshStartTruncatesLeftovers(): void
	{
		file_put_contents($this->file, 'stale data');

		fclose(ChunkedDownloadFile::openForResume($this->file, -1));

		$this->assertSame(0, filesize($this->file));
	}
}
