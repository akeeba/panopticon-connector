<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Panopticon\Api\Model;

defined('_JEXEC') || die;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;

class TemplatechangedModel extends ListModel
{
	public function __construct($config = [], MVCFactoryInterface $factory = null)
	{
		$config['filter_fields'] = $config['filter_fields'] ?? [
			'client',
		];

		parent::__construct($config, $factory);
	}


	protected function getListQuery()
	{
		$db     = method_exists($this, 'getDatabase') ? $this->getDatabase() : $this->getDbo();
		$client = (int) $this->getState('filter.client_id', 0);

		$subQuery = $db->getQuery(true)
			->select('1')
			->from($db->quoteName('#__template_styles', 's'))
			->where([
				$db->quoteName('s.template') . '=' . $db->quoteName('o.template'),
				$db->quoteName('s.client_id') . '=' . $client,
				$db->quoteName('s.home') . '= 1',
			]);

		return $db->getQuery(true)
			->select($db->quoteName(
				[
					'id',
					'template',
					'extension_id',
					'state',
					'action',
					'created_date',
					'modified_date',
				]
			))
			->from($db->quoteName('#__template_overrides', 'o'))
			->where('EXISTS(' . $subQuery . ')');
	}

}