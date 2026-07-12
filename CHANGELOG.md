# Akeeba Panopticon Connector for Joomla 4.x and 5.x version 1.1.1

* ✨ Add machine translations for `de-DE`, `el-GR`, `es-ES`, `fr-FR`, `it-IT`, and `pt-PT`
* ✨ Support a custom base URL for the Core File Integrity checksums source
* 🐞 Core updates fail with HTTP 500 on Joomla 5.1–5.2 due to removed `File::exists()` method [#26]
* 🐞 System information reported the CPU load averages incorrectly (empty 5-minute value, wrong 15-minute value)
* 🐞 System information never reported Linux CPU usage from `/proc/stat`

# Akeeba Panopticon Connector for Joomla 4.x and 5.x version 1.1.0

* ✨ Remote extension installation

# Akeeba Panopticon Connector for Joomla 4.x and 5.x version 1.0.8

* ✨ Support for Joomla 6
* ✨ PHP 8.5 compatibility

# Akeeba Panopticon Connector for Joomla 4.x and 5.x version 1.0.7

* ✏️ Warn the user if “Web Services - Installer” is not enabled.
* ✏️ Option to disable system information collection.
* ✨ Use Admin Tools Professional's Reset Joomla! Update feature (if available) to fix stuck core updates.

# Akeeba Panopticon Connector for Joomla 4.x and 5.x version 1.0.6

* ✨ Troubleshooting aid for API application errors when connecting a Panopticon site

# Akeeba Panopticon Connector for Joomla 4.x and 5.x version 1.0.5

* ✏️ Re-release because of a packaging issue

# Akeeba Panopticon Connector for Joomla 4.x and 5.x version 1.0.4

* ✨ Support for TUF (The Update Framework) in Joomla! 5.1 for Joomla! itself
* 🐞 Occasional database exception when getting the extension update information 

# Akeeba Panopticon Connector for Joomla 4.x and 5.x version 1.0.3

* ✨ Console plugin for CLI commands
* 🐞 Chunked downloads were failing, in a way that was stalling the core update

# Akeeba Panopticon Connector for Joomla 4.x and 5.x version 1.0.2

* ✨ Collect server information.
* ✏️ Tell Joomla! to refresh its updates cache when requesting update information.

# Akeeba Panopticon Connector for Joomla 4.x and 5.x version 1.0.1

* 🐞 Linking Panopticon to Akeeba Backup may fail if the JSON API isn't already active.
* 🐞 The `version.php` file is not copied over during installation / update.

# Akeeba Panopticon Connector for Joomla 4.x and 5.x version 1.0.0

* ✨ Initial release
