<?php
/**
 * Plugin Name: Asite Site
 * Description: Det Breakdance ikke kan selv – på et site, kunden kan overtage: billedattributter og ærlig sizes, FAQ-schema og forfatter, omdirigeringer og robots.txt, hero uden lazy load, inline-SVG, redaktørlås og sporing. Alt styres fra Værktøjer → Asite Site. Opdateres fra GitHub.
 * Version: 0.1.11
 * Author: Asite
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: asite-site
 *
 * HVORFOR DETTE PLUGIN (ejeren 10-11-2026, PLAN-ASITE-SITE):
 * Et husmodel-site skal kunne overdrages til kunden og køre på REN WordPress + Breakdance – uden vores server,
 * vores scripts og vores mu-plugins. Kun Asite-plugins må følge med. Pluginet overtager derfor husmodellens
 * hm-*.php, og alt, der før stod som PHP-konstanter i <præfiks>-site.php eller som filtre i sitets egen kode,
 * er nu indstillinger i databasen (én option: asite_site).
 *
 * Det VISUELLE (farver, skrifter, grundlags-CSS) hører IKKE til her: det flyttes ind i Breakdance selv
 * (variabler, globale indstillinger, header_code), så sitet ser ud som det skal uden vores kode.
 */
if (!defined('ABSPATH')) { exit; }

define('ASITE_SITE_VERSION', '0.1.11');
define('ASITE_SITE_DIR', plugin_dir_path(__FILE__));
define('ASITE_SITE_FILE', __FILE__);

require_once ASITE_SITE_DIR . 'includes/class-core.php';
require_once ASITE_SITE_DIR . 'includes/class-html.php';
require_once ASITE_SITE_DIR . 'includes/class-seo.php';
require_once ASITE_SITE_DIR . 'includes/class-lancering.php';
require_once ASITE_SITE_DIR . 'includes/class-roller.php';
require_once ASITE_SITE_DIR . 'includes/class-sporing.php';
require_once ASITE_SITE_DIR . 'includes/class-admin.php';
require_once ASITE_SITE_DIR . 'includes/class-updater.php';

add_action('plugins_loaded', function () {
    // Indstillingssiden og opdateringen kører ALTID – også i overgangen, hvor husmodellens mu-plugins stadig
    // ligger på sitet. Så kan pluginet installeres og udfyldes, FØR mu-plugins fjernes.
    Asite_Site_Admin::init();
    Asite_Site_Updater::init();

    // Dobbeltkørsel ville give to output-buffere, to FAQ-schemaer og to robots-blokke. Husmodellens mu-plugins
    // sætter HM_PREFIKS; så længe den findes, holder pluginet sig helt væk fra sitet (admin-noten siger det).
    if (Asite_Site::i_overgang()) { return; }

    Asite_Site_Html::init();
    Asite_Site_Seo::init();
    Asite_Site_Lancering::init();
    Asite_Site_Roller::init();
    Asite_Site_Sporing::init();
}, 5);

// Aktivering: skriv standardindstillingerne, så siden virker fra første klik (intet slås til, som ikke var til før).
register_activation_hook(__FILE__, ['Asite_Site', 'ved_aktivering']);
