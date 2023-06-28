<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;

defined('_JEXEC') || die;

use Akeeba\AdminTools\Site\Model\Scans;
use Akeeba\PanopticonConnector\Controller\Mixit\AdminToolsTrait;

class AdmintoolsScannerStep extends AbstractController
{
	use AdminToolsTrait;

	public function __invoke(\JInput $input): object
	{
		/** @var Scans $model */
		$container = $this->getAdminToolsContainer();
		$model     = $container->factory->model('Scans')->tmpInstance();

		// Get the session data of the PHP File Change Scanner
		$sessionData = $input->post->getRaw('session');

		if (empty($sessionData))
		{
			throw new \RuntimeException('You need to pass the session data');
		}

		$this->setScannerState($sessionData);

		// Load the model and step the scan
		$result = (object) $model->stepScan();

		$result->session = $this->getScannerState();
		$result->id      = $result->id ?? $result->session['com_admintools.filescanner.scanID'] ?? 0;

		return $this->asSingleItem('admintools', $result);
	}
}