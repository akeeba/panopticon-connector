<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector;

defined('_JEXEC') || die;

class AcceptHeaderMatch
{
	private $allowed;

	public function __construct(string $allowed)
	{
		$this->allowed = $allowed;
	}

	public function __invoke(string $accept): bool
	{
		$accept = explode(',', $accept);
		$accept = array_map('trim', $accept);
		$accept = array_map(function (string $mime): string {
			if (strpos($mime, ';') === false)
			{
				return $mime;
			}

			[$mimeType, $priority] = explode(';', $mime);

			return trim($mimeType);
		}, $accept);
		$accept = array_filter($accept);

		[$type, $subtype] = explode('/', $this->allowed);
		$fuzzyAllowed = $type . '/*';

		return in_array($this->allowed, $accept)
			|| in_array($fuzzyAllowed, $accept)
			|| in_array('*/*', $accept);
	}

}