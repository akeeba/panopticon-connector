<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller\Mixit;

defined('_JEXEC') || die;

trait ElementToExtensionIdTrait
{
	public function getExtensionIdFromElement(string $extensionName): ?int
	{
		$criteria = $this->extensionNameToCriteria($extensionName);

		if (empty($criteria))
		{
			return null;
		}

		$db    = \JFactory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('extension_id'))
			->from($db->quoteName('#__extensions'));

		foreach ($criteria as $key => $value)
		{
			if (is_numeric($value))
			{
				$value = (int) $value;
			}
			elseif (is_bool($value))
			{
				$value = $value ? 'TRUE' : 'FALSE';
			}
			elseif (is_null($value))
			{
				$value = 'NULL';
			}
			else
			{
				$value = $db->quote($value);
			}

			$query->where($db->qn($key) . ' = ' . $value);
		}

		try
		{
			return ((int) $db->setQuery($query)->loadResult()) ?: null;
		}
		catch (\Throwable $e)
		{
			return null;
		}
	}

	private function extensionNameToCriteria(string $extensionName): array
	{
		$parts = explode('_', $extensionName, 3);

		switch ($parts[0])
		{
			case 'pkg':
				return [
					'type'    => 'package',
					'element' => $extensionName,
				];

			case 'com':
				return [
					'type'    => 'component',
					'element' => $extensionName,
				];

			case 'plg':
				return [
					'type'    => 'plugin',
					'folder'  => $parts[1],
					'element' => $parts[2],
				];

			case 'mod':
				return [
					'type'      => 'module',
					'element'   => $extensionName,
					'client_id' => 0,
				];

			// That's how we note admin modules
			case 'amod':
				return [
					'type'      => 'module',
					'element'   => substr($extensionName, 1),
					'client_id' => 1,
				];

			case 'files':
				return [
					'type'    => 'file',
					'element' => $parts[1],
				];

			case 'file':
				return [
					'type'    => 'file',
					'element' => $extensionName,
				];

			case 'lib':
				return [
					'type'    => 'library',
					'element' => $parts[1],
				];
		}

		return [];
	}
}