<?php
if (class_exists("WC_Payment_Gateway")) {
    class WC_strowallet_Payment_Gateway extends WC_Payment_Gateway_CC
    {
        /**
         * API strowallet key
         *
         * @var string
         */
        public $public_key;

        /**
         * API secret key
         *
         * @var string
         */
        public $secret_key;
        /**
         * strowallet APIURL.
         *
         * @var string
         */
        public $apiURL;


        /**
         * Constructor
         */
        public function __construct()
        {
            $this->id = "strowallet";
            // $this->icon = apply_filters("woocommerce_strowallet_icon", plugins_url( 'assets/images/strowallet.png', WC_STROWALLET_MAIN_FILE ));
            $this->has_fields = true;
            $this->method_title = __("strowallet Payment", "strowallet-woo-payment-gateway");
            $this->method_description = sprintf(__('strowallet provide merchants with the tools and services needed to accept online payments <a href="%1$s" target="_blank">Get your API keys</a>.', 'strowallet-woo-payment-gateway'), 'https://strowallet.com/user/api-key');
            $this->supports = array(
                'products',
                'tokenization',
                'subscriptions',
                'subscription_cancellation',
                'subscription_suspension',
                'subscription_reactivation',
                'subscription_amount_changes',
                'subscription_date_changes',
                'subscription_payment_method_change',
                'subscription_payment_method_change_customer',
                'subscription_payment_method_change_admin',
                'multiple_subscriptions',
            );
            // Load the form fields
            $this->init_form_fields();
            // Load the settings
            $this->init_settings();
            //Load 
            $this->title       = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled     = $this->get_option('enabled');

            $this->testmode    = false;

            $this->apiURL = "https://strowallet.com/express/initiate";

            $this->public_key = $this->get_option('public_key');
            $this->secret_key = $this->get_option('secret_key');
            // Hooks
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
            add_action('woocommerce_available_payment_gateways', array($this, 'add_gateway_to_checkout'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action('woocommerce_api_wc_strowallet_payment_gateway', array($this, 'strowallet_verify_payment'));
            add_action(
                'woocommerce_update_options_payment_gateways_' . $this->id,
                array(
                    $this,
                    'process_admin_options',
                )
            );
            // Check if the gateway can be used.
            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        public function init_form_fields()
        {
            $form_fields = apply_filters(
                "woo_ade_strowallet_fields",
                array(
                    "enabled" => array(
                        "title" => __("Enable/Disable", "strowallet-woo-payment-gateway"),
                        "type" => "checkbox",
                        "label" => __("Enable or Disable strowallet Payment", "strowallet-woo-payment-gateway"),
                        "default" => "no"
                    ),
                    "title" => array(
                        "title" => __("Title", "strowallet-woo-payment-gateway"),
                        "type" => "text",
                        "description" => __("This controls the payment method title which the user sees during checkout.", "strowallet-woo-payment-gateway"),
                        "default" => __("strowallet Payment", "strowallet-woo-payment-gateway"),
                        "desc_tip" => true
                    ),
                    "description" => array(
                        "title" => __("Payment Description", "strowallet-woo-payment-gateway"),
                        "type" => "textarea",
                        "description" => __("Add a new description", "strowallet-woo-payment-gateway"),
                        "default" => __("Accept Payments Seamlessly via Bank Transfer.", "strowallet-woo-payment-gateway"),
                        "desc_tip" => true
                    ),
                    "instructions" => array(
                        "title" => __("Instructions", "strowallet-woo-payment-gateway"),
                        "type" => "textarea",
                        "description" => __("Instructions that will be added to the thank you page."),
                        "default" => __("", "strowallet-woo-payment-gateway"),
                        "desc_tip" => true
                    ),
                    'public_key'                  => array(
                        'title'       => __('Public Key', 'strowallet-woo-payment-gateway'),
                        'type'        => 'text',
                        'description' => __('Enter your Public Key here.', 'strowallet-woo-payment-gateway'),
                        'default'     => '',
                    ),
                    'secret_key'                  => array(
                        'title'       => __('Secret Key', 'strowallet-woo-payment-gateway'),
                        'type'        => 'text',
                        'description' => __('Enter your Secret Key here.', 'strowallet-woo-payment-gateway'),
                        'default'     => '',
                    )
                )
            );

            $this->form_fields = $form_fields;
        }

        /**
         * Payment form on checkout page
         */
        public function payment_fields()
        {

            if ($this->description) {
                echo sanitize_text_field($this->description);
            }

            if (!is_ssl()) {
                return;
            }

            if ($this->supports('tokenization') && is_checkout() && $this->saved_cards && is_user_logged_in()) {
                $this->tokenization_script();
                $this->saved_payment_methods();
                $this->save_payment_method_checkbox();
            }
        }


        /**
         * Display strowallet payment icon.
         */
        public function get_icon()
        {

            $icon = '<img src="' . WC_HTTPS::force_https_url(plugins_url('assets/images/strowallet.png', WC_STROWALLET_MAIN_FILE)) . '" alt="strowallet Payment Options" style="height: 20px;" />';

            return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
        }

        public function customURL()
        {
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
                $url = "https://";
            else
                $url = "http://";
            // Append the host(domain name, ip) to the URL.   
            $url .= $_SERVER['HTTP_HOST'];

            // Append the requested resource location to the URL   
            $url .= $_SERVER['REQUEST_URI'];

            //clean url
            $url = sanitize_url($url);

            return $url;
        }

        /**
         * Displays the payment page.
         *
         * @param $order_id
         */
        public function receipt_page($order_id)
        {
            session_start();
            if (!isset($_SESSION['strowallet-woo-payment-gateway-session'])) {
                $session = rand(0, time());
                $_SESSION['strowallet-woo-payment-gateway-session'] = $session;
            }
            if (isset($_GET["success"])) {
                $this->strowallet_verify_payment($order_id);
            }
            $order = wc_get_order($order_id);
            echo '<div id="yes-add">' . __('Thank you for your order, please click the button below to pay with strowallet.', 'strowallet-woo-payment-gateway') . '</div>';
            $testmodedata = $this->testmode ? 'true' : 'false';
            echo '<div id="strowallet_form">
                    <form id="order_review" method="post" action="https://collect.strowallet.com/">
                        <input type="hidden" name="amount" value="' . sanitize_text_field($order->data["total"]) . '"/>
                        <input type="hidden" name="currency" value="' . sanitize_text_field($order->data["currency"]) . '"/>
                        <input type="hidden" name="details" value="WooCommerce Order :: ' . sanitize_text_field($order->data["order_key"]) . '"/>
                        <input type="hidden" name="custom" value="' . sanitize_text_field($order->data["billing"]["first_name"] . ' ' . $order->data["billing"]["last_name"]) . '"/>
                        <input type="hidden" name="name" value="' . sanitize_text_field($order->data["billing"]["first_name"] . ' ' . $order->data["billing"]["last_name"]) . '"/>
                        <input type="hidden" name="email" value="' . sanitize_text_field($order->data["billing"]["email"]) . '"/>
                        <input type="hidden" name="test" value="' . sanitize_text_field($testmodedata) . '"/>
                        <input type="hidden" name="ipn_url" value="' . esc_url($this->customURL()) . '"/>
                        <input type="hidden" name="success_url" value="' . esc_url($this->customURL() . '&success=' . $_SESSION['strowallet-woo-payment-gateway-session']) . '"/>
                        <input type="hidden" name="cancel_url" value="' . esc_url($this->customURL() . '&cancel=' . $_SESSION['strowallet-woo-payment-gateway-session']) . '"/>
                        <input type="hidden" name="public_key" value="' . $this->public_key . '"/>
                        <button class="button alt" id="strowallet-woo-payment-gateway-button" style="margin-bottom:3px;" type="submit">' . __('Pay Now', 'strowallet-woo-payment-gateway') . '</button></form>';

            if (!$this->remove_cancel_order_button) {
                echo '  <a class="button cancel" id="cancel-btn" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'strowallet-woo-payment-gateway') . '</a></div>';
            }
        }

        /**
         * Verify strowallet payment.
         */
        public function strowallet_verify_payment($order_id)
        {

            //If transactions_refrence is not set
            if (isset($_GET["success"]) && $_GET["success"] != "undefined" && $_GET["success"] != "") {
                //DO More
                $order = wc_get_order($order_id);
                $transactions_refrence = sanitize_text_field($_GET["success"]);
                if ($transactions_refrence == $_SESSION['strowallet-woo-payment-gateway-session']) {
                    //CLear Order
                    $order->payment_complete($transactions_refrence);
                    $order->update_status('completed');
                    $order->add_order_note('Payment was successful on strowallet');
                    $order->add_order_note(sprintf(__('Payment via strowallet successful (Transaction Reference: %s)', 'strowallet-woo-payment-gateway'), $transactions_refrence));
                    //Customer Note
                    $customer_note  = 'Thank you for your order.<br>';
                    $customer_note .= 'Your payment was successful, we are now <strong>processing</strong> your order.';

                    $order->add_order_note($customer_note, 1);

                    wc_add_notice($customer_note, 'notice');
                    //CLear Cart
                    WC()->cart->empty_cart();
                    //Quit and redirect
                    unset($_SESSION['strowallet-woo-payment-gateway-session']);
                    wp_redirect($this->get_return_url($order));
                    exit;
                } else {
                    //If error
                    $order->update_status('Failed');

                    update_post_meta($order_id, '_transaction_id', $transactions_refrence);

                    $notice      = sprintf(__('Thank you for shopping with us.%1$sYour payment is currently having issues with verification and .%1$sYour order is currently on-hold.%2$sKindly contact us for more information regarding your order and payment status.', 'strowallet-woo-payment-gateway'), '<br />', '<br />');
                    $notice_type = 'notice';

                    // Add Customer Order Note
                    $order->add_order_note($notice, 1);

                    // Add Admin Order Note
                    $admin_order_note = sprintf(__('<strong>Look into this order</strong>%1$sThis order is currently on hold.%2$sReason: Payment can not be verified.%3$swhile the <strong>strowallet Transaction Reference:</strong> %4$s', 'strowallet-woo-payment-gateway'), '<br />', '<br />', '<br />', $transactions_refrence);
                    $order->add_order_note($admin_order_note);

                    function_exists('wc_reduce_stock_levels') ? wc_reduce_stock_levels($order_id) : $order->reduce_order_stock();

                    wc_add_notice($notice, $notice_type);
                }
            }

            wp_redirect(wc_get_page_permalink('cart'));

            exit;
        }

        /**
         * Process the payment.
         *
         * @param int $order_id
         *
         * @return array|void
         */
        public function process_payment($order_id)
        {

            if (is_user_logged_in() && isset($_POST['wc-' . $this->id . '-new-payment-method']) && true === (bool)
            $_POST['wc-' . $this->id . '-new-payment-method'] && $this->saved_cards) {

                update_post_meta($order_id, '_wc_strowallet_save_card', true);
            }

            $order = wc_get_order($order_id);

            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true),
            );
        }

        /**
         * Check if this gateway is enabled and available in the user's country.
         */
        public function is_valid_for_use()
        {

            if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_strowallet_supported_currencies', array('NGN', 'USD')))) {

                $this->msg = sprintf(__("strowallet does not support your store currency. Kindly set it to either 'NGN', 'USD' <a href='%s'>here</a>", 'strowallet-woo-payment-gateway'), admin_url('admin.php?page=wc-settings&tab=general'));

                return false;
            }

            return true;
        }

        /**
         * Load admin scripts.
         */
        public function admin_scripts()
        {

            if ('woocommerce_page_wc-settings' !== get_current_screen()->id) {
                return;
            }

            $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '';

            $strowallet_admin_params = array(
                'plugin_url' => WC_STROWALLET_URL,
            );

            wp_enqueue_script('wc_strowallet_admin', plugins_url('assets/js/strowallet-admin' . $suffix . '.js', WC_STROWALLET_MAIN_FILE), array(), WC_STROWALLET_VERSION, true);

            wp_localize_script('wc_strowallet_admin', 'wc_strowallet_admin_params', $strowallet_admin_params);
        }

        /**
         * Outputs scripts used for strowallet payment.
         */
        public function payment_scripts()
        {

            if (!is_checkout_pay_page()) {
                return;
            }

            if ($this->enabled === 'no') {
                return;
            }

            $order_key = sanitize_text_field(urldecode($_GET['key']));
            $order_id  = absint(get_query_var('order-pay'));

            $order = wc_get_order($order_id);
            $api_verify_url = WC()->api_request_url('WC_strowallet_Payment_Gateway') . '?strowallet_id=' . $order_id;

            $payment_method = method_exists($order, 'get_payment_method') ? $order->get_payment_method() : $order->payment_method;

            if ($this->id !== $payment_method) {
                return;
            }

            if (is_checkout_pay_page() && get_query_var('order-pay')) {

                $email         = method_exists($order, 'get_billing_email') ? $order->get_billing_email() : $order->billing_email;
                $amount        = $order->get_total();
                $txnref        = $order_id . '_' . time();
                $the_order_id  = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
                $the_order_key = method_exists($order, 'get_order_key') ? $order->get_order_key() : $order->order_key;
                $currency      = method_exists($order, 'get_currency') ? $order->get_currency() : $order->order_currency;

                if ($the_order_id == $order_id && $the_order_key == $order_key) {

                    // $strowallet_params['email']        = $email;
                    // $strowallet_params['amount']       = $amount;
                    // $strowallet_params['txnref']       = $txnref;
                    // $strowallet_params['pay_page']     = $this->payment_page;
                    // $strowallet_params['currency']     = $currency;
                    // $strowallet_params['bank_channel'] = 'true';
                    // $strowallet_params['card_channel'] = 'true';
                    // $strowallet_params['first_name'] = $order->get_billing_first_name();
                    // $strowallet_params['last_name'] = $order->get_billing_last_name();
                    // $strowallet_params['phone'] = $order->get_billing_phone();
                    // $strowallet_params['card_channel'] = 'true';

                }
                update_post_meta($order_id, '_strowallet_txn_ref', $txnref);
            }
        }

        /**
         * Check if strowallet merchant details is filled.
         */
        public function admin_notices()
        {

            if ($this->enabled == 'no') {
                return;
            }

            // Check required fields.
            if (!($this->public_key && $this->secret_key)) {
                echo '<div class="error"><p>' . sprintf(__('Please enter your strowallet merchant details <a href="%s">here</a> to be able to use the strowallet WooCommerce plugin.', 'strowallet-woo-payment-gateway'), admin_url('admin.php?page=wc-settings&tab=checkout&section=strowallet')) . '</p></div>';
                return;
            }
        }

        /**
         * Check if strowallet gateway is enabled.
         *
         * @return bool
         */
        public function is_available()
        {

            if ('yes' == $this->enabled) {

                if (!($this->public_key && $this->secret_key)) {

                    return false;
                }

                return true;
            }

            return false;
        }

        /**
         * Add Gateway to checkout page.
         *
         * @param $available_gateways
         *
         * @return mixed
         */
        public function add_gateway_to_checkout($available_gateways)
        {

            if ('no' == $this->enabled) {
                unset($available_gateways[$this->id]);
            }

            return $available_gateways;
        }
    }
}
