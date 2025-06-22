<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Controller;

defined('_JEXEC') || die;

use Akeeba\Component\AdminTools\Administrator\Scanner\Util\Session;
use Akeeba\Component\Panopticon\Api\Mixin\J6FixBrokenModelStateTrait;
use DateInterval;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Crypt\Crypt;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Serializer\JoomlaSerializer;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;
use RuntimeException;
use Throwable;
use Tobscure\JsonApi\Resource;
use function ord;
use function strlen;

class AdmintoolsController extends ApiController
{
	use J6FixBrokenModelStateTrait;

	private static $models = [];

	protected $contentType = 'admintools';

	protected $default_view = 'admintools';

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

	public function unblock()
	{
		$ip = $this->input->post->getRaw('ip');

		if (empty($ip))
		{
			throw new RuntimeException('You must provide an IP address.');
		}

		$model = $this->getAdminToolsMVCFactory()->createModel('Unblockip', 'Administrator');
		$model->unblockIP($ip);

		$this->respond(
			(object) [
				'id'     => 0,
				'status' => true,
			]
		);
	}

	public function plugin_disable()
	{
		$this->assertSuperUser();

		$model = $this->getAdminToolsMVCFactory()->createModel('Controlpanel', 'Administrator');
		$ret   = (object) [
			'id'      => 0,
			'renamed' => true,
			'name'    => null,
		];

		if ($model->isMainPhpDisabled())
		{
			$ret->name = $model->getRenamedMainPhp();
			$this->respond($ret);

			return;
		}

		$from = JPATH_PLUGINS . '/system/admintools/services/provider.php';
		$to   = JPATH_PLUGINS . '/system/admintools/services/provider-disable.php';

		if (@rename($from, $to))
		{
			$ret->name = basename($to);
		}
		else
		{
			$ret->renamed = false;
		}

		$this->respond($ret);
	}

	public function plugin_enable()
	{
		$this->assertSuperUser();

		$model = $this->getAdminToolsMVCFactory()->createModel('Controlpanel', 'Administrator');
		$ret   = (object) [
			'id'      => 0,
			'renamed' => false,
			'name'    => null,
		];

		if (!$model->isMainPhpDisabled())
		{
			$this->respond($ret);

			return;
		}

		if (!$model->reenableMainPhp())
		{
			$ret->renamed = true;
			$ret->name    = $model->getRenamedMainPhp();
		}

		$this->respond($ret);
	}

	public function htaccess_disable()
	{
		$this->assertSuperUser();

		$ret = (object) [
			'id'      => 0,
			'exists'  => false,
			'renamed' => true,
		];

		$from = JPATH_SITE . '/.htaccess';
		$to   = JPATH_SITE . '/.htaccess.admintools';

		if (!file_exists($from) && file_exists($to))
		{
			$ret->exists  = false;
			$ret->renamed = true;
		}
		elseif (!file_exists($from))
		{
			$ret->exists  = false;
			$ret->renamed = false;
		}
		elseif (@rename($from, $to))
		{
			$ret->exists  = true;
			$ret->renamed = true;
		}
		else
		{
			$ret->exists  = true;
			$ret->renamed = false;
		}

		$this->respond($ret);
	}

	public function htaccess_enable()
	{
		$this->assertSuperUser();

		$ret = (object) [
			'id'       => 0,
			'exists'   => false,
			'restored' => false,
		];

		$from = JPATH_SITE . '/.htaccess.admintools';
		$to   = JPATH_SITE . '/.htaccess';

		if (!file_exists($from) && file_exists($to))
		{
			$ret->exists   = false;
			$ret->restored = true;
		}
		elseif (!file_exists($from))
		{
			$ret->exists   = false;
			$ret->restored = false;
		}
		elseif (@rename($from, $to))
		{
			$ret->exists   = true;
			$ret->restored = true;
		}
		else
		{
			$ret->exists   = true;
			$ret->restored = false;
		}

		$this->respond($ret);
	}

	public function tempsuperuser()
	{
		$this->assertSuperUser();

		$expiration = $this->input->post->getString('expiration', '');
		$now        = new Date();

		try
		{
			$test     = new Date($expiration);
			$earliest = (new Date($expiration))->add(new DateInterval('P1D'));

			if ($test <= $earliest)
			{
				$test = $now->add(new DateInterval('P1W'));
			}
		}
		catch (Throwable $e)
		{
			$now  = new Date();
			$test = $now->add(new DateInterval('P1W'));
		}

		$expiration = $test->toSql();

		$password = $this->genRandomPassword(
			32, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789`~!@#$%^&*()-_=+[{]};:\'"<,>.?/'
		);
		$username = 'tsu_' . $this->genRandomPassword(8);
		$data     = [
			'expiration' => $expiration,
			'name'       => 'Temporary Super User',
			'username'   => $username,
			'password'   => $password,
			'password2'  => $password,
			'email'      => $username . '@' . 'nowhere.invalid',
			'groups'     => $this->getSuperUserGroups(),
		];

		$model = $this->getAdminToolsMVCFactory()->createModel('Tempsuperuser', 'Administrator');
		$this->app->getLanguage()->load('com_admintools', JPATH_ADMINISTRATOR);

		if (!$model->save($data))
		{
			throw new RuntimeException($model->getError());
		}

		$data     = (object) $data;
		$data->id = $model->getState('tempsuperuser.id');

		$this->respond($data);
	}

	public function scanner_start()
	{
		$this->assertCanManage();

		$model = $this->getAdminToolsMVCFactory()->createModel('Scans', 'Administrator');

		$result          = (object) $model->startScan('api');
		$result->session = $this->getScannerState();
		$result->id      = $result->id ?? $result->session['com_admintools.filescanner.scanID'] ?? 0;

		$this->respond($result);
	}

	public function scanner_step()
	{
		// Make sure we can proceed
		$this->assertCanManage();

		// Get the session data of the PHP File Change Scanner
		$sessionData = $this->input->post->getRaw('session');

		if (empty($sessionData))
		{
			throw new RuntimeException('You need to pass the session data');
		}

		$this->setScannerState($sessionData);

		// Load the model and step the scan
		$model = $this->getAdminToolsMVCFactory()->createModel('Scans', 'Administrator');

		$result = (object) $model->stepScan();

		$result->session = $this->getScannerState();
		$result->id      = $result->id ?? $result->session['com_admintools.filescanner.scanID'] ?? 0;

		$this->respond($result);
	}

	public function scans()
	{
		$this->contentType = 'admintools.scans';
		$this->input->set('model', 'Scans');

		return $this->displayList();
	}

	public function scan()
	{
		$id = $this->input->getInt('id');

		if (empty($id))
		{
			throw new RuntimeException('You must specify the scan ID.');
		}

		$this->contentType = 'admintools.scanalerts';
		$this->input->set('model', 'Scanalerts');

		$this->modelState->set('filter.scan_id', $id);

		return $this->displayList();
	}

	public function scanalert()
	{
		$id = $this->input->getInt('id');

		if (empty($id))
		{
			throw new RuntimeException('You must specify the scanalert ID.');
		}

		$this->contentType = 'admintools.scanalert';
		$this->input->set('model', 'Scanalert');

		return $this->displayItem($id);
	}

	public function getModel($name = '', $prefix = '', $config = [])
	{
		switch (strtolower($name))
		{
			case 'scans':
			case 'scan':
			case 'scanalerts':
			case 'scanalert':
				self::$models[strtolower($name)] =
					self::$models[strtolower($name)]
					?? $this->getAdminToolsMVCFactory()->createModel($name, 'Administrator');

				return self::$models[strtolower($name)];
				break;

			default:
				return parent::getModel($name, $prefix, $config);
		}
	}


	private function getScannerState(): array
	{
		$scannerSession = Session::getInstance();
		$session        = Factory::getApplication()->getSession();
		$ret            = [];

		foreach ($scannerSession->getKnownKeys() as $key)
		{
			$key       = 'com_admintools.filescanner.' . $key;
			$ret[$key] = $session->get($key, null);
		}

		return $ret;
	}

	private function setScannerState(array $sessionData): void
	{
		$session = Factory::getApplication()->getSession();

		foreach ($sessionData as $k => $v)
		{
			$session->set($k, $v);
		}
	}

	private function respond(object $result)
	{
		$serializer = new JoomlaSerializer('admintools');
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

	private function getAdminToolsMVCFactory(): MVCFactoryInterface
	{
		return $this->app->bootComponent('com_admintools')->getMVCFactory();
	}

	private function genRandomPassword(
		$length = 8, $salt = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
	)
	{
		$base     = strlen($salt);
		$makepass = '';

		/*
		 * Start with a cryptographic strength random string, then convert it to
		 * a string with the numeric base of the salt.
		 * Shift the base conversion on each character so the character
		 * distribution is even, and randomize the start shift so it's not
		 * predictable.
		 */
		$random = Crypt::genRandomBytes($length + 1);
		$shift  = ord($random[0]);

		for ($i = 1; $i <= $length; ++$i)
		{
			$makepass .= $salt[($shift + ord($random[$i])) % $base];
			$shift    += ord($random[$i]);
		}

		return $makepass;
	}

	private function getSuperUserGroups()
	{
		if (!empty($this->superUserGroups))
		{
			return $this->superUserGroups;
		}

		// Get all groups
		/** @var DatabaseDriver $db */
		$db    = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true)
			->select([$db->qn('id')])
			->from($db->qn('#__usergroups'));

		$this->superUserGroups = $db->setQuery($query)->loadColumn(0);

		// This should never happen (unless your site is very dead, in which case I feel terribly sorry for you...)
		if (empty($this->superUserGroups))
		{
			$this->superUserGroups = [];
		}

		$this->superUserGroups = array_filter(
			$this->superUserGroups, function ($group) {
			return Access::checkGroup($group, 'core.admin');
		}
		);

		return $this->superUserGroups;
	}

	private function assertSuperUser(): void
	{
		$user = $this->app->getIdentity();

		if (empty($user) || !($user->authorise('core.admin')))
		{
			throw new RuntimeException('JERROR_ALERTNOAUTHOR');
		}
	}

	private function assertCanManage(): void
	{
		$user = $this->app->getIdentity();

		if (empty($user) || !($user->authorise('core.manage', 'com_admintools')))
		{
			throw new RuntimeException('JERROR_ALERTNOAUTHOR');
		}
	}
}