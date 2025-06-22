<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Controller;

defined('_JEXEC') || die;

use Akeeba\Component\Panopticon\Api\Mixin\J6FixBrokenModelStateTrait;
use Akeeba\Component\Panopticon\Api\Model\BackupModel;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Serializer\JoomlaSerializer;
use Joomla\CMS\Uri\Uri;
use RuntimeException;
use Throwable;
use Tobscure\JsonApi\Resource;

class BackupController extends ApiController
{
	use J6FixBrokenModelStateTrait;

	protected $contentType = 'akeebabackup';

	protected $default_view = 'backup';

	public function execute($task)
	{
		try
		{
			return parent::execute($task);
		}
		catch (RuntimeException $e)
		{
			$this->failWithError($e);
		}

		return '';
	}

	public function version()
	{
		if (!$this->app->getIdentity()->authorise('core.manage', 'com_installer'))
		{
			throw new NotAllowed($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/** @var BackupModel $model */
		$model = $this->getModel();

		$result     = $model->getVersion();
		$serializer = new JoomlaSerializer('akeebabackupinfo');
		$element    = (new Resource($result, $serializer))
			->fields(array_keys((array) $result));

		$this->app->getDocument()->setData($element);
		$this->app->getDocument()->addLink('self', Uri::current());
		$this->app->setHeader('status', 200);
	}

	private function failWithError(Throwable $e)
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