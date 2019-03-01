<?php


add_action('rest_api_init', function() {
    register_rest_route( 'shap_importer/v1', '/import/(?P<source>\w+)/(?P<page>\d+)', array(
        'methods' => 'POST',

        'callback' => function($request) {

            $source = $request->get_param('source');
            $page = (int) $request->get_param('page');

            try {
                $ds = shap_get_datasource($source);
                $check = $error = $ds->dependency_check();
                if (!$check) {
                    return new WP_Error("Pre-Import-Check failed: $check");
                }
            } catch (Exception $exception) {
                return new WP_Error("Error: " . $exception->getMessage());
            }

            if (!$ds->fetch($page)) {
                $ds->show_errors();
            }

            $results = count($ds->get_results(true));
            $results_all = count($ds->get_results());

            return array(
                "success" => true,
                "log" => $ds->log,
                "message" => "Page $page successfully fetched, $results/$results_all items added.",
                "results" => $results,
                "page" => $ds->page,
                "url" => $ds->last_fetched_url,
            );

        },
    ));
});

