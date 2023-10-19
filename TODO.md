# TO-DO

## Core Joomla

### Get configuration parameters

#### GET /v1/config/com_akeebabackup?page[limit]=200

Can be used to change the update source for core Joomla

## Own connector

### List extensions with version and update status

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

Is it even possible to coax Joomla's API to return the download URL of the currently installed version?

#### POST /v1/panopticon/reinstall/123

Reinstall the current version of an extension, given its extension ID

#### POST /v1/panopticon/reinstall/pkg_something

Reinstall the current version of an extension, given its element