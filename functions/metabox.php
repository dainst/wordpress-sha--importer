<?php

add_action('add_meta_boxes', function() {

    $ds = new \shap_datasource\shap_easydb();

    add_meta_box(
        'shap-meta-box',
        'SHAP Attributes',
        function($meta_id) use ($ds) {
            $outline = "";
            $meta = get_post_meta($meta_id->ID);
            foreach ($meta as $meta_key => $meta_values) {
                if (substr($meta_key, 0, 5) !== 'shap_') {
                    continue;
                }

                foreach ($meta_values as $nr => $meta_value) {
                    $v = esc_attr($meta_value);
                    $outline .= "<label for='title_field' style='width:150px; display:inline-block;'>$meta_key</label>";
                    $outline .= "<input type='text' name='$meta_key' id='$meta_key-$nr' value='$v' class='widefat' readonly />";
                }
            }

            if (isset($meta['_shap_easydb_id'])) {
                $url = $ds->api_record_url($meta['_shap_easydb_id'][0]);
                $outline .= "<a href='$url' target='_blank'>View in EasyDb</a>";
            }

            //$outline .= shap_debug($meta);
            echo $outline;
        },
        'attachment',
        'advanced',
        'high'
    );
});