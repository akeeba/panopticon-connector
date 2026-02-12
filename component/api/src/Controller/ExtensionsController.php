<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Controller;

defined('_JEXEC') || die;

use Akeeba\Component\Panopticon\Api\Mixin\J6FixBrokenModelStateTrait;
use Akeeba\Component\Panopticon\Api\Model\ExtensionsModel;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Serializer\JoomlaSerializer;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;
use Joomla\Http\HttpFactory;
use RuntimeException;
use Throwable;
use Tobscure\JsonApi\Resource;

class ExtensionsController extends ApiController
{
	use J6FixBrokenModelStateTrait;

	protected $contentType = 'extensions';

	protected $default_view = 'extensions';

	public function displayList()
	{
		foreach ([
			         'updatable',
			         'protected',
			         'core',
					 'force',
					 'timeout',
		         ] as $key)
		{
			$value = $this->input->get->get($key, null);

			if ($value !== null && $value !== '')
			{
				$this->modelState->set('filter.' . $key, intval($value));
			}
		}

		return parent::displayList();
	}

	/**
	 * Basic display of an item view
	 *
	 * @param   integer  $id  The primary key to display. Leave empty if you want to retrieve data from the request
	 *
	 * @return  static  A \JControllerLegacy object to support chaining.
	 *
	 * @since   4.0.0
	 */
	public function displayItem($id = null)
	{
		if ($id === null) {
			$id = $this->input->get('id', 0, 'int');
		}

		if (is_int($id) && $id === 0)
		{
			$element = $this->input->getCmd('element', '');
			/** @var ExtensionsModel $model */
			$model = $this->getModel('Extensions', 'Api', ['ignore_request' => true]);
			$id = $model->getExtensionIdFromElement($element);
		}

		if ($id === 0 || $id === null)
		{
			throw new ResourceNotFound();
		}

		return parent::displayItem($id);
	}

	public function install(): void
	{
		if (!$this->app->getIdentity()->authorise('core.manage', 'com_installer'))
		{
			throw new NotAllowed($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$params = ComponentHelper::getComponent('com_panopticon')->getParams();

		if (!$params->get('allow_remote_install', 1))
		{
			throw new RuntimeException('Remote extension installation is disabled on this site.', 403);
		}

		$tmpPath = Factory::getApplication()->get('tmp_path');

		if (empty($tmpPath) || !is_dir($tmpPath) || !is_writable($tmpPath))
		{
			throw new RuntimeException('The Joomla temporary directory is not writable.', 500);
		}

		$method      = strtoupper($this->input->getMethod());
		$packageFile = null;
		$extractDir  = null;
		try
		{
			if ($method === 'POST')
			{
				$url = $this->input->post->get('url', '', 'raw');
				$url = filter_var($url, FILTER_SANITIZE_URL);

				if (empty($url) || filter_var($url, FILTER_VALIDATE_URL) === false)
				{
					throw new RuntimeException('You must provide a URL to download the package.', 400);
				}

				$packageFile = $this->downloadPackageFromUrl($url, $tmpPath);
			}
			elseif ($method === 'PUT')
			{
				$packageFile = $this->storeUploadedPackage($tmpPath);
			}
			else
			{
				throw new RuntimeException('Unsupported method.', 405);
			}

			// Unpack the package archive into a temporary directory
			$extractDir = $tmpPath . '/install_' . bin2hex(random_bytes(8));

			if (!@mkdir($extractDir, 0755, true) && !is_dir($extractDir))
			{
				throw new RuntimeException('Failed to create extraction directory.', 500);
			}

			$zip = new \ZipArchive();

			if ($zip->open($packageFile) !== true)
			{
				throw new RuntimeException('Failed to open the extension package.', 500);
			}

			if (!$zip->extractTo($extractDir))
			{
				$zip->close();

				throw new RuntimeException('Failed to extract the extension package.', 500);
			}

			$zip->close();

			$installer = Installer::getInstance();
			$installed = $installer->install($extractDir);

			$result = (object) [
				'id'       => 0,
				'status'   => $installed,
				'messages' => $this->app->getMessageQueue(true),
			];

			$this->respondInstall($result);
		}
		catch (Throwable $e)
		{
			$this->failWithError($e);
		}
		finally
		{
			if (!empty($extractDir) && is_dir($extractDir))
			{
				$this->deleteDirectory($extractDir);
			}

			if (!empty($packageFile) && is_file($packageFile))
			{
				@unlink($packageFile);
			}
		}
	}

	private function storeUploadedPackage(string $tmpPath): string
	{
		$rawFilename = $this->input->getString('filename', '');
		$rawFilename = trim($rawFilename);
		$filename    = str_replace(['.', '/', '\\'], '', $rawFilename);

		if ($filename === '')
		{
			throw new RuntimeException('You must provide a filename.', 400);
		}

		$data = file_get_contents('php://input');

		if ($data === false || $data === '')
		{
			throw new RuntimeException('No package data was uploaded.', 400);
		}

		$tmpPath = rtrim($tmpPath, '/\\');
		$path    = $tmpPath . '/' . $filename;

		if (file_exists($path))
		{
			$path .= '-' . bin2hex(random_bytes(4));
		}

		if (!File::write($path, $data))
		{
			throw new RuntimeException('Unable to write the uploaded package file.', 500);
		}

		return $path;
	}

	private function downloadPackageFromUrl(string $url, string $tmpPath): string
	{
		$http     = HttpFactory::getHttp();
		$response = $http->get($url);

		if ((int) $response->code < 200 || (int) $response->code >= 300)
		{
			throw new RuntimeException('Unable to download the package file.', 400);
		}

		$pathPart = parse_url($url, PHP_URL_PATH) ?: '';
		$filename = basename($pathPart);
		$filename = File::makeSafe($filename);

		if ($filename === '')
		{
			$filename = 'package.zip';
		}

		$tmpPath = rtrim($tmpPath, '/\\');
		$path    = $tmpPath . '/' . $filename;

		if (file_exists($path))
		{
			$path .= '-' . bin2hex(random_bytes(4));
		}

		if (!File::write($path, $response->body))
		{
			throw new RuntimeException('Unable to write the downloaded package file.', 500);
		}

		return $path;
	}

	private function respondInstall(object $result): void
	{
		$serializer = new JoomlaSerializer('extensioninstall');
		$element    = (new Resource($result, $serializer))
			->fields(array_keys((array) $result));

		$this->app->getDocument()->setData($element);
		$this->app->getDocument()->addLink('self', Uri::current());
		$this->app->setHeader('status', 200);
	}

	private function deleteDirectory(string $dir): void
	{
		if (!is_dir($dir))
		{
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($items as $item)
		{
			$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
		}

		@rmdir($dir);
	}

	private function failWithError(Throwable $e): void
	{
		$errorCode = $e->getCode() ?: 500;

		$this->app->getDocument()->setErrors(
			[
				[
					'title' => $e->getMessage(),
					'code'  => $errorCode,
				],
			]
		);

		$this->app->setHeader('status', $errorCode);
	}

}
