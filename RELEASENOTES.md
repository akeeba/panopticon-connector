## Akeeba Panopticon Connector for Joomla! 4 & 5

This is the connector component for [Akeeba Panopticon](https://github.com/akeeba/panopticon), our self-hosted site monitoring software. You need to install it on your site to be able to monitor it with Akeeba Panopticon.

This connector is compatible with:

* Joomla! 4.0, 4.1, 4.3, 4.3, 4.4, and 5.0.
* PHP versions 7.2, 7.3, 7.4, 8.0, 8.1, 8.2, and 8.3

While the connector supports a wide range of Joomla! and PHP versions, each version of Joomla! itself only works with a subset of PHP versions. We cannot possibly support using Joomla! with a version of PHP it does not support. 

ℹ️ If you have a Joomla! 3 site please [look at the Joomla 3 connector's repository](https://github.com/akeeba/panopticon_connector_j3/releases/latest) instead.

## Important note about the Joomla! API

Akeeba Panopticon Connector uses the Joomla! API application (the `/api` folder on your site). Around February 2023 there was a lot of unnecessary panic, leading some people to disable access to this folder through their `.htaccess` file. If you did that, you need to undo that change to re-enable access to the `/api` folder.

When Akeeba Panopticon connects to sites running on Joomla! 4 and later it makes use of code provided not only by its own connector, but also core Joomla! plugins in the `webservices` and `api-authentication` folders. Please make sure that the following plugins are enabled on your site:

* `Web Services - Panopticon` (provided by this connector).
* `Web Services - Installer` (provided by Joomla!).
* `API Authentication - Web Services Joomla Token` (required for secure, token-based authentication to the Joomla! API).

If any of these plugins are disabled, _or if its Access is set to anything other than Public_, you will run into connection problems with your site. The connector itself will try to detect and report these issues, but there are cases it might fail to identify the problem.

## New in this version

* Fix: Linking Panopticon to Akeeba Backup may fail if the JSON API isn't already active
