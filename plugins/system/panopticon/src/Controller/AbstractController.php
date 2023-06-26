<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace Akeeba\PanopticonConnector\Controller;

defined('_JEXEC') || die;

use Joomla\CMS\Input\Input;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Uri\Uri;

abstract class AbstractController
{
	/**
	 * Executes this controller
	 *
	 * @param   Input  $input
	 *
	 * @return  object
	 * @since   1.0.0
	 */
	abstract public function __invoke(Input $input): object;

	/**
	 * Express a single item as an API return object
	 *
	 * @param   string        $type  Data type, e.g. 'thingie'
	 * @param   array|object  $data  The data to return
	 *
	 * @return  object  object ready to send.
	 * @since   1.0.0
	 */
	protected function asSingleItem(string $type, $data): object
	{
		$data = (object) $data;

		return (object) [
			'links' => [
				'self' => Uri::getInstance()->toString(),
			],
			'data'  => [
				'type'       => $type,
				'id'         => $data->id ?? 0,
				'attributes' => (object) $data,
			],
		];
	}

	/**
	 * Express a list of items as an API return object
	 *
	 * @param   string      $type        Data type of the single item, e.g. 'thingie'
	 * @param   array       $items       Array of items. Each item should be an JSON-serializable object or string
	 * @param   Pagination  $pagination  Pagination object for the collection of items
	 *
	 * @return  object
	 * @since   1.0.0
	 */
	protected function asItemsList(string $type, array $items, Pagination $pagination)
	{
		$out = (object) [
			'links' => (object) [
				'self' => Uri::getInstance()->toString(),
			],
			'data'  => [],
			'meta'  => (object) [
				'total-pages' => $pagination->pagesTotal,
			],
		];

		foreach ($items as $item)
		{
			$item = (object) $item;

			$out->data[] = (object) [
				'type'       => $type,
				'id'         => $item->id ?? 0,
				'attributes' => $item,
			];
		}

		if ($pagination->pagesTotal > 1)
		{
			$uri = clone Uri::getInstance();
			$uri->setVar('pages[offset]', 0);

			$out->links->first = $uri->toString();
		}

		if ($pagination->pagesCurrent > 1)
		{
			$uri = clone Uri::getInstance();
			$uri->setVar('pages[offset]', max(0, $pagination->limitstart - $pagination->limit));

			$out->links->previous = $uri->toString();
		}

		if ($pagination->pagesCurrent > 1 && $pagination->pagesCurrent < $pagination->pagesTotal)
		{
			$uri = clone Uri::getInstance();
			$uri->setVar('pages[offset]', min(($pagination->pagesTotal - 1) * $pagination->limitstart, $pagination->limitstart + $pagination->limit));

			$out->links->next = $uri->toString();
		}

		if ($pagination->pagesTotal > 1)
		{
			$uri = clone Uri::getInstance();
			$uri->setVar('pages[offset]', ($pagination->pagesTotal - 1) * $pagination->limitstart);

			$out->links->last = $uri->toString();
		}

		return $out;
	}
}