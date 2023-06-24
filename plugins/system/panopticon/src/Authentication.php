<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector;

defined('_JEXEC') || die;

use Joomla\CMS\Factory;

class Authentication
{
	public function getSecret(): string
	{
		static $secret = null;

		$app = Factory::getApplication();

		$secret = $secret ?: strtolower(hash_hmac('sha256', implode(':', [
			JPATH_SITE,
			$app->get('host'),
			$app->get('user'),
			$app->get('password'),
			$app->get('db'),
			$app->get('dbprefix'),
		]), $app->get('secret')));

		return $secret;
	}

	public function isAuthenticated(): bool
	{
		$input = Factory::getApplication()->input;

		/**
		 * First look for an HTTP Authorization header with the following format:
		 * Authorization: Bearer <token>
		 * Do keep in mind that Bearer is **case-sensitive**. Whitespace between Bearer and the
		 * token, as well as any whitespace following the token is discarded.
		 */
		$authHeader  = $input->server->get('HTTP_AUTHORIZATION', '', 'string');
		$tokenString = '';

		// Apache specific fixes. See https://github.com/symfony/symfony/issues/19693
		if (
			empty($authHeader) && \PHP_SAPI === 'apache2handler'
			&& function_exists('apache_request_headers') && apache_request_headers() !== false
		)
		{
			$apacheHeaders = array_change_key_case(apache_request_headers(), CASE_LOWER);

			if (array_key_exists('authorization', $apacheHeaders))
			{
				$authHeader = $apacheHeaders['authorization'];
			}
		}

		if (substr($authHeader, 0, 7) == 'Bearer ')
		{
			$parts       = explode(' ', $authHeader, 2);
			$tokenString = trim($parts[1]);
		}

		if (empty($tokenString))
		{
			$tokenString = $input->server->get('HTTP_X_JOOMLA_TOKEN', '', 'string');
		}

		return hash_equals($this->getSecret(), $tokenString);
	}
}