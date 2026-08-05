<?php
/**
 * Plugin Name: SilentShield – Captcha & Anti-Spam for WordPress (CF7, WPForms, Elementor, WooCommerce)
 * Plugin URI: https://www.forge12.com/product/wordpress-captcha/
 * Description: SilentShield is an all-in-one spam protection plugin. Protects WordPress login, registration, comments, and popular form plugins (CF7, WPForms, Elementor, WooCommerce) with captcha, honeypot, blacklist, IP blocking, and whitelisting for logged-in users.
 * Version: 2.11.0
 * Requires PHP: 7.4
 * Author: Forge12 Interactive GmbH
 * Author URI: https://www.forge12.com
 * Text Domain: captcha-for-contact-form-7
 * Domain Path: /languages
 */
namespace f12_cf7_captcha;


define( 'FORGE12_CAPTCHA_VERSION', '2.11.0' );
define( 'FORGE12_CAPTCHA_SLUG', 'f12-cf7-captcha' );
define( 'FORGE12_CAPTCHA_BASENAME', plugin_basename( __FILE__ ) );


/**
 * Dependencies
 */
require_once( 'autoload.php' );
require_once( 'logger/logger.php' );
require_once( 'core/helpers/uuid.php' );
require_once( 'core/bootstrap.php' );


/**
 * Init the contact form 7 captcha
 *
 * CF7Captcha itself lives in CF7Captcha.class.php and is pulled in by the autoloader
 * registered just above.
 */
CF7Captcha::get_instance();

do_action( 'f12_cf7_captcha_init' );