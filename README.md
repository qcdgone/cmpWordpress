=== Consent Management ===
Contributors: 410gone
Tags: consent, cookies, gtm, privacy
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable Tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

CMP légère pour WordPress : bandeau cookies, Consent Mode v2, GTM, traductions Polylang/WPML.

== Description ==
Consent Management propose une CMP légère avec bandeau d'information, popin de personnalisation et compatibilité Google Consent Mode v2 + Google Tag Manager. Le plugin inclut des aperçus desktop/mobile dans l'admin, des réglages de design et de libellés, et une compatibilité multilingue (Polylang, WPML ou via filtre).

== Fonctionnalités clés ==
* Bannière et popin "Personnaliser" configurables (titres, textes, boutons, liens).
* Palette éditable (fond, texte par défaut, couleurs des boutons) avec color pickers.
* Prévisualisation en direct desktop/mobile dans l'admin.
* Injection automatique du dataLayer/consent par défaut dans le `<head>` et chargement conditionnel du conteneur GTM.
* Shortcode `[cmp_manage_cookies]` pour afficher un lien « Gérer mes cookies » dans le contenu.
* Traductions prêtes pour Polylang et WPML, avec filtre `cmp_410gone_translate_setting` pour d'autres plugins multilingues.
* Options avancées : durée de conservation du consentement, debug console, forcer l'affichage pour les tests.

== Installation ==
1. Copier le dossier du plugin dans `wp-content/plugins/` (ou compresser en zip puis installer via *Extensions → Ajouter → Téléverser*).
2. Activer l'extension « Consent Management » depuis le tableau de bord WordPress.
3. Ouvrir *Réglages → 🍪 Consent Management* pour configurer la CMP.

== Configuration ==
=== Design ===
* Activez/désactivez la CMP.
* Choisissez les couleurs (fond/texte par défaut, boutons Accepter/Personnaliser) via les color pickers avec aperçu en direct.

=== Libellés ===
* Renseignez les titres/textes de la bannière et de la popin, ainsi que les libellés des boutons.
* Ajoutez les URLs de politique de confidentialité et de cookies.

=== Tracking & configuration ===
* Saisissez l'ID de conteneur GTM (ex. `GTM-XXXXXXX`).
* Ajustez `wait_for_update` (ms) pour le consentement par défaut si besoin.

=== Avancé ===
* Durée de conservation du choix (en jours).
* Mode debug (console) et option pour forcer l'affichage du bandeau en test.

== Traductions ==
1. Saisissez vos textes dans la langue principale puis enregistrez les réglages.
2. **Polylang** : allez dans *Langues → Traductions de chaînes*, groupe **CMP 410gone**, et traduisez chaque clé (`cmp_410gone_banner_title`, `cmp_410gone_btn_accept`, etc.).
3. **WPML** : allez dans *WPML → String Translation*, domaine **CMP 410gone**, puis traduisez les mêmes clés.
4. **Autres plugins** : branchez-vous sur le filtre `cmp_410gone_translate_setting` pour fournir vos traductions personnalisées.

== Utilisation front ==
* La bannière et la popin sont injectées automatiquement (`wp_head`, `wp_body_open`, `wp_footer`).
* Le shortcode `[cmp_manage_cookies label="Gérer mes cookies"]` permet d'afficher un lien de gestion des cookies dans vos pages.
* Les scripts tiers doivent être pilotés via GTM pour respecter les choix de consentement.

== Dépannage ==
* En cas d'optimisation JS (ex. WP Rocket), excluez `cmp.js`/`cmp-410gone` du « Delay JavaScript execution », puis purgez le cache.
* Activez le mode debug pour suivre le flux de consentement dans la console.

Plus d'informations sur [tutoriel sur le site de 410 gone](https://www.410-gone.fr/blog/plugin-cmp-wordpress-gratuit-consent-mode-v2-gtm.html)
