<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Model;


use Joomla\CMS\Filesystem\File;

defined('_JEXEC') || die;

if (!class_exists(\JoomlaupdateModelDefault::class))
{
	require_once JPATH_ADMINISTRATOR . '/components/com_joomlaupdate/models/default.php';
}

if (!defined('JPATH_COMPONENT_ADMINISTRATOR'))
{
	define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_joomlaupdate/');
}

class CoreUpdateModel extends \JoomlaupdateModelDefault
{
	public function getUpdatePackageURL(): ?string
	{
		$updateInfo = $this->getUpdateInformation();
		$packageURL = trim($updateInfo['object']->downloadurl->_data);
		$sources    = $updateInfo['object']->get('downloadSources', array());

		// We have to manually follow the redirects here so we set the option to false.
		$httpOptions = new \JRegistry;
		$httpOptions->set('follow_location', false);

		try
		{
			$head = \JHttpFactory::getHttp($httpOptions)->head($packageURL);
		}
		catch (\RuntimeException $e)
		{
			return null;
		}

		// Follow the Location headers until the actual download URL is known
		while (isset($head->headers['location']))
		{
			$packageURL = $head->headers['location'];

			try
			{
				$head = \JHttpFactory::getHttp($httpOptions)->head($packageURL);
			}
			catch (\RuntimeException $e)
			{
				return null;
			}
		}

		return $packageURL;
	}

	public function removeExtractPasswordFile()
	{
		$basePath = JPATH_ADMINISTRATOR . '/components/com_joomlaupdate';

		if (File::exists($basePath . '/update.php'))
		{
			File::delete($basePath . '/update.php');
		}

		if (File::exists($basePath . '/restoration.php'))
		{
			File::delete($basePath . '/restoration.php');
		}
	}

	public function cleanUp()
	{
		$this->removeExtractPasswordFile();

		parent::cleanUp();
	}


}