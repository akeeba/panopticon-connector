<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;

use Akeeba\PanopticonConnector\Model\CoreUpdateModel;

defined('_JEXEC') || die;

class CoreUpdatePostupdate extends AbstractController
{
	public function __invoke(\JInput $input): object
	{
		$basename = $input->get('basename', null, 'raw');

		if (strpos($basename, DIRECTORY_SEPARATOR) !== false || strpos($basename, '/') !== false)
		{
			$basename = basename($basename);
		}

		$options['format']    = '{DATE}\t{TIME}\t{LEVEL}\t{CODE}\t{MESSAGE}';
		$options['text_file'] = 'joomla_update.php';
		\JLog::addLogger($options, \JLog::INFO, ['Update', 'databasequery', 'jerror']);

		try
		{
			\JFactory::getLanguage()->load('com_joomlaupdate', JPATH_ADMINISTRATOR);
			\JLog::add(\JText::_('COM_JOOMLAUPDATE_UPDATE_LOG_FINALISE'), \JLog::INFO, 'Update');
		}
		catch (\RuntimeException $exception)
		{
			// Informational log only
		}

		/** @var CoreUpdateModel $model */
		$model = new CoreUpdateModel(['ignore_request' => true]);

		$model->finaliseUpgrade();

		\JFactory::getApplication()->setUserState('com_joomlaupdate.file', $basename);
		$model->cleanUp();

		return new \stdClass();
	}
}