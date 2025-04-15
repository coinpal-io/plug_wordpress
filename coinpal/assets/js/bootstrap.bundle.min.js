jQuery(function($) {
    $.blockUI({
        message: coinpal_block_msg,
        baseZ: 99999,
        overlayCSS: {
            background: "#fff",
            opacity: 0.6
        },
        css: {
            padding: "20px",
            zindex: "9999999",
            textAlign: "center",
            color: "#555",
            border: "3px solid #aaa",
            backgroundColor: "#fff",
            cursor: "wait",
            lineHeight: "24px"
        }
    });

    $("#submit_coinpal_payment_form").click();
    $(".payment_buttons").hide();
});
