<?php
/*
Plugin Name: Metamask Payment Plugin
Plugin URI: https://divincipay.com
Description: Adds a Metamask payment option with dropdown and button.
Version: 1.0.0
Author: Botlogiclabs
Author URI: https://divincipay.com
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Include the plugin settings file
require_once plugin_dir_path(__FILE__) . "settings.php";

if (!defined("ABSPATH")) {
    exit();
}

add_action("plugins_loaded", "my_custom_init_gateway_class");
function my_custom_init_gateway_class()
{
    if (!class_exists("WC_Payment_Gateway")) {
        return;
    }

    class WC_Gateway_Divincipay_Payment extends WC_Payment_Gateway
    {
        public function __construct()
        {
            // Initialize your gateway settings here.
            $this->id = "divincipay";
            $this->icon = ""; // URL to the gateway icon.
            $this->method_title = __(
                "DivinciPay",
                "divincipay-payment-gateway"
            );
            $this->method_description = __(
                "Pay with DivinciPay",
                "divincipay-payment-gateway"
            );

            // Add any additional settings here.
            $this->init_form_fields();
            $this->init_settings();

            // Define the gateway availability and the title displayed during checkout.
            $this->enabled = $this->get_option("enabled");
            $this->title = $this->get_option("title");

            add_action(
                "woocommerce_update_options_payment_gateways_" . $this->id,
                [$this, "process_admin_options"]
            );

            // Webhook for Payment Confirmation Divincipay
            add_action("woocommerce_api_divincipay_payment_confirm", [
                $this,
                "divincipay_payment_confirm_webhook",
            ]);
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                "enabled" => [
                    "title" => __(
                        "Enable/Disable",
                        "divincipay-payment-gateway"
                    ),
                    "type" => "checkbox",
                    "label" => __(
                        "Enable My Custom Gateway",
                        "divincipay-payment-gateway"
                    ),
                    "default" => "yes",
                ],
                "title" => [
                    "title" => __("Title", "divincipay-payment-gateway"),
                    "type" => "text",
                    "description" => __(
                        "This controls the title which the user sees during checkout.",
                        "my-custom-payment-gateway"
                    ),
                    "default" => __(
                        "DivinciPay Payment Gateway",
                        "divincipay-payment-gateway"
                    ),
                    "desc_tip" => true,
                ],
                // Add more settings as needed.
            ];
        }

        public function process_payment($order_id)
        {
			$checkout_data = pay_via_metamask_get_checkout_total();
            // Process the payment and redirect to the custom payment page
            $order = wc_get_order($order_id);
            $order->update_status(
                "on-hold",
                __("Awaiting payment confirmation.", "custom_payment")
            );

            // Redirect to the custom payment page URL
            return [
                "result" => "success",
                "redirect" => $checkout_data["data"]["payment_url"] , // Replace with the ID of your custom payment page
            ];
        }
    }

    // Register the custom payment gateway.
    function divincipay_add_payment_gateway($gateways)
    {
        $gateways[] = "WC_Gateway_Divincipay_Payment";
        return $gateways;
    }
    add_filter(
        "woocommerce_payment_gateways",
        "divincipay_add_payment_gateway"
    );
}

// Callback function to get the checkout total
function pay_via_metamask_get_checkout_total()
{
    // Get the WooCommerce cart object
    $cart = WC()->cart;
    $api_key = get_option("divincipay_plugin_api_key", "");
    // Check if the cart is empty
    if ($cart->is_empty()) {
        return new WP_Error("empty_cart", "Your cart is empty.", [
            "status" => 404,
        ]);
    }
    // Get the total amount from the cart
    $total_cart_price = floatval($cart->get_total("edit"));

    if (empty($total_cart_price)) {
        // If the total amount is not available, return an error or handle it as needed.
        return new WP_Error("total_amount_missing", "Total amount not found.", [
            "status" => 400,
        ]);
    }
    $api_url = "https://divincipay.com/_functions/payment_wp";
    $api_url .= "?apiKey=" . urlencode($api_key);
    $api_url .= "&total_amount=" . urlencode($total_cart_price);
    $args = [
        "headers" => [
            "User-Agent" =>
                "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3",
        ],
    ];
    $response = wp_remote_get($api_url, $args);
    if (
        is_wp_error($response) ||
        wp_remote_retrieve_response_code($response) !== 200
    ) {
        return [
            "status" => is_wp_error($response)
                ? $response->get_error_code()
                : wp_remote_retrieve_response_code($response),
            "data" => json_decode(wp_remote_retrieve_body($response), true),
        ];
    }
    // API call was successful
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data;
}

// Webhook that will run after Payment is Confirmed
function divincipay_payment_confirm_webhook()
{
    $order = wc_get_order($_GET["id"]);
    $order->payment_complete();
    $order->reduce_order_stock();
    update_option("webhook_debug", $_GET);
}
