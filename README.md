# Autopatch v1.01

Retrieve patches from the versacode API and automatically apply them to your versacode software.

#### Features

  - Gives ability to choose the patches to apply
  - Applies source code changes
  - Applies SQL changes
  - Can run unattended by setting up the necessary details inside the `$config` parameter

### Requirements

  - PHP CLI
  - PHP cURL extension or allow_url_fopen enabled
  - Your versacode API key and project ID

#### Instructions

  - Download the latest `autopatch` repository to your server by going to https://github.com/versacode/autopatch/archive/master.
  - Extract the repository
  - (optional) Open `autopatch.php` and setup the `$config` parameter as needed (refer to bottom table for more details)
  - Run `autopatch.php` by executing `php autopatch.php` 

### Valid Config Parameters

The `$config` parameter can be setup to avoid providing details interactively. The valid parameters and values can be viewed in the below table:

| Parameter Key | Description | Valid Values or Examples |
| ------ | ------ | ------ | 
| API_SERVER | versacode API server endpoint | https://account.versacode.org/api/ |
| API_KEY | Your versacode API key | - |
| PROJECT_ID | Your versacode project ID | - |
| REMOVE_PATCHES | Remove patches after processing/importing | `true`; `false` |
| DIRECTORY_PATH | Path to target directory where patches will be applied | - |
| PATCH_NUMBER | Patch file(s) to apply | `N`: sequence number of patch; `lN`: last N patches; `all`: all patches |
| APPLY_SQL | Import SQL files | `true`; `false` |
| AGREE_DISCLAIMER | Agree to SQL imports disclaimer | `true`; `false` |
| MYSQL_HOST | MySQL host | Ex: `localhost` |
| MYSQL_PORT | MySQL port number | Ex: `3306` |
| MYSQL_USER | MySQL database user | - |
| MYSQL_DATABASE | MySQL database name | - |
| MYSQL_PASSWORD | MySQL database user password | - |

You can pass any of these config parameters as command line arguments. Example:

```
php autopatch.php DIRECTORY_PATH=/var/www/html/
```

### License

Copyright (c) versacode. All rights reserved.

Licensed under the [MIT](LICENSE) license.