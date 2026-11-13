<?php
/**
 * Asite Site – synlige brødkrummer, når Rank Math er slået fra (seo: asite).
 *
 * Breakdances Breadcrumbs-element (integration »rankmath«) kalder rank_math_the_breadcrumbs(), hvis funktionen findes.
 * Er Rank Math slået fra, definerer Asite Site den (includes/rank-math-kompat.php), så elementet og skabelonen
 * bliver stående uændret. Markup, etiketter og separator er Rank Maths – tegn for tegn som Rank Math skrev dem, så
 * skiftet ikke flytter en pixel. Selve krummerne er de samme som BreadcrumbList i schema-grafen (Asite_Site_Graf).
 */

if (!defined('ABSPATH')) { exit; }

class Asite_Site_Brodkrummer {

    private static function rm(string $navn, $standard = '') {
        $o = get_option('rank-math-options-general', []);
        return (is_array($o) && isset($o[$navn]) && $o[$navn] !== '') ? $o[$navn] : $standard;
    }

    /** Krummerne for den aktuelle visning: [[navn, adresse], …] (sidste krumme vises uden link). */
    public static function krummer(): array {
        $hjem = self::rm('breadcrumbs_home', 'on') === 'on'
            ? [[(string) self::rm('breadcrumbs_home_label', 'Forside'), (string) self::rm('breadcrumbs_home_link', home_url())]]
            : [];
        if (is_front_page() || (is_singular() && Asite_Site_Meta::er_forside((int) get_queried_object_id()))) {
            return $hjem;
        }
        if (is_404()) {
            return array_merge($hjem, [[(string) self::rm('breadcrumbs_404_label', '404 Error: page not found'), '']]);
        }
        if (is_search()) {
            $f = (string) self::rm('breadcrumbs_search_format', 'Results for %s');
            $navn = strpos($f, '%s') !== false ? str_replace('%s', get_search_query(), $f) : $f;
            return array_merge($hjem, [[$navn, '']]);
        }
        if (is_singular()) {
            $id = (int) get_queried_object_id();
            $k = Asite_Site_Graf::brodkrummer($id);
            // Graf::brodkrummer() giver selv forsiden med, når den er slået til – brug Rank Maths link til den.
            return self::rm('breadcrumbs_home', 'on') === 'on' ? array_merge($hjem, array_slice($k, 1)) : $k;
        }
        // Arkiver (findes ikke på husmodel-sites i dag): forsiden + arkivets titel.
        $t = wp_strip_all_tags((string) get_the_archive_title());
        return $t !== '' ? array_merge($hjem, [[$t, '']]) : $hjem;
    }

    /** Rank Maths HTML: <nav aria-label="breadcrumbs" class="rank-math-breadcrumb"><p>…</p></nav> ('' når slået fra). */
    public static function html(array $args = []): string {
        if (!Asite_Site_Graf::brodkrummer_til()) {
            return '';
        }
        $a = array_merge([
            'separator'   => (string) self::rm('breadcrumbs_separator', '-'),
            'wrap_before' => '<nav aria-label="breadcrumbs" class="rank-math-breadcrumb"><p>',
            'wrap_after'  => '</p></nav>',
            'before'      => '',
            'after'       => '',
        ], $args);
        $k = self::krummer();
        $n = count($k);
        $html = '';
        $prefiks = (string) self::rm('breadcrumbs_prefix', '');
        if ($prefiks !== '') {
            $html .= sprintf('<span class="label">%s</span> ', $prefiks);
        }
        foreach (array_values($k) as $i => [$navn, $adr]) {
            $navn = wp_strip_all_tags((string) $navn);
            $html .= wp_kses_post($a['before'])
                . (($adr !== '' && $i + 1 !== $n)
                    ? '<a href="' . esc_url($adr) . '">' . esc_html($navn) . '</a>'
                    : '<span class="last">' . esc_html($navn) . '</span>')
                . wp_kses_post($a['after']);
            if ($i + 1 !== $n) {
                $html .= '<span class="separator"> ' . wp_kses_post($a['separator']) . ' </span>';
            }
        }
        $html = wp_kses_post($a['wrap_before']) . $html . wp_kses_post($a['wrap_after']);
        return (string) apply_filters('asite_site/brodkrummer', wp_kses_post($html), $k);
    }
}
