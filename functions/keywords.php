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
                'query_var'             => true,
            )
        );
    }
});