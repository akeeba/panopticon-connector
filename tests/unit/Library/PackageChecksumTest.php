<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Tests\Unit\Library;

use Akeeba\Component\Panopticon\Api\Library\PackageChecksum;
use PHPUnit\Framework\TestCase;

class PackageChecksumTest extends TestCase
{
	private string $file;

	private string $data = 'The quick brown fox jumps over the lazy dog';

	protected function setUp(): void
	{
		$this->file = tempnam(sys_get_temp_dir(), 'pnptest');
		file_put_contents($this->file, $this->data);
	}

	protected function tearDown(): void
	{
		@unlink($this->file);
	}

	public function testMatchingSizeAndAllHashes(): void
	{
		$hashes = [
			'sha256' => hash('sha256', $this->data),
			'sha384' => hash('sha384', $this->data),
			'sha512' => hash('sha512', $this->data),
		];

		$this->assertTrue(PackageChecksum::verify($this->file, strlen($this->data), $hashes));
	}

	public function testUppercaseExpectedHashIsAccepted(): void
	{
		$this->assertTrue(
			PackageChecksum::verify($this->file, 0, ['sha256' => strtoupper(hash('sha256', $this->data))])
		);
	}

	public function testOneByteShortFileFails(): void
	{
		file_put_contents($this->file, substr($this->data, 0, -1));

		$this->assertFalse(PackageChecksum::verify($this->file, strlen($this->data), []));
	}

	public function testHashMismatchFails(): void
	{
		$this->assertFalse(
			PackageChecksum::verify($this->file, strlen($this->data), ['sha512' => hash('sha512', 'other')])
		);
	}

	public function testOnlyOneBadHashAmongGoodOnesFails(): void
	{
		$hashes = [
			'sha256' => hash('sha256', $this->data),
			'sha384' => hash('sha384', 'other'),
		];

		$this->assertFalse(PackageChecksum::verify($this->file, 0, $hashes));
	}

	public function testNoHashesChecksSizeOnly(): void
	{
		$this->assertTrue(PackageChecksum::verify($this->file, strlen($this->data), []));
		$this->assertTrue(PackageChecksum::verify($this->file, 0, []));
	}

	public function testUnknownAlgorithmIsIgnored(): void
	{
		$this->assertTrue(PackageChecksum::verify($this->file, 0, ['md5' => 'nonsense']));
	}

	public function testMissingFileFails(): void
	{
		@unlink($this->file);

		$this->assertFalse(PackageChecksum::verify($this->file, 0, []));
	}
}
