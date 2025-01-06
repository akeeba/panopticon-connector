<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/** @var \Akeeba\Component\Panopticon\Administrator\View\Welcome\HtmlView $this */

$user = Factory::getApplication()->getIdentity();

if (!$user->authorise('core.manage') || $this->isWebServicesPluginEnabled)
{
	return;
}

?>
<div class="alert alert-danger">
	<h3 class="alert-heading">
		<?= Text::_('COM_PANOPTICON_WELCOME_ERR_NO_WEBSERVICES_PLG_TITLE') ?>
	</h3>
	<p>
		<?= Text::_('COM_PANOPTICON_WELCOME_ERR_NO_WEBSERVICES_PLG_DETAILS') ?>
	</p>
	<p>
		<a
			class="btn btn-primary text-light"
			href="index.php?option=com_plugins&view=plugins&filter[folder]=webservices&filter[element]=panopticon&filter[enabled]=0&filter[access]=&filter[search]=">
			<span class="icon-eye-open" aria-hidden="true"></span>
			<?= Text::_('COM_PANOPTICON_WELCOME_ERR_COMMON_ACTION') ?>
		</a>
	</p>
</div>
