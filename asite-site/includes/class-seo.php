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
        add_action('wp_head', [__CLASS__, 'service_schema'], 31);
        add_action('wp_head', [__CLASS__, 'ekstra_schema'], 32);
    }

    /**
     * Virksomhedens oplysninger som schema.org-felter. Kun udfyldte felter kommer med.
     * Før lå de i sitets egne mu-plugins (terrasse.nu: terrassenu-localbusiness-reviews.php) – nu er de indstillinger.
     */
    /** Én værdi pr. linje (åbningstider og stednavne har mellemrum og kommaer). */
    private static function linjer(string $tekst): array {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $tekst) ?: []), fn($x) => $x !== '' && $x[0] !== '#'));
    }

    public static function virksomhed(): array {
        $v = (array) Asite_Site::i('virksomhed', []);
        $f = fn($k) => trim((string) ($v[$k] ?? ''));
        $ud = [];
        if ($f('telefon') !== '') { $ud['telephone'] = $f('telefon'); }
        if ($f('email') !== '') { $ud['email'] = $f('email'); }
        if ($f('gade') !== '' || $f('by') !== '') {
            $ud['address'] = array_filter(['@type' => 'PostalAddress', 'streetAddress' => $f('gade'), 'addressLocality' => $f('by'),
                'addressRegion' => $f('region'), 'postalCode' => $f('postnr'), 'addressCountry' => $f('land')]);
        }
        if ($f('cvr') !== '') { $ud['identifier'] = ['@type' => 'PropertyValue', 'propertyID' => 'CVR', 'value' => $f('cvr')]; }
        if ($f('beskrivelse') !== '') { $ud['description'] = $f('beskrivelse'); }
        if ($f('prisniveau') !== '') { $ud['priceRange'] = $f('prisniveau'); }
        if (preg_match('/^\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)\s*$/', $f('geo'), $m)) {
            $ud['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $m[1], 'longitude' => (float) $m[2]];
        }
        if ($t = self::linjer($f('aabningstider'))) { $ud['openingHours'] = $t; }
        if ($o = self::linjer($f('omraader'))) {
            $ud['areaServed'] = array_map(fn($n) => ['@type' => 'AdministrativeArea', 'name' => $n], $o);
        }
        if ($p = self::linjer($f('profiler'))) { $ud['sameAs'] = $p; }
        $anm = [];
        foreach (preg_split('/\R/', $f('anmeldelser')) as $l) {
            $d = array_map('trim', explode('|', $l));
            if (count($d) < 2 || $d[0] === '' || $d[1] === '') { continue; }
            $stj = max(1, min(5, (int) ($d[2] ?? 5) ?: 5));
            $anm[] = ['@type' => 'Review', 'author' => ['@type' => 'Person', 'name' => $d[0]], 'reviewBody' => $d[1],
                      'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (string) $stj, 'bestRating' => '5']];
        }
        if ($anm) {
            $snit = array_sum(array_map(fn($a) => (int) $a['reviewRating']['ratingValue'], $anm)) / count($anm);
            $ud['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => (string) round($snit, 1),
                                      'reviewCount' => (string) count($anm), 'bestRating' => '5'];
            $ud['review'] = $anm;
        }
        return $ud;
    }

    /**
     * Service-schema for sider, der sælger en ydelse (fx en by-side). Data fra sidens post meta »_asite_service«
     * (JSON: navn, type, omraader, omraade_type), som hm asite_site skriver ud fra sidens spec – live's
     * terrassenu-service-schema.php byggede det af ACF-felter, som builder-modellen ikke har (terrasse.nu 11-11-2026).
     */
    /** Sidens egne schema-data (post meta »_asite_schema«: sidetype + ekstra), skrevet af hm asite_site ud fra specen. */
    private static function sidens_schema($id): array {
        $s = json_decode((string) get_post_meta($id, '_asite_schema', true), true);
        return is_array($s) ? $s : [];
    }

    /** Ekstra JSON-LD fra sidens spec – fx prisberegnerens WebApplication (live: terrassenu-webapp-schema.php). */
    public static function ekstra_schema() {
        if (!is_singular('page')) { return; }
        foreach ((array) (self::sidens_schema(get_queried_object_id())['ekstra'] ?? []) as $obj) {
            if (!is_array($obj) || empty($obj['@type'])) { continue; }
            $json = str_replace('{home}', untrailingslashit(home_url('/')), wp_json_encode(['@context' => 'https://schema.org'] + $obj,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            echo '<script type="application/ld+json" class="asite-schema">' . $json . "</script>\n";
        }
    }

    public static function service_schema() {
        if (!is_singular('page')) { return; }
        $id = get_queried_object_id();
        $s = json_decode((string) get_post_meta($id, '_asite_service', true), true);
        if (!is_array($s) || trim((string) ($s['navn'] ?? '')) === '') { return; }
        $type = in_array($s['omraade_type'] ?? '', ['City', 'AdministrativeArea', 'Place'], true) ? $s['omraade_type'] : 'City';
        $omr = array_values(array_filter(array_map('trim', (array) ($s['omraader'] ?? []))));
        $data = ['@context' => 'https://schema.org', '@type' => 'Service', 'name' => trim((string) $s['navn']),
                 'serviceType' => trim((string) ($s['type'] ?? '')) ?: trim((string) $s['navn']),
                 'provider' => ['@id' => home_url('/#organization')], 'url' => get_permalink($id)];
        if ($omr) {
            $liste = array_map(fn($n) => ['@type' => $type, 'name' => $n], $omr);
            $data['areaServed'] = count($liste) === 1 ? $liste[0] : $liste;
        }
        echo '<script type="application/ld+json" id="asite-service">' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
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
        // Virksomhedens oplysninger flettes ind i Rank Maths virksomheds-node (den første Organization/LocalBusiness).
        $v = self::virksomhed();
        $type = preg_replace('/[^A-Za-z]/', '', (string) (Asite_Site::i('virksomhed', [])['type'] ?? ''));
        if ($v || $type !== '') {
            foreach ($data as $k => $node) {
                if (!is_array($node)) { continue; }
                $typer = (array) ($node['@type'] ?? []);
                if (!array_intersect($typer, ['Organization', 'LocalBusiness', 'ProfessionalService', 'HomeAndConstructionBusiness'])) { continue; }
                if ($type !== '') { $data[$k]['@type'] = array_values(array_unique([$type, 'Organization'])); }
                $data[$k] = array_merge($data[$k], $v);
                break;   // kun den primære virksomheds-node
            }
        }
        if (!is_singular('page')) { return $data; }
        $id = get_queried_object_id();
        // Kun artikler beholder Rank Maths Article (når indstillingen er sat).
        $art = array_filter(array_map(fn($x) => trim($x, '/ '), preg_split('/[\s,]+/', (string) Asite_Site::i('artikler', ''))));
        if ($art) {
            $far = (int) wp_get_post_parent_id($id);
            if (!$far || !in_array(get_post_field('post_name', $far), $art, true)) {
                foreach ($data as $k => $node) {
                    if (is_array($node) && array_intersect((array) ($node['@type'] ?? []), ['Article', 'BlogPosting', 'NewsArticle'])) { unset($data[$k]); }
                }
            }
        }
        // Sidetype (ContactPage, AboutPage, CollectionPage …) på Rank Maths WebPage-node.
        $type = preg_replace('/[^A-Za-z]/', '', (string) (self::sidens_schema($id)['sidetype'] ?? ''));
        if ($type !== '') {
            foreach ($data as $k => $node) {
                if (is_array($node) && ($node['@type'] ?? '') === 'WebPage') { $data[$k]['@type'] = $type; break; }
            }
        }
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
