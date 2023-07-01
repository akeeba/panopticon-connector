<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Model;

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Component\Templates\Administrator\Model\TemplateModel;
use Joomla\Database\ParameterType;

class TemplatechangedModel extends ListModel
{
	public function __construct($config = [], MVCFactoryInterface $factory = null)
	{
		$config['filter_fields'] = $config['filter_fields'] ?? [
			'client',
		];

		parent::__construct($config, $factory);
	}

	public function getItem()
	{
		// Get the template override ID
		$id = (int) $this->getState('templatechanged.id', 0);

		// Get the template override information
		$db    = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__template_overrides'))
			->where($db->quoteName('id') . ' = :id')
			->bind(':id', $id, ParameterType::INTEGER);

		$sourceObject = $db->setQuery($query)->loadObject();

		if (empty($sourceObject))
		{
			throw new \RuntimeException('Override not found', 404);
		}

		// Does the template exist?
		$query    = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__extensions'))
			->where([
				$db->quoteName('type') . ' = ' . $db->quote('template'),
				$db->quoteName('element') . ' = :template',
				$db->quoteName('client_id') . ' = :client_id',
			])
			->bind(':template', $sourceObject->template)
			->bind(':client_id', $sourceObject->client_id, ParameterType::INTEGER);
		$template = $db->setQuery($query)->loadObject();

		if (empty($template))
		{
			throw new \RuntimeException('Template not found', 404);
		}

		// Does the overridden file exist?
		$fileName = base64_decode($sourceObject->hash_id);
		$fileName = str_replace('//', '/', $fileName);
		$fileName = Path::clean(JPATH_ROOT . ($sourceObject->client_id == 0 ? '' : '/administrator') . '/templates/' . $sourceObject->template . $fileName);

		try
		{
			$filePath = Path::check($fileName);
		}
		catch (\Exception $e)
		{
			throw new \RuntimeException('Template override not found', 404);
		}

		if (!file_exists($filePath))
		{
			throw new \RuntimeException('Template override not found', 404);
		}

		$overrideSource = @file_get_contents($filePath);

		if (!is_readable($filePath) || $overrideSource === false)
		{
			throw new \RuntimeException('Cannot read override file', 500);
		}

		// Does the source file exist?
		$basePath = $sourceObject->client_id == 1
			? DIRECTORY_SEPARATOR . 'administrator'
			: '';

		$cleanFileName = str_replace(JPATH_ROOT . $basePath . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $sourceObject->template, '', $fileName);

		/** @var MVCFactoryInterface $comTemplatesFactory */
		$comTemplatesFactory = Factory::getApplication()->bootComponent('com_templates')->getMVCFactory();
		/** @var TemplateModel $model */
		$model    = $comTemplatesFactory->createModel('Template', 'Administrator', ['ignore_request' => true]);
		$coreFile = $model->getCoreFile($cleanFileName, $sourceObject->client_id);

		if ($coreFile === false)
		{
			throw new \RuntimeException('Cannot find the corresponding core file', 500);
		}

		$coreSource = @file_get_contents($coreFile);

		if (!is_readable($coreFile) || $coreSource === false)
		{
			throw new \RuntimeException('Cannot read core file', 500);
		}

		// Construct the return item
		return (object) [
			'id'                   => $id,
			'template'             => $sourceObject->template,
			'client'               => $sourceObject->client_id,
			'name'                 => $cleanFileName,
			'overridePath'         => $filePath,
			'overridePathRelative' => substr($filePath, strlen(JPATH_ROOT) + 1),
			'overrideSource'       => $overrideSource,
			'corePath'             => $coreFile,
			'corePathRelative'     => substr($coreFile, strlen(JPATH_ROOT) + 1),
			'coreSource'           => $coreSource,
		];
	}

	protected function getListQuery()
	{
		$db     = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();
		$client = (int) $this->getState('filter.client_id', 0);

		$subQuery = $db->getQuery(true)
			->select('1')
			->from($db->quoteName('#__template_styles', 's'))
			->where([
				$db->quoteName('s.template') . '=' . $db->quoteName('o.template'),
				$db->quoteName('s.client_id') . '=' . $client,
				//$db->quoteName('s.home') . '= 1',
			]);

		$query = $db->getQuery(true)
			->select($db->quoteName(
				[
					'id',
					'template',
					'hash_id',
					'extension_id',
					'state',
					'action',
					'client_id',
					'created_date',
					'modified_date',
				]
			))
			->from($db->quoteName('#__template_overrides', 'o'))
			->where('EXISTS(' . $subQuery . ')')
			->order([
				$db->quoteName('client_id') . ' ASC',
				$db->quoteName('template') . ' ASC',
				$db->quoteName('modified_date') . ' ASC',
			]);

		return $query;
	}
}