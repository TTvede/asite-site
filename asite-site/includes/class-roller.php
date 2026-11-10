<?php
/**
 * Asite Site – redaktørlås og SVG-upload.
 *
 * Fra husmodellens hm-redaktoer.php og SVG-delen af hm-grundlag.php.
 *
 * REDAKTØRLÅSEN: Breakdances indholdstyper (Global Blocks, skabeloner, header, footer, popups) er forbeholdt
 * administratorer. WordPress' redaktørrolle må ellers rette og slette dem fra listerne i administrationen – og
 * en Global Block i papirkurven tager modulet med sig på ALLE sider. Kunden (redaktør) retter sider og medier.
 * Builderen er desuden allerede lukket for redaktører af Breakdances egen User Access, og »Edit Content
 * Interface Only« forenkler kun brugerfladen – den begrænser ikke rettigheder (Breakdances egen advarsel).
 *
 * SVG-UPLOAD: kun for administratorer og WP-CLI. Ikonerne er vores egne filer; en SVG fra en fremmed kan
 * indeholde script. Filen tjekkes desuden, før den lægges inline (se class-html.php).
 */
if (!defined('ABSPATH')) { exit; }

class Asite_Site_Roller {

    public static function init() {
        if (Asite_Site::i('redaktoerlaas')) {
            add_filter('map_meta_cap', [__CLASS__, 'laas'], 10, 4);
            add_action('admin_menu', [__CLASS__, 'skjul_menuer'], 999);
        }
        if (Asite_Site::i('svg_upload')) {
            add_filter('upload_mimes', [__CLASS__, 'svg_mime']);
            add_filter('wp_check_filetype_and_ext', [__CLASS__, 'svg_type'], 10, 3);
        }
    }

    public static function laaste_typer(): array {
        $typer = [];
        foreach (['BREAKDANCE_BLOCK_POST_TYPE', 'BREAKDANCE_TEMPLATE_POST_TYPE', 'BREAKDANCE_HEADER_POST_TYPE',
                  'BREAKDANCE_FOOTER_POST_TYPE', 'BREAKDANCE_POPUP_POST_TYPE'] as $k) {
            if (defined($k)) { $typer[] = constant($k); }
        }
        return $typer ?: ['breakdance_block', 'breakdance_template', 'breakdance_header', 'breakdance_footer', 'breakdance_popup'];
    }

    public static function laas($caps, $cap, $user_id, $args) {
        if (!in_array($cap, ['edit_post', 'delete_post', 'publish_post', 'read_post'], true) || empty($args[0])) { return $caps; }
        if ($cap === 'read_post') { return $caps; }   // læsning (visning på siden) er uændret
        $type = get_post_type((int) $args[0]);
        if ($type && in_array($type, self::laaste_typer(), true) && !user_can($user_id, 'manage_options')) {
            return ['do_not_allow'];
        }
        return $caps;
    }

    public static function skjul_menuer() {
        if (current_user_can('manage_options')) { return; }
        foreach (self::laaste_typer() as $t) { remove_menu_page("edit.php?post_type=$t"); }
    }

    private static function maa_uploade_svg(): bool {
        return (defined('WP_CLI') && WP_CLI) || current_user_can('manage_options');
    }

    public static function svg_mime($m) {
        if (self::maa_uploade_svg()) { $m['svg'] = 'image/svg+xml'; }
        return $m;
    }

    public static function svg_type($d, $file, $navn) {
        if (strtolower((string) pathinfo($navn, PATHINFO_EXTENSION)) === 'svg' && self::maa_uploade_svg()) {
            $d['ext'] = 'svg';
            $d['type'] = 'image/svg+xml';
        }
        return $d;
    }
}
