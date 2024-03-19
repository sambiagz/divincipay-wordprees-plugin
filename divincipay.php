<?php
/*
Plugin Name: Metamask Payment Plugin
Plugin URI: https://divincipay.com
Description: Adds a Metamask payment Option
Version: 1.0.0
Author: Botlogiclabs
Author URI: https://divincipay.com
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Include the plugin settings file
require_once plugin_dir_path(__FILE__) . "settings.php";
require_once __DIR__ . '/vendor/firebase/php-jwt/src/JWT.php';
require_once __DIR__ . '/vendor/firebase/php-jwt/src/Key.php';

use \Firebase\JWT\JWT;
use Firebase\JWT\Key;

if (!defined("ABSPATH")) {
    exit();
}

add_action("plugins_loaded", "divincipay_init_gateway_class");
function divincipay_init_gateway_class()
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
			$this->icon = "";
            $this->method_title = __(
                "DiVinciPay",
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

            // Webhook for Payment Confirmation DivinciPay
            add_action("woocommerce_api_divincipay_payment_confirm", [
                $this,
                "divincipay_payment_confirm_webhook",
            ]);

            // Hook the function to run before the order is made
            add_action("woocommerce_after_checkout_validation", [
                $this,
                "divincipay_before_checkout",
            ]);
        }

        // Webhook that will run after Payment is Confirmed
        function divincipay_payment_confirm_webhook() {
            // Get JWT token from the Authorization header
            $authorization_header = $_SERVER['HTTP_AUTHORIZATION'];

            // Check if Authorization header exists
            if (!$authorization_header) {
                // Return error response if Authorization header is missing
                http_response_code(401);
                exit("Unauthorized: Missing JWT token");
            }

            // Extract JWT token from the Authorization header
            $jwt_token = str_replace('Bearer ', '', $authorization_header);

            // Public key for verifying JWT signature
            $public_key_path = plugin_dir_path( __FILE__ ) . 'keys/public_key.pem';
            $public_key = file_get_contents($public_key_path);

            try {
                // Verify JWT token using the public key
                $decoded = JWT::decode($jwt_token, new Key($public_key, 'RS256'));

                // Extract transaction hash and order ID from the decoded JWT payload
                $transaction_hash = $decoded->transaction_hash;
                $order_id = $decoded->order_id;

                // Get the WooCommerce order
                $order = wc_get_order($order_id);

                // Add order note with transaction hash
                $order->add_order_note("Transaction Hash: " . $transaction_hash);

                // Mark order as payment complete
                $order->payment_complete();

                // Reduce order stock
                wc_reduce_stock_levels($order_id);

                // Update webhook debug option
                update_option("webhook_debug", $_GET);

                // Return success response
                exit("Payment confirmation webhook processed successfully");
            } catch (Exception $e) {
                // Return error response if JWT token verification fails
                http_response_code(401);
                exit("Unauthorized: Invalid JWT token");
            }
        }


        public function divincipay_before_checkout()
        {
            $api_key = get_option("divincipay_plugin_api_key", "");
            if (empty($api_key)) {
                $error = new WP_Error(
                    "empty_api_key",
                    "DivinciPay is not setup Correctly ! Contact the Site Administrator.",
                    [
                        "status_code" => 404,
                    ]
                );
                wc_add_notice($error->get_error_message(), "error");
            }
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
            $api_key = get_option("divincipay_plugin_api_key", "");

            $checkout_data = pay_via_metamask_get_checkout_total($order_id);

            // Check if Payment URL is null
            if (empty($checkout_data["payment_link"])) {
                if (empty($checkout_data["payment_link"])) {
					$order = wc_get_order($order_id);
					error_log($order,0);
					if($order){
						wp_delete_post($order_id,true);
					}
					wc_add_notice('Error Encountered while making the payment. Contact Site Administrator to Fix the issue.', 'error');
					return [
						"result" => "failed"
					];
				}
            }

            // Redirect to the custom payment page URL
            return [
                "result" => "success",
                "redirect" => $checkout_data["payment_link"],
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
function pay_via_metamask_get_checkout_total($order_id)
{
    $api_key = get_option("divincipay_plugin_api_key", "");
	
	$currency = get_woocommerce_currency();

    // Get the WooCommerce cart object
    $cart = WC()->cart;
	
    // Get the total amount from the cart
    $total_cart_price = floatval($cart->get_total("edit"));

    if (empty($total_cart_price)) {
        return new WP_Error("total_amount_missing", "Total amount not found.", [
            "status" => 400,
        ]);
    }
	
    // Check if currency is USD else error
    if ($currency !== "USD") {
        wc_add_notice(
            "DivinciPay only supports USD as the currency. Please change your currency to USD and try again.",
            "error"
        );
        return [
            "result" => "failed"
        ];
    }

    $api_url = "https://divincipay.vercel.app/api/transactions/create";
    $api_url .= "?apiKey=" . urlencode($api_key);
    $api_url .= "&total_amount=" . urlencode($total_cart_price);
    $api_url .= "&order_id=" . urlencode($order_id);
	$api_url .= "&currency=" . urlencode($currency);
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