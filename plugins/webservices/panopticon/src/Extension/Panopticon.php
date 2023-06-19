<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Plugin\WebServices\Panopticon\Extension;

defined('_JEXEC') || die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\ApiRouter;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Router\Route;

class Panopticon extends CMSPlugin implements SubscriberInterface
{
	private const API_PREFIX = 'v1/panopticon/';

	protected $allowLegacyListeners = false;

	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return [
			'onBeforeApiRoute' => 'registerRoutes',
		];
	}

	public function registerRoutes(Event $event): void
	{
		/** @var ApiRouter $router */
		[$router] = $event->getArguments();

		$defaults = [
			'component' => 'com_panopticon',
		];

		$routes = [];

		$routes[] = new Route(
			['GET'],
			self::API_PREFIX . 'extensions',
			'extensions.displayList',
			[],
			$defaults
		);

		// TODO Determine if we should keep this
		$routes[] = new Route(
			['GET'],
			self::API_PREFIX . 'extension/:id',
			'extensions.displayItem',
			[
				'id' => '(\d+)',
			],
			$defaults
		);

		// TODO Determine if we should keep this
		$routes[] = new Route(
			['GET'],
			self::API_PREFIX . 'extension/:element',
			'extensions.displayItem',
			[
				'element' => '([0-9a-z_\.-]+)',
			],
			$defaults
		);

		// TODO Determine if we should keep this
		$routes[] = new Route(
			['POST'],
			self::API_PREFIX . 'updates',
			'updates.refresh',
			[],
			$defaults
		);

		$routes[] = new Route(
			['POST'],
			self::API_PREFIX . 'update',
			'updates.update',
			[],
			$defaults
		);

		// TODO Determine if we should keep this
		$routes[] = new Route(
			['GET'],
			self::API_PREFIX . 'updatesites',
			'updatesites.displayList',
			[],
			$defaults
		);

		// TODO Determine if we should keep this
		$routes[] = new Route(
			['GET'],
			self::API_PREFIX . 'updatesite/:id',
			'updatesites.displayItem',
			['id' => '(\d+)'],
			$defaults
		);

		$routes[] = new Route(
			['PATCH'],
			self::API_PREFIX . 'updatesite/:id',
			'updatesites.edit',
			['id' => '(\d+)'],
			$defaults
		);

		// TODO Determine if we should keep this
		$routes[] = new Route(
			['DELETE'],
			self::API_PREFIX . 'updatesite/:id',
			'updatesites.delete',
			['id' => '(\d+)'],
			$defaults
		);

		// TODO Determine if we should keep this
		$routes[] = new Route(
			['POST'],
			self::API_PREFIX . 'updatesites/rebuild',
			'updatesites.rebuild',
			[],
			$defaults
		);

		$routes[] = new Route(
			['GET'],
			self::API_PREFIX . 'core/update',
			'core.getupdate',
			[],
			$defaults
		);

		$routes[] = new Route(
			['POST'],
			self::API_PREFIX . 'core/update',
			'core.applyUpdateSite',
			[],
			$defaults
		);

		$routes[] = new Route(
			['POST'],
			self::API_PREFIX . 'core/update/download',
			'core.downloadUpdate',
			[],
			$defaults
		);

		$routes[] = new Route(
			['POST'],
			self::API_PREFIX . 'core/update/activate',
			'core.activateExtract',
			[],
			$defaults
		);

		// TODO Determine if we should keep this
		$routes[] = new Route(
			['POST'],
			self::API_PREFIX . 'core/update/disable',
			'core.disableExtract',
			[],
			$defaults
		);

		$routes[] = new Route(
			['POST'],
			self::API_PREFIX . 'core/update/postupdate',
			'core.postUpdate',
			[],
			$defaults
		);

		// TODO 📋Consumer code in planning stage
		$routes[] = new Route(
			['GET'],
			self::API_PREFIX . 'template/overrides/changed',
			'templatechanged.displayList',
			[],
			$defaults
		);

		$routes[] = new Route(
			['GET'],
			self::API_PREFIX . 'akeebabackup/info',
			'backup.version',
			[],
			$defaults
		);

		$router->addRoutes($routes);
	}
}