<?php
/**
 * Plugin Name: cmp
 * Plugin URI: https://www.410-gone.fr
 * Description: CMP locale (Accepter / Refuser / Personnaliser) avec Google Consent Mode v2 + GTM (Variante A2) et chargement conditionnel Meta/LinkedIn.
 * Version: 1.3.0
 * Author: 410gone
 * Author URI: https://www.410-gone.fr
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) exit;

class CMP_410GONE {
  const OPTION_KEY = 'cmp_410gone_settings';
  const COOKIE_NAME = 'cmp_consent';

  public static function init() {
    add_action('admin_menu', [__CLASS__, 'admin_menu']);
    add_action('admin_init', [__CLASS__, 'register_settings']);
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), [__CLASS__, 'plugin_action_links']);

    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

    // Consent default + GTM early
    add_action('wp_head', [__CLASS__, 'output_consent_default_and_gtm'], 1);

    // Banner markup: inject early in body for Astra + fallback footer
    add_action('wp_body_open', [__CLASS__, 'render_banner_markup'], 1);
    add_action('wp_footer', [__CLASS__, 'render_banner_markup'], 30);

    add_shortcode('cmp_manage_cookies', [__CLASS__, 'shortcode_manage_cookies']);
  }

  public static function defaults() {
    return [
      'enable' => 1,

      // Google
      'gtm_id' => '',
      'consent_wait_for_update_ms' => 500,

      // Retargeting IDs
      'meta_pixel_id' => '',
      'linkedin_partner_id' => '',

      // Links & content
      'privacy_url' => '',
      'cookie_policy_url' => '',
      'banner_title' => '🍪 Gestion des cookies',
      'banner_text' => "Nous utilisons des cookies essentiels au fonctionnement du site, et (si vous l’acceptez) des cookies pour la mesure d’audience et le retargeting publicitaire.",
      'btn_accept' => 'Accepter',
      'btn_reject' => 'Refuser',
      'btn_customize' => 'Personnaliser',
      'btn_save' => 'Enregistrer',

      // UI
      'accept_btn_color' => '#00ceff',
      'accept_btn_text_color' => '#001018', // lisible sur cyan

      // Storage
      'ttl_days' => 180,

      // Advanced
      'debug' => 0,
      'force_show' => 0,
    ];
  }

  public static function get_settings() {
    $opts = get_option(self::OPTION_KEY, []);
    return array_merge(self::defaults(), is_array($opts) ? $opts : []);
  }

  public static function plugin_action_links($links) {
    $settings_url = admin_url('options-general.php?page=cmp-410gone');
    return array_merge(['settings' => '<a href="' . esc_url($settings_url) . '">Réglages</a>'], $links);
  }

  public static function admin_menu() {
    add_options_page('cmp — Réglages', 'cmp', 'manage_options', 'cmp-410gone', [__CLASS__, 'settings_page']);
  }

  public static function register_settings() {
    register_setting('cmp_410gone_group', self::OPTION_KEY, [
      'type' => 'array',
      'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
      'default' => self::defaults(),
    ]);

    add_settings_section('cmp_410gone_main', 'Configuration', function () {
      echo '<p><strong>Variante A2</strong> : GTM peut être chargé tôt, mais le Consent Mode v2 est initialisé en <code>denied</code> par défaut, puis mis à jour selon le choix utilisateur.</p>';
      echo '<p><strong>WP Rocket</strong> : exclure <code>cmp.js</code> du “Delay JS execution”.</p>';
    }, 'cmp-410gone');

    add_settings_field('enable', 'Activer la CMP', [__CLASS__, 'field_enable'], 'cmp-410gone', 'cmp_410gone_main');

    add_settings_field('gtm_id', 'GTM Container ID', [__CLASS__, 'field_gtm_id'], 'cmp-410gone', 'cmp_410gone_main');
    add_settings_field('consent_wait_for_update_ms', 'Consent wait_for_update (ms)', [__CLASS__, 'field_wait'], 'cmp-410gone', 'cmp_410gone_main');

    add_settings_field('meta_pixel_id', 'Meta Pixel ID', [__CLASS__, 'field_meta_pixel_id'], 'cmp-410gone', 'cmp_410gone_main');
    add_settings_field('linkedin_partner_id', 'LinkedIn Partner ID', [__CLASS__, 'field_linkedin_partner_id'], 'cmp-410gone', 'cmp_410gone_main');

    add_settings_field('privacy_url', 'URL Politique de confidentialité', [__CLASS__, 'field_privacy_url'], 'cmp-410gone', 'cmp_410gone_main');
    add_settings_field('cookie_policy_url', 'URL Politique cookies (optionnel)', [__CLASS__, 'field_cookie_policy_url'], 'cmp-410gone', 'cmp_410gone_main');

    add_settings_field('banner_title', 'Titre du bandeau', [__CLASS__, 'field_banner_title'], 'cmp-410gone', 'cmp_410gone_main');
    add_settings_field('banner_text', 'Texte du bandeau', [__CLASS__, 'field_banner_text'], 'cmp-410gone', 'cmp_410gone_main');

    add_settings_field('btn_labels', 'Libellés des boutons', [__CLASS__, 'field_btn_labels'], 'cmp-410gone', 'cmp_410gone_main');

    add_settings_field('accept_btn_color', 'Couleur bouton “Accepter”', [__CLASS__, 'field_accept_btn_color'], 'cmp-410gone', 'cmp_410gone_main');
    add_settings_field('accept_btn_text_color', 'Couleur texte bouton “Accepter”', [__CLASS__, 'field_accept_btn_text_color'], 'cmp-410gone', 'cmp_410gone_main');

    add_settings_field('ttl_days', 'Durée de conservation du choix (jours)', [__CLASS__, 'field_ttl_days'], 'cmp-410gone', 'cmp_410gone_main');

    add_settings_field('debug', 'Debug console', [__CLASS__, 'field_debug'], 'cmp-410gone', 'cmp_410gone_main');
    add_settings_field('force_show', 'Forcer l’affichage (test)', [__CLASS__, 'field_force_show'], 'cmp-410gone', 'cmp_410gone_main');
  }

  public static function sanitize_settings($in) {
    $d = self::defaults();
    $out = [];

    $out['enable'] = isset($in['enable']) ? (int)!!$in['enable'] : $d['enable'];
    $out['debug']  = isset($in['debug']) ? (int)!!$in['debug'] : $d['debug'];
    $out['force_show'] = isset($in['force_show']) ? (int)!!$in['force_show'] : $d['force_show'];

    $gtm = isset($in['gtm_id']) ? strtoupper(trim((string)$in['gtm_id'])) : '';
    if ($gtm !== '' && !preg_match('/^GTM-[A-Z0-9]+$/', $gtm)) $gtm = '';
    $out['gtm_id'] = $gtm;

    $wait = isset($in['consent_wait_for_update_ms']) ? (int)$in['consent_wait_for_update_ms'] : $d['consent_wait_for_update_ms'];
    if ($wait < 0) $wait = 0;
    if ($wait > 5000) $wait = 5000;
    $out['consent_wait_for_update_ms'] = $wait;

    $mp = isset($in['meta_pixel_id']) ? preg_replace('/[^0-9]/', '', (string)$in['meta_pixel_id']) : '';
    $out['meta_pixel_id'] = $mp;

    $li = isset($in['linkedin_partner_id']) ? preg_replace('/[^0-9]/', '', (string)$in['linkedin_partner_id']) : '';
    $out['linkedin_partner_id'] = $li;

    $out['privacy_url'] = isset($in['privacy_url']) ? esc_url_raw((string)$in['privacy_url']) : $d['privacy_url'];
    $out['cookie_policy_url'] = isset($in['cookie_policy_url']) ? esc_url_raw((string)$in['cookie_policy_url']) : $d['cookie_policy_url'];

    $out['banner_title'] = isset($in['banner_title']) ? sanitize_text_field((string)$in['banner_title']) : $d['banner_title'];
    $out['banner_text']  = isset($in['banner_text']) ? wp_kses_post((string)$in['banner_text']) : $d['banner_text'];

    $out['btn_accept']    = isset($in['btn_accept']) ? sanitize_text_field((string)$in['btn_accept']) : $d['btn_accept'];
    $out['btn_reject']    = isset($in['btn_reject']) ? sanitize_text_field((string)$in['btn_reject']) : $d['btn_reject'];
    $out['btn_customize'] = isset($in['btn_customize']) ? sanitize_text_field((string)$in['btn_customize']) : $d['btn_customize'];
    $out['btn_save']      = isset($in['btn_save']) ? sanitize_text_field((string)$in['btn_save']) : $d['btn_save'];

    // Colors
    $col = isset($in['accept_btn_color']) ? trim((string)$in['accept_btn_color']) : $d['accept_btn_color'];
    if (!preg_match('/^#([A-Fa-f0-9]{6})$/', $col)) $col = $d['accept_btn_color'];
    $out['accept_btn_color'] = $col;

    $tcol = isset($in['accept_btn_text_color']) ? trim((string)$in['accept_btn_text_color']) : $d['accept_btn_text_color'];
    if (!preg_match('/^#([A-Fa-f0-9]{6})$/', $tcol)) $tcol = $d['accept_btn_text_color'];
    $out['accept_btn_text_color'] = $tcol;

    $ttl = isset($in['ttl_days']) ? (int)$in['ttl_days'] : $d['ttl_days'];
    if ($ttl < 1) $ttl = 1;
    if ($ttl > 3650) $ttl = 3650;
    $out['ttl_days'] = $ttl;

    return $out;
  }

  public static function settings_page() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
      <h1>cmp — Réglages</h1>
      <p><a href="https://www.410-gone.fr" target="_blank" rel="noopener noreferrer">410gone</a></p>

      <div style="margin:10px 0; padding:12px; background:#fff; border:1px solid #ddd; border-radius:8px;">
        <strong>WP Rocket (recommandé) :</strong>
        <ul style="margin:8px 0 0 18px;">
          <li>Optimiser les fichiers → JavaScript → <strong>Exclure</strong> <code>cmp.js</code> (ou <code>cmp-410gone</code>) de “Delay JavaScript execution”.</li>
          <li>Puis : purge WP Rocket + Ctrl+F5 (ou navigation privée).</li>
        </ul>
      </div>

      <form method="post" action="options.php">
        <?php
          settings_fields('cmp_410gone_group');
          do_settings_sections('cmp-410gone');
          submit_button('Enregistrer');
        ?>
      </form>

      <hr />
      <h2>Lien “Gérer mes cookies”</h2>
      <p>Shortcode :</p>
      <code>[cmp_manage_cookies label="Gérer mes cookies"]</code>
    </div>
    <?php
  }

  // Fields
  public static function field_enable() {
    $s = self::get_settings(); ?>
    <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable]" value="1" <?php checked(1, (int)$s['enable']); ?> /> Activer l’affichage du bandeau</label>
  <?php }

  public static function field_gtm_id() {
    $s = self::get_settings(); ?>
    <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[gtm_id]" value="<?php echo esc_attr($s['gtm_id']); ?>" placeholder="GTM-XXXXXXX" class="regular-text" />
    <p class="description">A2 : GTM est chargé tôt (si renseigné). Dans GTM, configure tes tags pour exiger le consentement (analytics/ad).</p>
  <?php }

  public static function field_wait() {
    $s = self::get_settings(); ?>
    <input type="number" min="0" max="5000" name="<?php echo esc_attr(self::OPTION_KEY); ?>[consent_wait_for_update_ms]" value="<?php echo (int)$s['consent_wait_for_update_ms']; ?>" />
    <p class="description">Passé à <code>wait_for_update</code>. Valeurs typiques : 300–800ms.</p>
  <?php }

  public static function field_meta_pixel_id() {
    $s = self::get_settings(); ?>
    <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[meta_pixel_id]" value="<?php echo esc_attr($s['meta_pixel_id']); ?>" placeholder="123456789012345" class="regular-text" />
    <p class="description">Chargé uniquement si “Retargeting” est activé.</p>
  <?php }

  public static function field_linkedin_partner_id() {
    $s = self::get_settings(); ?>
    <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[linkedin_partner_id]" value="<?php echo esc_attr($s['linkedin_partner_id']); ?>" placeholder="1234567" class="regular-text" />
    <p class="description">Chargé uniquement si “Retargeting” est activé.</p>
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
    <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[banner_title]" value="<?php echo esc_attr($s['banner_title']); ?>" class="regular-text" />
  <?php }

  public static function field_banner_text() {
    $s = self::get_settings(); ?>
    <textarea name="<?php echo esc_attr(self::OPTION_KEY); ?>[banner_text]" rows="4" class="large-text"><?php echo esc_textarea($s['banner_text']); ?></textarea>
  <?php }

  public static function field_btn_labels() {
    $s = self::get_settings(); ?>
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; max-width:820px;">
      <div>
        <label>Accepter</label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[btn_accept]" value="<?php echo esc_attr($s['btn_accept']); ?>" class="regular-text" />
      </div>
      <div>
        <label>Refuser</label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[btn_reject]" value="<?php echo esc_attr($s['btn_reject']); ?>" class="regular-text" />
      </div>
      <div>
        <label>Personnaliser</label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[btn_customize]" value="<?php echo esc_attr($s['btn_customize']); ?>" class="regular-text" />
      </div>
      <div>
        <label>Enregistrer</label><br/>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[btn_save]" value="<?php echo esc_attr($s['btn_save']); ?>" class="regular-text" />
      </div>
    </div>
  <?php }

  public static function field_accept_btn_color() {
    $s = self::get_settings(); ?>
    <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[accept_btn_color]" value="<?php echo esc_attr($s['accept_btn_color']); ?>" class="regular-text" placeholder="#00ceff" />
    <p class="description">Couleur de fond du bouton Accepter (hex ex: #00ceff).</p>
  <?php }

  public static function field_accept_btn_text_color() {
    $s = self::get_settings(); ?>
    <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[accept_btn_text_color]" value="<?php echo esc_attr($s['accept_btn_text_color']); ?>" class="regular-text" placeholder="#ffffff" />
    <p class="description">Couleur du texte du bouton Accepter (hex ex: #001018 ou #ffffff).</p>
  <?php }

  public static function field_ttl_days() {
    $s = self::get_settings(); ?>
    <input type="number" min="1" max="3650" name="<?php echo esc_attr(self::OPTION_KEY); ?>[ttl_days]" value="<?php echo (int)$s['ttl_days']; ?>" />
  <?php }

  public static function field_debug() {
    $s = self::get_settings(); ?>
    <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[debug]" value="1" <?php checked(1, (int)$s['debug']); ?> /> Activer les logs console</label>
  <?php }

  public static function field_force_show() {
    $s = self::get_settings(); ?>
    <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[force_show]" value="1" <?php checked(1, (int)$s['force_show']); ?> /> Toujours afficher le bandeau (ignore le cookie)</label>
  <?php }

  public static function enqueue_assets() {
    $s = self::get_settings();
    if (!(int)$s['enable']) return;

    $base = plugin_dir_path(__FILE__);
    $url  = plugin_dir_url(__FILE__);

    $css_ver = file_exists($base . 'assets/cmp.css') ? (string)filemtime($base . 'assets/cmp.css') : '1.0.0';
    $js_ver  = file_exists($base . 'assets/cmp.js')  ? (string)filemtime($base . 'assets/cmp.js')  : '1.0.0';

    wp_enqueue_style('cmp-410gone', $url . 'assets/cmp.css', [], $css_ver);

    // Inject variables for button color (back-office control)
    $inline = ':root{--cmp410-accept-bg:' . $s['accept_btn_color'] . ';--cmp410-accept-fg:' . $s['accept_btn_text_color'] . ';}';
    wp_add_inline_style('cmp-410gone', $inline);

    wp_enqueue_script('cmp-410gone', $url . 'assets/cmp.js', [], $js_ver, true);

    wp_localize_script('cmp-410gone', 'CMP410', [
      'cookieName' => self::COOKIE_NAME,
      'ttlDays' => (int)$s['ttl_days'],
      'privacyUrl' => (string)$s['privacy_url'],
      'cookiePolicyUrl' => (string)$s['cookie_policy_url'],
      'metaPixelId' => (string)$s['meta_pixel_id'],
      'linkedinPartnerId' => (string)$s['linkedin_partner_id'],
      'debug' => (bool)$s['debug'],
      'forceShow' => (bool)$s['force_show'],
    ]);
  }

  public static function output_consent_default_and_gtm() {
    $s = self::get_settings();
    if (!(int)$s['enable']) return;
    ?>
    <!-- cmp (410gone) — Consent Mode v2 default -->
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}

      gtag('consent', 'default', {
        'analytics_storage': 'denied',
        'ad_storage': 'denied',
        'ad_user_data': 'denied',
        'ad_personalization': 'denied',
        'wait_for_update': <?php echo (int)$s['consent_wait_for_update_ms']; ?>
      });
    </script>
    <?php
    if (!empty($s['gtm_id'])):
      $gtm_id = esc_js($s['gtm_id']);
      ?>
      <!-- Google Tag Manager (cmp 410gone, A2) -->
      <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});
        var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
        j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
        f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','<?php echo $gtm_id; ?>');
      </script>
      <!-- End Google Tag Manager -->
      <?php
    endif;
  }

  public static function render_banner_markup() {
    static $done = false;
    if ($done) return;
    $done = true;

    $s = self::get_settings();
    if (!(int)$s['enable']) return;

    $privacy = !empty($s['privacy_url']) ? esc_url($s['privacy_url']) : '';
    $cookies = !empty($s['cookie_policy_url']) ? esc_url($s['cookie_policy_url']) : '';
    ?>
    <div class="cmp410-wrap" id="cmp410" aria-hidden="false">
      <div class="cmp410-banner" role="dialog" aria-modal="true" aria-label="Gestion des cookies">
        <div class="cmp410-text">
          <div class="cmp410-title"><?php echo esc_html($s['banner_title']); ?></div>
          <div class="cmp410-desc">
            <?php echo wp_kses_post($s['banner_text']); ?>
            <?php if ($privacy): ?>
              <a href="<?php echo $privacy; ?>" class="cmp410-link" target="_blank" rel="noopener noreferrer">Politique de confidentialité</a>
            <?php endif; ?>
            <?php if ($cookies): ?>
              <span class="cmp410-sep">·</span>
              <a href="<?php echo $cookies; ?>" class="cmp410-link" target="_blank" rel="noopener noreferrer">Politique cookies</a>
            <?php endif; ?>
          </div>
        </div>

        <div class="cmp410-actions">
          <button class="cmp410-btn cmp410-btn-ghost" data-cmp410="customize"><?php echo esc_html($s['btn_customize']); ?></button>
          <button class="cmp410-btn cmp410-btn-outline" data-cmp410="reject"><?php echo esc_html($s['btn_reject']); ?></button>
          <button class="cmp410-btn cmp410-btn-primary" data-cmp410="accept"><?php echo esc_html($s['btn_accept']); ?></button>
        </div>
      </div>

      <div class="cmp410-modal" role="dialog" aria-modal="true" aria-label="Préférences cookies">
        <div class="cmp410-modal-header">
          <div class="cmp410-modal-title">Préférences</div>
          <button class="cmp410-x" data-cmp410="close" aria-label="Fermer">×</button>
        </div>

        <div class="cmp410-modal-body">
          <div class="cmp410-row">
            <div class="cmp410-row-text">
              <div class="cmp410-row-title">Essentiels</div>
              <div class="cmp410-row-desc">Nécessaires au fonctionnement du site (toujours actifs).</div>
            </div>
            <div class="cmp410-toggle">
              <input type="checkbox" checked disabled />
            </div>
          </div>

          <div class="cmp410-row">
            <div class="cmp410-row-text">
              <div class="cmp410-row-title">Analytics</div>
              <div class="cmp410-row-desc">Mesure d’audience (ex. Google Analytics 4).</div>
            </div>
            <div class="cmp410-toggle">
              <input type="checkbox" id="cmp410-analytics" checked />
            </div>
          </div>

          <div class="cmp410-row">
            <div class="cmp410-row-text">
              <div class="cmp410-row-title">Retargeting</div>
              <div class="cmp410-row-desc">Publicités personnalisées (Google Ads, Meta, LinkedIn…).</div>
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
    $atts = shortcode_atts(['label' => 'Gérer mes cookies'], $atts, 'cmp_manage_cookies');
    $label = esc_html($atts['label']);
    return '<a href="#" onclick="window.CMP410_open && window.CMP410_open(); return false;">' . $label . '</a>';
  }
}

CMP_410GONE::init();
