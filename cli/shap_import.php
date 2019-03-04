<?php

ini_set('max_execution_time', -1);

echo "[SHAP command line import script]\n";

if (!isset($argv[1]) or !isset($argv[2])) {
    echo "usage: sh shap_import <start_page> <end_page>\1";
    exit(1);
}

$page_from = (int) $argv[1];
$page_to = (int) $argv[2];

if ($page_to < $page_from) {
    echo "Negative Page offset\n";
    exit(1);
}

echo "Import from page $page_from to $page_to\n";

$import_uuid = uniqid("shap_import_", true);

file_put_contents("/tmp/shap_import_current", $import_uuid);

$durations = array();

echo "Performing $import_uuid\n";

$log_file_handle = fopen("$import_uuid.log", 'w');

for ($round = $page_from; $round <= $page_to; $round++) {

    fwrite($log_file_handle, "\n---- Importing page $round ----\n");
    echo "Importing page $round\n";

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_PORT => "80",
        CURLOPT_URL => "http://localhost/wp-json/shap_importer/v1/import/shap_easydb/1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 3000,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_HTTPHEADER => array(
            "Cache-Control: no-cache",
            "shap-import-uuid: $import_uuid"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    $info = curl_getinfo($curl);

    curl_close($curl);

    if ($err) {
        fwrite($log_file_handle, "[fatal]\n\ncURL Error on page $round#: $err\n");
        fclose($log_file_handle);
        echo "Errors occurred on page $round. \nVisit $import_uuid.log for details.\n";
        unlink("/tmp/shap_import_current");
        exit(1);
    }

    $res = json_decode($response);
    if (!is_object($res)) {
        echo "Communication Error: $response\n";
        unlink("/tmp/shap_import_current");
        exit(1);
    }

    $log = isset($res->data) ? $res->data->log : array();

    foreach ($log as $log_entry) {
        if ($log_entry->type == 'debug') {
            continue;
        }
        $msg = strip_tags($log_entry->msg);
        fwrite($log_file_handle, "[{$log_entry->type}]\t\t\t{$msg}\n");
    }

    $durations[] = $info['total_time'];

    fwrite($log_file_handle, "[benchmark]\t\tPage $round took {$info['total_time']} seconds.\n");

    if (substr($info['http_code'],0, 1) !== '2') {
        fwrite($log_file_handle, "[fatal]\n\nError on page $round: {$res->message}\n");
        fclose($log_file_handle);
        echo "Errors occurred on page $round. \nVisit $import_uuid.log for details.\n";
        unlink("/tmp/shap_import_current");
        exit(1);
    }

}

$avg = array_sum($durations) / count($durations);
fwrite($log_file_handle, "\n---- ready ----\n");
fwrite($log_file_handle, "[success]\t\t\tAll done.\n");
fwrite($log_file_handle, "[benchmark]\t\t\tAvarage page $round took $avg seconds.\n");
fclose($log_file_handle);
unlink("/tmp/shap_import_current");
echo "Ready.\nVisit $import_uuid.log for details.\n";
exit(0);