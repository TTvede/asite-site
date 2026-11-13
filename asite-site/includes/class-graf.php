<?php
/**
 * Asite Site – schema-grafen (JSON-LD @graph): virksomheden, websitet, siden, brødkrummer og artiklen.
 *
 * HVORFOR (PLAN-ASITE-SITE trin 2b, 13-11-2026): det sidste, Rank Math leverer i <head>. Klassen bygger den
 * grundstamme, Rank Math byggede (samme noder, felter og @id'er ud fra de samme indstillinger), og sender den gennem
 * Asite Sites EGET filter (Asite_Site_Seo::rankmath_json_ld) – det, der i dag retter Rank Maths graf: virksomhedens
 * oplysninger, Place, logo som image, sidens billede, forfatter og sidetype. Så findes rettelserne ét sted, og
 * resultatet er det samme som før skiftet. Bevist med `hm seo_facit skygge` (schemaet tælles med).
 *
 * FAQ, Service og ekstra schema (fx WebApplication) er egne blokke, som Asite Site allerede skriver (class-seo.php).
 */
if (!defined('ABSPATH')) { exit; }

class Asite_Site_Graf {

    private static function rm(string $navn, $standard = '') {
        $o = get_option('rank-math-options-titles', []);
        return (is_array($o) && isset($o[$navn]) && $o[$navn] !== '') ? $o[$navn] : $standard;
    }

    private static function rm_general(string $navn, $standard = '') {
        $o = get_option('rank-math-options-general', []);
        return (is_array($o) && isset($o[$navn]) && $o[$navn] !== '') ? $o[$navn] : $standard;
    }

    /** Virksomhedens navn i schemaet (Rank Maths »knowledgegraph_name«, ellers sitets navn). */
    public static function firmanavn(): string {
        $s = trim((string) Asite_Site::i('firmanavn', ''));
        return $s !== '' ? $s : (string) self::rm('knowledgegraph_name', Asite_Site_Meta::site_navn());
    }

    /** Logoets vedhæftnings-id (Rank Maths »knowledgegraph_logo_id«; flyttes til en egen indstilling ved skiftet). */
    public static function logo_id(): int {
        return (int) self::rm('knowledgegraph_logo_id', 0);
    }

    public static function brodkrummer_til(): bool {
        return self::rm_general('breadcrumbs', 'off') === 'on';
    }

    /** Brødkrummerne til en side: [[navn, adresse], …] – forsiden, forfædrene, siden selv. */
    public static function brodkrummer(int $id): array {
        $ud = [];
        if (self::rm_general('breadcrumbs_home', 'on') === 'on') {
            $ud[] = [(string) self::rm_general('breadcrumbs_home_label', 'Forside'), untrailingslashit(home_url())];
        }
        foreach (array_reverse(get_post_ancestors($id)) as $a) {
            $ud[] = [self::krumme_navn((int) $a), (string) get_permalink($a)];
        }
        $ud[] = [self::krumme_navn($id), (string) get_permalink($id)];
        return $ud;
    }

    private static function krumme_navn(int $id): string {
        $n = trim((string) get_post_meta($id, 'rank_math_breadcrumb_title', true));
        return $n !== '' ? $n : wp_strip_all_tags(get_the_title($id));
    }

    /** Grundstammen som Rank Math byggede den (nøglerne er Rank Maths, så filteret genkender dem). */
    public static function grundstamme(int $id): array {
        $hjem = untrailingslashit(home_url());
        $url = (string) get_permalink($id);
        $sprog = (string) get_bloginfo('language');
        $forside = Asite_Site_Meta::er_forside($id);
        $org_id = home_url('/#organization');
        $p = get_post($id);
        $pub = mysql2date(DATE_W3C, $p->post_date, false);
        $mod = mysql2date(DATE_W3C, $p->post_modified, false);
        $titel = Asite_Site_Meta::titel($id);
        $d = [];

        // Rank Math HTML-escaper navnene i grafen (»Fauth teknik &amp; service«).
        $org = ['@type' => 'Organization', '@id' => $org_id, 'name' => esc_html(self::firmanavn())];
        $lid = self::logo_id();
        if ($lid && ($l = wp_get_attachment_image_src($lid, 'full'))) {
            $org['logo'] = ['@type' => 'ImageObject', '@id' => home_url('/#logo'), 'url' => $l[0], 'contentUrl' => $l[0],
                            'caption' => esc_html(self::firmanavn()), 'inLanguage' => $sprog, 'width' => (string) $l[1], 'height' => (string) $l[2]];
        }
        $d['publisher'] = $org;

        $ws = ['@type' => 'WebSite', '@id' => home_url('/#website'), 'url' => $hjem, 'name' => esc_html(Asite_Site_Meta::site_navn()),
               'publisher' => ['@id' => $org_id], 'inLanguage' => $sprog];
        if ($forside) {
            $ws['potentialAction'] = ['@type' => 'SearchAction', 'target' => home_url('/?s={search_term_string}'),
                                      'query-input' => 'required name=search_term_string'];
        }
        $d['WebSite'] = $ws;

        // Rank Math HTML-escaper sidens navn (»&« → »&amp;«) – kun her, ikke i artiklens headline.
        $side = ['@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => esc_html($titel),
                 'datePublished' => $pub, 'dateModified' => $mod, 'isPartOf' => ['@id' => home_url('/#website')], 'inLanguage' => $sprog];
        if ($forside) {
            $side['about'] = ['@id' => $org_id];
        } elseif (self::brodkrummer_til()) {
            $side['breadcrumb'] = ['@id' => $url . '#breadcrumb'];
            $liste = [];
            foreach (self::brodkrummer($id) as $i => [$navn, $adr]) {
                $liste[] = ['@type' => 'ListItem', 'position' => (string) ($i + 1), 'item' => ['@id' => $adr, 'name' => $navn]];
            }
            $d['BreadcrumbList'] = ['@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $liste];
        }
        $d['WebPage'] = $side;

        // Artiklen (Rank Maths standard-»rich snippet« for typen) – også på forsiden; Asite Sites »artikler« fjerner
        // den bagefter fra sider, der ikke er artikler. Tekster HTML-escapet som hos Rank Math.
        $type = get_post_type($id);
        if (self::rm("pt_{$type}_default_rich_snippet", 'off') === 'article') {
            $a = ['@type' => (string) self::rm("pt_{$type}_default_article_type", 'Article'), 'headline' => esc_html($titel)];
            $kw = trim((string) get_post_meta($id, 'rank_math_focus_keyword', true));
            if ($kw !== '') { $a['keywords'] = $kw; }
            $a += ['datePublished' => $pub, 'dateModified' => $mod, 'author' => ['@id' => $org_id],
                   'publisher' => ['@id' => $org_id]];
            $besk = Asite_Site_Meta::beskrivelse($id);
            if ($besk !== '') { $a['description'] = esc_html($besk); }
            $a += ['name' => esc_html($titel), '@id' => $url . '#richSnippet', 'isPartOf' => ['@id' => $url . '#webpage'],
                   'inLanguage' => $sprog, 'mainEntityOfPage' => ['@id' => $url . '#webpage']];
            $d['richSnippet'] = $a;
        }
        return $d;
    }

    /** Hele grafen for den viste side: grundstammen gennem Asite Sites filter. Kræver, at siden er den forespurgte. */
    public static function graf(int $id): array {
        $d = self::grundstamme($id);
        if (method_exists('Asite_Site_Seo', 'rankmath_json_ld')) { $d = Asite_Site_Seo::rankmath_json_ld($d); }
        $d = apply_filters('asite_site/graf', $d, $id);
        // Rank Math skriver alle tal som tekst (fx geo »55.79«) – samme her, så grafen er identisk.
        array_walk_recursive($d, function (&$v) { if (is_int($v) || is_float($v)) { $v = (string) $v; } });
        return ['@context' => 'https://schema.org', '@graph' => array_values(array_filter($d, 'is_array'))];
    }

    public static function html(int $id): string {
        return '<script type="application/ld+json" class="asite-graf">'
            . wp_json_encode(self::graf($id), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
    }
}
