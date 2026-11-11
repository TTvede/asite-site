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
        // Delingsbillede: Rank Math finder kun featured image/standardbillede – husmodel-sider har sjældent et
        // featured image, så ny.terrasse.nu delte uden billede (live viste sidens hero). 11-11-2026.
        if (Asite_Site::i('og_billede', true)) {
            add_action('rank_math/opengraph/facebook/add_additional_images', [__CLASS__, 'og_billede']);
            add_action('rank_math/opengraph/twitter/add_additional_images', [__CLASS__, 'og_billede']);
        }
        // locations.kml + local-sitemap.xml: live terrasse.nu havde dem fra Rank Maths Local SEO (slået fra på ny). 11-11-2026.
        if (Asite_Site::i('locations_kml', true)) {
            add_action('parse_request', [__CLASS__, 'kml_fil'], 0);
            add_filter('rank_math/sitemap/index', [__CLASS__, 'kml_index']);
        }
        // Forfattersider: live terrasse.nu gav 404; WordPress sender ?author=1 videre til /author/<brugernavn>/. 11-11-2026.
        if (Asite_Site::i('forfattersider_fra', true)) {
            add_action('template_redirect', [__CLASS__, 'forfattersider'], 0);
        }
    }

    /** Rank Maths eget Local SEO-modul laver filerne selv – så rører vi dem ikke. */
    private static function kml_aktiv(): bool {
        if (class_exists('\\RankMath\\Helper') && \RankMath\Helper::is_module_active('local-seo')) { return false; }
        $v = (array) Asite_Site::i('virksomhed', []);
        return trim((string) ($v['gade'] ?? '')) !== '' || trim((string) ($v['by'] ?? '')) !== '';
    }

    private static function cdata(string $s): string {
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $s) . ']]>';
    }

    /** Svaret for /locations.kml og /local-sitemap.xml (ellers null) – som Rank Maths Local SEO, men med koordinaterne. */
    public static function kml_svar(string $sti): ?string {
        $sti = strtolower('/' . ltrim($sti, '/'));
        if (!in_array($sti, ['/locations.kml', '/local-sitemap.xml'], true) || !self::kml_aktiv()) { return null; }
        $hjem = untrailingslashit(home_url());
        if ($sti === '/local-sitemap.xml') {
            return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
                . "\t<url>\n\t\t<loc>" . esc_url($hjem . '/locations.kml') . "</loc>\n\t\t<lastmod>" . self::kml_dato() . "</lastmod>\n\t</url>\n</urlset>\n";
        }
        $v = (array) Asite_Site::i('virksomhed', []);
        $f = fn($k) => trim((string) ($v[$k] ?? ''));
        $navn = (string) get_bloginfo('name');
        $adr = implode(', ', array_filter([$f('gade'), $f('by'), $f('region'), $f('postnr'), $f('land')]));
        $punkt = '';
        if (preg_match('/^\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)\s*$/', $f('geo'), $m)) {
            $punkt = "\n\t\t\t\t\t<coordinates>{$m[2]},{$m[1]},0</coordinates>\n\t\t\t\t";
        }
        $l = '<atom:link href="' . esc_url($hjem) . '"/>';
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n"
            . "\t<Document>\n\t\t<name>" . esc_html('Locations for ' . $navn) . "</name>\n\t\t<open>1</open>\n\t\t<Folder>\n\t\t\t$l\n"
            . "\t\t\t<Placemark>\n\t\t\t\t<name>" . self::cdata($navn) . "</name>\n"
            . ($f('beskrivelse') !== '' ? "\t\t\t\t<description>" . self::cdata($f('beskrivelse')) . "</description>\n" : '')
            . "\t\t\t\t<address>" . self::cdata($adr) . "</address>\n"
            . ($f('telefon') !== '' ? "\t\t\t\t<phoneNumber>" . self::cdata($f('telefon')) . "</phoneNumber>\n" : '')
            . "\t\t\t\t$l\n\t\t\t\t<Point>$punkt</Point>\n\t\t\t</Placemark>\n\t\t</Folder>\n\t</Document>\n</kml>\n";
    }

    /** Sidst ændret: forsidens ændringsdato (virksomhedens data ændres sjældent og har ingen egen dato). */
    private static function kml_dato(): string {
        $id = (int) get_option('page_on_front');
        $t = $id ? get_post_modified_time('c', true, $id) : '';
        return $t ?: gmdate('c');
    }

    public static function kml_fil() {
        $svar = self::kml_svar((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        if ($svar === null) { return; }
        status_header(200);
        header('Content-Type: text/xml; charset=UTF-8');
        echo $svar;
        exit;
    }

    /** local-sitemap.xml i Rank Maths sitemap-indeks (som på live). */
    public static function kml_index($xml) {
        if (!self::kml_aktiv()) { return $xml; }
        return $xml . "\t<sitemap>\n\t\t<loc>" . esc_url(untrailingslashit(home_url()) . '/local-sitemap.xml') . "</loc>\n\t\t<lastmod>"
            . self::kml_dato() . "</lastmod>\n\t</sitemap>\n";
    }

    /** Forfatterarkiver findes ikke: 404, før WordPress når at omdirigere til /author/<brugernavn>/. */
    public static function forfattersider() {
        if (!is_author()) { return; }
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
    }

    /** Standardbilledet (indstillingen er et id eller et filnavn fra mediebiblioteket) → id, eller 0. */
    public static function standardbillede(): int {
        return (int) Asite_Site::husk('standardbillede', function () {
            $v = trim((string) Asite_Site::i('standardbillede', ''));
            if ($v === '') { return 0; }
            if (ctype_digit($v)) { return (int) $v; }
            global $wpdb;
            $fil = basename($v);
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND (meta_value = %s OR meta_value LIKE %s) ORDER BY post_id LIMIT 1",
                $fil, '%/' . $wpdb->esc_like($fil)));
        });
    }

    /** Sidens billede: featured image, sidens første billede – ellers standardbilledet. */
    public static function sidens_billede($id): int {
        return self::foerste_billede($id) ?: self::standardbillede();
    }

    /** ImageObject som Rank Maths (live): @id = billedets adresse, billedteksten fra alt-teksten. */
    private static function billede_node(int $bid): array {
        $src = wp_get_attachment_image_src($bid, 'full');
        if (!$src || empty($src[0])) { return []; }
        $tekst = trim((string) get_post_meta($bid, '_wp_attachment_image_alt', true));
        if ($tekst === '') { $tekst = trim((string) wp_get_attachment_caption($bid)); }
        return array_filter(['@type' => 'ImageObject', '@id' => $src[0], 'url' => $src[0],
            'width' => $src[1] ? (string) $src[1] : '', 'height' => $src[2] ? (string) $src[2] : '',
            'caption' => $tekst, 'inLanguage' => (string) get_bloginfo('language')], fn($x) => $x !== '');
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
    /** Rank Maths billedobjekt: har siden intet billede, lægges featured image eller sidens første billede ind. */
    public static function og_billede($billede) {
        if (!is_object($billede) || $billede->has_images() || !is_singular()) { return; }
        $id = self::sidens_billede(get_queried_object_id());
        if ($id) { $billede->add_image_by_id($id); }
    }

    /** Featured image – ellers det første billede fra mediebiblioteket i sidens Breakdance-træ (hero'en). */
    public static function foerste_billede($id): int {
        $t = (int) get_post_thumbnail_id($id);
        if ($t) { return $t; }
        $d = json_decode((string) get_post_meta($id, '_breakdance_data', true), true);
        $trae = json_decode((string) ($d['tree_json_string'] ?? ''), true);
        $find = function ($n) use (&$find) {
            $m = (int) ($n['data']['properties']['content']['image']['media']['id'] ?? 0);
            if ($m) { return $m; }
            foreach (($n['children'] ?? []) as $c) { if ($r = $find($c)) { return $r; } }
            return 0;
        };
        return is_array($trae) && !empty($trae['root']) ? $find($trae['root']) : 0;
    }

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

    /**
     * Matcher siden en liste af forælder-slugs (»wiki« = undersider af wiki) og stier (»/sti/« = netop den side)?
     * Bruges af »artikler« og af forfatteren.
     */
    private static function i_listen($id, array $liste): bool {
        if (!$liste) { return false; }
        $far = (int) wp_get_post_parent_id($id);
        $sti = '/' . trim((string) wp_parse_url(get_permalink($id), PHP_URL_PATH), '/') . '/';
        $stier = array_map(fn($x) => '/' . trim($x, '/') . '/', array_filter($liste, fn($x) => $x[0] === '/'));
        return in_array($sti, $stier, true)
            || ($far && in_array(get_post_field('post_name', $far), array_filter($liste, fn($x) => $x[0] !== '/'), true));
    }

    private static function artikel_liste(): array {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) Asite_Site::i('artikler', '')))));
    }

    /**
     * Artikler med en navngiven forfatter: undersider af »forfatter_forael« (fx /wiki/) – og, når »artikler« er sat,
     * alle sider, der beholder Article. Live terrasse.nu havde forfatteren på pleje, cases og tidligere blogindlæg
     * (QA 11-11-2026); ellers stod virksomheden som forfatter dér.
     */
    private static function er_artikel($id): bool {
        if (!Asite_Site::i('forfatter_navn')) { return false; }
        $slug = trim((string) Asite_Site::i('forfatter_forael', 'wiki'), '/');
        return ($slug !== '' && self::i_listen($id, [$slug])) || self::i_listen($id, self::artikel_liste());
    }

    /**
     * Det, live's Rank Math (med Local SEO) havde, og som ny ikke fik (QA 11-11-2026): WebSite alternateName,
     * virksomhedens url og image (logoet), og adressen som Place med location fra virksomheden.
     */
    private static function graf_som_live(array $data): array {
        $hjem = untrailingslashit(home_url());
        $alt = trim((string) Asite_Site::i('site_altnavn', ''));
        foreach ($data as $k => $node) {
            if (!is_array($node)) { continue; }
            $typer = (array) ($node['@type'] ?? []);
            if ($alt !== '' && in_array('WebSite', $typer, true) && empty($node['alternateName'])) {
                $data[$k]['alternateName'] = $alt;
            }
            if (($node['@id'] ?? '') !== home_url('/#organization')) { continue; }
            if (empty($node['url'])) { $data[$k]['url'] = $hjem; }
            if (empty($node['image']) && !empty($node['logo']['@id'])) { $data[$k]['image'] = ['@id' => $node['logo']['@id']]; }
            if (!empty($node['address']) && empty($node['location'])) {
                $pid = home_url('/#place');
                $har_place = false;
                foreach ($data as $n) { if (is_array($n) && ($n['@id'] ?? '') === $pid) { $har_place = true; } }
                if (!$har_place) { $data['asite_place'] = ['@type' => 'Place', '@id' => $pid, 'address' => $node['address']]; }
                $data[$k]['location'] = ['@id' => $pid];
            }
        }
        return $data;
    }

    /** Sidens billede som ImageObject, primaryImageOfPage og Article image – når Rank Math intet billede fandt. */
    private static function sidens_billede_i_graf(array $data, int $id): array {
        $side = null;
        foreach ($data as $k => $node) {
            if (is_array($node) && substr((string) ($node['@id'] ?? ''), -8) === '#webpage') { $side = $k; break; }
        }
        if ($side === null || !empty($data[$side]['primaryImageOfPage'])) { return $data; }
        $bid = self::sidens_billede($id);
        $billede = $bid ? self::billede_node($bid) : [];
        if (!$billede) { return $data; }
        $data['asite_billede'] = $billede;
        $data[$side]['primaryImageOfPage'] = ['@id' => $billede['@id']];
        foreach ($data as $k => $node) {
            if (is_array($node) && array_intersect((array) ($node['@type'] ?? []), ['Article', 'BlogPosting', 'NewsArticle']) && empty($node['image'])) {
                $data[$k]['image'] = ['@id' => $billede['@id']];
            }
        }
        return $data;
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
        $data = self::graf_som_live($data);
        if (is_singular()) { $data = self::sidens_billede_i_graf($data, (int) get_queried_object_id()); }
        if (!is_singular('page')) { return $data; }
        $id = get_queried_object_id();
        // Kun artikler beholder Rank Maths Article (når indstillingen er sat).
        // »wiki« = undersider af wiki; »/sti/« = netop den side (fx et tidligere blogindlæg, der nu er en topside).
        $art = self::artikel_liste();
        if ($art) {
            if (!self::i_listen($id, $art)) {
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
