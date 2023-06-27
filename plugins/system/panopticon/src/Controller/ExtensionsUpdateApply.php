<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;


use Joomla\CMS\Pagination\Pagination;

defined('_JEXEC') || die;

class ExtensionsUpdateApply extends AbstractController
{
	public function __invoke(\JInput $input): object
	{
		$app = \JFactory::getApplication();
		// Load com_installer language
		$app->getLanguage()
			->load('com_installer', JPATH_ADMINISTRATOR);

		// Get the cache timeout and minimum stability.
		$params           = \JComponentHelper::getComponent('com_installer')->getParams();
		$minimumStability = (int) $params->get('minimum_stability', \JUpdater::STABILITY_STABLE);
		$cacheTimeout     = 3600 * ((int) $params->get('cachetimeout', 6));

		// Get the extension IDs to update
		$extensionIDs = (array) $input->get('eid', [], 'int');
		$extensionIDs = array_filter($extensionIDs);

		// Fail on empty array
		if (empty($extensionIDs))
		{
			throw new \RuntimeException('No extensions to update', 400);
		}

		$reportedResults = [];

		foreach ($extensionIDs as $eid)
		{
			/**
			 * IMPORTANT! Always clear the Installer cache and create a new UpdateModel instance.
			 *
			 * There is a bug in Joomla when updating heterogeneous extension types, e.g. packages, modules, and plugins
			 * with the same UpdateModel instance. The UpdateModel gets a static Installer instance which caches the
			 * adapter type the first time it's used.
			 *
			 * If a subsequent update is for a different extension type the WRONG installer adapter is used, causing the
			 * pre-/post-installation scripts to not run, or throw PHP type errors.
			 *
			 * By resetting the Installer adapter and creating a fresh UpdateModel instance we work around this bug.
			 *
			 * PS: For anyone complaining that I should instead report it to the Joomla project, so it can be fixed
			 *     for everyone: I have, since October 2022: https://github.com/joomla/joomla-cms/issues/38956
			 *     I am not the only one hitting this problem, see https://github.com/joomla/joomla-cms/issues/39148
			 *     So, um, thank you for being condescending; you're part of the problem.
			 */
			$refClass = new \ReflectionClass(\JInstaller::class);
			$refProp  = $refClass->getProperty('instances');
			$refProp->setAccessible(true);
			$refProp->setValue([]);

			if (!class_exists(\InstallerModelUpdate::class))
			{
				require_once JPATH_ADMINISTRATOR . '/components/com_installer/models/update.php';
			}

			/** @var \InstallerModelUpdate $model */
			$model = \JModelLegacy::getInstance('Update', 'InstallerModel', ['ignore_request' => true]);

			// Get the updates for the extension
			$model->setState('filter.extension_id', $eid);
			$updates = $model->getItems();

			if (empty($updates))
			{
				$app->enqueueMessage('No updates', 'warning');

				$reportedResults[$eid] = (object) [
					'id'       => $eid,
					'status'   => false,
					'messages' => $app->getMessageQueue(true),
				];

				continue;
			}

			$update = array_pop($updates);

			$model->update([$update->update_id], $minimumStability);

			$reportedResults[$eid] = (object) [
				'id'       => $eid,
				'status'   => $model->getState('result'),
				'messages' => $app->getMessageQueue(true),
			];
		}

		$pagination = new Pagination(count($reportedResults), 0, 10 * ceil(count($reportedResults) / 10));

		return $this->asItemsList('updates', $reportedResults, $pagination);
	}
}