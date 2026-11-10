<?php
/**
 * Asite Site – omdirigeringer og robots.txt.
 *
 * Fra husmodellens hm-lancering.php.
 *
 * UDVIKLINGSADRESSEN (fx ny.fauth.dk) sender kun videre, når sitet er lanceret – altså når WordPress' egen
 * hjemmeadresse er skiftet til det rigtige domæne. Før lanceringen ER udviklingsadressen byggepladsen, og en
 * omdirigering dér ville gøre sitet umuligt at arbejde på. Derfor sammenlignes der med home_url() hver gang,
 * i stedet for at have et »lanceret«-flag, nogen kan glemme at sætte.
 *
 * GAMLE ADRESSER: et site, der afløser et ældre site, skal sende de gamle stier videre til de nye sider, så
 * links udefra og Googles indeks ikke går i stykker. De permanente omdirigeringer skal blive mindst et år.
 *
 * ROBOTS.TXT (ejeren 07-11-2026, B-257): AI-systemernes SØGE- og BRUGERUDLØSTE crawlere er velkomne – vi vil
 * findes og citeres. Rene TRÆNINGS-crawlere afvises. To bevidste undtagelser: Google-Extended styrer også
 * »grounding« i Gemini-appen, og bingbot bruges både til søgning og Copilot – at blokere dem fjerner sitet derfra.
 * robots.txt er en henstilling, ikke adgangskontrol.
 */
if (!defined('ABSPATH')) { exit; }

class Asite_Site_Lancering {

    /** Rene træningscrawlere: afvises, med mindre sitet har valgt at tillade træning. */
    const TRAENING = ['GPTBot', 'ClaudeBot', 'anthropic-ai', 'Applebot-Extended', 'Meta-ExternalAgent', 'CCBot',
        'Bytespider', 'Amazonbot', 'cohere-ai', 'Diffbot', 'ImagesiftBot', 'Omgilibot', 'omgili', 'PanguBot',
        'Timpibot', 'Webzio-Extended', 'FriendlyCrawler', 'Scrapy', 'AI2Bot', 'Kangaroo Bot'];

    /** Søgning og brugerudløste svar: altid velkomne. (Amazon: Amazonbot = træning, Amzn-SearchBot/-User = svar.) */
    const SOEGNING = ['Googlebot', 'Googlebot-Image', 'Google-Extended', 'bingbot', 'Applebot',
        'OAI-SearchBot', 'ChatGPT-User', 'OAI-AdsBot', 'Claude-SearchBot', 'Claude-User',
        'PerplexityBot', 'Perplexity-User', 'MistralAI-User', 'MistralAI-Index',
        'DuckDuckBot', 'DuckAssistBot', 'YouBot', 'Meta-ExternalFetcher', 'Amzn-SearchBot', 'Amzn-User'];

    public static function init() {
        add_action('init', [__CLASS__, 'fra_udviklingsadressen'], 0);
        add_action('template_redirect', [__CLASS__, 'gamle_adresser'], 1);
        if (Asite_Site::i('robots')) {
            add_filter('robots_txt', [__CLASS__, 'robots_txt'], 99, 2);
        }
    }

    /**
     * Hvor skal en forespørgsel på udviklingsadressen sendes hen? null = ingen omdirigering.
     * Beslutningen står for sig selv, så den kan prøvekøres uden en webserver.
     */
    public static function maal_for(string $vaert, string $home, string $uri): ?string {
        $dev = strtolower(trim((string) Asite_Site::i('dev_vaert', '')));
        if ($dev === '' || $vaert === '') { return null; }
        $vaert = strtolower(preg_replace('/:\d+$/', '', $vaert));
        $home = untrailingslashit($home);
        // Kun når sitet ER lanceret: så længe hjemmeadressen selv er udviklingsadressen, er den byggepladsen.
        if ($vaert !== $dev || strtolower((string) wp_parse_url($home, PHP_URL_HOST)) === $dev) { return null; }
        return $home . ($uri !== '' ? $uri : '/');
    }

    public static function fra_udviklingsadressen() {
        if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) { return; }   // wp-cli har intet værtsnavn at omdirigere
        $maal = self::maal_for((string) $_SERVER['HTTP_HOST'], (string) get_option('home'), (string) ($_SERVER['REQUEST_URI'] ?? '/'));
        if ($maal !== null) {
            wp_redirect($maal, 301, 'Asite Site');
            exit;
        }
    }

    public static function kort(): array {
        return Asite_Site::husk('gamle_adresser', function () {
            $raa = apply_filters('asite_site/gamle_adresser', Asite_Site::par((string) Asite_Site::i('gamle_adresser', '')));
            $k = [];
            foreach ($raa as $fra => $til) {
                $fra = strtolower(trailingslashit('/' . trim(parse_url($fra, PHP_URL_PATH) ?: $fra, '/')));
                if ($fra !== '/') { $k[$fra] = $til; }   // »/« ville omdirigere forsiden til sig selv i en løkke
            }
            return $k;
        });
    }

    public static function gamle_adresser() {
        $kort = self::kort();
        if (!$kort) { return; }
        $sti = strtolower(trailingslashit(wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
        if (isset($kort[$sti])) {
            $til = $kort[$sti];
            wp_redirect(preg_match('#^https?://#i', $til) ? $til : home_url($til), 301, 'Asite Site');
            exit;
        }
    }

    /** Kun når sitet er offentligt: et site under opbygning (»blog_public« = 0) skal ikke invitere nogen ind. */
    public static function robots_txt($tekst, $offentlig) {
        if (!$offentlig) { return $tekst; }
        $tillad_traening = (bool) apply_filters('asite_site/robots_traening', (bool) Asite_Site::i('robots_traening'));
        $ny = "\n# Asite Site: AI-søgning og -svar er velkomne" . ($tillad_traening ? ", også træning\n" : "; træning nej tak\n");
        foreach ($tillad_traening ? array_merge(self::SOEGNING, self::TRAENING) : self::SOEGNING as $a) {
            $ny .= "User-agent: $a\nAllow: /\nDisallow: /wp-admin/\n\n";
        }
        if (!$tillad_traening) {
            foreach (self::TRAENING as $a) { $ny .= "User-agent: $a\nDisallow: /\n\n"; }
        }
        // sitemap-linjerne skal stå til sidst
        $linjer = preg_split('/\R/', trim($tekst));
        $sitemaps = array_values(array_filter($linjer, fn($l) => stripos(trim($l), 'sitemap:') === 0));
        $resten = array_values(array_filter($linjer, fn($l) => stripos(trim($l), 'sitemap:') !== 0));
        if (!$sitemaps) { $sitemaps[] = 'Sitemap: ' . home_url('/sitemap_index.xml'); }
        return implode("\n", $resten) . "\n" . $ny . implode("\n", $sitemaps) . "\n";
    }
}
