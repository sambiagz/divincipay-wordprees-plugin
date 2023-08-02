// jQuery(document).ready(function($) {
//     // Handle the click event on the "Pay with Metamask" button
//     $(document).on('click', '#metamask-payment-button', function(e) {
//         e.preventDefault();
//         initiateUSDTPayment()
//     });
// });

function pay_via_metamask_get_checkout_total() {
    jQuery.ajax({
        url: ajaxurl, // The WordPress AJAX URL defined by WordPress
        type: 'POST',
        data: {
            action: 'pay_via_metamask_get_checkout_total'
        },
        success: function (response) {
            // Handle the response from the backend
            if (response.status && response.status === 'error') {
                // Handle the error case
                console.error(response.message);
            } else {
                // Handle the success case
                console.log(response);
                // Now you can use the API URL as needed
                var api_url = response.api_url;
                // Do whatever you want with the API URL here
            }
        },
        error: function (xhr, status, error) {
            // Handle AJAX error
            console.error(error);
        }
    });
}

