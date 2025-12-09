<?php

if (!defined('ABSPATH')) {
  exit;
}

class CMP_410GONE {
  const OPTION_KEY = 'cmp_410gone_settings';
  const COOKIE_NAME = 'cmp_consent';

  private static function consent_mode_defaults() {
    return [
      'analytics_storage' => 'denied',
      'ad_storage' => 'denied',
      'ad_user_data' => 'denied',
      'ad_personalization' => 'denied',
    ];
  }

  private static function consent_mode_from_cookie() {
    $defaults = self::consent_mode_defaults();

    if (empty($_COOKIE[self::COOKIE_NAME])) {
      return $defaults;
    }

    $raw = wp_unslash((string)$_COOKIE[self::COOKIE_NAME]);
    $data = json_decode($raw, true);

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
    add_filter('plugin_action_links_' . plugin_basename(CMP_410GONE_PLUGIN_FILE), [__CLASS__, 'plugin_action_links']);
    add_filter('plugin_row_meta', [__CLASS__, 'filter_plugin_row_meta'], 10, 2);

    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

    add_action('wp_head', [__CLASS__, 'output_consent_default_and_gtm'], 1);

    add_action('wp_body_open', [__CLASS__, 'render_banner_markup'], 1);
    add_action('wp_footer', [__CLASS__, 'render_banner_markup'], 30);

    add_shortcode('cmp_manage_cookies', [__CLASS__, 'shortcode_manage_cookies']);
  }

  public static function defaults() {
    return [
      'enable' => 1,

      'gtm_id' => '',
      'consent_wait_for_update_ms' => 500,

      'privacy_url' => '',
      'cookie_policy_url' => '',
      'banner_title' => __('🍪 Gestion des cookies', 'cmp'),
      'banner_text' => __('Nous utilisons des cookies essentiels au fonctionnement du site, et (si vous l’acceptez) des cookies pour la mesure d’audience et le retargeting publicitaire.', 'cmp'),
      'btn_accept' => __('Accepter', 'cmp'),
      'btn_reject' => __('Refuser', 'cmp'),
      'btn_customize' => __('Personnaliser', 'cmp'),
      'btn_save' => __('Enregistrer', 'cmp'),
      'modal_title' => __('Préférences', 'cmp'),
      'modal_desc_essentials' => __('Nécessaires au fonctionnement du site (toujours actifs).', 'cmp'),
      'modal_desc_analytics' => __('Mesure d’audience (ex. Google Analytics 4).', 'cmp'),
      'modal_desc_retargeting' => __('Publicités personnalisées (gérées via GTM).', 'cmp'),

      'background_color' => '#ffffff',
      'text_color' => '#0b1621',

      'accept_btn_color' => '#00ceff',
      'accept_btn_text_color' => '#001018',
      'customize_btn_color' => '#f5f5f5',
      'customize_btn_text_color' => '#111111',

      'ttl_days' => 180,

      'debug' => 0,
      'force_show' => 0,
    ];
  }

  public static function get_settings() {
    $opts = get_option(self::OPTION_KEY, []);
    $settings = array_merge(self::defaults(), is_array($opts) ? $opts : []);

    return self::translate_settings($settings);
  }

  public static function plugin_action_links($links) {
    $settings_url = admin_url('options-general.php?page=cmp-410gone');
    return array_merge(['settings' => '<a href="' . esc_url($settings_url) . '">' . esc_html(__('Réglages', 'cmp')) . '</a>'], $links);
  }

  public static function filter_plugin_row_meta($links, $file) {
    if ($file !== plugin_basename(CMP_410GONE_PLUGIN_FILE)) {
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
    add_options_page(__('🍪 Consent Management — Réglages', 'cmp'), __('🍪 Consent Management', 'cmp'), 'manage_options', 'cmp-410gone', [__CLASS__, 'settings_page']);
  }

  public static function register_settings() {
    register_setting('cmp_410gone_group', self::OPTION_KEY, [
      'type' => 'array',
      'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
      'default' => self::defaults(),
    ]);

    add_settings_section('cmp_410gone_design', __('Design', 'cmp'), [__CLASS__, 'section_design'], 'cmp-410gone');
    add_settings_field('enable', __('Activer la CMP', 'cmp'), [__CLASS__, 'field_enable'], 'cmp-410gone', 'cmp_410gone_design');
    add_settings_field('accept_btn_color', __('Couleur bouton “Accepter”', 'cmp'), [__CLASS__, 'field_accept_btn_color'], 'cmp-410gone', 'cmp_410gone_design');
    add_settings_field('accept_btn_text_color', __('Couleur texte bouton “Accepter”', 'cmp'), [__CLASS__, 'field_accept_btn_text_color'], 'cmp-410gone', 'cmp_410gone_design');
    add_settings_field('background_color', __('Couleur de fond', 'cmp'), [__CLASS__, 'field_background_color'], 'cmp-410gone', 'cmp_410gone_design');
    add_settings_field('text_color', __('Couleur du texte', 'cmp'), [__CLASS__, 'field_text_color'], 'cmp-410gone', 'cmp_410gone_design');
    add_settings_field('customize_btn_color', __('Couleur bouton “Personnaliser”', 'cmp'), [__CLASS__, 'field_customize_btn_color'], 'cmp-410gone', 'cmp_410gone_design');
    add_settings_field('customize_btn_text_color', __('Couleur texte bouton “Personnaliser”', 'cmp'), [__CLASS__, 'field_customize_btn_text_color'], 'cmp-410gone', 'cmp_410gone_design');

    add_settings_section('cmp_410gone_labels', __('Libellé', 'cmp'), [__CLASS__, 'section_labels'], 'cmp-410gone');
    add_settings_field('privacy_url', __('URL Politique de confidentialité', 'cmp'), [__CLASS__, 'field_privacy_url'], 'cmp-410gone', 'cmp_410gone_labels');
    add_settings_field('cookie_policy_url', __('URL Politique cookies (optionnel)', 'cmp'), [__CLASS__, 'field_cookie_policy_url'], 'cmp-410gone', 'cmp_410gone_labels');
    add_settings_field('banner_title', __('Titre du bandeau', 'cmp'), [__CLASS__, 'field_banner_title'], 'cmp-410gone', 'cmp_410gone_labels');
    add_settings_field('banner_text', __('Texte du bandeau', 'cmp'), [__CLASS__, 'field_banner_text'], 'cmp-410gone', 'cmp_410gone_labels');
    add_settings_field('btn_labels', __('Libellés des boutons', 'cmp'), [__CLASS__, 'field_btn_labels'], 'cmp-410gone', 'cmp_410gone_labels');
    add_settings_field('modal_labels', __('Libellés de la popin', 'cmp'), [__CLASS__, 'field_modal_labels'], 'cmp-410gone', 'cmp_410gone_labels');

    add_settings_section('cmp_410gone_tracking', __('Tracking & configuration', 'cmp'), [__CLASS__, 'section_tracking'], 'cmp-410gone');
    add_settings_field('gtm_id', 'GTM Container ID', [__CLASS__, 'field_gtm_id'], 'cmp-410gone', 'cmp_410gone_tracking');
    add_settings_field('consent_wait_for_update_ms', __('Consent wait_for_update (ms)', 'cmp'), [__CLASS__, 'field_wait'], 'cmp-410gone', 'cmp_410gone_tracking');
    add_settings_section('cmp_410gone_advanced', __('Avancé', 'cmp'), [__CLASS__, 'section_advanced'], 'cmp-410gone');
    add_settings_field('ttl_days', __('Durée de conservation du choix (jours)', 'cmp'), [__CLASS__, 'field_ttl_days'], 'cmp-410gone', 'cmp_410gone_advanced');
    add_settings_field('debug', __('Debug console', 'cmp'), [__CLASS__, 'field_debug'], 'cmp-410gone', 'cmp_410gone_advanced');
    add_settings_field('force_show', __('Forcer l’affichage (test)', 'cmp'), [__CLASS__, 'field_force_show'], 'cmp-410gone', 'cmp_410gone_advanced');
  }

  public static function sanitize_settings($in) {
    $d = self::defaults();
    $out = [];

    $out['enable'] = isset($in['enable']) ? (int)!!$in['enable'] : $d['enable'];
    $out['debug']  = isset($in['debug']) ? (int)!!$in['debug'] : $d['debug'];
    $out['force_show'] = isset($in['force_show']) ? (int)!!$in['force_show'] : $d['force_show'];

    $gtm = isset($in['gtm_id']) ? strtoupper(trim((string)$in['gtm_id'])) : '';
    if ($gtm !== '' && !preg_match('/^GTM-[A-Z0-9]+$/', $gtm)) {
      $gtm = '';
    }
    $out['gtm_id'] = $gtm;

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

  public static function settings_page() {
    if (!current_user_can('manage_options')) {
      return;
    }

    $page = 'cmp-410gone';

    // Fallback in case hooks didn’t register sections (e.g., custom admin flows).
    if (empty($GLOBALS['wp_settings_sections'][$page])) {
      self::register_settings();
    }
    ?>
    <div class="wrap cmp410-admin">
      <h1><?php esc_html_e('🍪 Consent Management — Réglages', 'cmp'); ?></h1>
      <p><a href="https://www.410-gone.fr" target="_blank" rel="noopener noreferrer">410gone</a></p>

      <style>
        .cmp410-admin .cmp410-callout {
          margin: 10px 0 20px;
          padding: 12px 14px;
          background: #fff;
          border: 1px solid #d7d7d7;
          border-radius: 10px;
        }

        .cmp410-anchors {
          display: flex;
          flex-wrap: wrap;
          gap: 8px;
          margin: 0 0 14px;
          padding: 0;
          list-style: none;
        }

        .cmp410-anchors a {
          display: inline-flex;
          align-items: center;
          gap: 6px;
          padding: 6px 10px;
          border-radius: 8px;
          border: 1px solid #d9d9d9;
          background: #fff;
          text-decoration: none;
        }

        .cmp410-panels {
          display: grid;
          gap: 18px;
        }

        .cmp410-panel {
          background: #fff;
          border: 1px solid #e2e2e2;
          border-radius: 12px;
          padding: 18px 20px;
          box-shadow: 0 1px 1px rgba(0,0,0,0.03);
        }

        .cmp410-panel__header {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 10px;
          margin-bottom: 8px;
        }

        .cmp410-panel__desc {
          margin: 0 0 6px 0;
          color: #444;
        }

        .cmp410-preview-grid {
          display: grid;
          gap: 12px;
          grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
          margin: 12px 0 4px;
        }

        .cmp410-preview-device {
          border: 1px solid #e5e5e5;
          border-radius: 10px;
          padding: 10px;
          background: #f9f9f9;
          overflow: hidden;
          min-width: 0;
        }

        .cmp410-preview-frame {
          max-width: 100%;
          overflow: hidden;
          display: flex;
          justify-content: center;
        }

        .cmp410-preview-device.is-mobile {
          width: clamp(320px, 90vw, 430px);
          max-width: 100%;
          margin: 0 auto;
        }

        .cmp410-preview-inner {
          display: flex;
          flex-direction: column;
          gap: 12px;
          align-items: center;
          width: 100%;
        }

        .cmp410-preview-surface {
          max-width: min(760px, 100%);
          width: auto;
        }

        .cmp410-preview-device.is-mobile .cmp410-preview-surface {
          max-width: min(430px, 100%);
          width: auto;
        }

        .cmp410-preview-label {
          font-weight: 600;
          margin-bottom: 6px;
        }

        .cmp410-banner-preview {
          --cmp410-accept-bg: #00ceff;
          --cmp410-accept-fg: #001018;
          --cmp410-customize-bg: #f5f5f5;
          --cmp410-customize-fg: #111;
          --cmp410-surface: var(--cmp410-background, #ffffff);
          --cmp410-surface-border: #dfe6ee;
          --cmp410-text: var(--cmp410-foreground, #0b1621);
          --cmp410-secondary: color-mix(in srgb, var(--cmp410-foreground, #0b1621) 70%, #8b96a4);
          max-width: 100%;
          margin: 0 auto;
          border-radius: 12px;
          background: var(--cmp410-surface);
          border: 1px solid var(--cmp410-surface-border);
          color: var(--cmp410-text);
          font-size: 14px;
          line-height: 1.4;
          padding: 14px 16px;
          box-shadow: 0 6px 16px rgba(0,0,0,0.08);
        }

        .cmp410-banner-preview .cmp410-title {
          font-size: 16px;
          font-weight: 700;
          margin-bottom: 6px;
        }

        .cmp410-banner-preview .cmp410-desc {
          color: var(--cmp410-secondary);
          margin-bottom: 12px;
        }

        .cmp410-banner-preview .cmp410-link {
          color: #6bc8ff;
          text-decoration: underline;
        }

        .cmp410-banner-preview .cmp410-actions {
          display: flex;
          gap: 8px;
          flex-wrap: wrap;
        }

        .cmp410-banner-preview .cmp410-actions .cmp410-btn {
          border-radius: 999px;
          padding: 9px 18px;
          border: 1px solid transparent;
          font-weight: 700;
          cursor: default;
        }

        .cmp410-preview-device.is-mobile .cmp410-banner-preview .cmp410-actions .cmp410-btn {
          flex: 1 1 100%;
          min-width: 0;
          width: 100%;
          text-align: center;
        }

        .cmp410-banner-preview .cmp410-btn-primary {
          background: var(--cmp410-accept-bg);
          color: var(--cmp410-accept-fg);
          border-color: var(--cmp410-accept-bg);
        }

        .cmp410-banner-preview .cmp410-btn-outline {
          background: transparent;
          color: var(--cmp410-text);
          border-color: rgba(255,255,255,0.4);
        }

        .cmp410-banner-preview .cmp410-btn-ghost {
          background: var(--cmp410-customize-bg);
          color: var(--cmp410-customize-fg);
          border-color: var(--cmp410-customize-bg);
        }

        .cmp410-modal-preview {
          margin-top: 12px;
          background: var(--cmp410-surface);
          border: 1px solid var(--cmp410-surface-border);
          border-radius: 12px;
          padding: 12px;
          color: var(--cmp410-text);
          font-size: 13px;
        }

        .cmp410-modal-preview .cmp410-modal-header {
          display: flex;
          align-items: center;
          justify-content: space-between;
          margin-bottom: 10px;
        }

        .cmp410-modal-preview .cmp410-row {
          display: flex;
          justify-content: space-between;
          align-items: center;
          border-top: 1px solid #e1e7ee;
          padding: 10px 0;
          gap: 14px;
        }

        .cmp410-modal-preview .cmp410-row:first-of-type { border-top: 0; }

        .cmp410-modal-preview .cmp410-row-title {
          font-weight: 700;
        }

        .cmp410-modal-preview .cmp410-actions-modal {
          display: flex;
          gap: 8px;
          flex-wrap: wrap;
          margin-top: 10px;
        }

        .cmp410-modal-preview .cmp410-btn-primary { background: var(--cmp410-accept-bg); color: var(--cmp410-accept-fg); border-color: var(--cmp410-accept-bg); }
        .cmp410-modal-preview .cmp410-btn-outline { background: #fff; border-color: #1c2430; color: #0b1621; }

        .cmp410-color-control {
          display: flex;
          align-items: center;
          gap: 8px;
        }

        .cmp410-color-control input[type="color"] {
          width: 46px;
          height: 32px;
          padding: 0;
          border: 1px solid #c8c8c8;
          border-radius: 6px;
          background: #fff;
        }
      </style>

      <div class="cmp410-callout">
        <strong><?php esc_html_e('WP Rocket (recommandé) :', 'cmp'); ?></strong>
        <ul style="margin:8px 0 0 18px;">
          <li><?php esc_html_e('Optimiser les fichiers → JavaScript → Exclure cmp.js (ou cmp-410gone) de “Delay JavaScript execution”.', 'cmp'); ?></li>
          <li><?php esc_html_e('Puis : purge WP Rocket + Ctrl+F5 (ou navigation privée).', 'cmp'); ?></li>
        </ul>
      </div>

      <ul class="cmp410-anchors">
        <li><a href="#cmp_410gone_design">🎨 <?php esc_html_e('Design', 'cmp'); ?></a></li>
        <li><a href="#cmp_410gone_labels">✏️ <?php esc_html_e('Libellé', 'cmp'); ?></a></li>
        <li><a href="#cmp_410gone_tracking">📈 <?php esc_html_e('Tracking & configuration', 'cmp'); ?></a></li>
        <li><a href="#cmp_410gone_advanced">🛠️ <?php esc_html_e('Avancé', 'cmp'); ?></a></li>
      </ul>

      <form method="post" action="options.php">
        <?php settings_fields('cmp_410gone_group'); ?>

        <div class="cmp410-panels">
          <?php
            self::render_settings_section($page, 'cmp_410gone_design');
            self::render_settings_section($page, 'cmp_410gone_labels');
            self::render_settings_section($page, 'cmp_410gone_tracking');
            self::render_settings_section($page, 'cmp_410gone_advanced');
          ?>
        </div>

        <?php submit_button(__('Enregistrer', 'cmp')); ?>
      </form>

      <hr />
      <h2><?php esc_html_e('Lien “Gérer mes cookies”', 'cmp'); ?></h2>
      <p><?php esc_html_e('Shortcode :', 'cmp'); ?></p>
      <code>[cmp_manage_cookies label="<?php echo esc_attr(__('Gérer mes cookies', 'cmp')); ?>"]</code>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const colorPickers = document.querySelectorAll('.cmp410-color-picker');
        const colorInputs = document.querySelectorAll('.cmp410-color-value');
        const bindInputs = document.querySelectorAll('[data-preview-bind]');

        function updatePreviewColor(target, value) {
          document.querySelectorAll('[data-preview-root]').forEach(function(root) {
            if (!value) { return; }
            if (target === 'accept_btn_color') {
              root.style.setProperty('--cmp410-accept-bg', value);
            }
            if (target === 'accept_btn_text_color') {
              root.style.setProperty('--cmp410-accept-fg', value);
            }
            if (target === 'customize_btn_color') {
              root.style.setProperty('--cmp410-customize-bg', value);
            }
            if (target === 'customize_btn_text_color') {
              root.style.setProperty('--cmp410-customize-fg', value);
            }
            if (target === 'background_color') {
              root.style.setProperty('--cmp410-background', value);
              root.style.setProperty('--cmp410-surface', value);
            }
            if (target === 'text_color') {
              root.style.setProperty('--cmp410-foreground', value);
              root.style.setProperty('--cmp410-text', value);
            }

            root.querySelectorAll('[data-preview="' + target + '"]').forEach(function(el) {
              el.style.backgroundColor = value;
            });
          });
        }

        function syncColor(target, value, source) {
          document.querySelectorAll('[data-target="' + target + '"]').forEach(function(el) {
            if (el !== source) {
              el.value = value;
            }
          });
          updatePreviewColor(target, value);
        }

        colorPickers.forEach(function(input) {
          input.addEventListener('input', function() {
            syncColor(this.dataset.target, this.value, this);
          });
        });

        colorInputs.forEach(function(input) {
          input.addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
              syncColor(this.dataset.target, this.value, this);
            }
          });
        });

        bindInputs.forEach(function(input) {
          input.addEventListener('input', function() {
            const selector = '[data-preview-bind="' + this.dataset.previewBind + '"]';
            document.querySelectorAll(selector).forEach(function(el) {
              if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') { return; }
              el.textContent = input.value;
            });
          });
        });
      });
    </script>
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
    echo '<p>' . esc_html__('Personnalise les couleurs du bandeau et vérifie le rendu desktop/mobile.', 'cmp') . '</p>';
    self::render_preview_block($s, 'design');
  }

  public static function section_labels() {
    $s = self::get_settings();
    echo '<p>' . esc_html__('Adapte les textes et liens. Les libellés restent compatibles Polylang / WPML et filtres personnalisés.', 'cmp') . '</p>';
    self::render_preview_block($s, 'labels');
  }

  public static function section_tracking() {
    echo '<p><strong>' . esc_html__('Variante A2', 'cmp') . '</strong> : ' . esc_html__('GTM peut être chargé tôt, mais le Consent Mode v2 démarre en', 'cmp') . ' <code>denied</code> ' . esc_html__('puis se met à jour selon le choix utilisateur.', 'cmp') . '</p>';
    echo '<p><strong>WP Rocket</strong> : ' . esc_html__('exclure', 'cmp') . ' <code>cmp.js</code> ' . esc_html__('du “Delay JS execution”.', 'cmp') . '</p>';
  }

  public static function section_advanced() {
    echo '<p>' . esc_html__('Options pour le debug, les tests forcés et la durée de conservation du consentement.', 'cmp') . '</p>';
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
          <div class="cmp410-preview-label"><?php esc_html_e('Aperçu desktop', 'cmp'); ?></div>
          <?php self::render_preview_content($settings, 'desktop'); ?>
        </div>
        <div class="cmp410-preview-device is-mobile">
          <div class="cmp410-preview-label"><?php esc_html_e('Aperçu mobile', 'cmp'); ?></div>
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
                <a href="<?php echo esc_url($privacy); ?>" class="cmp410-link" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Politique de confidentialité', 'cmp'); ?></a>
              <?php endif; ?>
              <?php if ($cookies): ?>
                <span class="cmp410-sep">·</span>
                <a href="<?php echo esc_url($cookies); ?>" class="cmp410-link" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Politique cookies', 'cmp'); ?></a>
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
              <div class="cmp410-row-title"><?php esc_html_e('Essentiels', 'cmp'); ?></div>
              <div class="cmp410-row-desc" data-preview-bind="modal_desc_essentials"><?php echo esc_html($settings['modal_desc_essentials']); ?></div>
            </div>
            <input type="checkbox" checked disabled />
          </div>
          <div class="cmp410-row">
            <div>
              <div class="cmp410-row-title"><?php esc_html_e('Analytics', 'cmp'); ?></div>
              <div class="cmp410-row-desc" data-preview-bind="modal_desc_analytics"><?php echo esc_html($settings['modal_desc_analytics']); ?></div>
            </div>
            <input type="checkbox" checked />
          </div>
          <div class="cmp410-row">
            <div>
              <div class="cmp410-row-title"><?php esc_html_e('Retargeting', 'cmp'); ?></div>
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
    <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable]" value="1" <?php checked(1, (int)$s['enable']); ?> /> <?php esc_html_e("Activer l’affichage du bandeau", 'cmp'); ?></label>
  <?php }

  public static function field_gtm_id() {
    $s = self::get_settings(); ?>
    <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[gtm_id]" value="<?php echo esc_attr($s['gtm_id']); ?>" placeholder="GTM-XXXXXXX" class="regular-text" />
    <p class="description">A2 : <?php esc_html_e('GTM est chargé tôt (si renseigné). Dans GTM, configure tes tags pour exiger le consentement (analytics/ad).', 'cmp'); ?></p>
  <?php }

  public static function field_wait() {
    $s = self::get_settings(); ?>
    <input type="number" min="0" max="5000" name="<?php echo esc_attr(self::OPTION_KEY); ?>[consent_wait_for_update_ms]" value="<?php echo (int)$s['consent_wait_for_update_ms']; ?>" />
    <p class="description"><?php esc_html_e('Passé à wait_for_update. Valeurs typiques : 300–800ms.', 'cmp'); ?></p>
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
        <label><?php esc_html_e('Accepter', 'cmp'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[btn_accept]" value="<?php echo esc_attr($s['btn_accept']); ?>" class="regular-text" data-preview-bind="btn_accept" />
      </div>
      <div>
        <label><?php esc_html_e('Refuser', 'cmp'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[btn_reject]" value="<?php echo esc_attr($s['btn_reject']); ?>" class="regular-text" data-preview-bind="btn_reject" />
      </div>
      <div>
        <label><?php esc_html_e('Personnaliser', 'cmp'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[btn_customize]" value="<?php echo esc_attr($s['btn_customize']); ?>" class="regular-text" data-preview-bind="btn_customize" />
      </div>
      <div>
        <label><?php esc_html_e('Enregistrer', 'cmp'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[btn_save]" value="<?php echo esc_attr($s['btn_save']); ?>" class="regular-text" data-preview-bind="btn_save" />
      </div>
    </div>
  <?php }

  public static function field_modal_labels() {
    $s = self::get_settings(); ?>
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; max-width:820px;">
      <div>
        <label><?php esc_html_e('Titre de la popin', 'cmp'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[modal_title]" value="<?php echo esc_attr($s['modal_title']); ?>" class="regular-text" data-preview-bind="modal_title" />
      </div>
      <div>
        <label><?php esc_html_e('Sous-titre Essentiels', 'cmp'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[modal_desc_essentials]" value="<?php echo esc_attr($s['modal_desc_essentials']); ?>" class="regular-text" data-preview-bind="modal_desc_essentials" />
      </div>
      <div>
        <label><?php esc_html_e('Sous-titre Analytics', 'cmp'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[modal_desc_analytics]" value="<?php echo esc_attr($s['modal_desc_analytics']); ?>" class="regular-text" data-preview-bind="modal_desc_analytics" />
      </div>
      <div>
        <label><?php esc_html_e('Sous-titre Retargeting', 'cmp'); ?></label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[modal_desc_retargeting]" value="<?php echo esc_attr($s['modal_desc_retargeting']); ?>" class="regular-text" data-preview-bind="modal_desc_retargeting" />
      </div>
    </div>
  <?php }

  public static function field_accept_btn_color() {
    $s = self::get_settings(); ?>
    <div class="cmp410-color-control">
      <input type="color" class="cmp410-color-picker" data-target="accept_btn_color" value="<?php echo esc_attr($s['accept_btn_color']); ?>" aria-label="<?php esc_attr_e('Choisir la couleur du bouton Accepter', 'cmp'); ?>" />
      <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[accept_btn_color]" value="<?php echo esc_attr($s['accept_btn_color']); ?>" class="regular-text cmp410-color-value" data-target="accept_btn_color" placeholder="#00ceff" pattern="^#[A-Fa-f0-9]{6}$" />
    </div>
    <p class="description"><?php esc_html_e('Couleur de fond du bouton Accepter (hex ex: #00ceff).', 'cmp'); ?></p>
  <?php }

  public static function field_accept_btn_text_color() {
    $s = self::get_settings(); ?>
    <div class="cmp410-color-control">
      <input type="color" class="cmp410-color-picker" data-target="accept_btn_text_color" value="<?php echo esc_attr($s['accept_btn_text_color']); ?>" aria-label="<?php esc_attr_e('Choisir la couleur du texte “Accepter”', 'cmp'); ?>" />
      <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[accept_btn_text_color]" value="<?php echo esc_attr($s['accept_btn_text_color']); ?>" class="regular-text cmp410-color-value" data-target="accept_btn_text_color" placeholder="#ffffff" pattern="^#[A-Fa-f0-9]{6}$" />
    </div>
    <p class="description"><?php esc_html_e('Couleur du texte du bouton Accepter (hex ex: #001018 ou #ffffff).', 'cmp'); ?></p>
  <?php }

  public static function field_background_color() {
    $s = self::get_settings(); ?>
    <div class="cmp410-color-control">
      <input type="color" class="cmp410-color-picker" data-target="background_color" value="<?php echo esc_attr($s['background_color']); ?>" aria-label="<?php esc_attr_e('Choisir la couleur de fond du bandeau et du modal', 'cmp'); ?>" />
      <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[background_color]" value="<?php echo esc_attr($s['background_color']); ?>" class="regular-text cmp410-color-value" data-target="background_color" placeholder="#ffffff" pattern="^#[A-Fa-f0-9]{6}$" />
    </div>
    <p class="description"><?php esc_html_e('Couleur de fond appliquée au bandeau et à l’écran de personnalisation.', 'cmp'); ?></p>
  <?php }

  public static function field_text_color() {
    $s = self::get_settings(); ?>
    <div class="cmp410-color-control">
      <input type="color" class="cmp410-color-picker" data-target="text_color" value="<?php echo esc_attr($s['text_color']); ?>" aria-label="<?php esc_attr_e('Choisir la couleur de texte par défaut', 'cmp'); ?>" />
      <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[text_color]" value="<?php echo esc_attr($s['text_color']); ?>" class="regular-text cmp410-color-value" data-target="text_color" placeholder="#0b1621" pattern="^#[A-Fa-f0-9]{6}$" />
    </div>
    <p class="description"><?php esc_html_e('Couleur de texte principale utilisée dans le bandeau et le modal.', 'cmp'); ?></p>
  <?php }

  public static function field_customize_btn_color() {
    $s = self::get_settings(); ?>
    <div class="cmp410-color-control">
      <input type="color" class="cmp410-color-picker" data-target="customize_btn_color" value="<?php echo esc_attr($s['customize_btn_color']); ?>" aria-label="<?php esc_attr_e('Choisir la couleur du bouton Personnaliser', 'cmp'); ?>" />
      <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[customize_btn_color]" value="<?php echo esc_attr($s['customize_btn_color']); ?>" class="regular-text cmp410-color-value" data-target="customize_btn_color" placeholder="#f5f5f5" pattern="^#[A-Fa-f0-9]{6}$" />
    </div>
    <p class="description"><?php esc_html_e('Couleur de fond du bouton Personnaliser (hex ex: #f5f5f5).', 'cmp'); ?></p>
  <?php }

  public static function field_customize_btn_text_color() {
    $s = self::get_settings(); ?>
    <div class="cmp410-color-control">
      <input type="color" class="cmp410-color-picker" data-target="customize_btn_text_color" value="<?php echo esc_attr($s['customize_btn_text_color']); ?>" aria-label="<?php esc_attr_e('Choisir la couleur du texte “Personnaliser”', 'cmp'); ?>" />
      <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[customize_btn_text_color]" value="<?php echo esc_attr($s['customize_btn_text_color']); ?>" class="regular-text cmp410-color-value" data-target="customize_btn_text_color" placeholder="#111111" pattern="^#[A-Fa-f0-9]{6}$" />
    </div>
    <p class="description"><?php esc_html_e('Couleur du texte du bouton Personnaliser (hex ex: #111111).', 'cmp'); ?></p>
  <?php }

  public static function field_ttl_days() {
    $s = self::get_settings(); ?>
    <input type="number" min="1" max="3650" name="<?php echo esc_attr(self::OPTION_KEY); ?>[ttl_days]" value="<?php echo (int)$s['ttl_days']; ?>" />
  <?php }

  public static function field_debug() {
    $s = self::get_settings(); ?>
    <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[debug]" value="1" <?php checked(1, (int)$s['debug']); ?> /> <?php esc_html_e('Activer les logs console', 'cmp'); ?></label>
  <?php }

  public static function field_force_show() {
    $s = self::get_settings(); ?>
    <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[force_show]" value="1" <?php checked(1, (int)$s['force_show']); ?> /> <?php esc_html_e('Toujours afficher le bandeau (ignore le cookie)', 'cmp'); ?></label>
  <?php }

  public static function enqueue_assets() {
    $s = self::get_settings();
    if (!(int)$s['enable']) {
      return;
    }

    $base = plugin_dir_path(CMP_410GONE_PLUGIN_FILE);
    $url  = plugin_dir_url(CMP_410GONE_PLUGIN_FILE);

    $css_ver = file_exists($base . 'assets/cmp.css') ? (string)filemtime($base . 'assets/cmp.css') : '1.0.0';
    $js_ver  = file_exists($base . 'assets/cmp.js')  ? (string)filemtime($base . 'assets/cmp.js')  : '1.0.0';

    wp_enqueue_style('cmp-410gone', $url . 'assets/cmp.css', [], $css_ver);

    $inline = ':root{--cmp410-accept-bg:' . $s['accept_btn_color'] . ';--cmp410-accept-fg:' . $s['accept_btn_text_color'] . ';--cmp410-customize-bg:' . $s['customize_btn_color'] . ';--cmp410-customize-fg:' . $s['customize_btn_text_color'] . ';--cmp410-surface:' . $s['background_color'] . ';--cmp410-text:' . $s['text_color'] . ';--cmp410-background:' . $s['background_color'] . ';--cmp410-foreground:' . $s['text_color'] . ';}';
    wp_add_inline_style('cmp-410gone', $inline);

    wp_enqueue_script('cmp-410gone', $url . 'assets/cmp.js', [], $js_ver, true);

    wp_localize_script('cmp-410gone', 'CMP410', [
      'cookieName' => self::COOKIE_NAME,
      'ttlDays' => (int)$s['ttl_days'],
      'privacyUrl' => (string)$s['privacy_url'],
      'cookiePolicyUrl' => (string)$s['cookie_policy_url'],
      'debug' => (bool)$s['debug'],
      'forceShow' => (bool)$s['force_show'],
    ]);
  }

  public static function output_consent_default_and_gtm() {
    $s = self::get_settings();
    $consent_defaults = self::consent_mode_from_cookie();
    $consent_defaults['wait_for_update'] = (int)$s['consent_wait_for_update_ms'];
    ?>
    <!-- cmp (410gone) — Consent Mode v2 default -->
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}

      gtag('consent', 'default', <?php echo wp_json_encode($consent_defaults); ?>);
    </script>
    <?php
    if (!(int)$s['enable']) {
      return;
    }

    if (!empty($s['gtm_id'])):
      $gtm_id = esc_js($s['gtm_id']);
      ?>
      <!-- Google Tag Manager (cmp 410gone, A2) -->
      <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});
        var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
        j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
        f.parentNode.insertBefore(j,f);
          })(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');
      </script>
      <!-- End Google Tag Manager -->
      <?php
    endif;
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

    $privacy = !empty($s['privacy_url']) ? esc_url($s['privacy_url']) : '';
    $cookies = !empty($s['cookie_policy_url']) ? esc_url($s['cookie_policy_url']) : '';
    ?>
    <div class="cmp410-wrap" id="cmp410" aria-hidden="false">
      <div class="cmp410-banner" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Gestion des cookies', 'cmp'); ?>">
        <div class="cmp410-text">
          <div class="cmp410-title"><?php echo esc_html($s['banner_title']); ?></div>
          <div class="cmp410-desc">
            <?php echo wp_kses_post($s['banner_text']); ?>
              <?php if ($privacy): ?>
                <a href="<?php echo esc_url($privacy); ?>" class="cmp410-link" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Politique de confidentialité', 'cmp'); ?></a>
              <?php endif; ?>
              <?php if ($cookies): ?>
                <span class="cmp410-sep">·</span>
                <a href="<?php echo esc_url($cookies); ?>" class="cmp410-link" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Politique cookies', 'cmp'); ?></a>
              <?php endif; ?>
          </div>
        </div>

        <div class="cmp410-actions">
          <button class="cmp410-btn cmp410-btn-ghost" data-cmp410="customize"><?php echo esc_html($s['btn_customize']); ?></button>
          <button class="cmp410-btn cmp410-btn-outline" data-cmp410="reject"><?php echo esc_html($s['btn_reject']); ?></button>
          <button class="cmp410-btn cmp410-btn-primary" data-cmp410="accept"><?php echo esc_html($s['btn_accept']); ?></button>
        </div>
      </div>

      <div class="cmp410-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Préférences cookies', 'cmp'); ?>">
        <div class="cmp410-modal-header">
          <div class="cmp410-modal-title"><?php echo esc_html($s['modal_title']); ?></div>
          <button class="cmp410-x" data-cmp410="close" aria-label="<?php esc_attr_e('Fermer', 'cmp'); ?>">×</button>
        </div>

        <div class="cmp410-modal-body">
          <div class="cmp410-row">
            <div class="cmp410-row-text">
              <div class="cmp410-row-title"><?php esc_html_e('Essentiels', 'cmp'); ?></div>
              <div class="cmp410-row-desc"><?php echo esc_html($s['modal_desc_essentials']); ?></div>
            </div>
            <div class="cmp410-toggle">
              <input type="checkbox" checked disabled />
            </div>
          </div>

          <div class="cmp410-row">
            <div class="cmp410-row-text">
              <div class="cmp410-row-title"><?php esc_html_e('Analytics', 'cmp'); ?></div>
              <div class="cmp410-row-desc"><?php echo esc_html($s['modal_desc_analytics']); ?></div>
            </div>
            <div class="cmp410-toggle">
              <input type="checkbox" id="cmp410-analytics" checked />
            </div>
          </div>

          <div class="cmp410-row">
            <div class="cmp410-row-text">
              <div class="cmp410-row-title"><?php esc_html_e('Retargeting', 'cmp'); ?></div>
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
    $atts = shortcode_atts(['label' => __('Gérer mes cookies', 'cmp')], $atts, 'cmp_manage_cookies');
    $label = esc_html($atts['label']);
    return '<a href="#" onclick="window.CMP410_open && window.CMP410_open(); return false;">' . $label . '</a>';
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
      $settings[$key] = apply_filters('cmp_410gone_translate_setting', $settings[$key], $key, $settings);
    }

    return $settings;
  }

  private static function register_translation_strings($settings) {
    $fields = [
      'banner_title' => __('Titre du bandeau', 'cmp'),
      'banner_text' => __('Texte du bandeau', 'cmp'),
      'btn_accept' => __('Bouton accepter', 'cmp'),
      'btn_reject' => __('Bouton refuser', 'cmp'),
      'btn_customize' => __('Bouton personnaliser', 'cmp'),
      'btn_save' => __('Bouton enregistrer', 'cmp'),
      'modal_title' => __('Titre popin personnalisation', 'cmp'),
      'modal_desc_essentials' => __('Sous-titre essentiels', 'cmp'),
      'modal_desc_analytics' => __('Sous-titre analytics', 'cmp'),
      'modal_desc_retargeting' => __('Sous-titre retargeting', 'cmp'),
      'privacy_url' => __('URL Politique de confidentialité', 'cmp'),
      'cookie_policy_url' => __('URL Politique cookies', 'cmp'),
    ];

    foreach ($fields as $key => $label) {
      if (!isset($settings[$key])) {
        continue;
      }
      if (function_exists('pll_register_string')) {
        pll_register_string('cmp_410gone_' . $key, $settings[$key], 'CMP 410gone', false);
      }

      if (has_filter('wpml_register_single_string')) {
        do_action('wpml_register_single_string', 'CMP 410gone', 'cmp_410gone_' . $key, $settings[$key]);
      }
    }
  }

  private static function translate_text($key, $value) {
    $translated = $value;

    if (function_exists('pll__')) {
      $translated = pll__($translated);
    } elseif (has_filter('wpml_translate_single_string')) {
      $translated = apply_filters('wpml_translate_single_string', $translated, 'CMP 410gone', 'cmp_410gone_' . $key);
    }

    return $translated;
  }
}
