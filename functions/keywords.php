<?php

$shap_taxonomies = array(
    "tags" => "Keywords",
    "places" => "Places",
    "time" => "Time",
    "theme" => "Theme",
    "subject" => "Subject",
    "pool" => "Pool"
);

add_action('init', function() {

    global $shap_taxonomies;

    foreach ($shap_taxonomies as $name => $label) {
        register_taxonomy(
            "shap_$name",
            'attachment',
            array(
                'label'                 => $label,
                'rewrite'               => array('slug' => $name),
                'hierarchical'          => false,
                'show_ui'               => true,
                'show_admin_column'     => false,
                'show_in_modal'         => false,
                'query_var'             => true,
            )
        );
    }


    add_action('shap_places_edit_form_fields', function($term, $taxonomy) {

        $meta = get_term_meta($term->term_id);

        $display_meta = array("gazetteer_id", "latitude", "longitude");

        echo "<tr class=''><th scope='row'>SHAP Metadata</th><td>";

        foreach ($display_meta as $meta_key) {
            echo "<div>{$meta_key}: {$meta[$meta_key][0]}</div>";
        }

        echo "</td></div>";

    }, 10, 2);


});