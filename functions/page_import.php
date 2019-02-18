<?php

add_action('admin_menu', function () {

    add_menu_page('SHAP-Importer', 'SHAP-Import', 'administrator', SHAP_FILE . '-import', function () {

        echo "<h2>Import to cache</h2>";

        echo "<div class='wrap' id='shap-import'>";

        echo "<div id='shap-input-form'>";

        if (!isset($_POST['shap_ds_type'])) {
            echo "<h2>Select Datasource</h2>";
            echo shap_select_datasource();
            echo "</div>";
            echo "</div>";
            return;
        }

        echo "<h2>Start Import</h2>";
        $ds = shap_get_datasource($_POST['shap_ds_type']);

        $query = $_POST['shap_ds_query'];
        $success = $ds->search($query);

        if ($success) {
            echo "<strong>Import {$ds->pages} pages of data?</strong><br>";
            echo "<button id='shap-import-start' disabled>Start</button>";

        } else {
            $ds->show_errors();
        }

        echo "<ol id='shap-import-log' style='list-style-type: decimal'>";
        echo "</ol>";
        echo "<hr>";
        echo "<div id='shap-import-status'></div>";

        echo "</div>";
    });
});

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook == 'toplevel_page_shap-importer/shap-importer-import') {
        wp_enqueue_script(
            'shap_import.js',
            plugins_url() . '/' . dirname(SHAP_BASE) . '/js/shap_import.js',
            array('jquery')
        );
        wp_localize_script('shap_import.js', 'shap', array('ajax_url' => admin_url('admin-ajax.php')));
    }
});


add_action('wp_ajax_shap_get_ds_form', function() {
    $engine = isset($_POST['shap_ds']) ? $_POST['shap_ds'] : false;
    $ds = shap_get_datasource($engine);
    if (!$ds) {
        echo "error. $engine not found";
        wp_die();
    }

    echo "ne";

    wp_die();
});


add_action('wp_ajax_esa_import_next_page', function() {
    if (!isset($_POST['esa_ds_type'])) {
        echo json_encode(array(
            "success" => false,
            "message" => "No Datasource"
        ));
        wp_die();
    }

    ob_start();
    $ds = esa_get_datasource($_POST['esa_ds_type']);
    if (!$ds) {
        echo json_encode(array(
            "success" => false,
            "message" => ob_get_clean()
        ));
        wp_die();
    }
    ob_end_flush();

    if (!$ds->search()) {
        echo json_encode(array(
            "success" => false,
            "message" => implode(",", $ds->errors)
        ));
        wp_die();
    }

    $warnings = esa_cache_result($ds);
    $results = count($ds->results) - count($warnings);
    $results_all = count($ds->results);
    $list = implode("</li>\n<li>", array_map(function($item) {
        return $item->title;
    }, $ds->results));

    echo json_encode(array(
        "success" => true,
        "warnings" => $warnings,
        "message" => "Page {$ds->page} successfully fetched, $results/$results_all items added.<ul title='{$ds->last_fetched_url}'><li>$list</li></ul>",
        "results" => $results,
        "page" => $ds->page,
        "url" => $ds->last_fetched_url
    ));

    wp_die();
});

function shap_select_datasource() {
    global $shap_datasources;

    $current = isset($_POST['shap_ds_type']) ? $_POST['shap_ds_type'] : "";

    $return = "<select id='shap-select-datasource'>";
    $return .= "<option value='-'>Select</option>";
    foreach ($shap_datasources as $ed_name) {
        $selected = ($current == $ed_name) ? "selected" : '';
        $return .= "<option value='$ed_name' $selected>$ed_name</option>";
    }

    $return .= "</select>";

    return $return;

}

function esa_cache_result($ds) {
    $warnings = array();
    foreach ($ds->results as $result) {

        if (get_class($result) == 'esa_item') {
            $result->store();
            esa_get_wrapper($result);
        } else {
            $warnings[] = esa_debug(get_class($result));
        }
    }
    return $warnings;
}


