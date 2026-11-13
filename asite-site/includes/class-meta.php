<?php
/**
 * Asite Site – SEO-meta i <head>: title, description, robots, canonical, Open Graph og Twitter.
 *
 * HVORFOR (PLAN-ASITE-SITE trin 2, ejeren 12-11-2026): Asite Site skal kunne erstatte Rank Math, så et site kan køre på
 * ren WordPress + Breakdance + Asite-plugins. Kravet er »ingen uro«: Google må ikke kunne se skiftet. Derfor laver
 * klassen PRÆCIS de tags, Rank Math lavede (samme rækkefølge, samme regler), ud fra de samme data – og det bevises
 * med `hm seo_facit` (0 forskelle på alle adresser), før Rank Math slås fra.
 *
 * Data: sidens egne felter (_asite_seo_*) – ellers Rank Maths (rank_math_*), som bliver liggende urørt, så Rank Math
 * kan slås til igen med ét klik. Indstillingen »seo«: 'rankmath' (standard – klassen skriver intet ud) eller 'asite'
 * (Asite Site skriver tags; kræver at Rank Math er slået fra, ellers ville siden få to sæt).
 */
if (!defined('ABSPATH')) { exit; }

class Asite_Site_Meta {

    // Sidens egne felter (post meta). Tomme = Rank Maths felt, ellers skabelonen.
    const TITEL = '_asite_seo_titel';
    const BESKRIVELSE = '_asite_seo_beskrivelse';
    const ROBOTS = '_asite_seo_robots';        // '' | 'noindex' | 'noindex,nofollow'
    const CANONICAL = '_asite_seo_canonical';  // tom = sidens egen adresse
    const BILLEDE = '_asite_seo_billede';      // delingsbillede (vedhæftnings-id); tom = featured / sidens første / standard

    const ROBOTS_AVANCERET = 'max-snippet:-1, max-video-preview:-1, max-image-preview:large';

    public static function init() {
        if (!self::aktiv()) { return; }
        // WordPress' egne: <title>, canonical og robots (»max-image-preview:large«) – ellers står de to gange.
        remove_action('wp_head', '_wp_render_title_tag', 1);
        remove_action('wp_head', 'rel_canonical');
        remove_action('wp_head', 'wp_robots', 1);
        add_action('wp_head', [__CLASS__, 'udskriv'], 1);
    }

    /** Skriver Asite Site SEO-tags? Kun når indstillingen siger det OG Rank Math ikke kører. */
    public static function aktiv(): bool {
        return Asite_Site::i('seo', 'rankmath') === 'asite' && !self::rank_math();
    }

    public static function rank_math(): bool {
        return defined('RANK_MATH_VERSION');
    }

    public static function udskriv() {
        if (!is_singular()) {   // arkiver, søgning og 404: kun title (WordPress' egen) – sitets sider er sider
            echo '<title>' . esc_html(wp_get_document_title()) . "</title>\n";
            if (is_404() || is_search()) { echo '<meta name="robots" content="noindex, follow"/>' . "\n"; }
            return;
        }
        echo self::html((int) get_queried_object_id());
    }

    // ---------- data ----------

    private static function rm_opt(string $navn, $standard = '') {
        $o = get_option('rank-math-options-titles', []);
        return (is_array($o) && isset($o[$navn]) && $o[$navn] !== '') ? $o[$navn] : $standard;
    }

    /** Sitets navn i og:site_name (Rank Maths »website_name«, ellers WordPress' navn). */
    public static function site_navn(): string {
        $s = trim((string) Asite_Site::i('site_navn', ''));
        return $s !== '' ? $s : (string) self::rm_opt('website_name', get_bloginfo('name'));
    }

    private static function felt(int $id, string $eget, string $rm): string {
        $v = trim((string) get_post_meta($id, $eget, true));
        return $v !== '' ? $v : trim((string) get_post_meta($id, $rm, true));
    }

    /** Skabelonens variabler (de få, sitets skabeloner bruger). */
    public static function variabler(string $s, int $id): string {
        $sep = (string) self::rm_opt('title_separator', '|');
        $s = strtr($s, [
            '%title%' => get_the_title($id), '%sitename%' => self::site_navn(), '%sep%' => $sep,
            '%sitedesc%' => get_bloginfo('description'), '%page%' => '', '%excerpt%' => (string) get_post_field('post_excerpt', $id),
            '%currentyear%' => wp_date('Y'),
        ]);
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    public static function titel(int $id): string {
        $t = self::felt($id, self::TITEL, 'rank_math_title');
        if ($t === '') { $t = (string) self::rm_opt('pt_' . get_post_type($id) . '_title', '%title% %sep% %sitename%'); }
        return self::variabler($t, $id);
    }

    public static function beskrivelse(int $id): string {
        $b = self::felt($id, self::BESKRIVELSE, 'rank_math_description');
        if ($b === '') { $b = (string) self::rm_opt('pt_' . get_post_type($id) . '_description', '%excerpt%'); }
        return self::variabler($b, $id);
    }

    /** Robots som Rank Math: [follow=>…, index=>…] eller standard; noindex → ingen avancerede felter. */
    public static function robots(int $id): array {
        $egen = trim((string) get_post_meta($id, self::ROBOTS, true));
        $r = $egen !== '' ? array_map('trim', explode(',', $egen)) : (array) get_post_meta($id, 'rank_math_robots', true);
        $r = array_values(array_filter($r, fn($x) => in_array($x, ['index', 'noindex', 'follow', 'nofollow'], true)));
        $noindex = in_array('noindex', $r, true);
        $nofollow = in_array('nofollow', $r, true);
        if (!$r) { return ['follow', 'index']; }
        return [$nofollow ? 'nofollow' : 'follow', $noindex ? 'noindex' : 'index'];
    }

    public static function noindex(int $id): bool {
        return in_array('noindex', self::robots($id), true) || get_post_status($id) !== 'publish' || post_password_required($id);
    }

    public static function canonical(int $id): string {
        $c = trim((string) get_post_meta($id, self::CANONICAL, true));
        if ($c === '') { $c = trim((string) get_post_meta($id, 'rank_math_canonical_url', true)); }
        return $c !== '' ? $c : (string) get_permalink($id);
    }

    public static function er_forside(int $id): bool {
        return get_option('show_on_front') === 'page' && (int) get_option('page_on_front') === $id;
    }

    /** Delingsbilledet: eget felt → Rank Maths → featured → sidens første billede → standardbilledet. */
    public static function billede_id(int $id): int {
        foreach ([self::BILLEDE, 'rank_math_facebook_image_id'] as $k) {
            $b = (int) get_post_meta($id, $k, true);
            if ($b && wp_attachment_is_image($b)) { return $b; }
        }
        if (Asite_Site::i('og_billede', true) && method_exists('Asite_Site_Seo', 'sidens_billede')) { return Asite_Site_Seo::sidens_billede($id); }
        return (int) get_post_thumbnail_id($id);
    }

    /** Første størrelse mellem 200 og 2000 px (full, large, medium_large) – Rank Maths regel for delingsbilleder. */
    public static function billede(int $id): array {
        $bid = self::billede_id($id);
        if (!$bid) { return []; }
        foreach (['full', 'large', 'medium_large'] as $str) {
            $s = wp_get_attachment_image_src($bid, $str);
            if (!$s || $s[1] < 200 || $s[1] > 2000 || $s[2] < 200 || $s[2] > 2000) { continue; }
            if (!preg_match('/\.(jpe?g|gif|png|webp|avif)$/i', strtok($s[0], '?'))) { return []; }
            $alt = trim((string) get_post_meta($bid, '_wp_attachment_image_alt', true));
            if ($alt === '') {
                $fk = trim((string) get_post_meta($id, 'rank_math_focus_keyword', true));
                $alt = $fk !== '' ? trim(explode(',', $fk)[0]) : get_the_title($id);
            }
            return ['url' => strtok($s[0], '?'), 'width' => $s[1], 'height' => $s[2], 'alt' => $alt,
                    'type' => (string) get_post_mime_type($bid)];
        }
        return [];
    }

    /** Læsetid som Rank Math (ord i post_content / 200, rundet ned). */
    public static function laesetid(int $id): string {
        $ord = preg_match_all('/\p{L}+/u', wp_strip_all_tags((string) get_post_field('post_content', $id)));
        $min = (int) floor($ord / 200);
        if ($min < 1) { return 'Mindre end et minut'; }
        return $min === 1 ? '1 minut' : $min . ' minutter';
    }

    // ---------- output ----------

    /** Tags i Rank Maths rækkefølge: [[art, navn, værdi], …]. */
    public static function tags(int $id): array {
        $t = [];
        $titel = self::titel($id);
        $besk = self::beskrivelse($id);
        $robots = self::robots($id);
        $noindex = self::noindex($id);
        $t[] = ['title', '', $titel];
        if ($besk !== '') { $t[] = ['name', 'description', $besk]; }
        if ($noindex && !in_array('noindex', $robots, true)) { $robots = [$robots[0], 'noindex']; }
        $t[] = ['name', 'robots', implode(', ', $noindex ? $robots : [$robots[1], $robots[0]])
                . ($noindex ? '' : ', ' . self::ROBOTS_AVANCERET)];
        if (!$noindex) { $t[] = ['link', 'canonical', self::canonical($id)]; }

        $type = self::er_forside($id) ? 'website' : 'article';
        $og_t = trim((string) get_post_meta($id, 'rank_math_facebook_title', true)) ?: $titel;
        // Delingsteksten: eget felt → beskrivelsen → WordPress' uddrag (som Rank Math; kun sider uden beskrivelse).
        $og_b = trim((string) get_post_meta($id, 'rank_math_facebook_description', true)) ?: $besk;
        if ($og_b === '' && !post_password_required($id)) { $og_b = trim((string) get_the_excerpt($id)); }
        $t[] = ['property', 'og:locale', get_locale()];
        $t[] = ['property', 'og:type', $type];
        $t[] = ['property', 'og:title', $og_t];
        if ($og_b !== '') { $t[] = ['property', 'og:description', $og_b]; }
        $t[] = ['property', 'og:url', self::canonical($id)];
        $t[] = ['property', 'og:site_name', self::site_navn()];
        $p = get_post($id);
        $pub = mysql2date(DATE_W3C, $p->post_date, false);
        $mod = mysql2date(DATE_W3C, $p->post_modified, false);
        if (strtotime($mod) > strtotime($pub)) { $t[] = ['property', 'og:updated_time', $mod]; }
        $b = self::billede($id);
        if ($b) {
            $t[] = ['property', 'og:image', $b['url']];
            if (strpos($b['url'], 'https://') === 0) { $t[] = ['property', 'og:image:secure_url', $b['url']]; }
            foreach (['width', 'height', 'alt', 'type'] as $k) {
                if (!empty($b[$k])) { $t[] = ['property', 'og:image:' . $k, (string) $b[$k]]; }
            }
        }
        $t[] = ['property', 'article:published_time', $pub];
        $t[] = ['property', 'article:modified_time', $mod];
        $t[] = ['name', 'twitter:card', 'summary_large_image'];
        $t[] = ['name', 'twitter:title', $og_t];
        if ($og_b !== '') { $t[] = ['name', 'twitter:description', $og_b]; }
        if ($b) { $t[] = ['name', 'twitter:image', $b['url']]; }
        if (!post_password_required($id)) {
            $t[] = ['name', 'twitter:label1', 'Tid for at læse'];
            $t[] = ['name', 'twitter:data1', self::laesetid($id)];
        }
        return apply_filters('asite_site/seo_tags', $t, $id);
    }

    public static function html(int $id): string {
        $ud = "<!-- SEO: Asite Site -->\n";
        foreach (self::tags($id) as [$art, $navn, $v]) {
            if ($art === 'title') { $ud .= '<title>' . esc_html($v) . "</title>\n"; }
            elseif ($art === 'link') { $ud .= '<link rel="' . esc_attr($navn) . '" href="' . esc_url($v) . "\" />\n"; }
            else { $ud .= '<meta ' . $art . '="' . esc_attr($navn) . '" content="' . esc_attr($v) . "\" />\n"; }
        }
        return $ud . "<!-- /SEO: Asite Site -->\n";
    }
}
