<?php
/**
 * Asite Site – sporing, samtykke og leads. Slået FRA, indtil der står et Google-tag, et Ads-id eller en Meta-pixel
 * i indstillingerne.
 *
 * Fra husmodellens hm-sporing.php (B-258). HVORFOR: terrasse.nu havde 7 mu-plugins + Site Kit til det her, Google
 * hentet to gange (GA4-tag 194 kB + separat Ads-container 166 kB) og samtykke-default sat to gange. Målt: 250 ms
 * blokering på mobil mod ca. 50 ms uden. Nu: ét modul, samtykke først, ÉT tagbibliotek, hentet lige efter sidens
 * load – samtykke og hændelser står i kø fra første linje, så intet tabes undervejs. Ingen GTM, ingen Site Kit.
 * Samtykkebanneret er Complianz.
 *
 * Sporingen kører KUN på sitets eget domæne (det, WordPress' hjemmeadresse peger på, med og uden www) – aldrig på
 * en udviklingsadresse, så tallene ikke forurenes. Til test kan et værtsnavn skrives i indstillingerne.
 *
 * Egne hændelser (fx en prisberegner):
 *   document.dispatchEvent(new CustomEvent('asite:lead', {detail: 'prisberegner'}))
 * med en Ads-label under »Konverteringer« og evt. et GA4-navn under »GA4-navne«.
 */
if (!defined('ABSPATH')) { exit; }

class Asite_Site_Sporing {

    /** Cookies og meta-nøgler: vi SKRIVER as_, men LÆSER også de gamle navne, så et site, der flyttes hertil,
     *  ikke mister klik-id på besøg, der er i gang, eller på leads, der allerede er gemt (90 dages cookie). */
    const COOKIE = 'as_';

    public static function cfg(): array {
        return Asite_Site::husk('sporing', [__CLASS__, 'laes_cfg']);
    }

    public static function laes_cfg(): array {
        $s = (array) Asite_Site::i('sporing', []);
        $id = function ($navn, $re) use ($s) {
            $v = trim((string) ($s[$navn] ?? ''));
            return preg_match($re, $v) ? $v : '';
        };
        return [
            'tag'   => $id('tag', '/^(GT|G|AW)-[A-Z0-9]{4,15}$/') ?: $id('ga4', '/^G-[A-Z0-9]{4,15}$/'),
            'ga4'   => $id('ga4', '/^G-[A-Z0-9]{4,15}$/'),
            'ads'   => $id('ads', '/^AW-[0-9]{6,15}$/'),
            'meta'  => $id('meta', '/^[0-9]{6,20}$/'),
            'ads_i_tag' => !empty($s['ads_i_tag']),
            'indlaes' => ($s['indlaes'] ?? 'load') === 'straks' ? 'straks' : 'load',
            'maks_ventetid' => max(0, (int) ($s['maks_ventetid'] ?? 3000)),
            'konverteringer' => Asite_Site::par((string) ($s['konverteringer'] ?? '')),
            'ga4_navne' => Asite_Site::par((string) ($s['ga4_navne'] ?? '')),
            'test_domaener' => array_map('strtolower', Asite_Site::liste((string) ($s['test_domaener'] ?? ''))),
            'vaert' => strtolower(trim((string) ($s['vaert'] ?? ''))),
        ];
    }

    /** Er der noget at spore? (Bruges også af lead-delene, som gælder uanset hvilken side man er på.) */
    public static function konfigureret(): bool {
        $c = self::cfg();
        return (bool) ($c['tag'] || $c['ads'] || $c['meta']);
    }

    /** Sporing er konfigureret OG vi er på sitets eget domæne på en rigtig side. */
    public static function aktiv(): bool {
        if (!self::konfigureret()) { return false; }
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || is_preview()) { return false; }
        if (isset($_GET['breakdance']) || isset($_GET['breakdance_iframe'])) { return false; }
        $c = self::cfg();
        $vaert = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
        $eget = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        // Aldrig på udviklingsadressen – heller ikke før lanceringen, hvor den ER hjemmeadressen. Ellers sender
        // testbesøg og testformularer rigtige målinger til live's konti (ny.terrasse.nu 11-11-2026).
        $dev = strtolower(trim((string) Asite_Site::i('dev_vaert', '')));
        if ($dev !== '' && $vaert === $dev) { return false; }
        $tilladt = $c['vaert'] !== '' ? [$c['vaert']] : [$eget, 'www.' . preg_replace('/^www\./', '', $eget)];
        return in_array($vaert, $tilladt, true);
    }

    public static function init() {
        add_action('wp_head', [__CLASS__, 'samtykke'], -1000);
        add_action('wp_head', [__CLASS__, 'klikid'], -999);
        add_action('wp_head', [__CLASS__, 'google_tag'], -998);
        add_action('wp_footer', [__CLASS__, 'haendelser'], 100);
        add_action('wp_insert_post', [__CLASS__, 'stempl_lead'], 10, 3);
        add_filter('manage_breakdance_form_res_posts_columns', [__CLASS__, 'kolonne']);
        add_action('manage_breakdance_form_res_posts_custom_column', [__CLASS__, 'kolonne_indhold'], 10, 2);
        add_filter('abseen_leads', [__CLASS__, 'abseen_leads'], 10, 2);
    }

    /** 1) Samtykke FØRST – før alt andet i <head>: alt afvist, indtil banneret siger andet (Consent Mode v2). */
    public static function samtykke() {
        if (!self::aktiv()) { return; }
        echo "<script id=\"asite-samtykke\">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}"
           . "gtag('consent','default',{ad_storage:'denied',analytics_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',wait_for_update:500});</script>\n";
    }

    /** 2) Klik-id og UTM fanges i browseren: sidecachen leverer siden uden at starte WordPress, så PHP ser ikke landingen. */
    public static function klikid() {
        if (!self::aktiv()) { return; }
        $p = wp_json_encode(self::COOKIE);
        ?>
<script id="asite-klikid">(function(){try{var P=<?php echo $p; ?>,q=new URLSearchParams(location.search),A=90*86400,ok=function(v){return!!v&&/^[A-Za-z0-9_-]{10,255}$/.test(v)};
function s(n,v){if(v)document.cookie=P+n+"="+encodeURIComponent(v)+";max-age="+A+";path=/;SameSite=Lax"}
var t=ok(q.get("gclid"))?"gclid":ok(q.get("gbraid"))?"gbraid":ok(q.get("wbraid"))?"wbraid":"";if(t){s("klikid",q.get(t));s("kliktype",t)}
if(t||q.get("utm_source")){["utm_source","utm_campaign","utm_term","utm_content"].forEach(function(u){s(u,(q.get(u)||"").slice(0,200))})}}catch(e){}})();</script>
        <?php
    }

    /** 3) Google-tagget: konfigurationen straks i kø, selve biblioteket hentes efter load (eller straks). */
    public static function google_tag() {
        if (!self::aktiv()) { return; }
        $c = self::cfg();
        $tag = $c['tag'] ?: $c['ads'];
        if (!$tag) { return; }
        $src = 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($tag);
        $cfg = "gtag('js',new Date());gtag('config'," . wp_json_encode($tag) . ");";
        if ($c['ads'] && $c['ads'] !== $tag && !$c['ads_i_tag']) {
            $cfg .= "gtag('config'," . wp_json_encode($c['ads']) . ");";
        }
        if ($c['indlaes'] === 'straks') {
            echo '<script async src="' . esc_url($src) . "\"></script>\n<script id=\"asite-google-tag\">{$cfg}</script>\n";
            return;
        }
        // B-258: lige efter load (og når browseren har tid) – aldrig først ved klik/scroll. Loft: senest N ms efter at
        // HTML'en er læst, for på langsom mobil kom load først efter 8 s (målt 07-11-2026), og besøg, der lukker
        // siden før det, ville ellers slet ikke blive målt.
        $maks = $c['maks_ventetid'];
        echo "<script id=\"asite-google-tag\">{$cfg}(function(){var f=false;function h(){if(f)return;f=true;var s=document.createElement('script');s.async=true;s.src="
           . wp_json_encode($src) . ";document.head.appendChild(s)}function k(){window.requestIdleCallback?requestIdleCallback(h,{timeout:1500}):setTimeout(h,1)}"
           . "if(document.readyState==='complete'){k()}else{window.addEventListener('load',k);var t=function(){setTimeout(h,{$maks})};"
           . "document.readyState==='loading'?document.addEventListener('DOMContentLoaded',t):t()}})();</script>\n";
    }

    /** 4) Konverteringer og hændelser (formular, telefon, mail, egne) + Meta-pixel efter marketing-samtykke. */
    public static function haendelser() {
        if (!self::aktiv()) { return; }
        $c = self::cfg();
        $js = [
            'ads'   => $c['ads'],
            'konv'  => array_filter($c['konverteringer'], fn($v) => is_string($v) && preg_match('/^[A-Za-z0-9_\/-]{6,60}$/', $v)),
            'ga4'   => $c['ga4'] ?: (strpos((string) $c['tag'], 'G-') === 0 ? $c['tag'] : ''),
            'navne' => array_merge(['kontakt' => 'kontaktformular', 'telefon' => 'klik_tel', 'mail' => 'klik_mail'], $c['ga4_navne']),
        ];
        ?>
<script id="asite-haendelser">(function(){var C=<?php echo wp_json_encode($js); ?>,sendt={},kunde=null;
function samtykke(k){try{return typeof cmplz_has_consent==='function'&&!!cmplz_has_consent(k)}catch(e){return false}}
function tlf(v){var s=String(v||'').replace(/[^\d+]/g,'');if(s.indexOf('00')===0)s='+'+s.slice(2);if(/^\d{8}$/.test(s))s='+45'+s;return/^\+\d{8,15}$/.test(s)?s:''}
/* Udvidede konverteringer: mail/telefon fra formularen gives til tagget (det hasher selv) – kun med marketing-samtykke. */
document.addEventListener('submit',function(e){var f=e.target;if(!f||!f.querySelector)return;var m=f.querySelector('input[type="email"]'),t=f.querySelector('input[type="tel"]'),d={};
var em=m?String(m.value||'').trim().toLowerCase():'';if(/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em))d.email=em;var p=t?tlf(t.value):'';if(p)d.phone_number=p;kunde=(d.email||d.phone_number)?d:null},true);
function lead(k){if(sendt[k])return;sendt[k]=true;
 if(C.ads&&C.konv[k]){if(kunde&&samtykke('marketing')&&(k!=='telefon'&&k!=='mail'))gtag('set','user_data',kunde);gtag('event','conversion',{send_to:C.ads+'/'+C.konv[k]})}
 if(C.navne[k]&&samtykke('statistics'))gtag('event',C.navne[k],C.ga4?{send_to:C.ga4}:{});
 try{document.dispatchEvent(new CustomEvent('asite:lead-sendt',{detail:k}))}catch(e){}}
/* »asite:lead« er navnet nu; »hm:lead« lyttes der stadig efter, så et site med egen kode fra husmodellen virker. */
document.addEventListener('asite:lead',function(e){if(e&&e.detail)lead(String(e.detail))});
document.addEventListener('hm:lead',function(e){if(e&&e.detail)lead(String(e.detail))});
/* Breakdance-formular: kun ved bekræftet succes (beskeden indsættes lige efter formularen). */
try{new MutationObserver(function(ms){ms.forEach(function(m){m.addedNodes.forEach(function(n){if(n.nodeType===1&&n.classList.contains('breakdance-form-message--success')&&n.previousElementSibling&&n.previousElementSibling.classList.contains('breakdance-form'))lead('kontakt')})})}).observe(document.body,{childList:true,subtree:true})}catch(e){}
document.addEventListener('click',function(e){var a=e.target&&e.target.closest?e.target.closest('a[href^="tel:"],a[href^="mailto:"]'):null;if(!a)return;lead((a.getAttribute('href')||'').indexOf('tel:')===0?'telefon':'mail')},true);
})();</script>
        <?php
        if (!$c['meta']) { return; }
        // Meta-pixel i sit eget script: hentes først efter marketing-samtykke (også hvis det gives midt på siden).
        ?>
<script id="asite-meta">(function(){var P=<?php echo wp_json_encode($c['meta']); ?>,ind=false;
function ok(){try{return typeof cmplz_has_consent==='function'&&!!cmplz_has_consent('marketing')}catch(e){return false}}
function hent(){if(ind||!ok())return;ind=true;!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init',P);fbq('track','PageView')}
hent();document.addEventListener('cmplz_fire_categories',hent);document.addEventListener('cmplz_status_change',hent);
document.addEventListener('asite:lead-sendt',function(e){hent();if(ind&&typeof fbq==='function')fbq('track',(e.detail==='telefon'||e.detail==='mail')?'Contact':'Lead',{content_name:e.detail})});
})();</script>
        <?php
    }

    /** Cookien: vores eget navn først, derefter husmodellens og sitets gamle præfiks (intet klik-id tabes ved flytning). */
    private static function cookie(string $navn): string {
        $p = Asite_Site::praefiks();
        foreach (array_filter([self::COOKIE, 'hm_', $p ? $p . '_' : '']) as $praefiks) {
            if (isset($_COOKIE[$praefiks . $navn]) && $_COOKIE[$praefiks . $navn] !== '') {
                return sanitize_text_field(wp_unslash((string) $_COOKIE[$praefiks . $navn]));
            }
        }
        return '';
    }

    /** Meta på et lead: samme rækkefølge – nye leads får _as_, gamle læses, som de står. */
    private static function lead_meta(int $id, string $navn): string {
        $p = Asite_Site::praefiks();
        foreach (array_filter(['_as_', '_hm_', $p ? '_' . $p . '_' : '']) as $praefiks) {
            $v = (string) get_post_meta($id, $praefiks . $navn, true);
            if ($v !== '') { return $v; }
        }
        return '';
    }

    /** 5) Hvert nyt lead får klik-id, UTM og svaret på cookiebanneret med – fra cookien. */
    public static function stempl_lead($id, $post, $update) {
        if ($update || !$post || $post->post_type !== 'breakdance_form_res' || !self::konfigureret()) { return; }
        foreach (['klikid', 'kliktype', 'utm_source', 'utm_campaign', 'utm_term', 'utm_content'] as $k) {
            $v = self::cookie($k);
            if ($k === 'klikid' && !preg_match('/^[A-Za-z0-9_-]{10,255}$/', $v)) { $v = ''; }
            if ($v !== '' && !get_post_meta($id, "_as_$k", true)) { add_post_meta($id, "_as_$k", $v, true); }
        }
        $mk = (string) ($_COOKIE['cmplz_marketing'] ?? '');
        if ($mk === 'allow' || $mk === 'deny') {
            add_post_meta($id, '_as_samtykke_marketing', $mk === 'allow' ? 'granted' : 'denied', true);
        }
    }

    /** 6) Kilden som kolonne under Breakdance → Form Submissions. */
    public static function kolonne($cols) {
        if (self::konfigureret()) { $cols['asite_kilde'] = 'Kilde'; }
        return $cols;
    }

    public static function kolonne_indhold($col, $id) {
        if ($col !== 'asite_kilde') { return; }
        $kamp = self::lead_meta((int) $id, 'utm_campaign');
        $ord = self::lead_meta((int) $id, 'utm_term');
        if ($kamp || $ord) {
            echo esc_html(trim(($kamp ? "Kampagne $kamp" : '') . ($ord ? " – »{$ord}«" : '')));
        } elseif (self::lead_meta((int) $id, 'klikid')) {
            echo 'Google Ads (klik-id)';
        } else {
            echo '<span style="color:#999">–</span>';
        }
    }

    /**
     * 7) A-B-Seen henter henvendelserne (offline-konverteringer i Google Ads). Kun klik-id, samtykke og en
     * SHA-256 af mailen forlader sitet – aldrig navn, mail eller telefon.
     */
    public static function abseen_leads($leads, $efter) {
        global $wpdb;
        if (!self::konfigureret()) { return $leads; }
        $test = self::cfg()['test_domaener'];
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'breakdance_form_res' AND ID > %d
            AND post_date_gmt < UTC_TIMESTAMP() - INTERVAL 5 MINUTE ORDER BY ID ASC LIMIT 200", (int) $efter));
        foreach ($ids as $id) {
            $id = (int) $id;
            $felter = (array) get_post_meta($id, '_breakdance_fields', true);
            $mail = strtolower(trim((string) ($felter['email'] ?? '')));
            $klik = self::lead_meta($id, 'klikid');
            $type = self::lead_meta($id, 'kliktype');
            $lead = [
                'id' => (string) $id, 'kind' => 'kontakt', 'occurred_at' => (string) get_post_time('c', true, $id),
                'click_id' => $klik ?: null,
                'click_type' => in_array($type, ['gclid', 'gbraid', 'wbraid'], true) ? $type : ($klik ? 'gclid' : null),
                'consent' => self::lead_meta($id, 'samtykke_marketing') ?: null,
                'qualified' => !($mail && in_array(substr(strrchr($mail, '@') ?: '', 1), $test, true)),
                'email_hash' => is_email($mail) ? hash('sha256', $mail) : null,
            ];
            $leads[] = apply_filters('asite_site/lead', $lead, $id, $felter);
        }
        return $leads;
    }
}
