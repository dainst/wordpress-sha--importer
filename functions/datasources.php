<?php
/**
 * @param $ds
 * @return \shap_datasource\abstract_datasource
 * @throws Exception
 */
function shap_get_datasource($ds) : \shap_datasource\abstract_datasource {
    if (!$ds) {
        return null;
    }

    $ds_class = "\\shap_datasource\\$ds";

    if (!class_exists($ds_class)) {
       throw new \Exception("datasource $ds_class not found");
    }

    return new $ds_class();
}