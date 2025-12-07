# Consent Management (CMP 410gone)

Une CMP légère pour WordPress avec bandeau d'information, gestion du consentement Google (Consent Mode v2) et intégration GTM. Le plugin propose des prévisualisations desktop/mobile, des réglages de design et de libellés, ainsi qu'une compatibilité multilingue (Polylang, WPML ou via filtre).

## Fonctionnalités clés
- Bannière et popin "Personnaliser" entièrement configurables (titres, textes, boutons, liens).
- Palette éditable (fond, texte par défaut, couleurs des boutons).
- Prévisualisation en direct desktop/mobile dans l'admin.
- Injection automatique du dataLayer/consent par défaut dans le `<head>` et chargement conditionnel du conteneur GTM.
- Shortcode `[cmp_manage_cookies]` pour afficher un lien "Gérer mes cookies" dans le contenu.
- Traductions prêtes pour Polylang et WPML, avec filtre `cmp_410gone_translate_setting` pour d'autres plugins multilingues.
- Options avancées : durée de conservation du consentement, debug console, forcer l'affichage pour les tests.

## Prérequis
- WordPress 6.0 ou plus récent.
- PHP 7.4+.
- Un conteneur Google Tag Manager (optionnel mais recommandé).

## Installation
1. Copier le dossier du plugin dans `wp-content/plugins/` (ou compresser en zip puis installer via *Extensions → Ajouter → Téléverser*).
2. Activer l'extension "Consent Management" depuis le tableau de bord WordPress.
3. Rendez-vous dans *Réglages → 🍪 Consent Management* pour configurer la CMP.

## Configuration
### Design
- Activez/désactivez la CMP.
- Choisissez les couleurs (fond/texte par défaut, boutons Accepter/Personnaliser) via les color pickers avec aperçu en direct.

### Libellés
- Renseignez les titres/textes de la bannière et de la popin, ainsi que les libellés des boutons.
- Ajoutez les URLs de politique de confidentialité et de cookies.

### Tracking & configuration
- Saisissez l'ID de conteneur GTM (ex. `GTM-XXXXXXX`).
- Ajustez `wait_for_update` (ms) pour le consentement par défaut si besoin.

### Avancé
- Durée de conservation du choix (en jours).
- Mode debug (console) et option pour forcer l'affichage du bandeau en test.

## Traductions
1. Saisissez vos textes dans la langue principale puis enregistrez les réglages.
2. **Polylang** : allez dans *Langues → Traductions de chaînes*, groupe **CMP 410gone**, et traduisez chaque clé (`cmp_410gone_banner_title`, `cmp_410gone_btn_accept`, etc.).
3. **WPML** : allez dans *WPML → String Translation*, domaine **CMP 410gone**, puis traduisez les mêmes clés.
4. **Autres plugins** : branchez-vous sur le filtre `cmp_410gone_translate_setting` pour fournir vos traductions personnalisées.

## Utilisation front
- La bannière et la popin sont injectées automatiquement (`wp_head`, `wp_body_open`, `wp_footer`).
- Le shortcode `[cmp_manage_cookies label="Gérer mes cookies"]` permet d'afficher un lien de gestion des cookies dans vos pages.
- Les scripts tiers doivent être pilotés via GTM pour respecter les choix de consentement.

## Dépannage
- En cas d'optimisation JS (ex. WP Rocket), excluez `cmp.js`/`cmp-410gone` du "Delay JavaScript execution", puis purgez le cache.
- Activez le mode debug pour suivre le flux de consentement dans la console.

