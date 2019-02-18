var shap_i = {

    selected_ds: false,
    status: "idle",
    states: ["idle", "running", "error", "finished", "aborted"],

    select_ds: function(e) {

        console.log("select", this.value);

        if (this.value === '-') {
            return;
        }

        jQuery.ajax({
            url: ajaxurl,
            type: 'post',
            data: {
                action: 'shap_get_ds_form',
                shap_ds: this.value
            },
            success: function(response) {
                jQuery('#shap-input-form').html(response);
            },
            error: function(exception) {
                console.warn(exception);

            }
        });

    },

    toggle_start_btn: function() {
        jQuery('#shap-import-start').attr('disabled', !jQuery(this).attr('checked'));
    },

    import_next_page: function(page) {

        if (shap_i.status !== "running") {
            return;
        }

        jQuery('[name="shap_ds_page"]').val(page);

        jQuery.ajax({
            url: ajaxurl,
            type: 'post',
            data: jQuery('#shap_search_form').serialize() + "&shap_ds_navigation=next&action=shap_import_next_page",
            success: function(response) {

                response = JSON.parse(response);

                shap_i.log(response.message, response.success);

                if (response.success) {
                    if (response.results) {
                        shap_i.import_next_page(page + 1);
                    } else {
                        shap_i.update_status("finished");
                    }
                } else {
                    shap_i.update_status("error");
                }
            },
            error: function(exception) {
                console.warn(exception);
                shap_i.log(exception, false);
                shap_i.update_status("error");
            }
        });
    },

    start_stop_import: function() {
        if (shap_i.status !== "running") {
            shap_i.update_status("running");
            shap_i.import_next_page(0);
        } else {
            shap_i.update_status("aborted");
        }
    },


    update_status: function(status) {
        if (shap_i.states.indexOf(status) === -1) {
            console.error("Unknown state:", status);
            status = "error";
        }

        shap_i.status = status;

        var startStopBtn = jQuery('#shap-import-start');
        var statusView = jQuery('#shap-import-status');

        if (status === "error") {
            startStopBtn.toggle(false);
            statusView.text("Error");
        }

        if (status === "aborted") {
            startStopBtn.toggle(false);
            statusView.text("Aborted by user");
        }

        if (status === "idle") {
            startStopBtn.toggle(true);
            startStopBtn.text("Start");
            statusView.text("");
        }

        if (status === "running") {
            startStopBtn.toggle(true);
            startStopBtn.text("Abort");
            statusView.text("Import Running");
        }

        if (status === "finished") {
            startStopBtn.toggle(false);
            statusView.text("Import Finished");
        }
    },

    log: function(msg, success) {
        var entry = jQuery("<li>" + msg + "</li>");
        entry.css('color', success ? 'green' : 'red');
        jQuery('#shap-import-log').append(entry);

    }



};

jQuery(document).ready(function() {
    jQuery('body').on('change', '#shap-select-datasource',  shap_i.select_ds);
    jQuery('body').on('click', '#shap-import-copyright',  shap_i.toggle_start_btn);
    jQuery('body').on('click', '#shap-import-start',  shap_i.start_stop_import);
    console.log("!");
});