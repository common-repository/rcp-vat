<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://www.maxiblog.fr
 * @since             1.0.0
 * @package           Rcp_Vat
 *
 * @wordpress-plugin
 * Plugin Name:       RCP VAT
 * Plugin URI:        http://www.termel.fr
 * Description:       VAT management in Stripe for Restrict Content Pro plugin. Sell inside EU respecting the rules.
 * Version:           1.2.4
 * Author:            Termel
 * Author URI:        http://www.maxiblog.fr
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       rcp-vat
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


if (! function_exists ( 'rcp_vat_log' )) {
    function rcp_vat_log($message) {
        if (WP_DEBUG === true) {
            if (is_array ( $message ) || is_object ( $message )) {
                error_log ( print_r ( $message, true ) );
            } else {
                error_log ( $message );
            }
        }
    }
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-rcp-vat-activator.php
 */
function activate_rcp_vat() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-rcp-vat-activator.php';
	Rcp_Vat_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-rcp-vat-deactivator.php
 */
function deactivate_rcp_vat() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-rcp-vat-deactivator.php';
	Rcp_Vat_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_rcp_vat' );
register_deactivation_hook( __FILE__, 'deactivate_rcp_vat' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-rcp-vat.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_rcp_vat() {

	$plugin = new Rcp_Vat();
	$plugin->run();
	//rcp_vat_log("Plugin running...");

}
run_rcp_vat();

function rcp_vat_get_vat_amount(){
	return 'test';
}
