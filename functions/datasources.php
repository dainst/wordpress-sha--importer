<?php
function shap_get_datasource($ds) {
    if (!$ds) {
        return null;
    }

    $ds_class = "\\shap_datasource\\$ds";

    if (class_exists($ds_class)) {
        return new $ds_class;
    }

    return new $ds_class;
}