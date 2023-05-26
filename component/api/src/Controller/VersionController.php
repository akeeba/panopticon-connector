<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Controller;

defined('_JEXEC') || die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Serializer\JoomlaSerializer;
use Joomla\CMS\Uri\Uri;
use Tobscure\JsonApi\Resource;

class VersionController extends ApiController
{
	protected $contentType = 'panopticon';

	protected $default_view = 'version';

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

	public function version()
	{
		if (!$this->app->getIdentity()->authorise('core.manage', 'com_joomlaupdate'))
		{
			throw new NotAllowed($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
		}

		if (!defined('AKEEBA_PANOPTICON_VERSION'))
		{
			@include_once JPATH_ADMINISTRATOR . '/components/com_panopticon/version.php';
		}

		$version  = defined('AKEEBA_PANOPTICON_VERSION') ? AKEEBA_PANOPTICON_VERSION : '0.0.0-dev1';
		$date     = defined('AKEEBA_PANOPTICON_DATE') ? AKEEBA_PANOPTICON_DATE : gmdate('Y-m-d');
		$apiLevel = defined('AKEEBA_PANOPTICON_API') ? AKEEBA_PANOPTICON_API : 100;

		$result = [
			'id'      => 0,
			'version' => $version,
			'date'    => $date,
			'api'     => $apiLevel,
		];

		$serializer = new JoomlaSerializer('panopticon');
		$element    = (new Resource((object)$result, $serializer))
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