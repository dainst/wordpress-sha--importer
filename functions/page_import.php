<?php

add_action('admin_menu', function () {

    add_menu_page('SHAP-Importer', 'SHAP-Import', 'administrator', SHAP_FILE, function () {

        echo "<h2>Import Data</h2>";

        if (defined("SHAP_BLOCKED") and SHAP_BLOCKED) {
            echo "<div>Under construction</div>";
            return;
        }

        echo "<div class='wrap' id='shap-import'>";

        echo "<form id='shap-import-form' method='post'>";

        if (!isset($_POST['shap_ds_type'])) {

            echo "<strong>Select Datasource</strong><br>";
            global $shap_datasources;

            echo "<select id='shap-select-datasource' name='shap_ds_type'>";
            foreach ($shap_datasources as $ed_name) {
                echo "<option value='$ed_name'>$ed_name</option>";
            }

            echo "</select>";
            echo "<input type='submit' class='button' value='OK'>";

        } else {

            echo "<input type='hidden' name='shap_ds_type' value='{$_POST['shap_ds_type']}'>";

            try {
                $ds = shap_get_datasource($_POST['shap_ds_type']);
                $check = $error = $ds->dependency_check();
                if (!$check) {
                    echo "<div class='error'>Pre-Import-Check-Failed: <pre>$check</pre></div>";
                    $success = false;
                } else {
                    $success = $ds->fetch(0, true);
                }
            } catch (Exception $exception) {
                $success = false;
                echo "<div class='error'>Error: <pre>". $exception->getMessage() . "</pre></div>";
            }

            if ($success) {
                $items = $ds->pages * $ds->items_per_page;
                echo "<strong>Import {$ds->pages} pages of data (about $items items)?</strong><br>";
                $page = isset($_POST['shap_ds_page']) ? $_POST['shap_ds_page'] : 0;
                echo "<label for='shap_ds_page'>Page</label><input type='text' name='shap_ds_page' value='$page' style='width: 4em'>";
                echo "<div class='button' id='shap-import-start'>Start</div>";
            } else {
                echo "<strong>Errors</strong><br>";
                $ds->show_errors();
            }

            echo "<div id='shap-import-status'></div>";
            echo "<hr>";
            echo "<div id='shap-import-log'></div>";

        }

        echo "</form>";
        echo "</div>";


    });
});

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook == 'toplevel_page_shap-importer/shap-importer') {
        wp_enqueue_script(
            'shap_import.js',
            plugins_url() . '/' . dirname(SHAP_BASE) . '/js/shap_import.js',
            array('jquery')
        );
        wp_localize_script('shap_import.js', 'shap', array('ajax_url' => admin_url('admin-ajax.php')));
    }
});


add_action('wp_ajax_shap_import_next_page', function() {
    if (!isset($_POST['shap_ds_type'])) {
        echo json_encode(array(
            "success" => false,
            "message" => "No Datasource"
        ));
        wp_die();
    }

    ob_start();
    $ds = shap_get_datasource($_POST['shap_ds_type']);
    if (!$ds) {
        echo json_encode(array(
            "success" => false,
            "message" => ob_get_clean()
        ));
        wp_die();
    }
    ob_end_flush();

    $next = isset($_POST['shap_ds_page']) ? (int) $_POST['shap_ds_page'] : 0;

    if (!$ds->fetch($next)) {
        echo json_encode(array(
            "success" => false,
            "message" => "Some Errors"
        ));
        wp_die();
    }

    $results = count($ds->get_results(true));
    $results_all = count($ds->get_results());
    $page = $ds->page - 1;

    echo json_encode(array(
        "success" => true,
        "log" => $ds->log,
        "message" => "Page $page successfully fetched, $results/$results_all items added.",
        "results" => $results,
        "page" => $ds->page,
        "url" => $ds->last_fetched_url
    ));

    wp_die();
});