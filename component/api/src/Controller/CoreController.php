<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Api\Controller;

defined('_JEXEC') || die;

use Akeeba\Component\Panopticon\Api\Model\CoreModel;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Serializer\JoomlaSerializer;
use Joomla\CMS\Uri\Uri;
use Tobscure\JsonApi\Resource;

class CoreController extends ApiController
{
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
		/** @var CoreModel $model */
		$model = $this->getModel();

		// Change the update site if necessary
		$updateSource = $this->input->post->getCmd('updatesource', '');

		if (!empty($updateSource))
		{
			$updateURL = $this->input->post->getRaw('updateurl', null);

			$model->changeUpdateSource($updateSource, $updateURL);
		}

		// Apply the update source
		$model->applyUpdateSource();

		// Reload the update information
		$model->getJoomlaUpdateInfo(true);

		$this->app->setHeader('status', 200);
	}

	public function downloadUpdate()
	{
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
}