<?php
// Plugin settings page
function divincipay_plugin_settings_page()
{
    add_menu_page(
        "DivinciPay Settings",
        "DivinciPay",
        "manage_options",
        "divincipay_plugin_settings",
        "divincipay_plugin_render_settings_page",
        "dashicons-admin-generic"
    );
}
add_action("admin_menu", "divincipay_plugin_settings_page");

// Render the settings page content
function divincipay_plugin_render_settings_page()
{
    ?>
  <div class="wrap">
    <h1>Divincipay Plugin Settings</h1>
    <form method="post" action="options.php">
      <?php
      settings_fields("divincipay_plugin_settings_group");
      do_settings_sections("divincipay_plugin_settings");
      submit_button();?>
    </form>
  </div>
  <?php
}

// Register settings and API key field
function divincipay_plugin_register_settings()
{
    register_setting(
        "divincipay_plugin_settings_group",
        "divincipay_plugin_api_key"
    );
    add_settings_section(
        "divincipay_plugin_settings_section",
        "API Key Settings",
        "divincipay_plugin_settings_section_callback",
        "divincipay_plugin_settings"
    );
    add_settings_field(
        "divincipay_plugin_api_key",
        "API Key",
        "divincipay_plugin_api_key_callback",
        "divincipay_plugin_settings",
        "divincipay_plugin_settings_section"
    );
}
add_action("admin_init", "divincipay_plugin_register_settings");

// Section callback
function divincipay_plugin_settings_section_callback()
{
    echo "Enter your API key below:";
}

// API key field callback
function divincipay_plugin_api_key_callback()
{
    $api_key = get_option("divincipay_plugin_api_key");
    echo '<input type="text" name="divincipay_plugin_api_key" value="' .
        esc_attr($api_key) .
        '" />';
}

// Save the API key
function divincipay_plugin_save_api_key()
{
    if (isset($_POST["divincipay_plugin_api_key"])) {
        update_option(
            "divincipay_plugin_api_key",
            sanitize_text_field($_POST["divincipay_plugin_api_key"])
        );
    }
}
add_action("admin_init", "divincipay_plugin_save_api_key");