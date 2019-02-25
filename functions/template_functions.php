<?php
/**
 * Usage: shap_bounding_box_query(35.5,40, 30,30)
 *
 * TODO how to handle multilanguage?
 * TODO implement geographical queries over term_meta
 *
 * @param $lat_1 - first point
 * @param $long_1 - first point
 * @param $lat_2 - second point
 * @param $long_2 - second point
 * @return array of WP_Post
 */
function shap_bounding_box_query($lat_1, $long_1, $lat_2, $long_2) {

    $x1 = min(180, max(-180, floatval($long_1)));
    $y1 = min(90, max(-90, floatval($lat_1)));
    $x2 = min(180, max(-180, floatval($long_2)));
    $y2 = min(90, max(-90, floatval($lat_2)));

    if (($x1 > $x2) or ($y1 > $y2)){
        list($x1, $y1, $x2, $y2) = array($x2, $y2, $x1, $y1);
    }

    $args = array(
        'nopaging' => true,
        'post_type'  => 'attachment',
        'post_statut' => 'publish',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key'     => 'shap_longitude',
                'value'   => $x1,
                'type'    => 'float',
                'compare' => '>=',
            ),
            array(
                'key'     => 'shap_longitude',
                'value'   => $x2,
                'type'    => 'float',
                'compare' => '<=',
            ),
            array(
                'key'     => 'shap_latitude',
                'value'   => $y1,
                'type'    => 'float',
                'compare' => '>=',
            ),
            array(
                'key'     => 'shap_latitude',
                'value'   => $y2,
                'type'    => 'float',
                'compare' => '<=',
            ),


        ),
    );
    return get_posts($args);
}