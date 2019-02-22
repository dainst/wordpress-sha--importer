<?php

add_action('add_meta_boxes', function() {

    $ds = new \shap_datasource\shap_easydb();

    $editable_attributes = array(
        'shap_easydb_id',
        'shap_copyright_vermerk',
        'shap_longitude',
        'shap_latitude',
        'shap_gazetteer_id'
    );

    add_meta_box(
        'shap-meta-box',
        'SHAP Attributes',
        function($meta_id) use ($ds, $editable_attributes) {

            $outline = "";
            $meta = get_post_meta($meta_id->ID);

            foreach ($editable_attributes as $attribute) {
                if (!in_array($attribute, $meta)) {
                    $outline .= "<label for='title_field' style='width:150px; display:inline-block;'>$attribute</label>";
                    $v = $meta[$attribute][0]; // TODO what if there are more than one?
                    $outline .= "<input type='text' name='set_$attribute' id='$attribute' value='$v' class='widefat' />";
                }
            }

            foreach ($meta as $meta_key => $meta_values) {

                if (substr($meta_key, 0, 5) !== 'shap_') {
                    continue;
                }

                if (in_array($meta_key, $editable_attributes)) {
                    continue;
                }

                foreach ($meta_values as $nr => $meta_value) { // TODO what if there are more than one?
                    $v = esc_attr($meta_value);
                    $outline .= "<label for='title_field' style='width:150px; display:inline-block;'>$meta_key</label>";
                    $outline .= "<input type='text' name='$meta_key' id='$meta_key-$nr' value='$v' class='widefat' readonly />";
                }

            }

            if (isset($meta['_shap_easydb_id']) or isset($meta['shap_easydb_id'])) {
                $update_id = isset($meta['_shap_easydb_id']) ? $meta['_shap_easydb_id'][0] : $meta['shap_easydb_id'];
                $url = $ds->api_record_url($update_id);
                $outline .= "<a href='$url' target='_blank'>View in EasyDb</a>";
            }

            //$outline .= shap_debug($meta);
            echo $outline;
            wp_nonce_field( 'shap_metabox_nonce', 'shap_metabox_nonce');

        },
        'attachment',
        'advanced',
        'high'
    );
});

add_action('edit_attachment', function($post_id) {

    if(!isset($_POST['shap_metabox_nonce']) or !wp_verify_nonce($_POST['shap_metabox_nonce'],'shap_metabox_nonce')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    foreach ($_POST as $post_key => $post_value) {
        if (substr($post_key, 0, 9) === 'set_shap_') {
            update_post_meta($post_id, substr($post_key, 4), $post_value);
        }
    }

}, 10, 1);