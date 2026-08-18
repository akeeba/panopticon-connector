<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Mixin;

defined('_JEXEC') || die;

/**
 * Reads the status code and body of a Joomla\Http\Response object across Joomla versions.
 *
 * Joomla 5.4 replaced the legacy public $code / $body properties with the
 * getStatusCode() / getBody() accessor methods. Older Joomla (4.x and 5.0–5.3)
 * only has the public properties; on Joomla 5.4+ (including 6.x) those
 * properties are no longer populated, so they must not be accessed directly.
 */
trait HttpResponseCompatibilityTrait
{
	private function getResponseStatusCode(object $response): int
	{
		if (version_compare(JVERSION, '5.4.0', 'lt'))
		{
			return (int) $response->code;
		}

		return (int) $response->getStatusCode();
	}

	private function getResponseBody(object $response): string
	{
		if (version_compare(JVERSION, '5.4.0', 'lt'))
		{
			return (string) $response->body;
		}

		return (string) $response->getBody();
	}
}
