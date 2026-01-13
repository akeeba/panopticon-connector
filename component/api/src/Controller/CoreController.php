<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Controller;

defined('_JEXEC') || die;

use Akeeba\Component\Panopticon\Api\Mixin\J6FixBrokenModelStateTrait;
use Akeeba\Component\Panopticon\Api\Model\CoreModel;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Serializer\JoomlaSerializer;
use Joomla\CMS\Uri\Uri;
use Joomla\Http\HttpFactory;
use Joomla\Registry\Registry;
use Tobscure\JsonApi\Resource;

class CoreController extends ApiController
{
	use J6FixBrokenModelStateTrait;

	protected $contentType = 'coreupdate';

	protected $default_view = 'core';

	public function execute($task)
	{
		try
		{
			return parent::execute($task);
		}
		catch (\RuntimeException $e)
		{
			$this->failWithError($e);
		}

		return '';
	}

	public function getupdate()
	{
		$force = $this->input->getInt('force', 0) === 1;

		$this->input->set('model', 'core');

		$this->modelState->panopticon_mode  = 'core.update';
		$this->modelState->panopticon_force = $force;

		return $this->displayItem();
	}

	public function applyUpdateSite()
	{
		if (!$this->app->getIdentity()->authorise('core.manage', 'com_joomlaupdate'))
		{
			throw new NotAllowed($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/** @var CoreModel $model */
		$model = $this->getModel();

		// Change the update site if necessary
		$updateSource = $this->input->post->getCmd('updatesource', '');

		if (!empty($updateSource))
		{
			$updateURL = $this->input->post->getRaw('updateurl', null);

			$model->changeUpdateSource($updateSource, $updateURL);
		}

		/**
		 * Reset the update source.
		 *
		 * We will first try to use Admin Tools Professional's Reset Joomla! Update feature which is the most complete
		 * way to do that, even covering corrupt / missing TUF metadata.
		 *
		 * If this doesn't work (you do not have Admin Tools Professional, or not have a version of it with this
		 * feature, or that feature fails) we will fall back to our legacy method.
		 */
		if (!$model->useAdminToolsResetJoomlaUpdate())
		{
			// Make sure there is a core update record
			$model->affirmCoreUpdateRecord();

			// Apply the update source
			$model->applyUpdateSite();
		}

		// Reload the update information
		$model->getJoomlaUpdateInfo(true);

		$this->app->setHeader('status', 200);
	}

	public function downloadUpdate()
	{
		if (!$this->app->getIdentity()->authorise('core.manage', 'com_joomlaupdate'))
		{
			throw new NotAllowed($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/** @var CoreModel $model */
		$model = $this->getModel();

		$result       = $model->download();
		$result['id'] = 0;

		$serializer = new JoomlaSerializer('coreupdatedownload');
		$element    = (new Resource((object) $result, $serializer))
			->fields(array_keys($result));

		$this->app->getDocument()->setData($element);
		$this->app->getDocument()->addLink('self', Uri::current());
		$this->app->setHeader('status', 200);
	}

	public function downloadUpdateChunked()
	{
		if (!$this->app->getIdentity()->authorise('core.manage', 'com_joomlaupdate'))
		{
			throw new NotAllowed($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Get potential POST parameters, used to resume the chunk download
		$url         = $this->input->post->get('url', null, 'raw');
		$size        = (int) $this->input->post->get('size', -1, 'int') ?: -1;
		$offset      = $this->input->post->get('offset', -1, 'int') ?: -1;
		$chunk_index = $this->input->post->get('chunk_index', -1, 'int') ?: -1;
		$max_time    = $this->input->post->get('max_time', -1, 'int') ?: -1;

		// Sanitise values
		$url         = empty($url) ? null : (filter_var($url, FILTER_SANITIZE_URL) ?: null);
		$size        = $size >= 0 ? $size : null;
		$offset      = $offset >= 0 ? $offset : null;
		$chunk_index = $chunk_index >= 0 ? $chunk_index : null;
		$max_time    = $max_time >= 0 ? $max_time : null;

		// Pass values to the model
		/** @var CoreModel $model */
		$model = $this->getModel();
		$model->setState('download.url', $url);
		$model->setState('download.size', $size);
		$model->setState('download.offset', $offset);
		$model->setState('download.chunk_index', $chunk_index);
		$model->setState('download.max_time', $max_time);

		// Perform a chunk download
		$result       = $model->downloadChunkedUpdateByPanopticon();
		$result['id'] = 0;

		// Return the result to the caller
		$serializer = new JoomlaSerializer('coreupdatedownloadchunked');
		$element    = (new Resource((object) $result, $serializer))
			->fields(array_keys($result));

		$this->app->getDocument()->setData($element);
		$this->app->getDocument()->addLink('self', Uri::current());
		$this->app->setHeader('status', 200);
	}

	public function activateExtract()
	{
		if (!$this->app->getIdentity()->authorise('core.manage', 'com_joomlaupdate'))
		{
			throw new NotAllowed($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$basename = $this->input->getRaw('basename', null);

		/** @var CoreModel $model */
		$model = $this->getModel();

		$method = method_exists($model, 'createUpdateFile') ? 'createUpdateFile' : 'createRestorationFile';

		if (!$model->{$method}($basename))
		{
			throw new \RuntimeException(
				sprintf(
					'Cannot create the administrator/components/com_joomlaupdate/%s file.',
					version_compare(JVERSION, '4.0.4', 'ge') ? 'update.php' : 'restoration.php'
				)
			);
		}

		$result = (object) [
			'id'       => 0,
			'password' => $model->getState('password'),
			'filesize' => $model->getState('filesize'),
			'file'     => $model->getState('file'),
		];

		$serializer = new JoomlaSerializer('coreupdateactivate');
		$element    = (new Resource((object) $result, $serializer))
			->fields(array_keys((array) $result));

		$this->app->getDocument()->setData($element);
		$this->app->getDocument()->addLink('self', Uri::current());
		$this->app->setHeader('status', 200);
	}

	public function disableExtract()
	{
		if (!$this->app->getIdentity()->authorise('core.manage', 'com_joomlaupdate'))
		{
			throw new NotAllowed($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/** @var CoreModel $model */
		$model = $this->getModel();

		$model->removeExtractPasswordFile();

		$this->app->setHeader('status', 200);
	}

	public function postUpdate()
	{
		if (!$this->app->getIdentity()->authorise('core.manage', 'com_joomlaupdate'))
		{
			throw new NotAllowed($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$options['format']    = '{DATE}\t{TIME}\t{LEVEL}\t{CODE}\t{MESSAGE}';
		$options['text_file'] = 'joomla_update.php';
		Log::addLogger($options, Log::INFO, ['Update', 'databasequery', 'jerror']);

		try
		{
			$this->app->getLanguage()->load('com_joomlaupdate', JPATH_ADMINISTRATOR);
			Log::add(Text::_('COM_JOOMLAUPDATE_UPDATE_LOG_FINALISE'), Log::INFO, 'Update');
		}
		catch (\RuntimeException $exception)
		{
			// Informational log only
		}


		/** @var CoreModel $model */
		$model = $this->getModel();

		$model->finaliseUpgrade();

		$basename = $this->input->getRaw('basename', null);
		$this->app->setUserState('com_joomlaupdate.file', $basename);
		$model->cleanUp();
	}


	private function failWithError(\Throwable $e)
	{
		$errorCode = $e->getCode() ?: 500;

		$this->app->getDocument()->setErrors([
			[
				'title' => $e->getMessage(),
				'code'  => $errorCode,
			],
		]);

		$this->app->setHeader('status', $errorCode);
	}

	public function prepareChecksum()
	{
		if (!$this->app->getIdentity()->authorise('core.manage', 'com_joomlaupdate'))
		{
			throw new NotAllowed($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$version = JVERSION;
		$url     = "https://getpanopticon.com/checksums/joomla/{$version}/sha256_squash.json.gz";
		$tmpFile = Factory::getApplication()->get('tmp_path') . '/sha256_squash.json.gz';

		$options = new Registry();
		$options->set('transport.curl', [CURLOPT_FOLLOWLOCATION => true]);
		$http     = (new HttpFactory)->getHttp($options);
		$response = $http->get($url);

		if ($response->getStatusCode() !== 200)
		{
			throw new \RuntimeException("Could not download checksums from $url (HTTP " . $response->getStatusCode() . ")");
		}

		$body = (string) $response->getBody();
		file_put_contents($tmpFile, $body);

		$gzContent = gzdecode($body);

		if ($gzContent === false)
		{
			@unlink($tmpFile);
			throw new \RuntimeException("Failed to decompress checksums file");
		}

		$checksums = json_decode($gzContent, true);

		if (json_last_error() !== JSON_ERROR_NONE)
		{
			@unlink($tmpFile);
			throw new \RuntimeException("Failed to parse checksums JSON: " . json_last_error_msg());
		}

		@unlink($tmpFile);

		$db = Factory::getDbo();
		$db->truncateTable('#__panopticon_coresums');

		$paths     = array_keys($checksums);
		$total     = count($paths);
		$batchSize = 100;

		for ($i = 0; $i < $total; $i += $batchSize)
		{
			$query = $db->getQuery(true)
				->insert($db->quoteName('#__panopticon_coresums'))
				->columns([
					$db->quoteName('path'),
					$db->quoteName('checksum'),
				]);

			$batch = array_slice($paths, $i, $batchSize);

			foreach ($batch as $path)
			{
				$query->values($db->quote($path) . ', ' . $db->quote($checksums[$path]));
			}

			$db->setQuery($query)->execute();
		}

		$this->app->setHeader('status', 200);
		echo json_encode(true);
		$this->app->close();
	}

	public function stepChecksum()
	{
		if (!$this->app->getIdentity()->authorise('core.manage', 'com_joomlaupdate'))
		{
			throw new NotAllowed($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$step = $this->input->getInt('step', 0);
		$db   = Factory::getDbo();

		$startTime    = microtime(true);
		$invalidFiles = [];
		$last_id      = $step;
		$done         = false;

		while (true)
		{
			$query = $db->getQuery(true)
				->select('*')
				->from($db->quoteName('#__panopticon_coresums'))
				->where($db->quoteName('id') . ' > ' . (int) $last_id)
				->order($db->quoteName('id') . ' ASC');
			$db->setQuery($query, 0, 250);
			$rows = $db->loadObjectList();

			if (empty($rows))
			{
				$done = true;
				break;
			}

			foreach ($rows as $row)
			{
				$path             = $row->path;
				$expectedChecksum = $row->checksum;
				$fullPath         = JPATH_ROOT . '/' . $path;
				$actualChecksum   = '';

				if (@is_file($fullPath))
				{
					$content = @file_get_contents($fullPath);

					if ($content === false)
					{
						continue;
					}

					$content        = preg_replace('#[\n\r\t\s\v]+#ms', ' ', $content);
					$actualChecksum = hash('sha256', $content);
				}

				if ($actualChecksum !== $expectedChecksum)
				{
					$invalidFiles[] = $path;
				}

				$last_id = $row->id;
			}

			if ((microtime(true) - $startTime) >= 2.0)
			{
				break;
			}
		}

		$result = [
			'done'         => $done,
			'last_id'      => (int) $last_id,
			'invalidFiles' => $invalidFiles,
		];

		$this->app->setHeader('status', 200);
		echo json_encode($result);
		$this->app->close();
	}
}