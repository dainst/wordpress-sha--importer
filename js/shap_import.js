var shap_i = {

    selected_ds: false,
    status: "idle",
    states: ["idle", "running", "error", "finished", "aborted"],


    import_next_page: function(page) {

        if (shap_i.status !== "running") {
            return;
        }

        jQuery('[name="shap_ds_page"]').val(page);

        jQuery.ajax({
            url: ajaxurl,
            type: 'post',
            data: jQuery('#shap-import-form').serialize() + "&shap_ds_navigation=next&action=shap_import_next_page",
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
        console.log("start import");
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

        statusView.removeClass();

        if (status === "error") {
            startStopBtn.toggle(false);
            statusView.text("Error");
            statusView.addClass("error");
        }

        if (status === "aborted") {
            startStopBtn.toggle(false);
            statusView.text("Aborted by user");
            statusView.addClass("notice notice-warning");
        }

        if (status === "idle") {
            startStopBtn.toggle(true);
            startStopBtn.text("Start");
            statusView.text("");
            statusView.addClass("notice notice-info");
        }

        if (status === "running") {
            startStopBtn.toggle(true);
            startStopBtn.text("Abort");
            statusView.text("Import Running");
            statusView.addClass("notice notice-info");
        }

        if (status === "finished") {
            startStopBtn.toggle(false);
            statusView.text("Import Finished");
            statusView.addClass("notice notice-success");
        }
    },

    log: function(msg, success) {
        if (jQuery.isPlainObject(msg)) {
            msg = "[" + msg.status + "] " + msg.statusText;
        }
        var entry = jQuery("<div>" + msg + "</div>");
        entry.addClass(success ? 'notice notice-success' : 'error');
        jQuery('#shap-import-log').append(entry);

    }



};

jQuery(document).ready(function() {
    console.log("hello");
    jQuery('body').on('click', '#shap-import-start',  shap_i.start_stop_import);
});