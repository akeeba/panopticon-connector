<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;

defined('_JEXEC') || die;

class ExtensionsUpdatesRefresh extends AbstractController
{
	public function __invoke(\JInput $input): object
	{
		$app = \JFactory::getApplication();

		// Load com_installer language and model
		$app->getLanguage()
			->load('com_installer', JPATH_ADMINISTRATOR);

		if (!class_exists(\InstallerModelUpdate::class))
		{
			require_once JPATH_ADMINISTRATOR . '/components/com_installer/models/update.php';
		}

		/** @var \InstallerModelUpdate $model */
		$model = \JModelLegacy::getInstance('Update', 'InstallerModel', ['ignore_request' => true]);

		// Get the updates caching duration.
		$params       = \JComponentHelper::getComponent('com_installer')->getParams();
		$cacheTimeout = 3600 * ((int) $params->get('cachetimeout', 6));

		// Get the minimum stability.
		$minimumStability = (int) $params->get('minimum_stability', \JUpdater::STABILITY_STABLE);

		// Purge the table before checking again?
		$force = $input->getInt('force', 0);

		if ($force === 1)
		{
			$model->purge();
		}

		$model->findUpdates(0, $cacheTimeout, $minimumStability);

		return new \stdClass();
	}
}