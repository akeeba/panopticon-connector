<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Library;

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use Throwable;
use const DIRECTORY_SEPARATOR;
use const PHP_OS;

class ServerInfo
{
	protected $db;

	public function __construct(DatabaseInterface $db)
	{
		$this->db = $db;
	}

	public function __invoke(): array
	{
		$osFamily    = $this->getOSFamily();
		$isAvailable = in_array($osFamily, ['macOS', 'BSD', 'Linux'])
		               && function_exists('exec')
		               && function_exists('escapeshellarg');

		if ($isAvailable)
		{
			try
			{
				[$siteDiskMount, $siteDiskFree, $siteDiskTotal] = $this->parseDiskFree(JPATH_ROOT);
			}
			catch (Throwable $e)
			{
			}

			try
			{
				[$dbDiskMount, $dbDiskFree, $dbDiskTotal] = $this->parseDiskFree($this->getDBRoot());
			}
			catch (Throwable $e)
			{
			}

			try
			{
				$osInfo = $this->getOSInfo();
			}
			catch (Throwable $e)
			{
			}

			try
			{
				$memInfo = $this->getMemUsingFree() ?? $this->getMemUsingMemoryPressure();
			}
			catch (Throwable $e)
			{
			}

			try
			{
				$load = $this->getLoad();
			}
			catch (Throwable $e)
			{
			}

			try
			{
				$cpuInfo = $this->getCpuInfo();
			}
			catch (Throwable $e)
			{
			}

			try
			{
				$cpuStats = $this->getCPUStatsFromProc() ?? $this->getCPUStatsFromTop();
			}
			catch (Throwable $e)
			{
			}
		}

		return [
			'collected' => $isAvailable,
			'os'        => array_merge(
				[
					'family' => $osFamily,
					'os'     => null,
					'kernel' => null,
				],
				$osInfo ?? []
			),
			'siteDisk'  => [
				'mount' => $siteDiskMount ?? null,
				'free'  => $siteDiskFree ?? null,
				'total' => $siteDiskTotal ?? null,
			],
			'dbDisk'    => [
				'mount' => $dbDiskMount ?? null,
				'free'  => $dbDiskFree ?? null,
				'total' => $dbDiskTotal ?? null,
			],
			'memory'    => array_merge(
				[
					'total' => null,
					'used'  => null,
					'cache' => null,
					'free'  => null,
				],
				$memInfo ?? []
			),
			'load'      => array_merge(
				[
					'uptime' => null,
					'load1'  => null,
					'load5'  => null,
					'load15' => null,
				],
				$load ?? []
			),
			'cpuInfo'   => array_merge(
				[
					'model' => null,
					'cores' => null,
				],
				$cpuInfo ?? []
			),
			'cpuUsage'  => array_merge(
				[
					'user'   => null,
					'system' => null,
					'iowait' => null,
					'idle'   => null,
				],
				$cpuStats ?? []
			),
		];
	}

	/**
	 * Get memory stats using `free`.
	 *
	 * This works on Linux and *BSD.
	 *
	 * @return  array|null
	 * @since   1.1.0
	 */
	private function getMemUsingFree(): ?array
	{
		exec('LC_ALL=C free -m', $output, $retCode);

		if ($retCode != 0 || count($output) < 2)
		{
			return null;
		}

		foreach ($output as $line)
		{
			$line = trim($line);

			if (strtolower(substr($line, 0, 4)) !== 'mem:')
			{
				continue;
			}
			[, $line] = explode(':', $line, 2);
			$line = trim($line);
			$line = preg_replace('#\s+#', ' ', $line);
			[$total, $used, $free,] = explode(' ', $line, 4);

			return [
				'total' => $total,
				'used'  => $used,
				'cache' => $total - $used - $free,
				'free'  => $free,
			];
		}

		return null;
	}

	/**
	 * Get the system uptime and load
	 *
	 * @return  array|null
	 * @since   1.1.0
	 */
	private function getLoad(): ?array
	{
		exec('LC_ALL=C uptime', $output, $retCode);

		if ($retCode != 0 || count($output) < 1)
		{
			return null;
		}

		// Samples:
		// 17:39:11 up  3:43,  6 users,  load average: 0.18, 0.22, 0.35
		// 15:40:51 up 33 days, 22:04,  1 user,  load average: 3.05, 2.44, 2.37
		// 15:40:51 up 1 day,  1:40,  2 users,  load average: 3.13, 1.77, 1.45

		// Get the uptime in minutes
		$line = trim($output[0]);
		[, $uptime] = explode(' up ', $line);
		if (preg_match('#(.*),\s+\d+\s+user#U', $uptime, $matches))
		{
			$uptime = $matches[1];
		}
		else
		{
			[$uptime,] = explode(',  ', $uptime ?? '');
		}
		$uptime = $this->uptimeToMinutes($uptime ?? '');

		// Get the load average (for the last 1, 5, and 15 minutes)
		$parts = explode('load average:', $line);

		if (count($parts) < 2)
		{
			$parts = explode('load averages:', $line);
		}

		if (count($parts) < 2)
		{
			return null;
		}

		$temp = $parts[1];

		if (empty($temp))
		{
			return null;
		}

		$temp = preg_replace('#\s+#', ',', trim($temp));
		$parts = explode(',', $temp);

		if (count($parts) < 3)
		{
			return null;
		}

		[$load1, $load5, $load15] = $parts;

		return [
			'uptime' => $uptime,
			'load1'  => $load1,
			'load5'  => $load5,
			'load15' => $load15,
		];
	}

	/**
	 * Convert an uptime expression to minutes
	 *
	 * @param   string  $uptime  The uptime expression, e.g. "10 days, 3 hours, 1 minute", "10 days, 3:10", etc
	 *
	 * @return  int
	 * @since   1.1.0
	 */
	private function uptimeToMinutes(string $uptime): int
	{
		$total = 0;
		$parts = explode(',', $uptime);

		foreach ($parts as $part)
		{
			// We have something like 1:23 (HH:MM)
			if (str_contains($part, ':'))
			{
				[$hours, $minutes] = explode(':', $part);
				$total += intval($hours) * 60 + intval($minutes);

				continue;
			}

			if (str_contains($part, 'year'))
			{
				$multiplier = 365.25 * 24 * 60;
			}
			elseif (str_contains($part, 'month'))
			{
				$multiplier = 30.4375 * 24 * 60;
			}
			elseif (str_contains($part, 'week'))
			{
				$multiplier = 7 * 24 * 60;
			}
			elseif (str_contains($part, 'day'))
			{
				$multiplier = 24 * 60;
			}
			elseif (str_contains($part, 'hour'))
			{
				$multiplier = 60;
			}
			elseif (str_contains($part, 'minute'))
			{
				$multiplier = 1;
			}
			else
			{
				continue;
			}

			$total += $multiplier * intval($part);
		}

		return is_int($total) ? $total : ceil($total);
	}

	/**
	 * Get CPU usage stats reading /proc/stat. Linux only.
	 *
	 * @return  float[]|null
	 * @since   1.1.0
	 */
	private function getCPUStatsFromProc(): ?array
	{
		if (!file_exists('/proc/stat') || !is_readable('/proc/stat'))
		{
			return null;
		}

		$lines = @file('/proc/stat');

		if ($lines === false)
		{
			return null;
		}

		$lines = array_filter(
			$lines,
			function ($x) {
				return str_starts_with('cpu ', $x);
			}
		);

		if (empty($lines))
		{
			return null;
		}

		$line   = $lines[0];
		$parts  = explode(' ', $line);
		$parts  = array_filter(
			$parts, function ($x) {
			return is_numeric($x);
		}
		);
		$parts  = array_map('intval', $parts);
		$user   = ($parts[0] ?? 0) + ($parts[1] ?? 0);
		$idle   = $parts[3] ?? 0;
		$ioWait = $parts[4] ?? 0;
		$total  = array_sum($parts);
		$system = $total - $user - $idle - $ioWait;

		return [
			'user'   => 100 * $user / $total,
			'system' => 100 * $system / $total,
			'iowait' => 100 * $ioWait / $total,
			'idle'   => 100 * $idle / $total,
		];
	}

	/**
	 * Get CPU usage stats reading the output of top. Linux, BSD, and macOS.
	 *
	 * @return  float[]|null
	 * @since   1.1.0
	 */
	private function getCPUStatsFromTop(): ?array
	{
		$cmd = in_array($this->getOSFamily(), ['Linux', 'Windows'])
			? 'LC_ALL=C top -n 1 -b'
			: 'LC_ALL=C top -d -F -l 1';
		exec($cmd, $output, $retVal);

		if ($retVal != 0 || count($output) < 5)
		{
			return null;
		}

		$user   = null;
		$system = null;
		$idle   = null;

		foreach ($output as $line)
		{
			// macOS / BSD format
			if (str_starts_with($line, 'CPU usage:'))
			{
				// The expected format is “CPU usage: 12.65% user, 17.72% sys, 69.62% idle ”
				[, $temp] = explode(':', $line, 2);
				$items = explode(',', $temp);

				foreach ($items as $item)
				{
					$item = trim($item);
					[$percent, $type] = explode(' ', $item);
					$percent = floatval(rtrim($percent, '%'));

					switch ($type)
					{
						case 'user':
							$user = $percent;
							break;

						case 'sys':
							$system = $percent;
							break;

						case 'idle':
							$idle = $percent;
							break;
					}
				}

				return [
					'user'   => $user,
					'system' => $system,
					'iowait' => null,
					'idle'   => $idle,
				];
			}

			// Linux format
			if (str_starts_with($line, '%Cpu(s):'))
			{
				// The expected format is “%Cpu(s):  0.6 us,  0.9 sy,  0.0 ni, 98.2 id,  0.3 wa,  0.0 hi,  0.0 si,  0.0 st”
				[, $temp] = explode(':', $line, 2);
				$items = explode(',', $temp);

				$iowait = null;

				foreach ($items as $item)
				{
					$item = trim($item);
					[$percent, $type] = explode(' ', $item);
					$percent = floatval(rtrim($percent, '%'));

					switch ($type)
					{
						case 'us': // user
						case 'ni': // nice
							$user = ($user ?? 0) + $percent;
							break;

						case 'wa': // I/O Wait
							$iowait = $percent;
							break;

						case 'id': // Idle
							$idle = $percent;
							break;

						case 'sy': // system
						case 'hi': // hardware interrupts
						case 'si': // software interrupts
						case 'st': // stolen by hypervisor
						default: // anything else, e.g. time running guests
							$system = ($system ?? 0.0) + $percent;
							break;
					}
				}

				return [
					'user'   => $user,
					'system' => $system,
					'iowait' => $iowait,
					'idle'   => $idle,
				];
			}
		}

		return null;
	}

	/**
	 * Get memory stats using `memory_pressure`.
	 *
	 * This works on Darwin / macOS.
	 *
	 * @return  array|null
	 * @since   1.1.0
	 */
	private function getMemUsingMemoryPressure(): ?array
	{
		exec('LC_ALL=C memory_pressure', $output, $retCode);

		if ($retCode != 0 || count($output) < 2)
		{
			return null;
		}

		// First, find the page size; it's different on x86_64 and Apple Silicon.
		$pageSize = 0;
		$total    = 0;

		foreach ($output as $line)
		{
			// Looking for sth like “The system has 25769803776 (1572864 pages with a page size of 16384).”
			if (!str_contains($line, 'with a page size of'))
			{
				continue;
			}

			// Extract the page size
			[, $temp] = explode('with a page size of', $line);
			$temp     = trim($temp, ' ).');
			$pageSize = intval($temp);

			// Extract the total memory size
			if (preg_match('#The system has (\d+) \(#', $line, $matches))
			{
				$total = intval($matches[1]) / 1048576;
			}
			break;
		}

		if ($pageSize <= 0 || $total <= 0)
		{
			return null;
		}

		// Extract free, active (used), and inactive (also free)
		$free = 0;
		$used = 0;

		foreach ($output as $line)
		{
			if (str_starts_with($line, 'Pages free:') || str_starts_with($line, 'Pages inactive:'))
			{
				[, $temp] = explode(':', $line, 2);
				$temp = ceil(intval($temp) * $pageSize / 1048576);

				$free += $temp;

				continue;
			}

			if (str_starts_with($line, 'Pages active:') || str_starts_with($line, 'Pages used by compressor:'))
			{
				[, $temp] = explode(':', $line, 2);
				$temp = ceil(intval($temp) * $pageSize / 1048576);

				$used += $temp;

				continue;
			}
		}

		return [
			'total' => $total,
			'used'  => $used,
			'cache' => $total - $used - $free,
			'free'  => $free,
		];
	}

	/**
	 * Returns the Operating System identification information
	 *
	 * @return  array{os: ?string, kernel: ?string}
	 * @since   1.1.0
	 */
	private function getOSInfo(): array
	{
		return [
			'os'     => $this->getOSFromFile() ?? $this->getOSFromLSB() ?? $this->getOSFromMacOSCommand(),
			'kernel' => $this->getKernelVersion(),
		];
	}

	/**
	 * Returns the OS name and version using the standard UNIX OS identification files.
	 *
	 * This will work at the very least under FreeBSD and most Linux distributions.
	 *
	 * @return  string|null
	 * @since   1.1.0
	 */
	private function getOSFromFile(): ?string
	{
		if (!function_exists('parse_ini_file'))
		{
			return null;
		}

		$ret   = null;
		$files = [
			'/usr/local/etc/os-release',
			'/etc/os-release',
		];

		foreach ($files as $file)
		{
			if (!file_exists($file) || !is_readable($file))
			{
				continue;
			}

			$parsed = parse_ini_file($file, false, INI_SCANNER_RAW);

			$prettyName = $parsed['PRETTY_NAME'] ?? null;
			$name       = $parsed['NAME'] ?? null;
			$version    = $parsed['VERSION'] ?? null;

			$altPrettyName = trim(($name ?? '') . ' ' . ($version ?? ''));
			$ret           = $prettyName ?? $altPrettyName;
			$ret           = empty($ret) ? null : $ret;

			if ($ret !== null)
			{
				break;
			}
		}

		return $ret;
	}

	/**
	 * Returns the OS name and version using the Linux Standard Base probing command `lsb_release`.
	 *
	 * This will only work on Linux distributions, and only if the (mostly optional) LSB package is installed.
	 *
	 * @return  string|null
	 * @since   1.1.0
	 */
	private function getOSFromLSB(): ?string
	{
		exec('LC_ALL=C lsb_release -drs', $output, $retCode);

		if ($retCode != 0)
		{
			return null;
		}

		if (count($output) < 2)
		{
			return null;
		}

		return trim(implode(' ', $output));
	}

	/**
	 * Returns the OS name and version on macOS hosts.
	 *
	 * This uses the Darwin `sw_vers` command to get the current product version. The name is assumed to be macOS.
	 *
	 * @return  string|null
	 * @since   1.1.0
	 */
	private function getOSFromMacOSCommand(): ?string
	{
		exec('LC_ALL=C sw_vers -productVersion', $output, $retCode);

		if ($retCode != 0)
		{
			return null;
		}

		if (count($output) < 1)
		{
			return null;
		}

		return trim('macOS ' . implode(' ', $output));
	}

	/**
	 * Returns the kernel name and version.
	 *
	 * @return  string|null
	 * @since   1.1.0
	 */
	private function getKernelVersion(): ?string
	{
		exec('LC_ALL=C uname -sr', $output, $retCode);

		if ($retCode != 0)
		{
			return null;
		}

		if (count($output) < 1)
		{
			return null;
		}

		return trim(implode(' ', $output));
	}

	/**
	 * Get the parsed output of the `df` (disk free) command
	 *
	 * @param   ?string  $rootPath
	 *
	 * @return  array|null[]
	 * @since   1.1.0
	 */
	private function parseDiskFree(?string $rootPath): array
	{
		if (empty($rootPath) || !is_dir($rootPath))
		{
			return [null, null, null];
		}

		$cmd = 'LC_ALL=C df -P -m ' . escapeshellarg($rootPath);
		exec($cmd, $output, $retCode);

		if ($retCode != 0)
		{
			return [null, null, null];
		}

		if (count($output) < 2)
		{
			return [null, null, null];
		}

		$line = preg_replace('#\s+#', ' ', $output[1]);
		[$deviceNode, $total, $used, $free, $capacity, $mount] = explode(' ', $line);

		return [$mount, $free, $total];
	}

	/**
	 * Return the OS family
	 *
	 * @return  string
	 * @since   1.1.0
	 */
	private function getOSFamily(): string
	{
		if (DIRECTORY_SEPARATOR === '\\')
		{
			return 'Windows';
		}

		$map = [
			'Darwin'    => 'macOS',
			'DragonFly' => 'BSD',
			'FreeBSD'   => 'BSD',
			'NetBSD'    => 'BSD',
			'OpenBSD'   => 'BSD',
			'Linux'     => 'Linux',
			'SunOS'     => 'Solaris',
		];

		return $map[PHP_OS] ?? 'Unknown';
	}

	/**
	 * Returns the directory where the database data is stored
	 *
	 * @return  string|null
	 * @since   1.1.0
	 */
	private function getDBRoot(): ?string
	{
		// Getting the database files root only makes sense on local database servers.
		if (!$this->isLocalDatabase())
		{
			return null;
		}

		/** @var DatabaseDriver $db */
		$db  = $this->db;
		$sql = 'SELECT @@datadir';

		try
		{
			return $db->setQuery($sql)->loadResult() ?: null;
		}
		catch (Throwable $e)
		{
			return null;
		}
	}

	/**
	 * Divine whether the database server is hosted on the same computing node as the web server.
	 *
	 * @return  bool
	 * @throws  \Exception
	 * @since   1.1.0
	 */
	private function isLocalDatabase(): bool
	{
		$dbHost = Factory::getApplication()->get('host');

		if ($dbHost === 'localhost' || $dbHost === '127.0.0.1')
		{
			return true;
		}

		$dbHostIPs = array_unique(gethostbynamel($dbHost));

		// If the DB host resolves to a definite localhost IP return true.
		if (in_array('127.0.0.1', $dbHostIPs) || in_array('::1', $dbHostIPs))
		{
			return true;
		}

		// If the DB host resolves to an IP which starts with 127. then it's a localhost IP
		foreach ($dbHostIPs as $ip)
		{
			if (str_starts_with($ip, '127.'))
			{
				return true;
			}
		}

		// Let's get all our hostname IPs and check if there's overlap with the DB server's IPs
		$hostname = gethostname();

		if ($hostname === false)
		{
			$hostnameIPs = ['127.0.0.1', '::1'];
		}
		else
		{
			$hostnameIPs = array_unique(gethostbynamel($hostname));
		}

		return array_intersect($dbHostIPs, $hostnameIPs);
	}

	/**
	 * Returns information about the CPU powering the server.
	 *
	 * @return  array
	 * @since   1.1.0
	 */
	private function getCpuInfo(): array
	{
		try
		{
			$cores = $this->getCpuCores();
		}
		catch (Throwable $e)
		{
			$cores = null;
		}

		try
		{
			$model = $this->getCpuModel();
		}
		catch (Throwable $e)
		{
			$model = null;
		}

		return [
			'model' => $model,
			'cores' => $cores,
		];
	}

	/**
	 * Get the number of CPUs installed on the machine.
	 *
	 * On modern systems this represents the number of physical and virtual CPU cores in a single processor package. So,
	 * that's what we're treating it as.
	 *
	 * BTW, `getconf` is cross-platform.
	 *
	 * @return  int|null
	 * @since   1.1.0
	 */
	private function getCpuCores(): ?int
	{
		exec('LC_ALL=C getconf _NPROCESSORS_ONLN', $output, $retCode);

		if ($retCode != 0 || count($output) < 1)
		{
			return null;
		}

		return intval($output[0]) ?: null;
	}

	/**
	 * Returns the CPU model in use.
	 *
	 * In case of multiple CPUs it only returns the first CPU's model.
	 *
	 * @return  string|null
	 * @since   1.1.0
	 */
	private function getCpuModel(): ?string
	{
		switch ($this->getOSFamily())
		{
			case 'Linux':
				$cmd = 'grep -m 1 ' . escapeshellarg('model name') . ' /proc/cpuinfo';
				break;

			case 'macOS':
				$cmd = '/usr/sbin/sysctl machdep.cpu.brand_string';
				break;

			case 'BSD':
				$cmd = '/usr/sbin/sysctl hw.model';
				break;

			default:
				return null;
		}

		exec($cmd, $output, $retCode);

		if ($retCode != 0 || count($output) < 1)
		{
			return null;
		}

		[, $ret] = explode(':', $output[0], 2);

		$ret = trim(preg_replace('#\s+#', ' ', trim($ret)));

		return $ret ?: null;
	}
}
