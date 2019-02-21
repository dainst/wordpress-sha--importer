<?php
/**
 * @package shap-importer
 * @version 0.0.1
 */
/*
Plugin Name: shap-importer
Plugin URI:  https://github.com/dainst/wordpress-importer
Description: wordpress-importer
Author:	     Philipp Franck
Author URI:	 http://www.dainst.org/
Version:     0.0.1
*/
/*

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/


// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die();
}

/**
 * ******************************************* Path Globals
 */
//define('ESA_DIR', '/' . basename(dirname(__FILE__)));
//define('ESA_PATH', plugin_dir_path(__FILE__));
define('SHAP_FILE', __FILE__);
//define('ESA_NAME', basename(dirname(__FILE__)));
define('SHAP_BASE', plugin_basename(__FILE__));


/**
 * ******************************************* Check PHP version
 */
if (version_compare(phpversion(), '7', '<')) {
    add_filter("plugin_action_links_" . SHAP_BASE, function($actions, $plugin_file) {
        $actions["version_hint"] = "<span style='color:red'>This Plugin cannot be used with PHP version below 7. You have: " . phpversion() . "</span>";
        return $actions;
    }, 10, 2);
    error_log("This Plugin cannot be used with PHP version below 7. You have: " . phpversion());
    return;
}

require_once("functions/page_import.php");
require_once("functions/page_settings.php");
require_once("functions/datasources.php");
require_once("functions/metabox.php");
require_once("functions/keywords.php");
require_once("functions/template_functions.php");
require_once("shap_datasource.class.php");


require_once("datasources/shap_easydb.class.php");

$shap_datasources = [
    'shap_easydb'
];

function shap_debug($whatever, bool $echo = false) {
    ob_start();
    echo "<pre>";
    var_dump($whatever);
    echo "</pre>";
    $r = ob_get_clean();
    if ($echo) echo $r;
    return $r;
}