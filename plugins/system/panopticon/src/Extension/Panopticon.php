<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Plugin\System\Panopticon\Extension;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Throwable;

defined('_JEXEC') || die;

/**
 * Automatic snitching of plugins causing PHP Errors in the Joomla! API application.
 *
 * Obligatory music video: https://www.youtube.com/watch?v=TSffz_bl6zo (you're welcome!)
 *
 * I had grown tired of people asking *me* to help them figure out why *someone else's* code was breaking their site's
 * API application when they were trying to link Panopticon with it, so I wrote this piece of code.
 *
 * This plugin will automatically intercept Error exceptions when it's a Panopticon request, it will identify the plugin
 * involved and print out its name, version, and developer contact information. The users can then contact the developer
 * with the broken plugin and let them know they need to fix their software. Why didn't I think of this earlier?
 *
 * @since 1.0.6
 */
class Panopticon extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
	use DatabaseAwareTrait;

	protected $allowLegacyListeners = false;

	public static function getSubscribedEvents(): array
	{
		return [
			'onError' => 'onError',
		];
	}

	/**
	 * Handles the Joomla! API application's error event.
	 *
	 * @param   Event  $e  The error event object.
	 *
	 * @return  void
	 * @since   1.0.6
	 */
	public function onError(Event $e)
	{
		/**
		 * @var  Throwable       $error
		 * @var  CMSApplication  $app
		 */
		[$error, $app] = array_values($e->getArguments());

		// Make sure this is the API application
		if (!$app instanceof CMSApplication || !$app->isClient('api'))
		{
			return;
		}

		// Make sure we have a fatal error
		if (!$error instanceof \Error)
		{
			return;
		}

		// Make sure this is a request coming from Panopticon
		$userAgent = $app->getInput()->server->getString('HTTP_USER_AGENT');

		if (strpos($userAgent, 'panopticon/') !== 0)
		{
			return;
		}

		// Set up the HTTP headers
		header('Content-Type: text/plain');
		header('X-Panopticon-Debug: enabled');
		header('HTTP/1.1 500 Internal Server Error');

		// Detect possibly offending plugins
		$temp    = $error;
		$plugins = [];

		while ($temp !== null)
		{
			$plugins[] = $this->detectPluginsInError($temp);

			$temp = $temp->getPrevious();
		}

		$plugins = array_filter($plugins);

		// Show header;
		$jVersion = JVERSION;
		$phpVersion = PHP_VERSION;

		echo <<< TEXT
 mmmmm  m    m mmmmm         mmmmmm                            
 #   "# #    # #   "#        #       m mm   m mm   mmm    m mm 
 #mmm#" #mmmm# #mmm#"        #mmmmm  #"  "  #"  " #" "#   #"  "
 #      #    # #             #       #      #     #   #   #    
 #      #    # #             #mmmmm  #      #     "#m#"   #    

========================================================================================================================

Your site experienced a PHP error while trying to serve the API application. This usually means that a third party
plugin is written incorrectly, breaking Joomla's API application. In other words, the plugin in question is incompatible
with Joomla! $jVersion and/or PHP $phpVersion.

TEXT;

		if (!empty($plugins))
		{
			echo <<< TEXT

The following plugin(s) have been automatically identified as involved in this error condition:


TEXT;
			echo implode(sprintf("\n%s\n", str_repeat('-', 120)), $plugins) . "\n";
		}
		else
		{
			echo <<< TEXT

We could not automatically identify the plugins involved in this error condition. We are either unable to parse the
debugging information, or the plugins involved are missing some information necessary to identify them. The detailed
error information below, however, can help a developer identify the offending plugin.

TEXT;
		}

		echo <<< TEXT

Please remember that you MUST contact the offending plugin's developer, NOT the developers of Akeeba Panopticon. We can
not fix problems in third party code. Remember to include this text in its entirety, *especially* the Detailed
Information below, when asking the plugin's developer for support.

========================================================================================================================

DETAILED INFORMATION
------------------------------------------------------------------------------------------------------------------------


TEXT;

		// Show debug backtrace
		/** @var \Error $error */
		while ($error !== null)
		{
			$file  = $this->replaceSiteRoot($error->getFile());
			$trace = $this->replaceSiteRoot($error->getTraceAsString());
			echo <<< TEXT
{$error->getMessage()}
in {$file}({$error->getLine()})

{$trace}

------------------------------------------------------------------------------------------------------------------------

TEXT;

			$error = $error->getPrevious();
		}

		$app->close(500);
	}

	/**
	 * Replaces the site root path in a given string with the placeholder '[SITE_ROOT]'.
	 *
	 * @param   string  $string  The input string to replace the site root path in.
	 *
	 * @return  string  The resulting string with the site root path replaced by '[SITE_ROOT]'.
	 * @since   1.1.5
	 */
	private function replaceSiteRoot(string $string): string
	{
		$siteRoot = JPATH_ROOT;

		if (empty($siteRoot) || $siteRoot === '/')
		{
			return $string;
		}

		return str_replace($siteRoot, '[SITE_ROOT]', $string);
	}

	/**
	 * Detects the plugin in which an error occurred.
	 *
	 * @param   Throwable  $e  The throwable object representing the error.
	 *
	 * @return  string|null  Returns a formatted string containing information about the plugin in which the error
	 *                       occurred. Returns `null` if the error does not belong to any plugin.
	 * @since   1.0.6
	 */
	private function detectPluginsInError(Throwable $e): ?string
	{
		$text = sprintf("#0 %s(%s)\n", $e->getFile(), $e->getLine());
		$text .= $e->getTraceAsString();
		$text = $this->replaceSiteRoot($text);

		$lines = explode("\n", $text);
		$folder = null;
		$plugin = null;

		foreach ($lines as $line)
		{
			$line = str_replace('\\', '/', $line);

			if (preg_match('@^#\d+\s+\[SITE_ROOT]/plugins/([a-z_.\-]+)/([^/]+)/.*@i', $line, $matches) === 0)
			{
				continue;
			}

			$folder = $matches[1];
			$plugin = $matches[2];

			$db = $this->getDatabase();
			$query = method_exists($db, 'createQuery') ? $db->createQuery() : $db->getQuery(true);
			$query
				->select($db->quoteName('manifest_cache'))
				->where([
					$db->quoteName('type') . ' = ' . $db->quote('plugin'),
					$db->quoteName('element') . ' = :plugin',
					$db->quoteName('folder') . ' = :folder',
				])
				->from($db->quoteName('#__extensions'))
				->bind(':plugin', $plugin)
				->bind(':folder', $folder);

			try
			{
				$manifest = $db->setQuery($query)->loadResult() ?: null;
			}
			catch (\Exception $e)
			{
				$manifest = null;
			}

			if (empty($manifest))
			{
				return null;
			}

			try
			{
				$manifest = json_decode($manifest, true);
			}
			catch (\Exception $e)
			{
				$manifest = null;
			}

			if (empty($manifest))
			{
				return null;
			}

			$lang = $this->getApplication()->getLanguage();
			$lang->load(
				sprintf('plg_%s_%s', $folder, $plugin),
				JPATH_ADMINISTRATOR,
			);

			// name, author, authorEmail, authorUrl, version
			$name        = $lang->_($manifest['name'] ?? sprintf('plg_%s_%s', $folder, $plugin));
			$version     = $manifest['version'] ?? '(no information)';
			$author      = $manifest['author'] ?? '(no information)';
			$authorEmail = $manifest['authorEmail'] ?? '(no information)';
			$authorUrl   = $manifest['authorUrl'] ?? '(no information)';

			return <<< TEXT
Plugin        : $name
Version       : $version
Folder        : $folder
Element       : $plugin
Author        : $author
Author Email  : $authorEmail
Author URL    : $authorUrl
TEXT;
		}

		return null;
	}
}