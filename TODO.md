# TO-DO

## Core Joomla

### Get configuration parameters

#### GET /v1/config/com_akeebabackup?page[limit]=200

Can be used to change the update source for core Joomla

## Own connector

### List extensions with version and update status

#### ✅ GET /v1/panopticon/extensions
    
List information about installed extensions and their update availability.

Filters:

* `updatable` Display only items with / without an update (default: null)
* `protected` Display only items which are / are not protected (default: 0)
* `id` Display only a specific extension (default: null)
* `core` Include / exclude core Joomla extensions (default: null)

#### ✅ GET /v1/panopticon/extension/123

Get information about extension with ID 123

#### ✅ GET /v1/panopticon/extension/com_foobar

Get information about an extension given its Joomla extension element e.g. com_example, plg_system_example, tpl_example, etc.

### Update handling

#### ✅ POST /v1/panopticon/updates

Tell Joomla to fetch update information.

Filters:

* `force` Should I force-reload all updates?

#### ✅ POST /v1/panopticon/update

Install updates for specific extension

POST parameters:

```eid[]=123&eid[]=345```

#### ✅ GET /v1/panopticon/updatesites

List update sites

Filters:
* `enabled` Filter by published / unpublished sites
* `eid[]` Filter by extension ID (array, multiple elements allowed)

##### ✅ PATCH /v1/panopticon/updatesite/123

Modify an update site

##### ✅ DELETE /v1/panopticon/updatesite/123

Delete an update site

##### ✅ POST /v1/panopticon/updatesites/rebuild

Rebuild the updates sites

### Joomla Core Update

#### ✅ GET /v1/panopticon/core/update

List core version and update availability

#### POST /v1/panopticon/core/update/download

Download the core update package to the server

#### POST /v1/panopticon/core/update/activate

Enable `administrator/components/com_joomlaupdate/extract.php` or `administrator/components/com_joomlaupdate/restore.php`

Note: the extraction and initial post-update processing is done using extract.php

#### POST /v1/panopticon/core/update/disable

Disable `administrator/components/com_joomlaupdate/extract.php` or `administrator/components/com_joomlaupdate/restore.php`

This should only be used for testing the communication with this file. Otherwise, it will be disabled automatically by Joomla.

#### POST /v1/panopticon/core/update/postupdate

Run the post-update code

### Database fix

#### GET /v1/panopticon/database

Get database fix info for all extensions.

#### GET /v1/panopticon/database/123

Get database fix info for an extension, given its ID

#### GET /v1/panopticon/database/pkg_something

Get database fix info for an extension, given its element

#### POST /v1/panopticon/database/123 

Apply database fix for an extension given its ID

#### POST /v1/panopticon/database/pkg_example

Apply database fix for an extension given its element

### Reinstall / refresh extensions

#### POST /v1/panopticon/reinstall/123 ❓

Reinstall the current version of an extension, given its extension ID

#### POST /v1/panopticon/reinstall/pkg_something ❓

Reinstall the current version of an extension, given its element
