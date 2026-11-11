<?php
/**
 * Asite Site – indstillingssiden: Værktøjer → Asite Site.
 *
 * HVORFOR: alt, der før stod som PHP-konstanter i <præfiks>-site.php eller som filtre i sitets egen kode, skal kunne
 * rettes af den, der ejer sitet – uden en udvikler, en terminal og vores server. Derfor én side med klartekst om,
 * hvad hver ting gør, og hvad der sker, hvis man slår den fra.
 */
if (!defined('ABSPATH')) { exit; }

class Asite_Site_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_asite_site', [__CLASS__, 'gem']);
        add_action('admin_notices', [__CLASS__, 'noter']);
    }

    public static function cap() { return current_user_can('manage_options'); }

    public static function menu() {
        add_management_page('Asite Site', 'Asite Site', 'manage_options', 'asite-site', [__CLASS__, 'side']);
    }

    public static function noter() {
        $k = 'asite_site_msg_' . get_current_user_id();
        if ($m = get_transient($k)) {
            delete_transient($k);
            echo '<div class="notice notice-success is-dismissible"><p><strong>Asite Site:</strong> ' . esc_html($m) . '</p></div>';
        }
        if (self::cap() && Asite_Site::i_overgang()) {
            echo '<div class="notice notice-warning"><p><strong>Asite Site er i dvale:</strong> husmodellens mu-plugins (<code>hm-*.php</code>) '
               . 'ligger stadig på sitet og gør det samme. Udfyld indstillingerne her, fjern derefter mu-plugins – så tager pluginet over. '
               . '<a href="' . esc_url(admin_url('tools.php?page=asite-site')) . '">Indstillinger</a></p></div>';
        }
    }

    public static function gem() {
        if (!self::cap()) { wp_die('Ingen adgang.'); }
        check_admin_referer('asite_site_gem');
        $p = wp_unslash($_POST);
        $tekst = fn($n) => sanitize_textarea_field((string) ($p[$n] ?? ''));
        $linje = fn($n) => sanitize_text_field((string) ($p[$n] ?? ''));
        $ny = [
            'praefiks'          => strtolower(preg_replace('/[^a-z0-9]/i', '', $linje('praefiks'))),
            'billeder'          => !empty($p['billeder']),
            'sizes_loft'        => max(0, (int) ($p['sizes_loft'] ?? 1200)),
            'sizes_regler'      => $tekst('sizes_regler'),
            'avif_kvalitet'     => min(100, max(0, (int) ($p['avif_kvalitet'] ?? 50))),
            'billedliste_stoerrelse' => sanitize_key($linje('billedliste_stoerrelse')) ?: 'large',
            'tomme_klasser'     => $tekst('tomme_klasser'),
            'hero_ivrig'        => !empty($p['hero_ivrig']),
            'inline_svg'        => !empty($p['inline_svg']),
            'scenelag'          => !empty($p['scenelag']),
            'global_styles_fra' => !empty($p['global_styles_fra']),
            'google_fonts_fra'  => !empty($p['google_fonts_fra']),
            'skrifter_forhaand' => $tekst('skrifter_forhaand'),
            'faq_schema'        => !empty($p['faq_schema']),
            'altnavn'           => $linje('altnavn'),
            'forfatter_navn'    => $linje('forfatter_navn'),
            'forfatter_titel'   => $linje('forfatter_titel'),
            'forfatter_url'     => esc_url_raw(trim($linje('forfatter_url'))),
            'forfatter_forael'  => trim($linje('forfatter_forael'), '/'),
            'artikler'          => $tekst('artikler'),
            'dev_vaert'         => strtolower(preg_replace('#^https?://#', '', trim($linje('dev_vaert'), '/ '))),
            'gamle_adresser'    => $tekst('gamle_adresser'),
            'robots'            => !empty($p['robots']),
            'robots_traening'   => !empty($p['robots_traening']),
            'redaktoerlaas'     => !empty($p['redaktoerlaas']),
            'svg_upload'        => !empty($p['svg_upload']),
            'virksomhed'        => [
                'type' => preg_replace('/[^A-Za-z]/', '', $linje('vi_type')),
                'telefon' => $linje('vi_telefon'), 'email' => sanitize_email($linje('vi_email')),
                'gade' => $linje('vi_gade'), 'postnr' => $linje('vi_postnr'), 'by' => $linje('vi_by'),
                'region' => $linje('vi_region'), 'land' => strtoupper($linje('vi_land')) ?: 'DK',
                'cvr' => preg_replace('/\D/', '', $linje('vi_cvr')), 'beskrivelse' => $tekst('vi_beskrivelse'),
                'prisniveau' => $linje('vi_prisniveau'), 'geo' => $linje('vi_geo'),
                'aabningstider' => $tekst('vi_aabningstider'), 'omraader' => $tekst('vi_omraader'),
                'profiler' => $tekst('vi_profiler'), 'anmeldelser' => $tekst('vi_anmeldelser'),
            ],
            'sporing'           => [
                'tag' => strtoupper($linje('sp_tag')), 'ga4' => strtoupper($linje('sp_ga4')),
                'ads' => strtoupper($linje('sp_ads')), 'meta' => preg_replace('/\D/', '', $linje('sp_meta')),
                'ads_i_tag' => !empty($p['sp_ads_i_tag']),
                'indlaes' => ($p['sp_indlaes'] ?? 'load') === 'straks' ? 'straks' : 'load',
                'maks_ventetid' => max(0, (int) ($p['sp_maks_ventetid'] ?? 3000)),
                'konverteringer' => $tekst('sp_konverteringer'),
                'ga4_navne' => $tekst('sp_ga4_navne'),
                'test_domaener' => $tekst('sp_test_domaener'),
                'vaert' => strtolower($linje('sp_vaert')),
            ],
        ];
        update_option(Asite_Site::OPT, array_merge(Asite_Site::indstillinger(), $ny));
        Asite_Site::nulstil_cache();   // ellers viser status og robots.txt de gamle værdier resten af forespørgslen
        // Indstillingerne ændrer sidernes HTML (billedattributter, schema, robots) – sidecachen skal tømmes.
        if (class_exists('Asite_Cache')) { Asite_Cache::purge_all(); }
        set_transient('asite_site_msg_' . get_current_user_id(), 'Indstillingerne er gemt.'
            . (class_exists('Asite_Cache') ? ' Sidecachen er tømt.' : ''), 60);
        wp_safe_redirect(admin_url('tools.php?page=asite-site'));
        exit;
    }

    /* ---------- tegning ---------- */

    private static function tjek($navn, $vaerdi, $label, $hjaelp = '') {
        echo '<label><input type="checkbox" name="' . esc_attr($navn) . '" value="1" ' . checked((bool) $vaerdi, true, false) . '> ' . esc_html($label) . '</label>';
        if ($hjaelp) { echo '<p class="description">' . wp_kses_post($hjaelp) . '</p>'; }
    }

    private static function felt($navn, $vaerdi, $bredde = 30, $pladsholder = '') {
        echo '<input type="text" name="' . esc_attr($navn) . '" value="' . esc_attr((string) $vaerdi) . '" size="' . (int) $bredde
           . '" placeholder="' . esc_attr($pladsholder) . '" class="regular-text">';
    }

    private static function omraade($navn, $vaerdi, $raekker = 5, $pladsholder = '') {
        echo '<textarea name="' . esc_attr($navn) . '" rows="' . (int) $raekker . '" cols="50" class="code large-text" placeholder="'
           . esc_attr($pladsholder) . '">' . esc_textarea((string) $vaerdi) . '</textarea>';
    }

    private static function status(): array {
        $bd = defined('__BREAKDANCE_VERSION') || class_exists('\Breakdance\Plugin');
        $ud = [
            ['Pluginets tilstand', Asite_Site::i_overgang()
                ? '<span style="color:#b32d2e">I dvale (hm-*.php er på sitet)</span>'
                : '<span style="color:#008a20">Aktiv</span>'],
            ['Klassepræfiks', Asite_Site::praefiks() ? '<code>' . esc_html(Asite_Site::praefiks()) . '</code>' : '<span style="color:#b32d2e">mangler</span>'],
            ['Breakdance', $bd ? 'til' : '<span style="color:#b32d2e">ikke fundet</span>'],
            ['Rank Math', class_exists('RankMath\\Helper') || defined('RANK_MATH_VERSION') ? 'til' : 'ikke fundet'],
            ['Asite Cache', class_exists('Asite_Cache') ? 'til' : 'ikke fundet'],
            ['Sporing', Asite_Site_Sporing::konfigureret() ? 'konfigureret' : 'slået fra'],
        ];
        return $ud;
    }

    public static function side() {
        if (!self::cap()) { return; }
        $s = Asite_Site::indstillinger();
        $sp = $s['sporing'];
        $vi = $s['virksomhed'];
        ?>
        <div class="wrap">
            <h1>Asite Site</h1>
            <p>Det, Breakdance ikke kan selv – på et site, der kan overdrages. Alt herunder virker uden vores server og uden kode i sitets filer.</p>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0">
                <?php foreach (self::status() as $r): ?>
                    <div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 16px;min-width:150px">
                        <div style="font-size:12px;color:#646970;text-transform:uppercase;letter-spacing:.04em"><?php echo esc_html($r[0]); ?></div>
                        <div style="font-size:15px;font-weight:600;margin-top:4px"><?php echo $r[1]; // allerede escapet ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:860px">
                <input type="hidden" name="action" value="asite_site">
                <?php wp_nonce_field('asite_site_gem'); ?>

                <h2 class="title">Sitet</h2>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Klassepræfiks</th><td>
                        <?php self::felt('praefiks', $s['praefiks'], 6, 'fa'); ?>
                        <p class="description">2–4 små bogstaver. Sitets CSS-klasser starter med det (<code>fa-hero</code>), og pluginet
                        bruger det til at kende heroens billeder (<code>&lt;præfiks&gt;-lcp</code>), SVG-ikoner (<code>-svg</code>) og scenelag (<code>-lag</code>).
                        Uden præfiks springes de dele over.</p></td></tr>
                    <tr><th scope="row">Udviklingsadresse</th><td>
                        <?php self::felt('dev_vaert', $s['dev_vaert'], 30, 'ny.eksempel.dk'); ?>
                        <p class="description">Byggeadressen fra før lanceringen. Den sender videre til det rigtige domæne med 301 – men
                        <strong>først når sitet er lanceret</strong> (WordPress' hjemmeadresse peger på domænet). Før det er den byggepladsen og røres ikke.</p></td></tr>
                </table>

                <h2 class="title">Billeder</h2>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Billedattributter</th><td>
                        <?php self::tjek('billeder', $s['billeder'], 'Giv alle billeder srcset, width/height og alt-tekst',
                            'Breakdance skriver billeder uden mål (layoutskift) og udelader <code>alt</code> helt, når mediebibliotekets alt-tekst er tom. Kilden røres ikke – kun det manglende lægges på.'); ?></td></tr>
                    <tr><th scope="row">Loft for sizes</th><td>
                        <input type="number" name="sizes_loft" min="0" max="4000" value="<?php echo (int) $s['sizes_loft']; ?>" style="width:90px"> px
                        <p class="description">Billeder bredere end dette får <code>(max-width: Npx) 100vw, Npx</code> i stedet for originalbredden,
                        så browseren ikke henter en variant, der er langt bredere end den plads, billedet har. 0 = fra.</p></td></tr>
                    <tr><th scope="row">Ærlig sizes pr. komponent</th><td>
                        <?php self::omraade('sizes_regler', $s['sizes_regler'], 6, "fa-hero-bg = 100vw\nfa-kort-img = (max-width:767px) 120px, 150px"); ?>
                        <p class="description">Én pr. linje: <code>&lt;klasse&gt; = &lt;sizes&gt;</code>. Her står, hvor bredt komponenten FAKTISK viser billedet
                        – det er det eneste sted, sandheden findes, og det sparer mest på mobil.</p></td></tr>
                    <tr><th scope="row">AVIF-kvalitet</th><td>
                        <input type="number" name="avif_kvalitet" min="0" max="100" value="<?php echo (int) $s['avif_kvalitet']; ?>" style="width:90px">
                        <p class="description">Understørrelser laves i samme kvalitet som originalerne (50). Med WordPress' standard (82) bliver
                        understørrelserne større end originalen. 0 = rør ikke kvaliteten.</p></td></tr>
                    <tr><th scope="row">Billedlister</th><td>
                        <?php self::felt('billedliste_stoerrelse', $s['billedliste_stoerrelse'], 12, 'large'); ?>
                        <p class="description">Størrelsen fra mediebiblioteket, som billedlister skrives om til (fx billederne i en lysboks,
                        som siden ikke viser, før man klikker). <code>large</code> er 1024 px – originalen kan være flere gange så stor.</p></td></tr>
                </table>

                <h2 class="title">Sidens finish</h2>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Hero uden lazy load</th><td>
                        <?php self::tjek('hero_ivrig', $s['hero_ivrig'], 'Billeder med <præfiks>-lcp, -scene-billede eller -ikke-lazy hentes med det samme',
                            'Breakdance lazy-loader alle billeder – også det første, man ser. <code>-lcp</code> får desuden <code>fetchpriority="high"</code>.'); ?></td></tr>
                    <tr><th scope="row">Inline-SVG</th><td>
                        <?php self::tjek('inline_svg', $s['inline_svg'], '<img class="<præfiks>-svg"> byttes til SVG-koden selv',
                            'Så kan ikonet farves og animeres med CSS. Kun filer i sitets eget mediebibliotek, og aldrig en fil med script i.'); ?></td></tr>
                    <tr><th scope="row">Scenelag</th><td>
                        <?php self::tjek('scenelag', $s['scenelag'], 'Et lag med klassen <præfiks>-lag får billedets filnavn som klasse',
                            'Lagene kommer fra en liste, så CSS\'en kan ikke kende dem på plads i rækken: <code>bold.avif</code> giver <code>&lt;præfiks&gt;-bold</code>.'); ?></td></tr>
                    <tr><th scope="row">Tomme felter</th><td>
                        <?php self::omraade('tomme_klasser', $s['tomme_klasser'], 4, "fa-knap\nfa-ingress"); ?>
                        <p class="description">Klasser på valgfrie felter, én pr. linje. Et tomt felt efterlader et linjeskift i elementet, så CSS'ens
                        <code>:empty</code> ikke rammer og der bliver et hul på siden; blanktegnene fjernes her.</p></td></tr>
                    <tr><th scope="row">WordPress-CSS i footeren</th><td>
                        <?php self::tjek('global_styles_fra', $s['global_styles_fra'], 'Fjern WordPress\' global-styles fra footeren',
                            'Breakdances Bloat Eliminator fjerner den kun i <code>&lt;head&gt;</code>; WordPress lægger den på igen i footeren (ca. 9 kB pr. side). Følger Breakdances egen indstilling, så der kun er ét sted at slå til og fra.'); ?></td></tr>
                    <tr><th scope="row">Google Fonts</th><td>
                        <?php self::tjek('google_fonts_fra', $s['google_fonts_fra'], 'Breakdance henter ikke skrifter fra Google på siderne',
                            'Slå til, når sitet har sine skrifter selv (@font-face i Breakdances globale stylesheet). Så sendes ingen besøgendes IP-adresse til Google (GDPR). I builderen hentes de stadig.'); ?></td></tr>
                    <tr><th scope="row">Hent skrifter med det samme</th><td>
                        <?php self::omraade('skrifter_forhaand', $s['skrifter_forhaand'], 3, "overskrift-600-latin.woff2"); ?>
                        <p class="description">Skriftfiler, browseren skal hente straks (preload), én pr. linje – et filnavn fra sitets egne skrifter
                        eller en adresse i mediebiblioteket. Bruges til overskriftens skrift, så teksten ikke skifter skrift og hopper, mens siden
                        tegnes. <strong>Kun små filer</strong>: en stor skrift gør siden langsommere at vise. Filer, der ikke findes, springes over.</p></td></tr>
                </table>

                <h2 class="title">Søgemaskiner og schema</h2>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">FAQ-schema</th><td>
                        <?php self::tjek('faq_schema', $s['faq_schema'], 'Læg FAQPage-schema på sider med et FAQ-modul',
                            'Spørgsmålene og svarene læses fra siden selv, så schemaet altid er det, siden viser – også efter rettelser i builderen. Har siden sit eget FAQ-schema i Rank Math, lægges der intet til.'); ?></td></tr>
                    <tr><th scope="row">Alternativt navn</th><td>
                        <?php self::felt('altnavn', $s['altnavn'], 30); ?>
                        <p class="description">Et gammelt firmanavn eller et domæne, der omdirigeres hertil. Kommer med i virksomhedens schema som <code>alternateName</code>.</p></td></tr>
                    <tr><th scope="row">Forfatter på artikler</th><td>
                        <?php self::felt('forfatter_navn', $s['forfatter_navn'], 24, 'Navn'); ?>
                        <?php self::felt('forfatter_titel', $s['forfatter_titel'], 24, 'Titel'); ?>
                        <?php self::felt('forfatter_url', $s['forfatter_url'], 30, 'https://… (forfatterens egen side)'); ?>
                        <p class="description">Sider under forælderen
                        <?php self::felt('forfatter_forael', $s['forfatter_forael'], 10, 'wiki'); ?>
                        <p style="margin-top:10px">Kun artikler under: <?php self::omraade('artikler', $s['artikler'], 2, "wiki\ncases"); ?></p>
                        <p class="description">Rank Math lægger »Article« på alle sider. Står der forældre her (én pr. linje), beholder kun deres undersider den. Tomt = Rank Maths standard.</p>
                        bliver <code>Article</code> med denne person som forfatter. Står navnet tomt, er virksomheden forfatter på alle sider.</p></td></tr>
                </table>

                <h2 class="title">Omdirigeringer og robots.txt</h2>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Gamle adresser</th><td>
                        <?php self::omraade('gamle_adresser', $s['gamle_adresser'], 7, "/start/ = /\n/gammel-side/ = /ny-side/"); ?>
                        <p class="description">Én pr. linje: <code>&lt;gammel sti&gt; = &lt;ny sti&gt;</code>. Sender videre med 301, så links udefra og
                        Googles indeks ikke går i stykker. <strong>De skal blive mindst et år.</strong> Sider med samme adresse som før behøver ingen linje.</p></td></tr>
                    <tr><th scope="row">robots.txt</th><td>
                        <?php self::tjek('robots', $s['robots'], 'Skriv reglerne for AI-crawlere i robots.txt',
                            'Søge- og svar-crawlere (Google, Bing, ChatGPT, Claude, Perplexity …) er velkomne – vi vil findes og citeres. Rene træningscrawlere afvises. Virker kun, når sitet er sat til at være synligt for søgemaskiner.'); ?>
                        <div style="margin-top:8px"><?php self::tjek('robots_traening', $s['robots_traening'], 'Tillad også ren AI-træning',
                            'Kun hvis sitet bevidst vil være træningsdata. Standard er nej.'); ?></div>
                        <p class="description"><a href="<?php echo esc_url(home_url('/robots.txt')); ?>" target="_blank" rel="noopener">Se robots.txt</a>
                        – robots.txt er en henstilling, ikke adgangskontrol.</p></td></tr>
                </table>

                <h2 class="title">Roller</h2>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Redaktørlås</th><td>
                        <?php self::tjek('redaktoerlaas', $s['redaktoerlaas'], 'Kun administratorer må rette Breakdances skabeloner, header, footer, blokke og popups',
                            'Ellers må en redaktør slette dem fra listerne i administrationen – og en global blok i papirkurven tager modulet med sig på <strong>alle</strong> sider. Redaktøren retter sider og medier som før.'); ?></td></tr>
                    <tr><th scope="row">SVG-upload</th><td>
                        <?php self::tjek('svg_upload', $s['svg_upload'], 'Administratorer må uploade SVG-filer',
                            'Til sitets egne ikoner. Redaktører kan det ikke – en SVG fra en fremmed kan indeholde script.'); ?></td></tr>
                </table>

                <h2 class="title">Virksomheden i schema</h2>
                <p>Det, Google og AI-søgning skal vide om virksomheden. Flettes ind i Rank Maths virksomheds-node; tomme felter udelades.</p>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Type</th><td><?php self::felt('vi_type', $vi['type'], 30, 'HomeAndConstructionBusiness'); ?>
                        <p class="description">schema.org-typen ud over Organization, fx <code>HomeAndConstructionBusiness</code>, <code>ProfessionalService</code>.</p></td></tr>
                    <tr><th scope="row">Telefon og e-mail</th><td><?php self::felt('vi_telefon', $vi['telefon'], 20, '+45 12 34 56 78'); ?> <?php self::felt('vi_email', $vi['email'], 30, 'info@eksempel.dk'); ?></td></tr>
                    <tr><th scope="row">Adresse</th><td><?php self::felt('vi_gade', $vi['gade'], 30, 'Vejnavn 1'); ?>
                        <?php self::felt('vi_postnr', $vi['postnr'], 6, '2800'); ?> <?php self::felt('vi_by', $vi['by'], 20, 'By'); ?>
                        <?php self::felt('vi_region', $vi['region'], 20, 'Region Hovedstaden'); ?> <?php self::felt('vi_land', $vi['land'], 4, 'DK'); ?></td></tr>
                    <tr><th scope="row">CVR</th><td><?php self::felt('vi_cvr', $vi['cvr'], 10); ?></td></tr>
                    <tr><th scope="row">Beskrivelse</th><td><?php self::omraade('vi_beskrivelse', $vi['beskrivelse'], 3); ?></td></tr>
                    <tr><th scope="row">Prisniveau og placering</th><td><?php self::felt('vi_prisniveau', $vi['prisniveau'], 25, '10.000–600.000 kr.'); ?>
                        <?php self::felt('vi_geo', $vi['geo'], 25, '55.7935, 12.5057'); ?>
                        <p class="description">Placering som breddegrad, længdegrad.</p></td></tr>
                    <tr><th scope="row">Åbningstider</th><td><?php self::omraade('vi_aabningstider', $vi['aabningstider'], 3, "Mo,Tu,We,Th 07:00-17:00\nFr 07:00-16:00"); ?>
                        <p class="description">Én pr. linje: dage (Mo Tu We Th Fr Sa Su) og tidsrum.</p></td></tr>
                    <tr><th scope="row">Dækningsområder</th><td><?php self::omraade('vi_omraader', $vi['omraader'], 3, "Nordsjælland\nKøbenhavn"); ?></td></tr>
                    <tr><th scope="row">Profiler</th><td><?php self::omraade('vi_profiler', $vi['profiler'], 3, "https://www.google.com/maps?cid=…"); ?>
                        <p class="description">Virksomhedens profiler andre steder (sameAs), én adresse pr. linje.</p></td></tr>
                    <tr><th scope="row">Anmeldelser</th><td><?php self::omraade('vi_anmeldelser', $vi['anmeldelser'], 4, "Navn | Tekst | 5"); ?>
                        <p class="description">Én pr. linje: navn | tekst | stjerner (1–5). Giver også et samlet gennemsnit.</p></td></tr>
                </table>

                <h2 class="title">Sporing og leads</h2>
                <p class="description" style="max-width:700px">Slået fra, indtil der står et id herunder. Samtykke sættes først på siden (alt afvist),
                og Google-tagget hentes <strong>ét sted</strong> og først efter at siden er indlæst. Samtykkebanneret er Complianz.</p>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Google-tag</th><td><?php self::felt('sp_tag', $sp['tag'], 20, 'GT-XXXXXXX'); ?>
                        <p class="description">Det ene tag, der hentes (<code>GT-…</code> eller <code>G-…</code>).</p></td></tr>
                    <tr><th scope="row">GA4-målings-id</th><td><?php self::felt('sp_ga4', $sp['ga4'], 20, 'G-XXXXXXXXXX'); ?>
                        <p class="description">Kun hvis hændelserne skal sendes til en anden GA4-ejendom end tagget selv.</p></td></tr>
                    <tr><th scope="row">Google Ads</th><td><?php self::felt('sp_ads', $sp['ads'], 20, 'AW-123456789'); ?>
                        <div style="margin-top:8px"><?php self::tjek('sp_ads_i_tag', $sp['ads_i_tag'], 'Ads er lagt sammen med tagget i Googles konti',
                            'Så hentes der ingen ekstra Ads-konfiguration. Står de hver for sig, lad feltet være tomt.'); ?></div></td></tr>
                    <tr><th scope="row">Konverteringer</th><td>
                        <?php self::omraade('sp_konverteringer', $sp['konverteringer'], 4, "kontakt = AbCdEfGhIj\ntelefon = KlMnOpQrSt"); ?>
                        <p class="description">Ads-labels, én pr. linje: <code>&lt;hændelse&gt; = &lt;label&gt;</code>. Hændelserne <code>kontakt</code> (formular sendt),
                        <code>telefon</code> og <code>mail</code> (klik) findes altid; egne hændelser kan sendes med
                        <code>document.dispatchEvent(new CustomEvent('asite:lead', {detail: 'navn'}))</code>.</p></td></tr>
                    <tr><th scope="row">GA4-navne</th><td>
                        <?php self::omraade('sp_ga4_navne', $sp['ga4_navne'], 3, "prisberegner = prisberegning"); ?>
                        <p class="description">Kun for egne hændelser. De tre faste heder <code>kontaktformular</code>, <code>klik_tel</code> og <code>klik_mail</code>.</p></td></tr>
                    <tr><th scope="row">Meta-pixel</th><td><?php self::felt('sp_meta', $sp['meta'], 20, '123456789012345'); ?>
                        <p class="description">Hentes først, når der er givet samtykke til marketing – også hvis det gives midt på siden.</p></td></tr>
                    <tr><th scope="row">Hent tagget</th><td>
                        <select name="sp_indlaes">
                            <option value="load" <?php selected($sp['indlaes'], 'load'); ?>>Efter at siden er indlæst (anbefalet)</option>
                            <option value="straks" <?php selected($sp['indlaes'], 'straks'); ?>>Straks</option>
                        </select>
                        <span style="margin-left:12px">senest <input type="number" name="sp_maks_ventetid" min="0" max="20000" value="<?php echo (int) $sp['maks_ventetid']; ?>" style="width:90px"> ms efter at HTML'en er læst</span>
                        <p class="description">»Straks« blokerer sidens visning i op mod 250 ms på mobil. Loftet er til langsomme forbindelser, hvor
                        »indlæst« først sker efter flere sekunder – så måles besøget alligevel.</p></td></tr>
                    <tr><th scope="row">Test-domæner</th><td>
                        <?php self::omraade('sp_test_domaener', $sp['test_domaener'], 3, "eksempel.dk"); ?>
                        <p class="description">Henvendelser fra mailadresser på disse domæner regnes som test og sendes ikke videre som kvalificerede leads.</p></td></tr>
                    <tr><th scope="row">Kør også på</th><td><?php self::felt('sp_vaert', $sp['vaert'], 30); ?>
                        <p class="description">Kun til test. Normalt kører sporingen <strong>kun</strong> på sitets eget domæne, så tallene ikke forurenes af byggeadressen.</p></td></tr>
                </table>

                <?php submit_button('Gem indstillinger'); ?>
            </form>
            <p style="color:#646970">Asite Site <?php echo esc_html(ASITE_SITE_VERSION); ?></p>
        </div>
        <?php
    }
}
