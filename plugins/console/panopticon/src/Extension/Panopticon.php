<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Plugin\Console\Panopticon\Extension;

defined('_JEXEC') || die;

use Akeeba\Plugin\Console\Panopticon\Command\CommandFactoryInterface;
use Akeeba\Plugin\Console\Panopticon\Command\GetToken;
use Joomla\Application\ApplicationEvents;
use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Throwable;

/**
 * Registers CLI commands for the Akeeba Panopticon Connector if it is installed and enabled.
 *
 * @since  1.0.3
 */
class Panopticon extends CMSPlugin implements SubscriberInterface
{
	/**
	 * The command classes to register with the Joomla CLI application.
	 *
	 * @since  1.0.3
	 */
	private const COMMAND_CLASSES = [
		GetToken::class,
	];

	/** @inheritdoc */
	protected $autoloadLanguage = true;

	/**
	 * The command factory service
	 *
	 * @var   CommandFactoryInterface
	 * @since 1.0.3
	 */
	protected $commandFactory;

	/**
	 * Constructor method for the class.
	 *
	 * @param   mixed &$subject  A reference to the subject.
	 * @param   array  $config   Optional configuration settings.
	 *
	 * @return  void
	 * @since   1.0.3
	 */
	public function __construct(&$subject, $config = [])
	{
		parent::__construct($subject, $config);

		$this->commandFactory = $config['panopticonCLICommandFactory'];
	}

	/**
	 * Returns the subscribed events and their corresponding event handlers.
	 *
	 * @return  array  An associative array where the keys are the event names and the values are the event handler
	 *               method names.
	 *
	 * @since   1.0.3
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			ApplicationEvents::BEFORE_EXECUTE => 'registerCLICommands',
		];
	}

	/**
	 * Registers the CLI commands for the Akeeba Panopticon Connector.
	 *
	 * @param   Event  $event  The event object.
	 *
	 * @return  void
	 *
	 * @since   1.0.3
	 */
	public function registerCLICommands(Event $event)
	{
		// Only register CLI commands if the Akeeba Panopticon Connector is installed and enabled
		try
		{
			if (!ComponentHelper::isEnabled('com_panopticon'))
			{
				return;
			}
		}
		catch (Throwable $e)
		{
			return;
		}

		/** @var ConsoleApplication $app */
		$app = $event->getApplication();

		foreach (self::COMMAND_CLASSES as $class)
		{
			$app->addCommand(
				$this->commandFactory->getCLICommand($class)
			);
		}
	}
}