### wordpress-shap-importer

This plugin is part of https://github.com/dainst/wordpress-components

## Installation

In case you're using the docker stack https://github.com/dainst/wordpress-components
the plugin will be auto-installed.

If you do not use the docker stack you can install this plugin manually.

- Navigate to the wp-content folder within the WordPress installation for your website or blog.
- Navigate to the /wp-content/plugins directory.
- Copy/Upload the plugin folder to the /wp-content/plugins directory

## Plugin requirements
- The plugin shap-importer must be installed (see above)
- Permalinks must be activated: In Wordpress backend: Settings → Permalinks, select the option "Post name"
- The default language must be English
- the uploads folder needs write permissions. For docker do:
`docker exec wordpress-components_cms_1 chown -R www-data: root/var/www/html/wp-content/uploads/`
- The plugin shap-import-blocker must not be active
- The WPML plugins must be installed and set up (you will get a warning in case of misconfigurations)
- In Wordpress backend: the URL for the Easy-DB must be entered under SHAP-Importer → Settings

> The import of individual pages can be triggered in the Wordpress backend. For larger amounts of data, the import via the CLI is recommended

## Import via WEB-GUI

The data source to be imported must be selected under SHAP-Importer → Shap-Importer
After entering the page number, the import can be started.

## Import via Command-Line-Interface

- log in to your server container or use docker exec
- cd wp-content/plugins/shap-importer/cli
- `php shap_import.php <home> <endpage>`

For importing some or only a single item you can do:

- `php shap_import.php 1 1 <system_object_id>`
- `php shap_import.php 1 1 1952,2352`
- `php shap_import.php 1 1 1952`

> Make sure to set from and to page to 1

_Home and end page are integers indicating the range to be imported. Each "page" contains four datasets, ie with about 5000 datasets, there are about 1250 pages._
