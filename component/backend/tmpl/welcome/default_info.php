<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/** @var \Akeeba\Component\Panopticon\Administrator\View\Welcome\HtmlView $this */

$user = Factory::getApplication()->getIdentity();

if (
	(!$this->isAllowedUser && !$user->authorise('core.manage'))
	|| !$this->isTokenAuthPluginEnabled
	|| !$this->isWebServicesPluginEnabled
	|| !$this->isUserTokenPluginEnabled
	|| !$this->hasToken
)
{
	return;
}

$this->document->getWebAssetManager()
	->registerAndUseScript('plg_user_token.token', 'plg_user_token/token.js', [], ['defer' => true], ['core']);

?>
<div class="card">
	<h3 class="card-header bg-secondary text-white">
		<?= Text::_('COM_PANOPTICON_WELCOME_INFO_HEAD') ?>
	</h3>
	<div class="card-body">
		<p class="text-muted">
			<?= Text::_('COM_PANOPTICON_WELCOME_INFO_DETAILS') ?>
		</p>
		<table class="table">
			<tbody>
			<tr>
				<th scope="row">
					<?= Text::_('COM_PANOPTICON_WELCOME_INFO_ENDPOINT') ?>
				</th>
				<td>
					<code><?= htmlentities(\Joomla\CMS\Uri\Uri::root()) ?>api</code>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?= Text::_('COM_PANOPTICON_WELCOME_INFO_API_TOKEN') ?>
				</th>
				<td>
					<code><?= htmlentities($this->getApiToken()) ?></code>
				</td>
			</tr>
			</tbody>
		</table>
	</div>
</div>
