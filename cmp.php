<?php
/**
 * Plugin Name: 410Gone Consent Manager for Google Consent Mode and GTM
 * Plugin URI: https://www.410-gone.fr/blog/plugin-cmp-wordpress-gratuit-consent-mode-v2-gtm.html
 * Description: Lightweight CMP (Accept / Reject / Customize) with Google Consent Mode v2 and GTM support.
 * Version: 1.3.0
 * Author: 410 Gone
 *  Author URI: https://www.410-gone.fr
 *
 * License: GPLv2 or later
 * Text Domain: 410gone-consent-manager
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
  exit;
}

if (!defined('CMP_410GONE_PLUGIN_FILE')) {
  define('CMP_410GONE_PLUGIN_FILE', __FILE__);
}

require_once plugin_dir_path(__FILE__) . 'includes/class-cmp-410gone.php';

CMP_410GONE::init();
