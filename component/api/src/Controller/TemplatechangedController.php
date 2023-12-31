<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\Component\Panopticon\Api\Controller;

defined('_JEXEC') || die;

use Joomla\CMS\MVC\Controller\ApiController;

class TemplatechangedController extends ApiController
{
	protected $contentType = 'templatechanged';

	protected $default_view = 'templatechanged';

	public function displayList()
	{
		$client = $this->input->getInt('client', 0);

		$this->modelState->set('filter.client_id', $client);

		return parent::displayList();
	}
}