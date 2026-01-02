<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Plugin\Console\Panopticon\Command\MixIt;

defined('_JEXEC') || die;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Trait ConfigureIOTrait
 *
 * This trait is used to configure the Symfony IO object for a command class.
 *
 * @since   1.0.3
 */
trait ConfigureIOTrait
{
	/**
	 * @var   SymfonyStyle
	 * @since 1.0.3
	 */
	private $ioStyle;

	/**
	 * @var   InputInterface
	 * @since 1.0.3
	 */
	private $cliInput;

	/**
	 * Configure the IO.
	 *
	 * @param   InputInterface   $input   The input to inject into the command.
	 * @param   OutputInterface  $output  The output to inject into the command.
	 *
	 * @return  void
	 *
	 * @since   1.0.3
	 */
	private function configureSymfonyIO(InputInterface $input, OutputInterface $output)
	{
		$this->cliInput = $input;
		$this->ioStyle  = new SymfonyStyle($input, $output);
	}

}