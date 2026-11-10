<?php
/**
 * Asite Site – billedattributter, ærlig sizes og den HTML-finish, Breakdance ikke laver selv.
 *
 * Fra husmodellens hm-billedattr.php og hm-grundlag.php. HVORFOR hver del (alt er målt på rigtige sites):
 *
 * BILLEDER (skilt.nu 03-10-2026)
 * - Breakdance skriver billeder uden width/height (soflyy/breakdance-bugs#1170) → layoutskift (CLS).
 * - WordPress' standard-sizes er »(max-width: ORIGINAL) 100vw, ORIGINAL«, så browseren henter for store varianter.
 *   Et loft (standard 1200 px) og en tabel pr. komponentklasse giver den sandheden om, hvor bredt billedet vises.
 * - Breakdance udelader attributten alt helt, når mediebibliotekets alt er tom → crawlere tæller det som fejl.
 * - Understørrelser i AVIF skal have samme kvalitet som originalen (q50), ellers bliver de større end originalen.
 * METODE: kilden røres aldrig – kun manglende attributter tilføjes, med WordPress-kernens egne funktioner (idempotent).
 *
 * FINISH (fauth.dk, skilt.nu, terrasse.nu)
 * - Breakdance lazy-loader ALLE billeder, også heroens – det første man ser (LCP).
 * - Ikoner, der skal kunne farves og animeres med CSS, er SVG-filer i mediebiblioteket: <img> byttes til SVG-koden.
 * - Scenelag kommer fra en liste, så CSS'en kan ikke kende dem på plads: laget får billedets filnavn som klasse.
 * - Tomme valgfrie felter giver et linjeskift i elementet, så CSS :empty ikke rammer – blanktegnene fjernes.
 *
 * ÉN output-buffer (før: to, en pr. mu-plugin) i samme rækkefølge som før: billederne først, så finishen –
 * hero-reglen skal fjerne loading="lazy" EFTER srcset er lagt på.
 */
if (!defined('ABSPATH')) { exit; }

class Asite_Site_Html {

    public static function init() {
        $s = Asite_Site::indstillinger();

        if (!empty($s['billeder'])) {
            /* WordPress' »sizes=auto« falder tilbage til 100vw for skjulte billeder og vinder over vores tal. */
            add_filter('wp_img_tag_add_auto_sizes', '__return_false');
            add_filter('wp_calculate_image_sizes', [__CLASS__, 'sizes_loft'], 10, 2);
            add_action('wp_head', function () {
                // width/height får height:auto med specificitet 0, så eksisterende højderegler stadig vinder.
                echo "<style id=\"asite-billeddim\">:where(img[width][height]){height:auto}</style>\n";
            }, 1);
        }
        $q = (int) ($s['avif_kvalitet'] ?? 0);
        if ($q > 0) {
            add_filter('wp_editor_set_quality', fn($standard, $mime) => $mime === 'image/avif' ? $q : $standard, 10, 2);
        }

        // WordPress-kernens CSS fjernes af Breakdances Bloat Eliminator – men kun i <head>. WordPress 7 lægger
        // global-styles (9,3 kB) på igen i footeren, og den rammer Breakdance ikke (målt fauth.dk 07-11-2026).
        // Følger Breakdances egen indstilling, så der kun er ét sted at slå til og fra.
        if (!empty($s['global_styles_fra'])) {
            add_action('wp_footer', [__CLASS__, 'global_styles_fra'], 2);
        }

        add_action('template_redirect', [__CLASS__, 'start_buffer'], 4);

        // Skrifter over folden hentes med det samme. Uden det opdager browseren dem først, når CSS'en er læst, og
        // teksten skifter skrift undervejs: på terrasse.nu voksede heroen på mobil, og USP-båndet hoppede (CLS 0,16–0,21,
        // 10-11-2026). Hent kun små filer – en stor skrift gjorde LCP 0,4 s langsommere.
        if (trim((string) ($s['skrifter_forhaand'] ?? '')) !== '') {
            add_action('wp_head', [__CLASS__, 'skrifter_forhaand'], 1);
        }
    }

    /** Skriftfilernes adresser. Et bart filnavn hører til sitets egne skrifter i uploads/<præfiks>-filer/fonts/. */
    public static function skrifter_adresser(): array {
        $ud = [];
        $up = wp_get_upload_dir();
        $p = Asite_Site::praefiks();
        foreach (Asite_Site::liste((string) Asite_Site::i('skrifter_forhaand', '')) as $f) {
            if (preg_match('#^https?://#i', $f) || $f[0] === '/') {
                $sti = wp_parse_url($f, PHP_URL_PATH);
                $rel = strpos((string) $sti, '/wp-content/uploads/') === 0 ? substr($sti, strlen('/wp-content/uploads/')) : null;
            } else {
                if (!$p) { continue; }
                $rel = $p . '-filer/fonts/' . basename($f);
            }
            // Kun filer, der faktisk ligger på sitet: en forkert linje må ikke give en 404 på hver side.
            if (!$rel || strpos($rel, '..') !== false || !is_file(trailingslashit($up['basedir']) . $rel)) { continue; }
            $ud[] = '/wp-content/uploads/' . $rel;
        }
        return array_values(array_unique($ud));
    }

    public static function skrifter_forhaand() {
        foreach (self::skrifter_adresser() as $u) {
            $type = preg_match('/\.woff2$/i', $u) ? 'font/woff2' : (preg_match('/\.woff$/i', $u) ? 'font/woff' : '');
            echo '<link rel="preload" href="' . esc_url($u) . '" as="font"' . ($type ? ' type="' . $type . '"' : '') . " crossorigin>\n";
        }
    }

    public static function global_styles_fra() {
        if (!function_exists('\Breakdance\Data\get_global_option')) { return; }
        if (in_array('gutenberg-blocks-css', (array) \Breakdance\Data\get_global_option('breakdance_settings_bloat_eliminator'), true)) {
            wp_dequeue_style('global-styles');
        }
    }

    private static function i_builder(): bool {
        return isset($_GET['breakdance']) || isset($_GET['breakdance_iframe']);
    }

    public static function start_buffer() {
        if (is_admin() || self::i_builder() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) { return; }
        ob_start([__CLASS__, 'behandl']);
    }

    public static function behandl($html) {
        if (!is_string($html) || $html === '') { return $html; }
        $s = Asite_Site::indstillinger();
        if (!empty($s['billeder'])) { $html = self::billedattr($html); }
        if (!empty($s['scenelag'])) { $html = self::lagnavn($html); }
        if (!empty($s['inline_svg'])) { $html = self::inline_svg($html); }
        if (!empty($s['hero_ivrig'])) { $html = self::hero_ivrig($html); }
        $html = self::tomme_felter($html);
        return apply_filters('asite_site/html', $html);
    }

    /* ---------- billeder ---------- */

    public static function sizes_loft($sizes, $size) {
        $loft = (int) Asite_Site::i('sizes_loft', 1200);
        $bredde = is_array($size) && !empty($size[0]) ? (int) $size[0] : 0;
        if ($loft <= 0 || $bredde <= $loft) { return $sizes; }
        return sprintf('(max-width: %1$dpx) 100vw, %1$dpx', $loft);
    }

    /** Tabellen »klasse → sizes«: hvor bredt viser komponenten FAKTISK billedet. */
    public static function sizes_regler(): array {
        return Asite_Site::husk('sizes_regler',
            fn() => apply_filters('asite_site/sizes_regler', Asite_Site::par((string) Asite_Site::i('sizes_regler', ''))));
    }

    private static function billed_sizes($tag) {
        $regler = self::sizes_regler();
        if (!$regler || !preg_match('/\sclass\s*=\s*["\']([^"\']*)["\']/i', $tag, $m)) { return $tag; }
        $klasser = ' ' . $m[1] . ' ';
        foreach ($regler as $k => $sizes) {
            if (strpos($klasser, ' ' . $k . ' ') === false) { continue; }
            if (preg_match('/\ssizes\s*=/i', $tag)) {
                return preg_replace('/\ssizes\s*=\s*["\'][^"\']*["\']/i', ' sizes="' . $sizes . '"', $tag, 1);
            }
            if (preg_match('/\ssrcset\s*=/i', $tag)) {
                return preg_replace('/<img\b/i', '<img sizes="' . $sizes . '"', $tag, 1);
            }
            return $tag;
        }
        return $tag;
    }

    private static function billed_id($src) {
        static $memo = [];
        if (isset($memo[$src])) { return $memo[$src]; }
        $url = (strpos($src, '/') === 0 && strpos($src, '//') !== 0) ? home_url($src) : $src;
        return $memo[$src] = (int) attachment_url_to_postid(preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $url));
    }

    public static function billedattr($html) {
        if (strpos($html, '<img') === false) { return $html; }
        $her = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $p = Asite_Site::praefiks();
        return preg_replace_callback('/<img\b[^>]*>/i', function ($m) use ($her, $p) {
            $tag = $m[0];
            if (!preg_match('/\ssrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $s)) { return $tag; }
            $src = html_entity_decode($s[1]);
            // Et tomt billedfelt: Breakdance skriver en usynlig SVG-pladsholder uden alt – crawlere tæller den som fejl.
            if (strpos($src, 'data:image/svg') === 0 && $p && preg_match('/\b' . preg_quote($p, '/') . '-/', $tag)) { return ''; }
            $vaert = wp_parse_url($src, PHP_URL_HOST);
            if (strpos($src, '/wp-content/uploads/') === false || ($vaert && strcasecmp($vaert, $her) !== 0)) { return $tag; }
            $tomAlt = !preg_match('/\salt\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $tag, $a) || trim(($a[1] ?? '') . ($a[2] ?? '')) === '';
            if ($tomAlt && strpos($tag, 'aria-hidden') === false) {
                $aid = self::billed_id($src);
                $alt = $aid ? trim((string) get_post_meta($aid, '_wp_attachment_image_alt', true)) : '';
                if ($alt !== '') {
                    $tag = preg_match('/\salt\s*=/i', $tag)
                        ? preg_replace('/\salt\s*=\s*["\'][^"\']*["\']/i', ' alt="' . esc_attr($alt) . '"', $tag, 1)
                        : preg_replace('/<img\b/i', '<img alt="' . esc_attr($alt) . '"', $tag, 1);
                }
            }
            // Pynt (tom alt-tekst i mediebiblioteket): Breakdance udelader hele attributten – det tælles som fejl.
            if (!preg_match('/\salt\s*=/i', $tag)) { $tag = preg_replace('/<img\b/i', '<img alt=""', $tag, 1); }
            if (preg_match('/\.svg(\?|$)/i', $src)) { return $tag; }
            $harSrcset = (bool) preg_match('/\ssrcset\s*=/i', $tag);
            $harW = (bool) preg_match('/\swidth\s*=/i', $tag);
            $harH = (bool) preg_match('/\sheight\s*=/i', $tag);
            if (!$harSrcset || !$harW || !$harH) {
                $id = self::billed_id($src);
                $meta = $id ? wp_get_attachment_metadata($id) : null;
                if (is_array($meta) && !empty($meta['file'])) {
                    if (!$harSrcset) {
                        $ny = wp_image_add_srcset_and_sizes($tag, $meta, $id);
                        if (is_string($ny) && $ny !== '') { $tag = $ny; }
                    }
                    if (!$harW || !$harH) {
                        $dim = wp_image_src_get_dimensions($src, $meta, $id);
                        if (is_array($dim) && !empty($dim[0]) && !empty($dim[1])) {
                            if (!$harW) { $tag = preg_replace('/<img\b/i', '<img width="' . (int) $dim[0] . '"', $tag, 1); }
                            if (!$harH) { $tag = preg_replace('/<img\b/i', '<img height="' . (int) $dim[1] . '"', $tag, 1); }
                        }
                    }
                }
            }
            return self::billed_sizes($tag);
        }, $html);
    }

    /* ---------- finish ---------- */

    /** <div class="… <p>-lag …"><div …><img src=".../bold-300x200.avif"> → laget får klassen <p>-bold. */
    public static function lagnavn($html) {
        $p = Asite_Site::praefiks();
        if (!$p || strpos($html, "$p-lag") === false) { return $html; }
        return preg_replace_callback('/(<div\b[^>]*\bclass="[^"]*\b' . $p . '-lag\b)([^"]*"[^>]*>(?:\s|<!--.*?-->)*<div\b[^>]*>(?:\s|<!--.*?-->)*<img\b[^>]*?\bsrc="[^"]*\/([a-z0-9-]+?)(?:-\d+x\d+)?\.(?:avif|webp|png|jpe?g|svg)")/s', function ($m) use ($p) {
            $navn = "$p-" . $m[3];
            // Står klassen der allerede, lægges den ikke på igen: så kan HTML'en behandles to gange uden at samle duplikater.
            $resten_af_klasserne = (string) strstr($m[2], '"', true);
            if (preg_match('/\b' . preg_quote($navn, '/') . '\b/', $resten_af_klasserne)) { return $m[0]; }
            return $m[1] . ' ' . $navn . $m[2];
        }, $html);
    }

    /** <img class="… <p>-svg …" src="/wp-content/uploads/….svg"> → SVG-koden selv (kun egne uploads). */
    public static function inline_svg($html) {
        $p = Asite_Site::praefiks();
        if (!$p || strpos($html, "$p-svg") === false) { return $html; }
        $up = wp_get_upload_dir();
        return preg_replace_callback('/<img\b[^>]*\bclass="[^"]*\b' . $p . '-svg\b[^"]*"[^>]*>/', function ($m) use ($up) {
            if (!preg_match('/\ssrc="([^"]+\.svg)"/', $m[0], $s)) { return $m[0]; }
            $sti = wp_parse_url($s[1], PHP_URL_PATH);
            $rel = strpos($sti, '/wp-content/uploads/') === 0 ? substr($sti, strlen('/wp-content/uploads/')) : null;
            if (!$rel || strpos($rel, '..') !== false) { return $m[0]; }
            $svg = @file_get_contents(trailingslashit($up['basedir']) . $rel);
            if (!$svg || stripos($svg, '<script') !== false || (stripos($svg, ' on') !== false && preg_match('/\son[a-z]+\s*=/i', $svg))) { return $m[0]; }
            $svg = preg_replace('/^.*?(<svg\b)/s', '$1', $svg);
            return preg_replace('/<svg\b/', '<svg aria-hidden="true" focusable="false"', $svg, 1);
        }, $html);
    }

    /** Heroens billeder: ingen lazy load; hovedmotivet (klassen <p>-lcp) får fetchpriority="high". */
    public static function hero_ivrig($html) {
        $p = Asite_Site::praefiks();
        if (!$p) { return $html; }
        return preg_replace_callback('/<img\b[^>]*\b(' . $p . '-scene-billede|' . $p . '-lcp|' . $p . '-ikke-lazy)\b[^>]*>/', function ($m) use ($p) {
            $tag = preg_replace('/\sloading="lazy"/', '', $m[0]);
            if ($m[1] === "$p-lcp" && strpos($tag, 'fetchpriority') === false) {
                $tag = str_replace('<img ', '<img fetchpriority="high" ', $tag);
            }
            return $tag;
        }, $html);
    }

    /** Tomme valgfrie felter: blanktegn væk, så CSS :empty skjuler dem. */
    public static function tomme_felter($html) {
        $k = apply_filters('asite_site/tomme_klasser', Asite_Site::liste((string) Asite_Site::i('tomme_klasser', '')));
        if (!$k) { return $html; }
        $alt = implode('|', array_map(fn($x) => preg_quote($x, '/'), $k));
        return preg_replace('/(<(p|a|div|span|pre|h2|h3|blockquote|figcaption|b)\b[^>]*\bclass="[^"]*\b(?:' . $alt . ')\b[^"]*"[^>]*>)\s+(<\/\2>)/', '$1$3', $html);
    }
}
