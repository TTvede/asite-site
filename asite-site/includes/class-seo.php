<?php
/**
 * Asite Site – FAQPage-schema og en rigtig forfatter i Rank Maths schema.
 *
 * Fra husmodellens hm-schema.php. HVORFOR (skilt.nu 04-10-2026): AI-søgning er førsteprioritet. FAQ-modulet viste
 * spørgsmål og svar, men maskinerne fik dem ikke struktureret. Samme synlige data driver modulet og skemaet, så der
 * aldrig er to sandheder. Sider oprettet med WP-CLI har post_author 0, og Rank Math skrev så en tom Person
 * (»/author/«) i schemaet; den fjernes.
 *
 * FAQ-punkterne læses fra sidens eget Breakdance-træ ud fra modulernes markører
 * (data-hm-modul="faq" → data-hm-loop="punkter" → data-hm-felt="spoergsmaal"/"svar"). Det er det, siden VISER –
 * også efter kundens egne rettelser i builderen. Husmodellens ACF-gren er udgået (builder-modellen, B-263).
 *
 * TRIN 2 (PLAN-ASITE-SITE): hele schemaet, titler, canonical, Open Graph og sitemap flytter hertil, og Rank Math
 * kan afinstalleres. Indtil da lægger vi os oven på Rank Maths json_ld.
 */
if (!defined('ABSPATH')) { exit; }

class Asite_Site_Seo {

    public static function init() {
        if (Asite_Site::i('faq_schema')) {
            add_action('wp_head', [__CLASS__, 'faq_schema'], 30);
        }
        add_filter('rank_math/json_ld', [__CLASS__, 'rankmath_json_ld'], 99);
    }

    /**
     * FAQ-punkterne i sidens Breakdance-træ. null = siden er ikke bygget med husmodellens markører.
     * Markørerne hedder stadig data-hm-* : de står i sidernes data på alle sites, der er bygget, og i
     * design/moduler.html i Git. At omdøbe dem ville kræve, at hver side blev bygget om.
     */
    public static function bd_faq($id) {
        $raw = (string) get_post_meta($id, '_breakdance_data', true);
        if ($raw === '' || strpos($raw, 'data-hm-modul') === false) { return null; }
        $d = json_decode($raw, true);
        $t = json_decode((string) ($d['tree_json_string'] ?? ''), true);
        if (!is_array($t) || empty($t['root'])) { return null; }
        $attr = function ($n, $navn) {
            foreach (($n['data']['properties']['settings']['advanced']['attributes'] ?? []) as $a) {
                if (($a['name'] ?? '') === $navn) { return (string) ($a['value'] ?? ''); }
            }
            return null;
        };
        $tekst = function ($n) use (&$tekst) {   // al tekst i elementet og dets børn
            $s = (string) ($n['data']['properties']['content']['content']['text'] ?? '');
            foreach (($n['children'] ?? []) as $c) { $s .= ' ' . $tekst($c); }
            return $s;
        };
        $felt = function ($n, $navn) use (&$felt, $attr, $tekst) {
            if ($attr($n, 'data-hm-felt') === $navn) { return $tekst($n); }
            foreach (($n['children'] ?? []) as $c) { $v = $felt($c, $navn); if ($v !== null) { return $v; } }
            return null;
        };
        $ren = fn($s) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags((string) $s), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $punkter = [];
        $find = function ($n, $i_faq = false) use (&$find, &$punkter, $attr, $felt, $ren) {
            $i_faq = $i_faq || $attr($n, 'data-hm-modul') === 'faq';
            if ($i_faq && $attr($n, 'data-hm-loop') === 'punkter') {
                foreach (($n['children'] ?? []) as $r) {
                    $punkter[] = ['spoergsmaal' => $ren($felt($r, 'spoergsmaal')), 'svar' => $ren($felt($r, 'svar'))];
                }
                return;
            }
            foreach (($n['children'] ?? []) as $c) { $find($c, $i_faq); }
        };
        $find($t['root']);
        return $punkter;
    }

    public static function faq_schema() {
        if (!is_singular('page')) { return; }
        $id = get_queried_object_id();
        $punkter = self::bd_faq($id);
        if (!is_array($punkter) || !$punkter) { return; }
        $spm = [];
        foreach ($punkter as $pkt) {
            $q = trim(wp_strip_all_tags((string) ($pkt['spoergsmaal'] ?? '')));
            $a = trim(wp_strip_all_tags((string) ($pkt['svar'] ?? '')));
            if ($q !== '' && $a !== '') {
                $spm[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
            }
        }
        // Har siden sit eget FAQPage-schema i Rank Math, er det redaktørens valg – vi lægger ikke et til.
        if (!$spm || !empty(get_post_meta($id, 'rank_math_schema_FAQPage', true))) { return; }
        $data = ['@context' => 'https://schema.org', '@type' => 'FAQPage',
                 '@id' => trailingslashit(get_permalink($id)) . '#faq', 'mainEntity' => $spm];
        echo '<script type="application/ld+json" id="asite-faqpage">' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
    }

    /** Sider under denne forælder (fx /wiki/) er artikler med en navngiven forfatter. */
    private static function er_artikel($id): bool {
        $slug = trim((string) Asite_Site::i('forfatter_forael', 'wiki'), '/');
        if ($slug === '' || !Asite_Site::i('forfatter_navn')) { return false; }
        $far = (int) wp_get_post_parent_id($id);
        return $far && get_post_field('post_name', $far) === $slug;
    }

    public static function rankmath_json_ld($data) {
        if (!is_array($data)) { return $data; }
        // Alternativt navn (gammelt firmanavn eller et domæne, der omdirigeres hertil).
        $altnavn = trim((string) Asite_Site::i('altnavn', ''));
        if ($altnavn !== '') {
            foreach ($data as $k => $node) {
                if (is_array($node) && array_intersect((array) ($node['@type'] ?? []), ['Organization', 'LocalBusiness', 'ProfessionalService'])) {
                    $data[$k]['alternateName'] = $altnavn;
                }
            }
        }
        if (!is_singular('page')) { return $data; }
        $id = get_queried_object_id();
        $artikel = self::er_artikel($id);
        $pid = home_url('/#forfatter');
        $forfatter = $artikel ? ['@id' => $pid] : ['@id' => home_url('/#organization')];
        foreach ($data as $k => $node) {
            if (!is_array($node)) { continue; }
            if (($node['@type'] ?? '') === 'Person' && untrailingslashit($node['@id'] ?? '') === home_url('/author')) {
                unset($data[$k]);
                continue;
            }
            if (isset($node['author'])) {
                $data[$k]['author'] = $forfatter;
                if ($artikel) { $data[$k]['@type'] = 'Article'; }
            }
        }
        if ($artikel) {
            $person = ['@type' => 'Person', '@id' => $pid, 'worksFor' => ['@id' => home_url('/#organization')],
                       'name' => (string) Asite_Site::i('forfatter_navn')];
            $titel = trim((string) Asite_Site::i('forfatter_titel', ''));
            if ($titel !== '') { $person['jobTitle'] = $titel; }
            $url = trim((string) Asite_Site::i('forfatter_url', ''));
            if ($url !== '') { $person['url'] = $url; }
            $data['asite_forfatter'] = $person;
        }
        return $data;
    }
}
