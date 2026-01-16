<?php

if (!defined('ABSPATH')) {
  exit;
}

class CMP410GONE_Manager {
  const OPTION_KEY = 'cmp410gone_settings';
  const LEGACY_OPTION_KEY = 'cmp_410gone_settings';
  const COOKIE_NAME = 'cmp410gone_consent';
  const LEGACY_COOKIE_NAME = 'cmp_consent';
  const PAGE_SLUG = '410gone-consent-manager';
  const SHORTCODE = 'cmp410gone_manage_cookies';
  const LEGACY_SHORTCODE = 'cmp_manage_cookies';
  const SCRIPT_HANDLE = 'cmp410gone';
  const ADMIN_STYLE_HANDLE = 'cmp410gone-admin';

  private static function consent_mode_defaults() {
    return [
      'analytics_storage' => 'denied',
      'ad_storage' => 'denied',
      'ad_user_data' => 'denied',
      'ad_personalization' => 'denied',
    ];
  }

  private static function get_raw_consent_cookie() {
    $raw = filter_input(INPUT_COOKIE, self::COOKIE_NAME, FILTER_UNSAFE_RAW);
    if (null === $raw || false === $raw) {
      $raw = $_COOKIE[self::COOKIE_NAME] ?? '';
    }

    if (empty($raw)) {
      $raw = filter_input(INPUT_COOKIE, self::LEGACY_COOKIE_NAME, FILTER_UNSAFE_RAW);
      if (null === $raw || false === $raw) {
        $raw = $_COOKIE[self::LEGACY_COOKIE_NAME] ?? '';
      }
    }

    if (empty($raw)) {
      return $raw;
    }

    return rawurldecode((string)$raw);
  }

  private static function parse_consent_cookie() {
    $raw = self::get_raw_consent_cookie();
    if (empty($raw)) {
      return null;
    }

    $candidates = [
      (string)$raw,
      wp_unslash((string)$raw),
      rawurldecode((string)$raw),
      urldecode((string)$raw),
    ];

    foreach ($candidates as $candidate) {
      $value = trim((string)$candidate);
      if ($value === '') {
        continue;
      }

      $len = strlen($value);
      if ($len >= 2) {
        $first = $value[0];
        $last = $value[$len - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
          $value = substr($value, 1, -1);
        }
      }

      $data = json_decode($value, true);
      if (is_array($data)) {
        return $data;
      }
    }

    return null;
  }

  private static function has_consent_cookie() {
    $data = self::parse_consent_cookie();
    if (!is_array($data)) {
      return false;
    }

    $keys = array_keys(self::consent_mode_defaults());
    foreach ($keys as $key) {
      if (isset($data[$key]) && in_array($data[$key], ['granted', 'denied'], true)) {
        return true;
      }
    }

    if (isset($data['analytics']) && is_bool($data['analytics'])) {
      return true;
    }

    if (isset($data['retargeting']) && is_bool($data['retargeting'])) {
      return true;
    }

    return false;
  }

  private static function consent_mode_from_cookie() {
    $defaults = self::consent_mode_defaults();
    $data = self::parse_consent_cookie();
    if (!is_array($data)) {
      return $defaults;
    }

    $consent = $defaults;
    foreach ($defaults as $key => $value) {
      if (isset($data[$key]) && in_array($data[$key], ['granted', 'denied'], true)) {
        $consent[$key] = $data[$key];
      }
    }

    if (isset($data['analytics']) && is_bool($data['analytics'])) {
      $consent['analytics_storage'] = $data['analytics'] ? 'granted' : 'denied';
    }

    if (isset($data['retargeting']) && is_bool($data['retargeting'])) {
      $value = $data['retargeting'] ? 'granted' : 'denied';
      $consent['ad_storage'] = $value;
      $consent['ad_user_data'] = $value;
      $consent['ad_personalization'] = $value;
    }

    return $consent;
  }

  public static function init() {
    add_action('admin_menu', [__CLASS__, 'admin_menu']);
    add_action('admin_init', [__CLASS__, 'register_settings']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    add_filter('plugin_action_links_' . plugin_basename(CMP410GONE_PLUGIN_FILE), [__CLASS__, 'plugin_action_links']);
    add_filter('plugin_row_meta', [__CLASS__, 'filter_plugin_row_meta'], 10, 2);

    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_head_scripts'], 1);
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

    add_action('wp_footer', [__CLASS__, 'render_banner_markup'], 30);

    add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode_manage_cookies']);
  }

  public static function defaults() {
    return [
      'enable' => 1,

      'gtm_id' => '',
      'ga4_id' => '',
      'consent_wait_for_update_ms' => 500,
      'init_datalayer' => 1,
      'tracking_mode' => 'hybrid',

      'privacy_url' => '',
      'cookie_policy_url' => '',
      'banner_title' => __('🍪 Cookie preferences', '410gone-consent-manager'),
      'banner_text' => __('We use essential cookies to run the site and, if you accept, cookies for analytics and advertising retargeting.', '410gone-consent-manager'),
      'btn_accept' => __('Accept', '410gone-consent-manager'),
      'btn_reject' => __('Reject', '410gone-consent-manager'),
      'btn_customize' => __('Customize', '410gone-consent-manager'),
      'btn_save' => __('Save', '410gone-consent-manager'),
      'modal_title' => __('Preferences', '410gone-consent-manager'),
      'modal_desc_essentials' => __('Required for the site to function (always active).', '410gone-consent-manager'),
      'modal_desc_analytics' => __('Analytics measurement (e.g. Google Analytics 4).', '410gone-consent-manager'),
      'modal_desc_retargeting' => __('Personalized ads (managed via GTM).', '410gone-consent-manager'),

      'background_color' => '#ffffff',
      'text_color' => '#0b1621',

      'accept_btn_color' => '#00ceff',
      'accept_btn_text_color' => '#001018',
      'customize_btn_color' => '#f5f5f5',
      'customize_btn_text_color' => '#111111',

      'ttl_days' => 180,

      'debug' => 0,
      'force_show' => 0,
      'overlay_mode' => 'inline',
    ];
  }

  public static function get_settings() {
    $opts = get_option(self::OPTION_KEY, null);
    if (!is_array($opts) || empty($opts)) {
      $legacy = get_option(self::LEGACY_OPTION_KEY, []);
      $opts = is_array($legacy) ? $legacy : [];
    }
    $settings = array_merge(self::defaults(), is_array($opts) ? $opts : []);

    return self::translate_settings($settings);
  }

  public static function plugin_action_links($links) {
    $settings_url = admin_url('options-general.php?page=' . self::PAGE_SLUG);
    return array_merge(['settings' => '<a href="' . esc_url($settings_url) . '">' . esc_html(__('Settings', '410gone-consent-manager')) . '</a>'], $links);
  }

  public static function filter_plugin_row_meta($links, $file) {
    if ($file !== plugin_basename(CMP410GONE_PLUGIN_FILE)) {
      return $links;
    }

    foreach ($links as $index => $link) {
      if (strpos($link, 'plugin-install.php') !== false || strpos($link, 'plugin-information') !== false) {
        unset($links[$index]);
      }
    }

    return $links;
  }

  public static function admin_menu() {
    add_options_page(__('🍪 410Gone Consent Manager — Settings', '410gone-consent-manager'), __('🍪 410Gone Consent Manager', '410gone-consent-manager'), 'manage_options', self::PAGE_SLUG, [__CLASS__, 'settings_page']);
  }

  public static function register_settings() {
    register_setting('cmp410gone_group', self::OPTION_KEY, [
      'type' => 'array',
      'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
      'default' => self::defaults(),
    ]);

    add_settings_section('cmp410gone_design', __('Design', '410gone-consent-manager'), [__CLASS__, 'section_design'], self::PAGE_SLUG);
    add_settings_field('enable', __('Enable CMP', '410gone-consent-manager'), [__CLASS__, 'field_enable'], self::PAGE_SLUG, 'cmp410gone_design');
    add_settings_field('accept_btn_color', __('Accept button color', '410gone-consent-manager'), [__CLASS__, 'field_accept_btn_color'], self::PAGE_SLUG, 'cmp410gone_design');
    add_settings_field('accept_btn_text_color', __('Accept button text color', '410gone-consent-manager'), [__CLASS__, 'field_accept_btn_text_color'], self::PAGE_SLUG, 'cmp410gone_design');
    add_settings_field('background_color', __('Background color', '410gone-consent-manager'), [__CLASS__, 'field_background_color'], self::PAGE_SLUG, 'cmp410gone_design');
    add_settings_field('text_color', __('Text color', '410gone-consent-manager'), [__CLASS__, 'field_text_color'], self::PAGE_SLUG, 'cmp410gone_design');
    add_settings_field('customize_btn_color', __('Customize button color', '410gone-consent-manager'), [__CLASS__, 'field_customize_btn_color'], self::PAGE_SLUG, 'cmp410gone_design');
    add_settings_field('customize_btn_text_color', __('Customize button text color', '410gone-consent-manager'), [__CLASS__, 'field_customize_btn_text_color'], self::PAGE_SLUG, 'cmp410gone_design');

    add_settings_section('cmp410gone_labels', __('Labels', '410gone-consent-manager'), [__CLASS__, 'section_labels'], self::PAGE_SLUG);
    add_settings_field('privacy_url', __('Privacy policy URL', '410gone-consent-manager'), [__CLASS__, 'field_privacy_url'], self::PAGE_SLUG, 'cmp410gone_labels');
    add_settings_field('cookie_policy_url', __('Cookie policy URL (optional)', '410gone-consent-manager'), [__CLASS__, 'field_cookie_policy_url'], self::PAGE_SLUG, 'cmp410gone_labels');
    add_settings_field('banner_title', __('Banner title', '410gone-consent-manager'), [__CLASS__, 'field_banner_title'], self::PAGE_SLUG, 'cmp410gone_labels');
    add_settings_field('banner_text', __('Banner text', '410gone-consent-manager'), [__CLASS__, 'field_banner_text'], self::PAGE_SLUG, 'cmp410gone_labels');
    add_settings_field('btn_labels', __('Button labels', '410gone-consent-manager'), [__CLASS__, 'field_btn_labels'], self::PAGE_SLUG, 'cmp410gone_labels');
    add_settings_field('modal_labels', __('Modal labels', '410gone-consent-manager'), [__CLASS__, 'field_modal_labels'], self::PAGE_SLUG, 'cmp410gone_labels');

    add_settings_section('cmp410gone_tracking', __('Tracking & configuration', '410gone-consent-manager'), [__CLASS__, 'section_tracking'], self::PAGE_SLUG);
    add_settings_field('tracking_mode', __('Tracking mode', '410gone-consent-manager'), [__CLASS__, 'field_tracking_mode'], self::PAGE_SLUG, 'cmp410gone_tracking');
    add_settings_field('gtm_id', __('GTM container ID', '410gone-consent-manager'), [__CLASS__, 'field_gtm_id'], self::PAGE_SLUG, 'cmp410gone_tracking');
    add_settings_field('ga4_id', __('GA4 measurement ID', '410gone-consent-manager'), [__CLASS__, 'field_ga4_id'], self::PAGE_SLUG, 'cmp410gone_tracking');
    add_settings_field('consent_wait_for_update_ms', __('Consent wait_for_update (ms)', '410gone-consent-manager'), [__CLASS__, 'field_wait'], self::PAGE_SLUG, 'cmp410gone_tracking');
    add_settings_field('init_datalayer', __('Initialize dataLayer', '410gone-consent-manager'), [__CLASS__, 'field_init_datalayer'], self::PAGE_SLUG, 'cmp410gone_tracking');
    add_settings_section('cmp410gone_advanced', __('Advanced', '410gone-consent-manager'), [__CLASS__, 'section_advanced'], self::PAGE_SLUG);
    add_settings_field('ttl_days', __('Consent retention (days)', '410gone-consent-manager'), [__CLASS__, 'field_ttl_days'], self::PAGE_SLUG, 'cmp410gone_advanced');
    add_settings_field('debug', __('Debug console', '410gone-consent-manager'), [__CLASS__, 'field_debug'], self::PAGE_SLUG, 'cmp410gone_advanced');
    add_settings_field('force_show', __('Force display (testing)', '410gone-consent-manager'), [__CLASS__, 'field_force_show'], self::PAGE_SLUG, 'cmp410gone_advanced');
    add_settings_field('overlay_mode', __('Overlay blocking timing', '410gone-consent-manager'), [__CLASS__, 'field_overlay_mode'], self::PAGE_SLUG, 'cmp410gone_advanced');
  }

  public static function sanitize_settings($in) {
    $d = self::defaults();
    $out = [];

    $out['enable'] = isset($in['enable']) ? (int)!!$in['enable'] : $d['enable'];
    $out['debug']  = isset($in['debug']) ? (int)!!$in['debug'] : $d['debug'];
    $out['force_show'] = isset($in['force_show']) ? (int)!!$in['force_show'] : $d['force_show'];
    $out['init_datalayer'] = isset($in['init_datalayer']) ? (int)!!$in['init_datalayer'] : $d['init_datalayer'];
    $tracking_mode = isset($in['tracking_mode']) ? sanitize_key((string)$in['tracking_mode']) : $d['tracking_mode'];
    if (!in_array($tracking_mode, ['all_inclusive', 'gtm', 'hybrid'], true)) {
      $tracking_mode = $d['tracking_mode'];
    }
    $out['tracking_mode'] = $tracking_mode;
    $overlay_mode = isset($in['overlay_mode']) ? sanitize_key((string)$in['overlay_mode']) : $d['overlay_mode'];
    if (!in_array($overlay_mode, ['inline', 'js'], true)) {
      $overlay_mode = $d['overlay_mode'];
    }
    $out['overlay_mode'] = $overlay_mode;

    $gtm = isset($in['gtm_id']) ? strtoupper(trim((string)$in['gtm_id'])) : '';
    if ($gtm !== '' && !preg_match('/^GTM-[A-Z0-9]+$/', $gtm)) {
      $gtm = '';
    }
    $out['gtm_id'] = $gtm;

    $ga4 = isset($in['ga4_id']) ? strtoupper(trim((string)$in['ga4_id'])) : '';
    if ($ga4 !== '' && !preg_match('/^G-[A-Z0-9]+$/', $ga4)) {
      $ga4 = '';
    }
    $out['ga4_id'] = $ga4;

    $wait = isset($in['consent_wait_for_update_ms']) ? (int)$in['consent_wait_for_update_ms'] : $d['consent_wait_for_update_ms'];
    if ($wait < 0) {
      $wait = 0;
    }
    if ($wait > 5000) {
      $wait = 5000;
    }
    $out['consent_wait_for_update_ms'] = $wait;

    $out['privacy_url'] = isset($in['privacy_url']) ? esc_url_raw((string)$in['privacy_url']) : $d['privacy_url'];
    $out['cookie_policy_url'] = isset($in['cookie_policy_url']) ? esc_url_raw((string)$in['cookie_policy_url']) : $d['cookie_policy_url'];

    $out['banner_title'] = isset($in['banner_title']) ? sanitize_text_field((string)$in['banner_title']) : $d['banner_title'];
    $out['banner_text']  = isset($in['banner_text']) ? wp_kses_post((string)$in['banner_text']) : $d['banner_text'];

    $out['btn_accept']    = isset($in['btn_accept']) ? sanitize_text_field((string)$in['btn_accept']) : $d['btn_accept'];
    $out['btn_reject']    = isset($in['btn_reject']) ? sanitize_text_field((string)$in['btn_reject']) : $d['btn_reject'];
    $out['btn_customize'] = isset($in['btn_customize']) ? sanitize_text_field((string)$in['btn_customize']) : $d['btn_customize'];
    $out['btn_save']      = isset($in['btn_save']) ? sanitize_text_field((string)$in['btn_save']) : $d['btn_save'];
    $out['modal_title']   = isset($in['modal_title']) ? sanitize_text_field((string)$in['modal_title']) : $d['modal_title'];
    $out['modal_desc_essentials']  = isset($in['modal_desc_essentials']) ? sanitize_text_field((string)$in['modal_desc_essentials']) : $d['modal_desc_essentials'];
    $out['modal_desc_analytics']   = isset($in['modal_desc_analytics']) ? sanitize_text_field((string)$in['modal_desc_analytics']) : $d['modal_desc_analytics'];
    $out['modal_desc_retargeting'] = isset($in['modal_desc_retargeting']) ? sanitize_text_field((string)$in['modal_desc_retargeting']) : $d['modal_desc_retargeting'];

    $bg = isset($in['background_color']) ? trim((string)$in['background_color']) : $d['background_color'];
    if (!preg_match('/^#([A-Fa-f0-9]{6})$/', $bg)) {
      $bg = $d['background_color'];
    }
    $out['background_color'] = $bg;

    $text = isset($in['text_color']) ? trim((string)$in['text_color']) : $d['text_color'];
    if (!preg_match('/^#([A-Fa-f0-9]{6})$/', $text)) {
      $text = $d['text_color'];
    }
    $out['text_color'] = $text;

    $col = isset($in['accept_btn_color']) ? trim((string)$in['accept_btn_color']) : $d['accept_btn_color'];
    if (!preg_match('/^#([A-Fa-f0-9]{6})$/', $col)) {
      $col = $d['accept_btn_color'];
    }
    $out['accept_btn_color'] = $col;

    $tcol = isset($in['accept_btn_text_color']) ? trim((string)$in['accept_btn_text_color']) : $d['accept_btn_text_color'];
    if (!preg_match('/^#([A-Fa-f0-9]{6})$/', $tcol)) {
      $tcol = $d['accept_btn_text_color'];
    }
    $out['accept_btn_text_color'] = $tcol;

    $custom_col = isset($in['customize_btn_color']) ? trim((string)$in['customize_btn_color']) : $d['customize_btn_color'];
    if (!preg_match('/^#([A-Fa-f0-9]{6})$/', $custom_col)) {
      $custom_col = $d['customize_btn_color'];
    }
    $out['customize_btn_color'] = $custom_col;

    $custom_tcol = isset($in['customize_btn_text_color']) ? trim((string)$in['customize_btn_text_color']) : $d['customize_btn_text_color'];
    if (!preg_match('/^#([A-Fa-f0-9]{6})$/', $custom_tcol)) {
      $custom_tcol = $d['customize_btn_text_color'];
    }
    $out['customize_btn_text_color'] = $custom_tcol;

    $ttl = isset($in['ttl_days']) ? (int)$in['ttl_days'] : $d['ttl_days'];
    if ($ttl < 1) {
      $ttl = 1;
    }
    if ($ttl > 3650) {
      $ttl = 3650;
    }
    $out['ttl_days'] = $ttl;

    self::register_translation_strings($out);

    return $out;
  }

  public static function enqueue_admin_assets($hook) {
    if ($hook !== 'settings_page_' . self::PAGE_SLUG) {
      return;
    }

    $base = plugin_dir_path(CMP410GONE_PLUGIN_FILE);
    $url  = plugin_dir_url(CMP410GONE_PLUGIN_FILE);
    $css_ver = file_exists($base . 'assets/admin.css') ? (string)filemtime($base . 'assets/admin.css') : '1.0.0';
    $js_ver = file_exists($base . 'assets/admin.js') ? (string)filemtime($base . 'assets/admin.js') : '1.0.0';

    wp_enqueue_style(self::ADMIN_STYLE_HANDLE, $url . 'assets/admin.css', [], $css_ver);
    wp_enqueue_script('cmp410gone-admin', $url . 'assets/admin.js', [], $js_ver, true);
  }

  public static function settings_page() {
    if (!current_user_can('manage_options')) {
      return;
    }

    $page = self::PAGE_SLUG;

    // Fallback in case hooks didn’t register sections (e.g., custom admin flows).
    if (empty($GLOBALS['wp_settings_sections'][$page])) {
      self::register_settings();
    }
    ?>
    <div class="wrap cmp410-admin">
      <h1><?php esc_html_e('🍪 410Gone Consent Manager — Settings', '410gone-consent-manager'); ?></h1>
      <p><a href="https://www.410-gone.fr" target="_blank" rel="noopener noreferrer">410gone</a></p>

      

      <div class="cmp410-callout">
        <strong><?php esc_html_e('WP Rocket (recommended):', '410gone-consent-manager'); ?></strong>
        <ul style="margin:8px 0 0 18px;">
          <li><?php esc_html_e('File optimization → JavaScript → exclude cmp.js (or 410gone-consent-manager) from “Delay JavaScript execution”.', '410gone-consent-manager'); ?></li>
          <li><?php esc_html_e('Then clear WP Rocket cache + Ctrl+F5 (or use private browsing).', '410gone-consent-manager'); ?></li>
        </ul>
      </div>

      <ul class="cmp410-anchors">
        <li><a href="#cmp410gone_design">🎨 <?php esc_html_e('Design', '410gone-consent-manager'); ?></a></li>
        <li><a href="#cmp410gone_labels">✏️ <?php esc_html_e('Labels', '410gone-consent-manager'); ?></a></li>
        <li><a href="#cmp410gone_tracking">📈 <?php esc_html_e('Tracking & configuration', '410gone-consent-manager'); ?></a></li>
        <li><a href="#cmp410gone_advanced">🛠️ <?php esc_html_e('Advanced', '410gone-consent-manager'); ?></a></li>
      </ul>

      <form method="post" action="options.php">
        <?php settings_fields('cmp410gone_group'); ?>

        <div class="cmp410-panels">
          <?php
            self::render_settings_section($page, 'cmp410gone_design');
            self::render_settings_section($page, 'cmp410gone_labels');
            self::render_settings_section($page, 'cmp410gone_tracking');
            self::render_settings_section($page, 'cmp410gone_advanced');
          ?>
        </div>

        <?php submit_button(__('Save', '410gone-consent-manager')); ?>
      </form>

      <hr />
      <h2><?php esc_html_e('“Manage my cookies” link', '410gone-consent-manager'); ?></h2>
      <p><?php esc_html_e('Shortcode :', '410gone-consent-manager'); ?></p>
      <code>[cmp410gone_manage_cookies label="<?php echo esc_attr(__('Manage my cookies', '410gone-consent-manager')); ?>"]</code>
    </div>
    <?php
  }

  private static function render_settings_section($page, $section_id) {
    global $wp_settings_sections, $wp_settings_fields;

    if (!isset($wp_settings_sections[$page][$section_id])) {
      return;
    }

    $section = $wp_settings_sections[$page][$section_id];

    echo '<section class="cmp410-panel" id="' . esc_attr($section_id) . '">';
    echo '<div class="cmp410-panel__header">';
    echo '<h2>' . esc_html($section['title']) . '</h2>';
    echo '</div>';

    if (!empty($section['callback'])) {
      echo '<div class="cmp410-panel__desc">';
      call_user_func($section['callback'], $section);
      echo '</div>';
    }

    if (!empty($wp_settings_fields[$page][$section_id])) {
      echo '<table class="form-table" role="presentation">';
      do_settings_fields($page, $section_id);
      echo '</table>';
    }

    echo '</section>';
  }

  public static function section_design() {
    $s = self::get_settings();
    echo '<p>' . esc_html__('Customize banner colors and preview desktop/mobile rendering.', '410gone-consent-manager') . '</p>';
    self::render_preview_block($s, 'design');
  }

  public static function section_labels() {
    $s = self::get_settings();
    echo '<p>' . esc_html__('Adjust texts and links. Labels remain compatible with Polylang/WPML and custom filters.', '410gone-consent-manager') . '</p>';
    self::render_preview_block($s, 'labels');
  }

  public static function section_tracking() {
    echo '<p>' . esc_html__('GTM can load early, but Consent Mode v2 starts as', '410gone-consent-manager') . ' <code>denied</code> ' . esc_html__('and updates based on the user choice.', '410gone-consent-manager') . '</p>';
    echo '<p><strong>WP Rocket</strong>: ' . esc_html__('exclude', '410gone-consent-manager') . ' <code>cmp.js</code> ' . esc_html__('from “Delay JS execution”.', '410gone-consent-manager') . '</p>';
  }

  public static function section_advanced() {
    echo '<p>' . esc_html__('Options for debugging, forced tests, and consent retention.', '410gone-consent-manager') . '</p>';
  }

  private static function render_preview_block($settings, $context) {
    $style = sprintf(
      '--cmp410-accept-bg:%1$s;--cmp410-accept-fg:%2$s;--cmp410-customize-bg:%3$s;--cmp410-customize-fg:%4$s;--cmp410-background:%5$s;--cmp410-foreground:%6$s;',
      esc_attr($settings['accept_btn_color']),
      esc_attr($settings['accept_btn_text_color']),
      esc_attr($settings['customize_btn_color']),
      esc_attr($settings['customize_btn_text_color']),
      esc_attr($settings['background_color']),
      esc_attr($settings['text_color'])
    );
    ?>
      <div class="cmp410-preview" data-preview-root style="<?php echo esc_attr($style); ?>">
      <div class="cmp410-preview-grid">
        <div class="cmp410-preview-device">
          <div class="cmp410-preview-label"><?php esc_html_e('Desktop preview', '410gone-consent-manager'); ?></div>
          <?php self::render_preview_content($settings, 'desktop'); ?>
        </div>
        <div class="cmp410-preview-device is-mobile">
          <div class="cmp410-preview-label"><?php esc_html_e('Mobile preview', '410gone-consent-manager'); ?></div>
          <?php self::render_preview_content($settings, 'mobile'); ?>
        </div>
      </div>
    </div>
    <?php
  }

  private static function render_preview_content($settings, $device) {
    $privacy = !empty($settings['privacy_url']) ? esc_url($settings['privacy_url']) : '';
    $cookies = !empty($settings['cookie_policy_url']) ? esc_url($settings['cookie_policy_url']) : '';
    ?>
    <div class="cmp410-preview-frame">
      <div class="cmp410-preview-inner">
        <div class="cmp410-preview-surface cmp410-banner-preview" aria-hidden="true">
          <div class="cmp410-title" data-preview-bind="banner_title"><?php echo esc_html($settings['banner_title']); ?></div>
          <div class="cmp410-desc">
            <span data-preview-bind="banner_text"><?php echo esc_html(wp_strip_all_tags($settings['banner_text'])); ?></span>
              <?php if ($privacy): ?>
                <a href="<?php echo esc_url($privacy); ?>" class="cmp410-link" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Privacy policy', '410gone-consent-manager'); ?></a>
              <?php endif; ?>
              <?php if ($cookies): ?>
                <span class="cmp410-sep">·</span>
                <a href="<?php echo esc_url($cookies); ?>" class="cmp410-link" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Cookie policy', '410gone-consent-manager'); ?></a>
              <?php endif; ?>
          </div>

          <div class="cmp410-actions">
            <button type="button" class="cmp410-btn cmp410-btn-ghost" data-preview="customize_btn_color"><span data-preview-bind="btn_customize"><?php echo esc_html($settings['btn_customize']); ?></span></button>
            <button type="button" class="cmp410-btn cmp410-btn-outline"><span data-preview-bind="btn_reject"><?php echo esc_html($settings['btn_reject']); ?></span></button>
            <button type="button" class="cmp410-btn cmp410-btn-primary" data-preview="accept_btn_color"><span data-preview-bind="btn_accept"><?php echo esc_html($settings['btn_accept']); ?></span></button>
          </div>
        </div>

        <div class="cmp410-preview-surface cmp410-modal-preview" aria-hidden="true">
          <div class="cmp410-modal-header">
            <div class="cmp410-modal-title" data-preview-bind="modal_title"><?php echo esc_html($settings['modal_title']); ?></div>
            <span aria-hidden="true">×</span>
          </div>
          <div class="cmp410-row">
            <div>
              <div class="cmp410-row-title"><?php esc_html_e('Essentials', '410gone-consent-manager'); ?></div>
              <div class="cmp410-row-desc" data-preview-bind="modal_desc_essentials"><?php echo esc_html($settings['modal_desc_essentials']); ?></div>
            </div>
            <input type="checkbox" checked disabled />
          </div>
          <div class="cmp410-row">
            <div>
              <div class="cmp410-row-title"><?php esc_html_e('Analytics', '410gone-consent-manager'); ?></div>
              <div class="cmp410-row-desc" data-preview-bind="modal_desc_analytics"><?php echo esc_html($settings['modal_desc_analytics']); ?></div>
            </div>
            <input type="checkbox" checked />
          </div>
          <div class="cmp410-row">
            <div>
              <div class="cmp410-row-title"><?php esc_html_e('Retargeting', '410gone-consent-manager'); ?></div>
              <div class="cmp410-row-desc" data-preview-bind="modal_desc_retargeting"><?php echo esc_html($settings['modal_desc_retargeting']); ?></div>
            </div>
            <input type="checkbox" />
          </div>
          <div class="cmp410-actions-modal">
            <button type="button" class="cmp410-btn cmp410-btn-outline"><span data-preview-bind="btn_reject"><?php echo esc_html($settings['btn_reject']); ?></span></button>
            <button type="button" class="cmp410-btn cmp410-btn-primary" data-preview="accept_btn_color"><span data-preview-bind="btn_save"><?php echo esc_html($settings['btn_save']); ?></span></button>
          </div>
        </div>
      </div>
    </div>
    <?php
  }

  public static function field_enable() {
    $s = self::get_settings(); ?>
    <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable]" value="1" <?php checked(1, (int)$s['enable']); ?> /> <?php esc_html_e('Enable banner display', '410gone-consent-manager'); ?></label>
  <?php }

  public static function field_tracking_mode() {
    $s = self::get_settings(); ?>
    <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[tracking_mode]">
      <option value="all_inclusive" <?php selected($s['tracking_mode'], 'all_inclusive'); ?>><?php esc_html_e('All inclusive', '410gone-consent-manager'); ?></option>
      <option value="gtm" <?php selected($s['tracking_mode'], 'gtm'); ?>><?php esc_html_e('GTM only', '410gone-consent-manager'); ?></option>
      <option value="hybrid" <?php selected($s['tracking_mode'], 'hybrid'); ?>><?php esc_html_e('Hybrid', '410gone-consent-manager'); ?></option>
    </select>
    <div class="description">
      <div id="all_inclusive" class="cmp410-tracking-legend"><strong><?php esc_html_e('All inclusive:', '410gone-consent-manager'); ?></strong> <?php esc_html_e('CMP initializes dataLayer and loads GTM/GA4 after consent is read.', '410gone-consent-manager'); ?></div>
      <div id="gtm" class="cmp410-tracking-legend"><strong><?php esc_html_e('GTM only:', '410gone-consent-manager'); ?></strong> <?php esc_html_e('everything is handled in GTM (including dataLayer).', '410gone-consent-manager'); ?></div>
      <div id="hybrid" class="cmp410-tracking-legend"><strong><?php esc_html_e('Hybrid:', '410gone-consent-manager'); ?></strong> <?php esc_html_e('CMP initializes dataLayer and loads GTM after consent is read.', '410gone-consent-manager'); ?></div>
    </div>
  <?php }

  public static function field_gtm_id() {
    $s = self::get_settings(); ?>
    <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[gtm_id]" value="<?php echo esc_attr($s['gtm_id']); ?>" placeholder="GTM-XXXXXXX" class="regular-text" />
    <p class="description"><?php esc_html_e('GTM loads early in GTM mode, otherwise it is loaded after consent is read. In GTM, configure tags to require consent (analytics/ads).', '410gone-consent-manager'); ?></p>
  <?php }

  public static function field_ga4_id() {
    $s = self::get_settings();
    $ga4_id = isset($s['ga4_id']) ? (string)$s['ga4_id'] : '';
    ?>
    <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[ga4_id]" value="<?php echo esc_attr($ga4_id); ?>" placeholder="G-XXXXXXXXXX" class="regular-text" />
    <p class="description"><?php esc_html_e('GA4 configuration tag ID:', '410gone-consent-manager'); ?> <strong><?php echo esc_html($ga4_id); ?></strong></p>
  <?php }

  public static function field_wait() {
    $s = self::get_settings(); ?>
    <input type="number" min="0" max="5000" name="<?php echo esc_attr(self::OPTION_KEY); ?>[consent_wait_for_update_ms]" value="<?php echo (int)$s['consent_wait_for_update_ms']; ?>" />
    <p class="description"><?php esc_html_e('Sent to wait_for_update. Typical values: 300–800ms.', '410gone-consent-manager'); ?></p>
  <?php }

  public static function field_init_datalayer() {
    $s = self::get_settings(); ?>
    <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[init_datalayer]" value="1" <?php checked(1, (int)$s['init_datalayer']); ?> /> <?php esc_html_e('Initialize an empty dataLayer in the head.', '410gone-consent-manager'); ?></label>
    <p class="description"><?php esc_html_e('Leave unchecked to avoid initializing dataLayer to denied (to avoid conflict when having cache strategy with plugin or external like Cloudflare).', '410gone-consent-manager'); ?></p>
  <?php }

  public static function field_privacy_url() {
    $s = self::get_settings(); ?>
    <input type="url" name="<?php echo esc_attr(self::OPTION_KEY); ?>[privacy_url]" value="<?php echo esc_attr($s['privacy_url']); ?>" class="regular-text" />
  <?php }

  public static function field_cookie_policy_url() {
    $s = self::get_settings(); ?>
    <input type="url" name="<?php echo esc_attr(self::OPTION_KEY); ?>[cookie_policy_url]" value="<?php echo esc_attr($s['cookie_policy_url']); ?>" class="regular-text" />
  <?php }

  public static function field_banner_title() {
    $s = self::get_settings(); ?>
    <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[banner_title]" value="<?php echo esc_attr($s['banner_title']); ?>" class="regular-text" data-preview-bind="banner_title" />
  <?php }

  public static function field_banner_text() {
    $s = self::get_settings(); ?>
    <textarea name="<?php echo esc_attr(self::OPTION_KEY); ?>[banner_text]" rows="4" class="large-text" data-preview-bind="banner_text"><?php echo esc_textarea($s['banner_text']); ?></textarea>
  <?php }

  public static function field_btn_labels() {
    $s = self::get_settings(); ?>
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; max-width:820px;">
      <div>
        <label><?php esc_html_e('Accept', '410gone-consent-manager'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[btn_accept]" value="<?php echo esc_attr($s['btn_accept']); ?>" class="regular-text" data-preview-bind="btn_accept" />
      </div>
      <div>
        <label><?php esc_html_e('Reject', '410gone-consent-manager'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[btn_reject]" value="<?php echo esc_attr($s['btn_reject']); ?>" class="regular-text" data-preview-bind="btn_reject" />
      </div>
      <div>
        <label><?php esc_html_e('Customize', '410gone-consent-manager'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[btn_customize]" value="<?php echo esc_attr($s['btn_customize']); ?>" class="regular-text" data-preview-bind="btn_customize" />
      </div>
      <div>
        <label><?php esc_html_e('Save', '410gone-consent-manager'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[btn_save]" value="<?php echo esc_attr($s['btn_save']); ?>" class="regular-text" data-preview-bind="btn_save" />
      </div>
    </div>
  <?php }

  public static function field_modal_labels() {
    $s = self::get_settings(); ?>
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; max-width:820px;">
      <div>
        <label><?php esc_html_e('Modal title', '410gone-consent-manager'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[modal_title]" value="<?php echo esc_attr($s['modal_title']); ?>" class="regular-text" data-preview-bind="modal_title" />
      </div>
      <div>
        <label><?php esc_html_e('Essentials subtitle', '410gone-consent-manager'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[modal_desc_essentials]" value="<?php echo esc_attr($s['modal_desc_essentials']); ?>" class="regular-text" data-preview-bind="modal_desc_essentials" />
      </div>
      <div>
        <label><?php esc_html_e('Analytics subtitle', '410gone-consent-manager'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[modal_desc_analytics]" value="<?php echo esc_attr($s['modal_desc_analytics']); ?>" class="regular-text" data-preview-bind="modal_desc_analytics" />
      </div>
      <div>
        <label><?php esc_html_e('Retargeting subtitle', '410gone-consent-manager'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[modal_desc_retargeting]" value="<?php echo esc_attr($s['modal_desc_retargeting']); ?>" class="regular-text" data-preview-bind="modal_desc_retargeting" />
      </div>
    </div>
  <?php }

  public static function field_accept_btn_color() {
    $s = self::get_settings(); ?>
    <div class="cmp410-color-control">
      <input type="color" class="cmp410-color-picker" data-target="accept_btn_color" value="<?php echo esc_attr($s['accept_btn_color']); ?>" aria-label="<?php esc_attr_e('Choose the accept button color', '410gone-consent-manager'); ?>" />
      <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[accept_btn_color]" value="<?php echo esc_attr($s['accept_btn_color']); ?>" class="regular-text cmp410-color-value" data-target="accept_btn_color" placeholder="#00ceff" pattern="^#[A-Fa-f0-9]{6}$" />
    </div>
    <p class="description"><?php esc_html_e('Accept button background color (hex e.g. #00ceff).', '410gone-consent-manager'); ?></p>
  <?php }

  public static function field_accept_btn_text_color() {
    $s = self::get_settings(); ?>
    <div class="cmp410-color-control">
      <input type="color" class="cmp410-color-picker" data-target="accept_btn_text_color" value="<?php echo esc_attr($s['accept_btn_text_color']); ?>" aria-label="<?php esc_attr_e('Choose the accept button text color', '410gone-consent-manager'); ?>" />
      <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[accept_btn_text_color]" value="<?php echo esc_attr($s['accept_btn_text_color']); ?>" class="regular-text cmp410-color-value" data-target="accept_btn_text_color" placeholder="#ffffff" pattern="^#[A-Fa-f0-9]{6}$" />
    </div>
    <p class="description"><?php esc_html_e('Accept button text color (hex e.g. #001018 or #ffffff).', '410gone-consent-manager'); ?></p>
  <?php }

  public static function field_background_color() {
    $s = self::get_settings(); ?>
    <div class="cmp410-color-control">
      <input type="color" class="cmp410-color-picker" data-target="background_color" value="<?php echo esc_attr($s['background_color']); ?>" aria-label="<?php esc_attr_e('Choose the banner and modal background color', '410gone-consent-manager'); ?>" />
      <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[background_color]" value="<?php echo esc_attr($s['background_color']); ?>" class="regular-text cmp410-color-value" data-target="background_color" placeholder="#ffffff" pattern="^#[A-Fa-f0-9]{6}$" />
    </div>
    <p class="description"><?php esc_html_e('Background color applied to the banner and customization modal.', '410gone-consent-manager'); ?></p>
  <?php }

  public static function field_text_color() {
    $s = self::get_settings(); ?>
    <div class="cmp410-color-control">
      <input type="color" class="cmp410-color-picker" data-target="text_color" value="<?php echo esc_attr($s['text_color']); ?>" aria-label="<?php esc_attr_e('Choose the default text color', '410gone-consent-manager'); ?>" />
      <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[text_color]" value="<?php echo esc_attr($s['text_color']); ?>" class="regular-text cmp410-color-value" data-target="text_color" placeholder="#0b1621" pattern="^#[A-Fa-f0-9]{6}$" />
    </div>
    <p class="description"><?php esc_html_e('Primary text color used in the banner and modal.', '410gone-consent-manager'); ?></p>
  <?php }

  public static function field_customize_btn_color() {
    $s = self::get_settings(); ?>
    <div class="cmp410-color-control">
      <input type="color" class="cmp410-color-picker" data-target="customize_btn_color" value="<?php echo esc_attr($s['customize_btn_color']); ?>" aria-label="<?php esc_attr_e('Choose the customize button color', '410gone-consent-manager'); ?>" />
      <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[customize_btn_color]" value="<?php echo esc_attr($s['customize_btn_color']); ?>" class="regular-text cmp410-color-value" data-target="customize_btn_color" placeholder="#f5f5f5" pattern="^#[A-Fa-f0-9]{6}$" />
    </div>
    <p class="description"><?php esc_html_e('Customize button background color (hex e.g. #f5f5f5).', '410gone-consent-manager'); ?></p>
  <?php }

  public static function field_customize_btn_text_color() {
    $s = self::get_settings(); ?>
    <div class="cmp410-color-control">
      <input type="color" class="cmp410-color-picker" data-target="customize_btn_text_color" value="<?php echo esc_attr($s['customize_btn_text_color']); ?>" aria-label="<?php esc_attr_e('Choose the customize button text color', '410gone-consent-manager'); ?>" />
      <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[customize_btn_text_color]" value="<?php echo esc_attr($s['customize_btn_text_color']); ?>" class="regular-text cmp410-color-value" data-target="customize_btn_text_color" placeholder="#111111" pattern="^#[A-Fa-f0-9]{6}$" />
    </div>
    <p class="description"><?php esc_html_e('Customize button text color (hex e.g. #111111).', '410gone-consent-manager'); ?></p>
  <?php }

  public static function field_ttl_days() {
    $s = self::get_settings(); ?>
    <input type="number" min="1" max="3650" name="<?php echo esc_attr(self::OPTION_KEY); ?>[ttl_days]" value="<?php echo (int)$s['ttl_days']; ?>" />
  <?php }

  public static function field_debug() {
    $s = self::get_settings(); ?>
    <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[debug]" value="1" <?php checked(1, (int)$s['debug']); ?> /> <?php esc_html_e('Enable console logs', '410gone-consent-manager'); ?></label>
  <?php }

  public static function field_force_show() {
    $s = self::get_settings(); ?>
    <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[force_show]" value="1" <?php checked(1, (int)$s['force_show']); ?> /> <?php esc_html_e('Always show the banner (ignore cookie)', '410gone-consent-manager'); ?></label>
  <?php }

  public static function field_overlay_mode() {
    $s = self::get_settings();
    ?>
    <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[overlay_mode]">
      <option value="inline" <?php selected($s['overlay_mode'], 'inline'); ?>><?php esc_html_e('Apply overlay immediately (inline CSS)', '410gone-consent-manager'); ?></option>
      <option value="js" <?php selected($s['overlay_mode'], 'js'); ?>><?php esc_html_e('Apply overlay after JavaScript', '410gone-consent-manager'); ?></option>
    </select>
    <p class="description"><?php esc_html_e('Controls when the background overlay blocks clicks outside the banner.', '410gone-consent-manager'); ?></p>
  <?php }

  public static function enqueue_head_scripts() {
    $s = self::get_settings();
    $tracking_mode = $s['tracking_mode'] ?? 'hybrid';
    $init_datalayer = (int)$s['init_datalayer'] && in_array($tracking_mode, ['all_inclusive', 'hybrid'], true);

    if ($init_datalayer) {
      $wait_ms = max(0, min(5000, (int)$s['consent_wait_for_update_ms']));
      wp_register_script('cmp410gone-consent-default', '', [], null, false);

      if ($tracking_mode === 'all_inclusive') {
        $cookie_name = self::COOKIE_NAME;
        $legacy_cookie = self::LEGACY_COOKIE_NAME;
        $gtm_id = esc_js($s['gtm_id']);
        $ga4_id = esc_js($s['ga4_id']);
        $inline = "(function(){\n"
          . "window.dataLayer = window.dataLayer || [];\n"
          . "window.gtag = window.gtag || function(){dataLayer.push(arguments);};\n"
          . "gtag('consent','default',{\n"
          . "  analytics_storage:'denied',\n"
          . "  ad_storage:'denied',\n"
          . "  ad_user_data:'denied',\n"
          . "  ad_personalization:'denied',\n"
          . "  wait_for_update:{$wait_ms}\n"
          . "});\n"
          . "function readCmpCookie(){\n"
          . "  var match=document.cookie.match(/(?:^|;\\s*)(" . preg_quote($cookie_name, '/') . "|" . preg_quote($legacy_cookie, '/') . ")=([^;]+)/);\n"
          . "  if(!match){return null;}\n"
          . "  try{return JSON.parse(decodeURIComponent(match[2]));}catch(e){return null;}\n"
          . "}\n"
          . "var consent=readCmpCookie();\n"
          . "if(consent && consent.analytics_storage==='granted'){\n"
          . "  gtag('consent','update',{\n"
          . "    analytics_storage:'granted',\n"
          . "    ad_storage:consent.ad_storage||'denied',\n"
          . "    ad_user_data:consent.ad_user_data||'denied',\n"
          . "    ad_personalization:consent.ad_personalization||'denied'\n"
          . "  });\n"
          . "  if('{$ga4_id}'){\n"
          . "    gtag('js', new Date());\n"
          . "    gtag('config', '{$ga4_id}');\n"
          . "    var ga=document.createElement('script');ga.async=true;ga.src='https://www.googletagmanager.com/gtag/js?id={$ga4_id}';document.head.appendChild(ga);\n"
          . "  }\n"
          . "  window.dataLayer.push({event:'ga4_after_consent'});\n"
          . "  if('{$gtm_id}'){\n"
          . "    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$gtm_id}');\n"
          . "  }\n"
          . "}\n"
          . "})();";
      } else {
        $inline = 'window.dataLayer = window.dataLayer || [];';
      }

      wp_add_inline_script('cmp410gone-consent-default', $inline);
      wp_enqueue_script('cmp410gone-consent-default');
    }

    if (!(int)$s['enable'] || empty($s['gtm_id']) || $tracking_mode !== 'gtm') {
      return;
    }

    $gtm_src = 'https://www.googletagmanager.com/gtm.js?id=' . rawurlencode($s['gtm_id']);
    wp_register_script('cmp410gone-gtm', $gtm_src, ['cmp410gone-consent-default'], null, false);
    wp_enqueue_script('cmp410gone-gtm');
  }

  public static function enqueue_assets() {
    $s = self::get_settings();
    if (!(int)$s['enable']) {
      return;
    }

    $base = plugin_dir_path(CMP410GONE_PLUGIN_FILE);
    $url  = plugin_dir_url(CMP410GONE_PLUGIN_FILE);

    $css_ver = file_exists($base . 'assets/cmp.css') ? (string)filemtime($base . 'assets/cmp.css') : '1.0.0';
    $js_ver  = file_exists($base . 'assets/cmp.js')  ? (string)filemtime($base . 'assets/cmp.js')  : '1.0.0';

    wp_enqueue_style(self::SCRIPT_HANDLE, $url . 'assets/cmp.css', [], $css_ver);

    $overlay_active = false;
    $overlay_bg = $overlay_active ? 'rgba(0,0,0,.35)' : 'transparent';
    $overlay_pe = $overlay_active ? 'auto' : 'none';
    $overlay_display = $overlay_active ? 'block' : 'none';
    $inline = sprintf(
      ':root{--cmp410-accept-bg:%s;--cmp410-accept-fg:%s;--cmp410-customize-bg:%s;--cmp410-customize-fg:%s;--cmp410-surface:%s;--cmp410-text:%s;--cmp410-background:%s;--cmp410-foreground:%s;--cmp410-overlay-bg:%s;--cmp410-overlay-pe:%s;--cmp410-overlay-display:%s;}',
      esc_html($s['accept_btn_color']),
      esc_html($s['accept_btn_text_color']),
      esc_html($s['customize_btn_color']),
      esc_html($s['customize_btn_text_color']),
      esc_html($s['background_color']),
      esc_html($s['text_color']),
      esc_html($s['background_color']),
      esc_html($s['text_color']),
      $overlay_bg,
      $overlay_pe,
      $overlay_display
    );
    wp_add_inline_style(self::SCRIPT_HANDLE, $inline);

    wp_enqueue_script(self::SCRIPT_HANDLE, $url . 'assets/cmp.js', [], $js_ver, true);

    wp_localize_script(self::SCRIPT_HANDLE, 'CMP410GONE', [
      'cookieName' => self::COOKIE_NAME,
      'legacyCookieName' => self::LEGACY_COOKIE_NAME,
      'ttlDays' => (int)$s['ttl_days'],
      'privacyUrl' => esc_url_raw($s['privacy_url']),
      'cookiePolicyUrl' => esc_url_raw($s['cookie_policy_url']),
      'debug' => (bool)$s['debug'],
      'forceShow' => (bool)$s['force_show'],
      'overlayMode' => $s['overlay_mode'],
      'trackingMode' => $s['tracking_mode'] ?? 'hybrid',
      'gtmId' => $s['gtm_id'],
      'ga4Id' => $s['ga4_id'],
    ]);
  }

  public static function render_banner_markup() {
    static $done = false;
    if ($done) {
      return;
    }
    $done = true;

    $s = self::get_settings();
    if (!(int)$s['enable']) {
      return;
    }

    $has_cookie = self::has_consent_cookie();
    $aria_hidden = $has_cookie && !(int)$s['force_show'] ? 'true' : 'false';

    $privacy = !empty($s['privacy_url']) ? esc_url($s['privacy_url']) : '';
    $cookies = !empty($s['cookie_policy_url']) ? esc_url($s['cookie_policy_url']) : '';
    ?>
    <div class="cmp410-wrap" id="cmp410" aria-hidden="<?php echo esc_attr($aria_hidden); ?>">
      <div class="cmp410-overlay" aria-hidden="true"></div>
      <div class="cmp410-banner" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Cookie management', '410gone-consent-manager'); ?>">
        <div class="cmp410-text">
          <div class="cmp410-title"><?php echo esc_html($s['banner_title']); ?></div>
          <div class="cmp410-desc">
            <?php echo wp_kses_post($s['banner_text']); ?>
              <?php if ($privacy): ?>
                <a href="<?php echo esc_url($privacy); ?>" class="cmp410-link" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Privacy policy', '410gone-consent-manager'); ?></a>
              <?php endif; ?>
              <?php if ($cookies): ?>
                <span class="cmp410-sep">·</span>
                <a href="<?php echo esc_url($cookies); ?>" class="cmp410-link" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Cookie policy', '410gone-consent-manager'); ?></a>
              <?php endif; ?>
          </div>
        </div>

        <div class="cmp410-actions">
          <button class="cmp410-btn cmp410-btn-ghost" data-cmp410="customize"><?php echo esc_html($s['btn_customize']); ?></button>
          <button class="cmp410-btn cmp410-btn-outline" data-cmp410="reject"><?php echo esc_html($s['btn_reject']); ?></button>
          <button class="cmp410-btn cmp410-btn-primary" data-cmp410="accept"><?php echo esc_html($s['btn_accept']); ?></button>
        </div>
      </div>

      <div class="cmp410-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Cookie preferences', '410gone-consent-manager'); ?>">
        <div class="cmp410-modal-header">
          <div class="cmp410-modal-title"><?php echo esc_html($s['modal_title']); ?></div>
          <button class="cmp410-x" data-cmp410="close" aria-label="<?php esc_attr_e('Close', '410gone-consent-manager'); ?>">×</button>
        </div>

        <div class="cmp410-modal-body">
          <div class="cmp410-row">
            <div class="cmp410-row-text">
              <div class="cmp410-row-title"><?php esc_html_e('Essentials', '410gone-consent-manager'); ?></div>
              <div class="cmp410-row-desc"><?php echo esc_html($s['modal_desc_essentials']); ?></div>
            </div>
            <div class="cmp410-toggle">
              <input type="checkbox" checked disabled />
            </div>
          </div>

          <div class="cmp410-row">
            <div class="cmp410-row-text">
              <div class="cmp410-row-title"><?php esc_html_e('Analytics', '410gone-consent-manager'); ?></div>
              <div class="cmp410-row-desc"><?php echo esc_html($s['modal_desc_analytics']); ?></div>
            </div>
            <div class="cmp410-toggle">
              <input type="checkbox" id="cmp410-analytics" checked />
            </div>
          </div>

          <div class="cmp410-row">
            <div class="cmp410-row-text">
              <div class="cmp410-row-title"><?php esc_html_e('Retargeting', '410gone-consent-manager'); ?></div>
              <div class="cmp410-row-desc"><?php echo esc_html($s['modal_desc_retargeting']); ?></div>
            </div>
            <div class="cmp410-toggle">
              <input type="checkbox" id="cmp410-retargeting" checked />
            </div>
          </div>
        </div>

        <div class="cmp410-modal-actions">
          <button class="cmp410-btn cmp410-btn-outline" data-cmp410="save"><?php echo esc_html($s['btn_save']); ?></button>
        </div>
      </div>
    </div>

    <?php if (!empty($s['gtm_id'])): ?>
      <noscript>
        <iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($s['gtm_id']); ?>"
                height="0" width="0" style="display:none;visibility:hidden"></iframe>
      </noscript>
    <?php endif; ?>
    <?php
  }

  public static function shortcode_manage_cookies($atts) {
    $atts = shortcode_atts(['label' => __('Manage my cookies', '410gone-consent-manager')], $atts, self::SHORTCODE);
    $label = esc_html($atts['label']);
    return '<a href="#" onclick="window.CMP410GONE_open && window.CMP410GONE_open(); return false;">' . $label . '</a>';
  }

  private static function translate_settings($settings) {
    $translatable = [
      'banner_title',
      'banner_text',
      'btn_accept',
      'btn_reject',
      'btn_customize',
      'btn_save',
      'modal_title',
      'modal_desc_essentials',
      'modal_desc_analytics',
      'modal_desc_retargeting',
      'privacy_url',
      'cookie_policy_url',
    ];

    foreach ($translatable as $key) {
      if (!isset($settings[$key])) {
        continue;
      }
      $settings[$key] = self::translate_text($key, $settings[$key]);
      $settings[$key] = apply_filters('cmp410gone_translate_setting', $settings[$key], $key, $settings);
    }

    return $settings;
  }

  private static function register_translation_strings($settings) {
    $fields = [
      'banner_title' => __('Banner title', '410gone-consent-manager'),
      'banner_text' => __('Banner text', '410gone-consent-manager'),
      'btn_accept' => __('Accept button', '410gone-consent-manager'),
      'btn_reject' => __('Reject button', '410gone-consent-manager'),
      'btn_customize' => __('Customize button', '410gone-consent-manager'),
      'btn_save' => __('Save button', '410gone-consent-manager'),
      'modal_title' => __('Customization modal title', '410gone-consent-manager'),
      'modal_desc_essentials' => __('Essentials subtitle', '410gone-consent-manager'),
      'modal_desc_analytics' => __('Analytics subtitle', '410gone-consent-manager'),
      'modal_desc_retargeting' => __('Retargeting subtitle', '410gone-consent-manager'),
      'privacy_url' => __('Privacy policy URL', '410gone-consent-manager'),
      'cookie_policy_url' => __('Cookie policy URL', '410gone-consent-manager'),
    ];

    foreach ($fields as $key => $label) {
      if (!isset($settings[$key])) {
        continue;
      }
      if (function_exists('pll_register_string')) {
        pll_register_string('cmp410gone_' . $key, $settings[$key], 'CMP 410gone', false);
      }

      if (has_filter('wpml_register_single_string')) {
        do_action('wpml_register_single_string', 'CMP 410gone', 'cmp410gone_' . $key, $settings[$key]);
      }
    }
  }

  private static function translate_text($key, $value) {
    $translated = $value;

    if (function_exists('pll__')) {
      $translated = pll__($translated);
    } elseif (has_filter('wpml_translate_single_string')) {
      $translated = apply_filters('wpml_translate_single_string', $translated, 'CMP 410gone', 'cmp410gone_' . $key);
    }

    return $translated;
  }
}
