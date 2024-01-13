<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Plugin\Console\Panopticon\Command;

defined('_JEXEC') || die;

use Akeeba\Plugin\Console\Panopticon\Command\MixIt\ConfigureIOTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Gets the Joomla! API Token for a given user.
 *
 * Intended to be used in an automation shell script like this:
 *
 * TOKEN = $(php /path/to/joomla.php panopticon:token:get -u my_user --no-ansi -q)
 *
 * @since 1.0.3
 */
class GetToken extends AbstractCommand implements DatabaseAwareInterface
{
	use ConfigureIOTrait;
	use DatabaseAwareTrait;

	protected static $defaultName = 'panopticon:token:get';

	/**
	 * @inheritDoc
	 *
	 * @since 1.0.3
	 */
	protected function doExecute(InputInterface $input, OutputInterface $output): int
	{
		$this->configureSymfonyIO($input, $output);

		// Get the user and make sure it's a Super User
		$username = $this->cliInput->getOption('username');
		$reset    = $this->cliInput->getOption('reset');

		$this->ioStyle->title(
			Text::sprintf('PLG_CONSOLE_PANOPTICON_GETTOKEN_TITLE', $username)
		);

		/** @var User $user */
		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserByUsername($username);

		if (empty($user) || !$user->id)
		{
			$this->ioStyle->error(Text::_('PLG_CONSOLE_PANOPTICON_GETTOKEN_ERR_NO_SUCH_USER'));

			return 1;
		}

		// Make sure the necessary plugins are enabled
		$this->ensurePluginEnabled('webservices', 'panopticon');
		$this->ensurePluginEnabled('api-authentication', 'token');
		$this->ensurePluginEnabled('user', 'token');

		// Is this an allowed user for token access?
		if (!$this->isAllowedUser($user))
		{
			$this->ioStyle->error(Text::_('PLG_CONSOLE_PANOPTICON_GETTOKEN_ERR_USER_NOT_ALLOWED'));

			return 2;
		}

		// Make sure the Joomla! token us enabled for the user, and a non-empty token seed available.
		$tokenSeed    = $this->getUserProfileValue($user, 'joomlatoken.token');
		$tokenEnabled = $this->getUserProfileValue($user, 'joomlatoken.enabled', 1);

		if (!$tokenEnabled)
		{
			$this->ioStyle->comment(Text::sprintf('PLG_CONSOLE_PANOPTICON_GETTOKEN_ENABLING_ACCESS', $username));

			$this->setUserProfileValue($user, 'joomlatoken.enabled', 1);
		}

		if (empty($tokenSeed) || $reset)
		{
			$this->ioStyle->comment(Text::sprintf('PLG_CONSOLE_PANOPTICON_GETTOKEN_CREATE_TOKEN_SEED', $username));

			$this->setUserProfileValue($user, 'joomlatoken.token', base64_encode(random_bytes(32)));
		}

		// Display the token – even in quiet mode
		$output->writeln(
			sprintf(
				'<info>%s</>',
				Text::sprintf('PLG_CONSOLE_PANOPTICON_GETTOKEN_THE_TOKEN_IS', $username)
			)
		);
		$output->writeln(
			sprintf(
				'<info>%s</>',
				$this->getApiToken($user)
			),
			Output::VERBOSITY_QUIET
		);

		return Command::SUCCESS;
	}

	/**
	 * @inheritDoc
	 *
	 * @since 1.0.3
	 */
	protected function configure(): void
	{
		$this->addOption(
			'username', 'u', InputOption::VALUE_REQUIRED,
			Text::_('PLG_CONSOLE_PANOPTICON_GETTOKEN_OPT_USERNAME')
		);
		$this->addOption(
			'reset', 'r', InputOption::VALUE_NONE,
			Text::_('PLG_CONSOLE_PANOPTICON_GETTOKEN_OPT_RESET')
		);
		$this->setDescription(Text::_('PLG_CONSOLE_PANOPTICON_GETTOKEN_DESC'));
		$this->setHelp(Text::_('PLG_CONSOLE_PANOPTICON_GETTOKEN_HELP'));
	}

	/**
	 * Get the API token for a given user.
	 *
	 * @param   User|null  $user  The user object. Default is null.
	 *
	 * @return  string The API token for the user. Returns an empty string if the token seed or token enabled is empty
	 *                 or false.
	 *
	 * @since   1.0.3
	 */
	private function getApiToken(User $user = null): string
	{
		$tokenSeed    = $this->getUserProfileValue($user, 'joomlatoken.token');
		$tokenEnabled = $this->getUserProfileValue($user, 'joomlatoken.enabled', 1);

		if (empty($tokenSeed) || !$tokenEnabled)
		{
			return '';
		}

		try
		{
			$siteSecret = $this->getApplication()->get('secret');
		}
		catch (Throwable $e)
		{
			$siteSecret = '';
		}

		// NO site secret? You monster!
		if (empty($siteSecret))
		{
			return '';
		}

		$algorithm = 'sha256';
		$userId    = $user->id;
		$rawToken  = base64_decode($tokenSeed);
		$tokenHash = hash_hmac($algorithm, $rawToken, $siteSecret);
		$message   = base64_encode("$algorithm:$userId:$tokenHash");

		return $message;
	}

	/**
	 * Check if a user is allowed to access the API application based on the specified user groups.
	 *
	 * @param   User  $user  The user to check.
	 *
	 * @return  bool  Returns true if the user is allowed, false otherwise.
	 * @since   1.0.3
	 */
	private function isAllowedUser(User $user): bool
	{
		$plugin            = PluginHelper::getPlugin('user', 'token');
		$params            = new Registry($plugin->params ?? '{}');
		$allowedUserGroups = $params->get('allowedUserGroups', [8]);
		$allowedUserGroups = is_array($allowedUserGroups)
			? $allowedUserGroups
			: ArrayHelper::toInteger(explode(',', $allowedUserGroups));

		return !empty(array_intersect($user->getAuthorisedGroups(), $allowedUserGroups));
	}

	/**
	 * Get the value of a user's profile field based on the specified key.
	 *
	 * @param   User        $user     The user object.
	 * @param   string      $key      The key of the profile value to retrieve.
	 * @param   mixed|null  $default  Optional. The default value to return if the profile value is not found. Defaults
	 *                                to null.
	 *
	 * @return  mixed|null  The value of the user's profile field if found, or the default value if not found.
	 * @throws  Throwable   If an error occurs while retrieving the profile value.
	 * @since   1.0.3
	 */
	private function getUserProfileValue(User $user, string $key, $default = null)
	{
		$id = $user->id;

		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName('profile_value'))
			->from($db->quoteName('#__user_profiles'))
			->where($db->quoteName('profile_key') . ' = :key')
			->where($db->quoteName('user_id') . ' = :id')
			->bind(':key', $key, ParameterType::STRING)
			->bind(':id', $id, ParameterType::INTEGER);

		try
		{
			return $db->setQuery($query)->loadResult() ?? $default;
		}
		catch (Throwable $e)
		{
			return $default;
		}
	}

	/**
	 * Sets the value of a user profile field for a given user.
	 *
	 * @param   User    $user   The user for whom to set the profile value.
	 * @param   string  $key    The key of the profile field to set.
	 * @param   string  $value  The value to set for the profile field.
	 *
	 * @since   1.0.3
	 */
	private function setUserProfileValue(User $user, string $key, string $value): void
	{
		/** @var DatabaseDriver $db */
		$db = $this->getDatabase();

		$userId = $user->id;
		$query  = $db->getQuery(true)
			->delete($db->quoteName('#__user_profiles'))
			->where($db->quoteName('user_id') . ' = :userId')
			->where($db->quoteName('profile_key') . ' = :key');

		$query->bind(':userId', $userId, ParameterType::INTEGER);
		$query->bind(':key', $key, ParameterType::STRING);

		$db->setQuery($query)->execute();

		$query = $db->getQuery(true)
			->insert($db->quoteName('#__user_profiles'))
			->columns(
				[
					$db->quoteName('user_id'),
					$db->quoteName('profile_key'),
					$db->quoteName('profile_value'),
					$db->quoteName('ordering'),
				]
			)
			->values(
				$userId . ',' . $db->quote($key) . ', ' . $db->quote($value) . ', 0'
			);

		$db->setQuery($query)->execute();
	}

	/**
	 * Ensure that a plugin is enabled.
	 *
	 * If the plugin is not enabled, it will be enabled, and the plugin will be forcibly loaded.
	 *
	 * @param   string  $folder  The folder containing the plugin.
	 * @param   string  $plugin  The name of the plugin.
	 *
	 * @return  void
	 *
	 * @since   1.0.3
	 */
	private function ensurePluginEnabled(string $folder, string $plugin): void
	{
		if (PluginHelper::isEnabled($folder, $plugin))
		{
			return;
		}

		$this->ioStyle->comment(Text::sprintf('PLG_CONSOLE_PANOPTICON_GETTOKEN_ENABLE_PLUGIN', $plugin, $folder));

		/** @var DatabaseDriver $db */
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('enabled') . ' = 1')
			->where(
				[
					$db->quoteName('type') . ' = ' . $db->quote('plugin'),
					$db->quoteName('element') . ' = :plugin',
					$db->quoteName('folder') . ' = :folder',
				]
			)
			->bind(':plugin', $plugin, ParameterType::STRING)
			->bind(':folder', $folder, ParameterType::STRING);
		$db->setQuery($query)->execute();

		$refClass = new \ReflectionClass(PluginHelper::class);
		$refProp  = $refClass->getProperty('plugins');
		$refProp->setAccessible(true);
		$refProp->setValue(null);

		PluginHelper::importPlugin($folder, $plugin);
	}
}