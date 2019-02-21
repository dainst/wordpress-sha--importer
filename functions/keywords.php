<?php

add_action('init', function() {
    register_taxonomy(
        'shap_tags',
        'attachment',
        array(
            'label'                 => 'SHAP Tags',
            'rewrite'               => array('slug' => 'data'),
            'hierarchical'          => false,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
        )
    );

    register_taxonomy(
        'shap_places',
        'attachment',
        array(
            'label'                 => 'SHAP Places',
            'rewrite'               => array('slug' => 'data'),
            'hierarchical'          => false,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
        )
    );

    register_taxonomy(
        'shap_time',
        'attachment',
        array(
            'label'                 => 'SHAP Places',
            'rewrite'               => array('slug' => 'data'),
            'hierarchical'          => false,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
        )
    );

    register_taxonomy(
        'shap_theme',
        'attachment',
        array(
            'label'                 => 'SHAP Theme',
            'rewrite'               => array('slug' => 'data'),
            'hierarchical'          => false,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
        )
    );

    register_taxonomy(
        'shap_subject',
        'attachment',
        array(
            'label'                 => 'SHAP Subject',
            'rewrite'               => array('slug' => 'data'),
            'hierarchical'          => false,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
        )
    );

    register_taxonomy(
        'shap_pool',
        'attachment',
        array(
            'label'                 => 'SHAP Pool',
            'rewrite'               => array('slug' => 'data'),
            'hierarchical'          => false,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
        )
    );
});