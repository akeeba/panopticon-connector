<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;

defined('_JEXEC') || die;

use Akeeba\Engine\Factory;
use Akeeba\Engine\Platform;
use Akeeba\PanopticonConnector\Controller\Mixit\SaveComponentParamsTrait;

class AkeebaBackupInfo extends AbstractController
{
	use SaveComponentParamsTrait;

	public function __invoke(\JInput $input): object
	{
		return $this->asSingleItem('akeebabackup', $this->getVersion());
	}

	public function getVersion(): object
	{
		$ret = (object) [
			'id'        => 0,
			'installed' => false,
			'version'   => '0.0.0',
			'api'       => null,
			'secret'    => null,
			'endpoints' => [],
		];

		if (!\JComponentHelper::isEnabled('com_akeeba'))
		{
			return $ret;
		}

		$ret->id        = \JComponentHelper::getComponent('com_akeeba')->id;
		$ret->installed = true;
		$ret->version   = $this->getComponentVersion();
		$ret->api       = $this->getMaxApiVersion();
		$ret->secret    = $this->getSecret();
		$ret->endpoints = $this->getEndpoints();

		return $ret;
	}

	private function getComponentVersion(): string
	{
		return $this->getVersionFromManifest()
			?: $this->getVersionFromExtensionsTable()
				?: '0.0.0';

	}

	private function getMaxApiVersion(): ?string
	{
		$prefix    = rtrim(JPATH_SITE, DIRECTORY_SEPARATOR . '/') . '/components/com_akeeba';

		$apiFiles = [
			$prefix . '/src/Controller/ApiController.php'    => '2',
			$prefix . '/Controller/Api.php'                  => '2',
			$prefix . '/Controller/Json.php'                 => '1',
			$prefix . '/controllers/json.php'                => '1',
		];

		foreach ($apiFiles as $filePath => $version)
		{
			if (@file_exists($filePath) && is_file($filePath) && is_readable($filePath))
			{
				return $version;
			}
		}

		return null;
	}

	private function getSecret(): ?string
	{
		$this->ensureFrontendApiEnabled();

		return $this->getDecodedSecret() ?: $this->createSecret();
	}

	private function getEndpoints(): ?object
	{
		$maxVersion = $this->getMaxApiVersion();
		$rootUri    = rtrim(\JUri::base(), '/') . '/';

		if ($maxVersion >= 3)
		{
			return (object) [
				'v2' => [
					$rootUri . 'index.php?option=com_akeeba&view=Api&format=raw',
				],
			];
		}

		if ($maxVersion == 2)
		{
			return (object) [
				'v2' => [
					$rootUri . 'index.php?option=com_akeeba&view=Api&format=raw',
				],
				'v1' => [
					$rootUri . 'index.php?option=com_akeeba&view=json',
				],
			];
		}

		return (object) [
			'v1' => [
				$rootUri . 'index.php?option=com_akeeba&view=json',
			],
		];
	}

	private function getVersionFromManifest(): ?string
	{
		$manifestPath = rtrim(JPATH_ADMINISTRATOR, DIRECTORY_SEPARATOR . '/') .
			'/components/com_akeeba/akeeba.xml';

		if (!file_exists($manifestPath) || !is_readable($manifestPath))
		{
			return null;
		}

		$contents = @file_get_contents($manifestPath);

		if ($contents === false)
		{
			return null;
		}

		$hasMatch = preg_match('#<version>\s*([\d.]+)\s*</version>#is', $contents, $matches);

		if (!$hasMatch)
		{
			return null;
		}

		return $matches[1] ?: null;
	}

	private function getVersionFromExtensionsTable(): ?string
	{
		$db    = \JFactory::getDbo();
		$query = $db->getQuery()
			->select($db->quoteName('manifest_cache'))
			->from($db->quoteName('#__extensions'))
			->where(
				[
					$db->quoteName('element') . ' = ' . $db->quote('com_akeeba'),
					$db->quoteName('type') . ' = ' . $db->quote('component'),
				]
			);
		$json  = $db->setQuery($query)->loadResult() ?: '{}';

		try
		{
			$manifest = @json_decode($json);

			return ($manifest->version ?? null) ?: null;
		}
		catch (\Exception $e)
		{
			return null;
		}
	}

	private function ensureFrontendApiEnabled(): void
	{
		// Which maximum API version do I have?
		$maxApiVersion = $this->getMaxApiVersion();

		$params = \JComponentHelper::getParams('com_akeeba');

		// APIv2 (AB 7.4+): jsonapi_enabled must be 1
		if ($maxApiVersion >= 2)
		{
			if ($params->get('jsonapi_enabled') != 1)
			{
				$params->set('jsonapi_enabled', 1);

				$this->saveComponentParameters('com_akeeba', $params);
			}
		}

		// APIv1: jsonapi_enabled (AB 7.0 to 7.3) or frontend_enable (AB 3, 4, 5, 6) must be 1
		$version = $this->getComponentVersion();

		if (version_compare($version, '6.999.999', 'lt'))
		{
			if ($params->get('frontend_enable') != 1)
			{
				$params->set('frontend_enable', 1);

				$this->saveComponentParameters('com_akeeba', $params);
			}
		}
		elseif ($params->get('jsonapi_enabled') != 1)
		{
			$params->set('jsonapi_enabled', 1);

			$this->saveComponentParameters('com_akeeba', $params);
		}
	}

	private function getDecodedSecret(): ?string
	{
		$params = \JComponentHelper::getParams('com_akeeba');
		$secret = $params->get('frontend_secret_word', null);

		// No secret set
		if (empty($secret))
		{
			return null;
		}

		// A secret is set, and it's under 12 characters. Not enough space for encryption. Return verbatim.
		if (strlen($secret) < 12)
		{
			return $secret;
		}

		// If the secret does not have the encryption prefix, return verbatim.
		$prefix = substr($secret, 0, 12);

		if (!in_array($prefix, ['###AES128###', '###CTR128###']))
		{
			return $secret;
		}

		// Do I have the correct engine location?
		$engineRoot   = JPATH_ADMINISTRATOR . '/components/com_akeeba/BackupEngine';
		$platformRoot = JPATH_ADMINISTRATOR . '/components/com_akeeba/BackupPlatform/Joomla3x';

		// Yeah, well, no idea what is going on. Let me create a new secret key instead.
		if (!@is_dir($engineRoot))
		{
			return null;
		}

		// Necessary defines for Akeeba Engine
		if (!defined('AKEEBAENGINE'))
		{
			define('AKEEBAENGINE', 1);
			define('AKEEBAROOT', $engineRoot);
		}

		if (!defined('AKEEBA_BACKUP_ORIGIN'))
		{
			define('AKEEBA_BACKUP_ORIGIN', 'json');
		}

		// Make sure we have a profile set throughout the component's lifetime
		$app        = \JFactory::getApplication();
		$profile_id = $app->getSession()->get('akeebabackup.profile', null);

		if (is_null($profile_id))
		{
			$app->getSession()->set('akeebabackup.profile', 1);
		}

		// Load Akeeba Engine
		require_once AKEEBAROOT . '/Factory.php';

		// Tell the Akeeba Engine where to load the platform from
		Platform::addPlatform('joomla', $platformRoot);

		// Load the configuration
		$akeebaEngineConfig = Factory::getConfiguration();

		Platform::getInstance()->load_configuration();

		unset($akeebaEngineConfig);

		// Decrypt the encrypted setting
		return Factory::getSecureSettings()->decryptSettings($secret) ?: '';
	}

	private function createSecret(): ?string
	{
		$newSecret = \JUserHelper::genRandomPassword(32);
		$params    = \JComponentHelper::getParams('com_akeeba');
		$params->set('frontend_secret_word', $newSecret);

		self::saveComponentParameters('com_akeeba', $params);

		return $newSecret;
	}

}