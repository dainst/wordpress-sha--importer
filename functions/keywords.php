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
});