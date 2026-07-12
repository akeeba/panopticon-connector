<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Panopticon\Tests\Unit\Library;

use Akeeba\Component\Panopticon\Api\Library\ServerInfo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exposes the pure `parse*()` methods (and `uptimeToMinutes()` / `getOSFamily()`) of ServerInfo for
 * direct testing, without requiring a `Joomla\Database\DatabaseInterface` instance: none of the
 * methods under test touch `$this->db`, so the constructor is overridden to a no-op.
 */
final class TestableServerInfo extends ServerInfo
{
	public function __construct()
	{
		// Intentionally does not call parent::__construct(): we never touch $this->db in this suite,
		// and the real constructor requires a Joomla\Database\DatabaseInterface we do not want to load.
	}

	public function callParseFreeOutput($lines): ?array
	{
		return $this->parseFreeOutput($lines);
	}

	public function callParseMemoryPressure(string $output): ?array
	{
		return $this->parseMemoryPressure($output);
	}

	public function callParseLoad(string $output): ?array
	{
		return $this->parseLoad($output);
	}

	public function callParseProcStat(string $content): ?array
	{
		return $this->parseProcStat($content);
	}

	public function callParseTopCpu(string $output): ?array
	{
		return $this->parseTopCpu($output);
	}

	public function callParseDiskFreeOutput(string $output): array
	{
		return $this->parseDiskFreeOutput($output);
	}

	public function callParseOsRelease(string $content): ?string
	{
		return $this->parseOsRelease($content);
	}

	public function callUptimeToMinutes(string $uptime): int
	{
		return $this->uptimeToMinutes($uptime);
	}

	public function callGetOSFamily(): string
	{
		return $this->getOSFamily();
	}
}

class ServerInfoTest extends TestCase
{
	private TestableServerInfo $serverInfo;

	protected function setUp(): void
	{
		$this->serverInfo = new TestableServerInfo();
	}

	// ---------------------------------------------------------------------------------------------
	// parseFreeOutput() — Linux/BSD `free -m`
	// ---------------------------------------------------------------------------------------------

	public static function freeOutputProvider(): iterable
	{
		$typical = [
			'              total        used        free      shared  buff/cache   available',
			'Mem:           7975        3120        1234         512        3621        4321',
			'Swap:          2047           0        2047',
		];

		yield 'typical free -m output, as array of lines' => [
			$typical,
			['total' => '7975', 'used' => '3120', 'cache' => 3621, 'free' => '1234'],
		];

		yield 'typical free -m output, as a single newline-joined string' => [
			implode("\n", $typical),
			['total' => '7975', 'used' => '3120', 'cache' => 3621, 'free' => '1234'],
		];

		yield 'no Mem: line present' => [
			['header', 'Swap:     2047        0     2047'],
			null,
		];

		yield 'empty input' => [
			[],
			null,
		];
	}

	#[DataProvider('freeOutputProvider')]
	public function testParseFreeOutput($lines, ?array $expected): void
	{
		$this->assertSame($expected, $this->serverInfo->callParseFreeOutput($lines));
	}

	// ---------------------------------------------------------------------------------------------
	// parseMemoryPressure() — macOS `memory_pressure`
	// ---------------------------------------------------------------------------------------------

	public function testParseMemoryPressureWithRealCapturedOutput(): void
	{
		// Real output captured on an Apple Silicon Mac (16384-byte pages, ~32GB RAM) via `memory_pressure`.
		$output = <<<TXT
The system has 34359738368 (2097152 pages with a page size of 16384).

Stats:
Pages free: 35381
Pages purgeable: 83395
Pages purged: 4039494

Swap I/O:
Swapins: 15
Swapouts: 20

Page Q counts:
Pages active: 796926
Pages inactive: 795166
Pages speculative: 1708
Pages throttled: 0
Pages wired down: 199195

Compressor Stats:
Pages used by compressor: 212608
Pages decompressed: 3181164
Pages compressed: 5505411

File I/O:
Pageins: 13080566
Pageouts: 521081

System-wide memory free percentage: 79%
TXT;

		$this->assertSame(
			[
				'total' => 32768,
				'used'  => 15774.0,
				'cache' => 4016.0,
				'free'  => 12978.0,
			],
			$this->serverInfo->callParseMemoryPressure($output)
		);
	}

	public function testParseMemoryPressureWithoutPageSizeLineReturnsNull(): void
	{
		$this->assertNull($this->serverInfo->callParseMemoryPressure("Pages free: 100\nPages active: 100\n"));
	}

	// ---------------------------------------------------------------------------------------------
	// parseLoad() — `uptime`
	// ---------------------------------------------------------------------------------------------

	public static function loadProvider(): iterable
	{
		yield 'a few hours uptime' => [
			'17:39:11 up  3:43,  6 users,  load average: 0.18, 0.22, 0.35',
			['uptime' => 223, 'load1' => '0.18', 'load5' => '0.22', 'load15' => '0.35'],
		];

		yield '33 days uptime' => [
			'15:40:51 up 33 days, 22:04,  1 user,  load average: 3.05, 2.44, 2.37',
			['uptime' => 48844, 'load1' => '3.05', 'load5' => '2.44', 'load15' => '2.37'],
		];

		yield '1 day uptime' => [
			'15:40:51 up 1 day,  1:40,  2 users,  load average: 3.13, 1.77, 1.45',
			['uptime' => 1540, 'load1' => '3.13', 'load5' => '1.77', 'load15' => '1.45'],
		];
	}

	#[DataProvider('loadProvider')]
	public function testParseLoad(string $output, array $expected): void
	{
		$this->assertSame($expected, $this->serverInfo->callParseLoad($output));
	}

	public function testParseLoadWithoutLoadAverageReturnsNull(): void
	{
		$this->assertNull($this->serverInfo->callParseLoad('17:39:11 up  3:43,  6 users'));
	}

	// ---------------------------------------------------------------------------------------------
	// parseProcStat() — Linux `/proc/stat`
	// ---------------------------------------------------------------------------------------------

	public static function procStatProvider(): iterable
	{
		// The aggregate "cpu" line is: user nice system idle iowait irq softirq steal guest guest_nice.
		// The parser reports user (= user + nice), idle, iowait and a lumped "system" (= everything
		// else), each as a percentage of the total.
		yield 'realistic multi-line /proc/stat, trailing newline' => [
			"cpu  130216 19926 7699652 1191821 3846 0 986 0 0 0\n"
			. "cpu0 16277 2491 962456 148977 480 0 123 0 0 0\n"
			. "intr 1234567 0 0 0\nctxt 987654321\nbtime 1700000000\nprocesses 123456\n",
			['user' => 1.659696, 'system' => 85.123399, 'iowait' => 0.042514, 'idle' => 13.174390],
		];

		yield 'realistic multi-line /proc/stat, no trailing newline' => [
			"cpu  130216 19926 7699652 1191821 3846 0 986 0 0 0\ncpu0 16277 2491 962456 148977 480 0 123 0 0 0",
			['user' => 1.659696, 'system' => 85.123399, 'iowait' => 0.042514, 'idle' => 13.174390],
		];

		yield 'a single cpu line only' => [
			'cpu  1 2 3 4 5 6 7 8 9 10',
			['user' => 5.454545, 'system' => 78.181818, 'iowait' => 9.090909, 'idle' => 7.272727],
		];
	}

	#[DataProvider('procStatProvider')]
	public function testParseProcStat(string $content, array $expected): void
	{
		$actual = $this->serverInfo->callParseProcStat($content);

		$this->assertIsArray($actual);
		$this->assertSame(['user', 'system', 'iowait', 'idle'], array_keys($actual));

		foreach ($expected as $key => $value)
		{
			$this->assertEqualsWithDelta($value, $actual[$key], 0.0001, "CPU stat '$key' is off");
		}

		// The four figures partition 100% of CPU time.
		$this->assertEqualsWithDelta(100.0, array_sum($actual), 0.0001);
	}

	public function testParseProcStatWithoutACpuLineReturnsNull(): void
	{
		$this->assertNull($this->serverInfo->callParseProcStat(''));
		$this->assertNull($this->serverInfo->callParseProcStat("intr 1234567 0 0 0\nctxt 987654321\n"));
	}

	// ---------------------------------------------------------------------------------------------
	// parseTopCpu() — `top` (Linux and macOS/BSD formats)
	// ---------------------------------------------------------------------------------------------

	public function testParseTopCpuLinuxFormat(): void
	{
		$output = "top - 12:00:00 up 3 days,  4:32,  2 users,  load average: 0.52, 0.58, 0.59\n"
			. "Tasks: 210 total,   1 running, 209 sleeping,   0 stopped,   0 zombie\n"
			. "%Cpu(s):  0.6 us,  0.9 sy,  0.0 ni, 98.2 id,  0.3 wa,  0.0 hi,  0.0 si,  0.0 st\n"
			. "KiB Mem :  8137884 total,  1234567 free,  3456789 used,  3456528 buff/cache\n"
			. "KiB Swap:  2097148 total,  2097148 free,        0 used.  4321098 avail Mem\n";

		$this->assertSame(
			['user' => 0.6, 'system' => 0.9, 'iowait' => 0.3, 'idle' => 98.2],
			$this->serverInfo->callParseTopCpu($output)
		);
	}

	public function testParseTopCpuMacOSFormat(): void
	{
		$output = "Processes: 412 total, 2 running, 410 sleeping, 2222 threads\n"
			. "2023/01/01 12:00:00\n"
			. "Load Avg: 2.14, 2.35, 2.41\n"
			. "CPU usage: 12.65% user, 17.72% sys, 69.62% idle \n"
			. "SharedLibs: 512M resident, 88M data, 22M linkedit.\n"
			. "MemRegions: 123456 total, 3210M resident, 210M private, 890M shared.\n";

		$this->assertSame(
			['user' => 12.65, 'system' => 17.72, 'iowait' => null, 'idle' => 69.62],
			$this->serverInfo->callParseTopCpu($output)
		);
	}

	public function testParseTopCpuWithNoRecognisableLineReturnsNull(): void
	{
		$this->assertNull($this->serverInfo->callParseTopCpu("some\nunrelated\noutput\n"));
	}

	// ---------------------------------------------------------------------------------------------
	// parseDiskFreeOutput() — `df -P -m`
	// ---------------------------------------------------------------------------------------------

	public static function diskFreeProvider(): iterable
	{
		yield 'single-space separated' => [
			'/dev/sda1 102400 45056 57344 45% /',
			['/', '57344', '102400'],
		];

		yield 'multi-space separated (typical real df -P -m output)' => [
			'/dev/sda1        102400   45056   57344   45% /',
			['/', '57344', '102400'],
		];

		yield 'a non-root mount point' => [
			'/dev/mapper/data--vg-data  512000  128000  384000  25% /var/lib/mysql',
			['/var/lib/mysql', '384000', '512000'],
		];
	}

	#[DataProvider('diskFreeProvider')]
	public function testParseDiskFreeOutput(string $line, array $expected): void
	{
		$this->assertSame($expected, $this->serverInfo->callParseDiskFreeOutput($line));
	}

	// ---------------------------------------------------------------------------------------------
	// parseOsRelease() — `/etc/os-release`
	// ---------------------------------------------------------------------------------------------

	public static function osReleaseProvider(): iterable
	{
		yield 'Ubuntu, has PRETTY_NAME' => [
			"NAME=\"Ubuntu\"\nVERSION=\"22.04.3 LTS (Jammy Jellyfish)\"\nID=ubuntu\nID_LIKE=debian\n"
			. "PRETTY_NAME=\"Ubuntu 22.04.3 LTS\"\nVERSION_ID=\"22.04\"\n",
			'Ubuntu 22.04.3 LTS',
		];

		yield 'Debian, has PRETTY_NAME' => [
			"PRETTY_NAME=\"Debian GNU/Linux 12 (bookworm)\"\nNAME=\"Debian GNU/Linux\"\nVERSION_ID=\"12\"\n"
			. "VERSION=\"12 (bookworm)\"\nID=debian\n",
			'Debian GNU/Linux 12 (bookworm)',
		];

		yield 'no PRETTY_NAME, falls back to NAME + VERSION' => [
			"NAME=\"Alpine Linux\"\nVERSION=\"3.18.4\"\nID=alpine\n",
			'Alpine Linux 3.18.4',
		];

		yield 'nothing usable at all' => [
			"ID=unknown\n",
			null,
		];

		yield 'empty content' => [
			'',
			null,
		];
	}

	#[DataProvider('osReleaseProvider')]
	public function testParseOsRelease(string $content, ?string $expected): void
	{
		$this->assertSame($expected, $this->serverInfo->callParseOsRelease($content));
	}

	// ---------------------------------------------------------------------------------------------
	// uptimeToMinutes()
	// ---------------------------------------------------------------------------------------------

	public static function uptimeToMinutesProvider(): iterable
	{
		yield '33 days, 22:04' => ['33 days, 22:04', 33 * 24 * 60 + 22 * 60 + 4];
		yield '1 day, 1:40' => ['1 day, 1:40', 24 * 60 + 60 + 40];
		yield '1:40' => ['1:40', 60 + 40];
		yield '3:43' => ['3:43', 3 * 60 + 43];
		yield '2 weeks, 1 day' => ['2 weeks, 1 day', (int) (2 * 7 * 24 * 60 + 24 * 60)];
		yield 'empty string' => ['', 0];
	}

	#[DataProvider('uptimeToMinutesProvider')]
	public function testUptimeToMinutes(string $uptime, int $expected): void
	{
		$this->assertSame($expected, $this->serverInfo->callUptimeToMinutes($uptime));
	}

	public function testUptimeToMinutesWithYearsAndMonths(): void
	{
		// 1 year + 2 months, using the same (approximate, 365.25/30.4375-day) multipliers as production.
		$expected = (int) ceil(1 * 365.25 * 24 * 60 + 2 * 30.4375 * 24 * 60);

		$this->assertSame($expected, $this->serverInfo->callUptimeToMinutes('1 year, 2 months'));
	}

	// ---------------------------------------------------------------------------------------------
	// getOSFamily()
	// ---------------------------------------------------------------------------------------------

	public function testGetOSFamilyReturnsAKnownFamily(): void
	{
		$this->assertContains(
			$this->serverInfo->callGetOSFamily(),
			['Windows', 'macOS', 'BSD', 'Linux', 'Solaris', 'Unknown']
		);
	}

	public function testGetOSFamilyMatchesTheDocumentedMappingForTheCurrentPlatform(): void
	{
		// getOSFamily() reads the built-in DIRECTORY_SEPARATOR / PHP_OS constants, which cannot be
		// swapped out per-test-case. Instead, we re-derive the expected value using the very same
		// mapping table documented in ServerInfo::getOSFamily(), so this still catches accidental
		// changes to that table or to the method's control flow.
		$map = [
			'Darwin'    => 'macOS',
			'DragonFly' => 'BSD',
			'FreeBSD'   => 'BSD',
			'NetBSD'    => 'BSD',
			'OpenBSD'   => 'BSD',
			'Linux'     => 'Linux',
			'SunOS'     => 'Solaris',
		];

		$expected = DIRECTORY_SEPARATOR === '\\' ? 'Windows' : ($map[PHP_OS] ?? 'Unknown');

		$this->assertSame($expected, $this->serverInfo->callGetOSFamily());
	}
}
