<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;

defined('_JEXEC') || die;

use Akeeba\AdminTools\Admin\Model\TempSuperUsers;
use Akeeba\PanopticonConnector\Controller\Mixit\AdminToolsTrait;

class AdmintoolsTempsuperuser extends AbstractController
{
	use AdminToolsTrait;

	public function __invoke(\JInput $input): object
	{
		/** @var TempSuperUsers $model */
		$container = $this->getAdminToolsContainer();
		$model     = $container->factory->model('TempSuperUsers')->tmpInstance();

		$expiration = $input->post->getString('expiration', '');
		$now        = new \JDate();

		try
		{
			$test     = new \JDate($expiration);
			$earliest = (new \JDate($expiration))->add(new \DateInterval('P1D'));

			if ($test <= $earliest)
			{
				$test = $now->add(new \DateInterval('P1W'));
			}
		}
		catch (\Throwable $e)
		{
			$now  = new \JDate();
			$test = $now->add(new \DateInterval('P1W'));
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

		\JFactory::getApplication()->getLanguage()->load('com_admintools', JPATH_ADMINISTRATOR);

		if (!$model->save($data))
		{
			throw new \RuntimeException($model->getError());
		}

		$data     = (object) $data;
		$data->id = $model->getState('tempsuperuser.id');

		return $this->asSingleItem('admintools', $data);
	}

	private function genRandomPassword(
		$length = 8, $salt = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
	)
	{
		$base     = strlen($salt);
		$makepass = '';

		/*
		 * Start with a cryptographic strength random string, then convert it to a string with the numeric base of the
		 * salt.
		 *
		 * Shift the base conversion on each character so the character distribution is even, and randomize the start
		 * shift, so it's not predictable.
		 */
		$random = \JCrypt::genRandomBytes($length + 1);
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
		$db    = \JFactory::getDbo();
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
			return \JAccess::checkGroup($group, 'core.admin');
		}
		);

		return $this->superUserGroups;
	}

}