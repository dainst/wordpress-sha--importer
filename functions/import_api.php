<?php
add_action('rest_api_init', function() {
    register_rest_route( 'shap_importer/v1', '/import/(?P<source>\w+)/(?P<page>\d+)', array(

        'methods' => 'POST',

        'callback' => function(WP_REST_Request $request) {

            $shap_import_uuid = $request->get_header("shap_import_uuid");
            if (!$shap_import_uuid) {
                return new WP_Error(405, "No import uuid given", $request->get_headers());
            }
            $present_import_uuid = file_exists("/tmp/shap_import_current")
                ? file_get_contents("/tmp/shap_import_current")
                : "";
            if ($shap_import_uuid != $present_import_uuid) {
                return new WP_Error(405, "Import UUID authentication failed!");
            }

            $source = $request->get_param('source');
            $page = (int) $request->get_param('page');

            try {
                $ds = shap_get_datasource($source);
                $check = $error = $ds->dependency_check();
                if (!$check) {
                    return new WP_Error(500,"Pre-Import-Check failed: $check");
                }
            } catch (Exception $exception) {
                return new WP_Error(500, "Error: " . $exception->getMessage());
            }

            $ds->items_per_page = 4;
            $success = $ds->fetch($page);

            if (!$success) {
                return new WP_Error(500, "Errors while importing", array('log' => $ds->log));
            }

            $results = count($ds->get_results(true));
            $results_all = count($ds->get_results());

            $log = $ds->log;

            $log[] = array(
                "type" => "success",
                "msg" => "Page $page successfully fetched, $results/$results_all items added."
            );

            return (object) array('data' =>
                (object) array(
                    "log" => $log,
                    "results" => $results,
                    "page" => $ds->page,
                )
            );

        },
    ));
});

