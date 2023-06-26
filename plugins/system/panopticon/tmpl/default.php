<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

defined('_JEXEC') || die;

/** @var string $secret The site's secret key */

$app     = \Joomla\CMS\Factory::getApplication();
$sef     = $app->get('sef', 0) == 1;
$rewrite = $app->get('sef_rewrite', 0) == 1;

?>

<div class="well">
	<h3>
		<?= JText::_('PLG_SYSTEM_PANOPTICON_CONNECTION_INFO') ?>
	</h3>
	<p>
		<?= JText::_('PLG_SYSTEM_PANOPTICON_CONNECTION_HEAD') ?>
	</p>
	<table class="table">
		<tbody>
		<tr>
			<th scope="row">
				<?= JText::_('PLG_SYSTEM_PANOPTICON_ENDPOINT') ?>
			</th>
			<td>
				<?php if ($sef && $rewrite): ?>
					<code><?= htmlentities(\Joomla\CMS\Uri\Uri::root()) ?>panopticon_api</code>
				<?php elseif ($sef): ?>
					<code><?= htmlentities(\Joomla\CMS\Uri\Uri::root()) ?>index.php/panopticon_api</code>
				<?php else: ?>
					<code><?= htmlentities(\Joomla\CMS\Uri\Uri::root()) ?>index.php?/panopticon_api</code>
				<?php endif ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<?= JText::_('PLG_SYSTEM_PANOPTICON_TOKEN') ?>
			</th>
			<td>
				<code><?= htmlentities($secret) ?></code>
			</td>
		</tr>
		</tbody>
	</table>

</div>