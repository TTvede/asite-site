<?php
/**
 * Asite Site – XML-sitemap og IndexNow, på Rank Maths adresser.
 *
 * HVORFOR (PLAN-ASITE-SITE trin 3, 13-11-2026): Search Console og robots.txt kender /sitemap_index.xml. Skiftes
 * adressen, skal Google finde en ny sitemap – det er uro, vi ikke vil have. Derfor samme adresser og samme indhold
 * som Rank Math: /sitemap_index.xml → /<type>-sitemap.xml (200 pr. fil, /<type>-sitemap2.xml …) og local-sitemap.xml
 * (class-seo.php). WordPress' egen /wp-sitemap.xml (og /sitemap.xml) sender 301 til indekset, som hos Rank Math.
 * IndexNow (Bing, Yandex, Seznam …): samme nøgle som Rank Math, nøglefilen på /<nøgle>.txt og et ping, når en
 * indekserbar side gemmes. Bevist med `hm seo_facit skygge` (sitemappen tælles med).
 *
 * Kører kun, når Asite Site skriver SEO-tags (seo = asite, Rank Math fra).
 */
if (!defined('ABSPATH')) { exit; }

class Asite_Site_Sitemap {

    const PR_FIL = 200;

    public static function init() {
        if (!Asite_Site_Meta::aktiv()) { return; }
        add_filter('wp_sitemaps_enabled', '__return_false');
        add_action('parse_request', [__CLASS__, 'svar_fil'], 0);
        add_action('transition_post_status', [__CLASS__, 'indexnow'], 10, 3);
    }

    private static function rm(string $navn, $standard = '') {
        $o = get_option('rank-math-options-sitemap', []);
        return (is_array($o) && isset($o[$navn]) && $o[$navn] !== '') ? $o[$navn] : $standard;
    }

    /** Posttyper med sitemap (Rank Maths »pt_<type>_sitemap«; sider og indlæg som standard). */
    public static function typer(): array {
        $ud = [];
        foreach (get_post_types(['public' => true]) as $t) {
            if ($t === 'attachment') { continue; }
            if (self::rm("pt_{$t}_sitemap", in_array($t, ['page', 'post'], true) ? 'on' : 'off') === 'on') { $ud[] = $t; }
        }
        return $ud;
    }

    private static function dato(string $gmt): string {
        return gmdate('Y-m-d\TH:i:s+00:00', strtotime($gmt . ' UTC'));
    }

    /** En types indekserbare sider: [[adresse, lastmod], …] – forsiden først, så nyest ændrede (som Rank Math). */
    public static function adresser(string $type): array {
        $ider = get_posts(['post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids',
                           'orderby' => 'modified', 'order' => 'DESC', 'has_password' => false, 'suppress_filters' => true]);
        $forside = (int) get_option('page_on_front');
        usort($ider, fn($a, $b) => ((int) $b === $forside) <=> ((int) $a === $forside));
        $ud = [];
        foreach ($ider as $id) {
            $id = (int) $id;
            if (Asite_Site_Meta::noindex($id)) { continue; }
            $url = (string) get_permalink($id);
            if (untrailingslashit(Asite_Site_Meta::canonical($id)) !== untrailingslashit($url)) { continue; }   // kopi af en anden side
            $ud[] = [$id === $forside ? home_url('/') : $url, self::dato((string) get_post_field('post_modified_gmt', $id))];
        }
        return $ud;
    }

    private static function hoved(): string {
        return '<?xml version="1.0" encoding="UTF-8"?><?xml-stylesheet type="text/xsl" href="//'
            . wp_parse_url(home_url(), PHP_URL_HOST) . '/main-sitemap.xsl"?>' . "\n";
    }

    public static function indeks(): string {
        $x = self::hoved() . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (self::typer() as $t) {
            $a = self::adresser($t);
            foreach (array_chunk($a, self::PR_FIL) as $i => $del) {
                $nyest = max(array_column($del, 1));
                $x .= "\t<sitemap>\n\t\t<loc>" . esc_url(home_url('/' . $t . '-sitemap' . ($i ? $i + 1 : '') . '.xml'))
                    . "</loc>\n\t\t<lastmod>" . $nyest . "</lastmod>\n\t</sitemap>\n";
            }
        }
        if (method_exists('Asite_Site_Seo', 'kml_index')) { $x = Asite_Site_Seo::kml_index($x); }
        return $x . "</sitemapindex>\n";
    }

    public static function fil(string $type, int $nr): ?string {
        if (!in_array($type, self::typer(), true)) { return null; }
        $del = array_chunk(self::adresser($type), self::PR_FIL)[$nr - 1] ?? null;
        if ($del === null) { return null; }
        $x = self::hoved() . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($del as [$url, $mod]) {
            $x .= "\t<url>\n\t\t<loc>" . esc_url($url) . "</loc>\n\t\t<lastmod>" . $mod . "</lastmod>\n\t</url>\n";
        }
        return $x . "</urlset>\n";
    }

    /** IndexNow-nøglen: Rank Maths (så nøglefilen ikke skifter), ellers en, der laves én gang. */
    public static function noegle(): string {
        $rm = get_option('rank-math-options-instant-indexing', []);
        $k = is_array($rm) ? (string) ($rm['indexnow_api_key'] ?? '') : '';
        if (!preg_match('/^[a-f0-9]{32}$/', $k)) {
            $k = (string) get_option('asite_site_indexnow', '');
            if (!preg_match('/^[a-f0-9]{32}$/', $k)) { $k = md5(wp_generate_password(32, true, true)); update_option('asite_site_indexnow', $k, false); }
        }
        return $k;
    }

    /** Svaret for en sti: [status, content-type, krop, location] – eller null (ikke vores). Uden sideeffekter. */
    public static function svar(string $sti): ?array {
        $sti = strtolower('/' . ltrim($sti, '/'));
        if (in_array($sti, ['/wp-sitemap.xml', '/sitemap.xml'], true) || preg_match('#^/wp-sitemap-[a-z0-9-]+\.xml$#', $sti)) {
            return [301, '', '', home_url('/sitemap_index.xml')];
        }
        if ($sti === '/sitemap_index.xml') { return [200, 'text/xml; charset=UTF-8', self::indeks(), '']; }
        if ($sti === '/main-sitemap.xsl') { return [200, 'text/xml; charset=UTF-8', self::xsl(), '']; }
        if ($sti === '/' . self::noegle() . '.txt') { return [200, 'text/plain; charset=UTF-8', self::noegle(), '']; }
        if (preg_match('#^/([a-z0-9_-]+)-sitemap(\d*)\.xml$#', $sti, $m) && $m[1] !== 'local') {
            $x = self::fil($m[1], $m[2] === '' ? 1 : (int) $m[2]);
            return $x === null ? null : [200, 'text/xml; charset=UTF-8', $x, ''];
        }
        return null;
    }

    public static function svar_fil() {
        $s = self::svar((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        if ($s === null) { return; }
        [$status, $type, $krop, $til] = $s;
        if ($til !== '') { wp_redirect($til, 301, 'Asite Site'); exit; }
        status_header($status);
        nocache_headers();
        header('Content-Type: ' . $type);
        header('X-Robots-Tag: noindex', true);
        echo $krop;
        exit;
    }

    /** Ping IndexNow, når en indekserbar side udgives eller opdateres (kun på det offentlige domæne). */
    public static function indexnow($ny, $gammel, $post) {
        if ($ny !== 'publish' || wp_is_post_revision($post) || wp_is_post_autosave($post)) { return; }
        if (!in_array($post->post_type, self::typer(), true) || Asite_Site_Meta::noindex((int) $post->ID)) { return; }
        if (!get_option('blog_public') || (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE !== 'production')) { return; }
        $vaert = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $k = self::noegle();
        wp_remote_post('https://api.indexnow.org/indexnow', [
            'blocking' => false, 'timeout' => 3, 'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => wp_json_encode(['host' => $vaert, 'key' => $k, 'keyLocation' => home_url('/' . $k . '.txt'),
                                      'urlList' => [(string) get_permalink($post)]]),
        ]);
    }

    /** Stylesheetet, så en sitemap kan læses i en browser (søgemaskinerne bruger det ikke). */
    public static function xsl(): string {
        return '<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9">
<xsl:output method="html" encoding="UTF-8" indent="yes"/>
<xsl:template match="/">
<html><head><title>XML-sitemap</title><meta name="robots" content="noindex,follow"/>
<style>body{font:14px/1.5 system-ui,sans-serif;color:#1d2327;margin:32px}table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:6px 10px;border-bottom:1px solid #dcdcde}a{color:#2271b1}</style></head>
<body><h1>XML-sitemap</h1><p>Til søgemaskiner. <xsl:value-of select="count(s:sitemapindex/s:sitemap) + count(s:urlset/s:url)"/> adresser.</p>
<table><tr><th>Adresse</th><th>Sidst ændret</th></tr>
<xsl:for-each select="s:sitemapindex/s:sitemap | s:urlset/s:url"><tr><td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td><td><xsl:value-of select="s:lastmod"/></td></tr></xsl:for-each>
</table></body></html>
</xsl:template>
</xsl:stylesheet>
';
    }
}
