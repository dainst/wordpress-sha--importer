<?php

add_action('admin_menu', function() {

    add_submenu_page(SHAP_FILE, 'Settings', 'Settings', 'administrator', SHAP_FILE . '-settings', function () {

        $ds = shap_get_datasource('shap_easydb');
        try {
            $check = $ds->dependency_check();
            $class = "notice-success";
        } catch (Exception $exception) {
            $check = $exception->getMessage();
            $class = "notice-error";
        }

        echo "<div class='notice $class'>Plugin health status: $check</div>";

        $url = admin_url('admin.php');

        echo "<div class='wrap' id='esa_settings'>";
        echo "<h2>Settings</h2>";

        echo "<form method='POST' action='$url'>";

        $settings = array('shap_db_url', 'shap_db_user', 'shap_db_pass');
        $values = array();

        foreach ($settings as $setting) {
            $values[$setting] = get_option($setting);
        }

        echo "<input type='text' name='shap_db_url'  value='{$values['shap_db_url']}'>";
        echo "<label for='shap_db_url'><strong>/api/v1</strong> URL</label><br>";
        echo "<p>Leave out username and passwort for anonymous login</p>";
        echo "<input type='text' name='shap_db_user' value='{$values['shap_db_user']}'>";
        echo "<label for='shap_db_user'>Username</label><br>";
        echo "<input type='text' name='shap_db_pass' value='{$values['shap_db_pass']}'>";
        echo "<label for='shap_db_pass'>Password</label><br>";

        wp_nonce_field('shap_save_settings', 'shap_save_settings_nonce');
        echo "<input type='hidden' name='action' value='shap_save_settings'>";
        echo "<input type='submit' value='Save' class='button button-primary'>";
        echo "</form>";

        echo "</div>";



    });
});

add_action('admin_action_shap_save_settings' ,function() {
    if (!check_admin_referer('shap_save_settings', 'shap_save_settings_nonce')) {
        die("<div class='error'>Nonce failed</div>");
    }

    $settings = array('shap_db_url', 'shap_db_user', 'shap_db_pass');

    foreach ($settings as $setting) {
        $value = (isset($_POST[$setting])) ? $_POST[$setting] : 0;
        update_option($setting, $value);
    }

    wp_redirect($_SERVER['HTTP_REFERER']);
    exit();
});