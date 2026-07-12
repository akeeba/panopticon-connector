<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

/**
 * Minimal stand-ins for the handful of Joomla Framework symbols that
 * `Akeeba\Plugin\Console\Panopticon\Command\GetToken` needs to be *loadable* (its class declaration
 * `extends`/`implements`/`use`s them, which PHP resolves eagerly when the file is parsed) — not to be
 * *usable*. Only `GetToken::computeApiToken()` (a plain `public static` method using nothing but
 * scalar types) is exercised by ApiTokenTest; every other method on GetToken references further
 * Joomla/Symfony types in its own signature, but those are resolved lazily (only when actually
 * called), so they are deliberately left unstubbed here.
 *
 * Loaded unconditionally from tests/unit/bootstrap.php, but guarded so it never clobbers the real
 * classes if this suite is ever run alongside a full Joomla Framework.
 */

namespace Joomla\Console\Command {

	if (!class_exists(AbstractCommand::class, false))
	{
		// Deliberately not abstract, and with no declared methods: GetToken's own configure() and
		// doExecute() should be treated as brand new methods rather than overrides, so there is no
		// method-signature compatibility to satisfy here.
		class AbstractCommand
		{
		}
	}
}

namespace Joomla\Database {

	if (!interface_exists(DatabaseAwareInterface::class, false))
	{
		// Deliberately empty: the real interface declares getDatabase()/setDatabase(), but nothing in
		// this suite calls them, so there is nothing to implement.
		interface DatabaseAwareInterface
		{
		}
	}

	if (!trait_exists(DatabaseAwareTrait::class, false))
	{
		// Deliberately empty for the same reason as DatabaseAwareInterface above.
		trait DatabaseAwareTrait
		{
		}
	}
}
