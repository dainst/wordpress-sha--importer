<?php

add_action('init', function() {
    register_taxonomy(
        'shap_tags',
        'attachment',
        array(
            'label'                 => 'Tags',
            'rewrite'               => array('slug' => 'data'),
            'hierarchical'          => false,
            'show_ui'               => true,
            'show_admin_column'     => false,
            'query_var'             => true,
        )
    );

    register_taxonomy(
        'shap_places',
        'attachment',
        array(
            'label'                 => 'Places',
            'rewrite'               => array('slug' => 'data'),
            'hierarchical'          => false,
            'show_ui'               => true,
            'show_admin_column'     => false,
            'query_var'             => true,
        )
    );

    register_taxonomy(
        'shap_time',
        'attachment',
        array(
            'label'                 => 'Period',
            'rewrite'               => array('slug' => 'data'),
            'hierarchical'          => false,
            'show_ui'               => true,
            'show_admin_column'     => false,
            'query_var'             => true,
        )
    );

    register_taxonomy(
        'shap_theme',
        'attachment',
        array(
            'label'                 => 'Theme',
            'rewrite'               => array('slug' => 'data'),
            'hierarchical'          => false,
            'show_ui'               => true,
            'show_admin_column'     => false,
            'query_var'             => true,
        )
    );

    register_taxonomy(
        'shap_subject',
        'attachment',
        array(
            'label'                 => 'Subject',
            'rewrite'               => array('slug' => 'data'),
            'hierarchical'          => false,
            'show_ui'               => true,
            'show_admin_column'     => false,
            'query_var'             => true,
        )
    );

    register_taxonomy(
        'shap_pool',
        'attachment',
        array(
            'label'                 => 'Pool',
            'rewrite'               => array('slug' => 'data'),
            'hierarchical'          => false,
            'show_ui'               => true,
            'show_admin_column'     => false,
            'query_var'             => true,
        )
    );
});