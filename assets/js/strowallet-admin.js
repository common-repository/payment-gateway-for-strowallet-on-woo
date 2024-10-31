jQuery(function ($) {
  "use strict";

  /**
   * Object to handle strowallet admin functions.
   */
  var wc_strowallet_admin = {
    /**
     * Initialize.
     */
    init: function () {
      // Toggle api key settings.
      // $(document.body).on(
      //   "change",
      //   "#woocommerce_strowallet_testmode",
      //   function () {
      //     var secret_key = $("#woocommerce_strowallet_secret_key")
      //         .parents("tr")
      //         .eq(0),
      //       public_key = $("#woocommerce_strowallet_public_key")
      //         .parents("tr")
      //         .eq(0);

      //     if ($(this).is(":checked")) {
      //       secret_key.show();
      //       public_key.show();
      //     } else {
      //       secret_key.hide();
      //       public_key.hide();
      //     }
      //   }
      // );

      $("#woocommerce_strowallet_testmode").change();

      $(".wc-wc-strowallet-payment-gateway-icons").select2({
        templateResult: formatstrowalletPaymentIcons,
        templateSelection: formatstrowalletPaymentIconDisplay
      });
    }
  };

  function formatstrowalletPaymentIcons(payment_method) {
    if (!payment_method.id) {
      return payment_method.text;
    }

    var $payment_method = $(
      '<span><img src=" ' +
        wc_strowallet_admin_params.plugin_url +
        "/assets/images/" +
        payment_method.element.value.toLowerCase() +
        '.png" class="img-flag" style="height: 15px; weight:18px;" /> ' +
        payment_method.text +
        "</span>"
    );

    return $payment_method;
  }

  function formatstrowalletPaymentIconDisplay(payment_method) {
    return payment_method.text;
  }

  wc_strowallet_admin.init();
});
