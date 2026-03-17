## ℹ️ What is this?

This is the connector component for [Akeeba Panopticon](https://github.com/akeeba/panopticon), our self-hosted site monitoring software. You need to install it on your site to be able to monitor it with Akeeba Panopticon.

ℹ️ If you have a Joomla! 3 site please [look at the Joomla 3 connector's repository](https://github.com/akeeba/panopticon_connector_j3/releases/latest) instead.

`ℹ️ If you have a WordPress site please [look at the WordPress connector's repository](https://github.com/akeeba/panopticon-connector-wordpress/releases/latest) instead. 
`
## 🔎 Release highlights

* **✨ Remote extension installation**. Panopticon 2 will allow you to batch-install extensions across multiple sites. For this feature to work, you need to update to connector version 1.1.0 which provides the remote extension installation feature. 

## 🖥️ System Requirements

* Joomla! 4.0 to 6.1, inclusive.
* PHP versions 7.2 to 8.5, inclusive.

**Important notes on system requirements**

PHP version compatibility refers to our connector, not Joomla! itself. PHP 8.1 or later required for Joomla! 5, PHP 8.3 or later required for Joomla! 6.

Joomla! 6.1 is a beta version at the time of this writing. We expect the connector to work with the final version just fine, but we cannot guarantee it. If we discover any issues with the stable version of Joomla! 6.1, we will release a new version of the connector.

Future versions of the connector will drop support for Joomla 5.3 and earlier versions. We strongly advise you to upgrade to Joomla! 5.4 or 6.x as soon as possible.

## 🧑🏽‍💻 Important note about the Joomla! API

Akeeba Panopticon Connector uses the Joomla! API application (the `/api` folder on your site). Around February 2023 there was a lot of unnecessary panic, leading some people to disable access to this folder through their `.htaccess` file. If you did that, you need to undo that change to re-enable access to the `/api` folder.

When Akeeba Panopticon connects to sites running on Joomla! 4 and later it makes use of code provided not only by its own connector, but also core Joomla! plugins in the `webservices` and `api-authentication` folders. Please make sure that the following plugins are enabled on your site:

* `Web Services - Panopticon` (provided by this connector).
* `Web Services - Installer` (provided by Joomla!).
* `API Authentication - Web Services Joomla Token` (required for secure, token-based authentication to the Joomla! API).

If any of these plugins are disabled, _or if its Access is set to anything other than Public_, you will run into connection problems with your site. The connector itself will try to detect and report these issues, but there are cases it might fail to identify the problem.

## 📋 CHANGELOG

* ✨ Remote extension installation

Legend:
* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix