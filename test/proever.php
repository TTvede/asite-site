<?php
/**
 * Prøvekørsel af Asite Site uden database: robots.txt, omdirigeringer, schema, roller, indstillingernes tolkning
 * og overgangsvagten. Skriver én linje pr. prøve: »ok <navn>« eller »FEJL <navn>«.
 *
 * Brug: php proever.php <sti til asite-site-mappen>
 */
require __DIR__ . '/wp-stub.php';
require __DIR__ . '/fixtur.php';

$dir = rtrim($argv[1], '/') . '/';
define('ASITE_SITE_VERSION', 'proeve');
define('ASITE_SITE_DIR', $dir);
define('ASITE_SITE_FILE', $dir . 'asite-site.php');
foreach (['core', 'html', 'seo', 'lancering', 'roller', 'sporing', 'admin', 'updater'] as $k) {
    require $dir . "includes/class-$k.php";
}

$fejl = 0;
function p(string $navn, bool $ok) {
    global $fejl;
    if (!$ok) { $fejl++; }
    echo ($ok ? 'ok   ' : 'FEJL '), $navn, "\n";
}
/** Sæt indstillingerne (eventuelt med en ændring) og glem alt, der var husket fra sidste prøve. */
function saet(array $ekstra = []) {
    update_option(Asite_Site::OPT, array_merge(proeve_indstillinger(), $ekstra));
    Asite_Site::nulstil_cache();
}

/* ---------- 1. Indstillingernes tolkning ---------- */
echo "1. Indstillinger\n";
p('par(): »a = b« pr. linje', Asite_Site::par("x = 1\ny=2\n# kommentar\n\nz  3") === ['x' => '1', 'y' => '2', 'z' => '3']);
p('par(): pil og tabulator virker også', Asite_Site::par("a => b\nc\td") === ['a' => 'b', 'c' => 'd']);
p('liste(): linjer, komma og mellemrum', Asite_Site::liste("a\nb, c  d") === ['a', 'b', 'c', 'd']);
saet();
p('praefiks: læses', Asite_Site::praefiks() === 'fa');
saet(['praefiks' => 'FORLANGT']);
p('praefiks: ugyldig værdi afvises (modulerne går i dvale i stedet for at lave rod)', Asite_Site::praefiks() === '');
saet();

/* ---------- 2. robots.txt ---------- */
echo "2. robots.txt (søgning ja, træning nej)\n";
$ind = "User-agent: *\nDisallow: /wp-admin/\nSitemap: https://test.invalid/sitemap_index.xml";
$a = Asite_Site_Lancering::robots_txt($ind, true);
p('træning afvist', str_contains($a, "User-agent: GPTBot\nDisallow: /\n"));
p('søgning tilladt', str_contains($a, "User-agent: OAI-SearchBot\nAllow: /"));
p('Google-Extended ja (styrer også Gemini-grounding)', str_contains($a, "User-agent: Google-Extended\nAllow: /"));
p('sitemap til sidst', str_ends_with($a, "Sitemap: https://test.invalid/sitemap_index.xml\n"));
p('ikke offentligt site: robots.txt urørt', Asite_Site_Lancering::robots_txt($ind, false) === $ind);
saet(['robots_traening' => true]);
$b = Asite_Site_Lancering::robots_txt($ind, true);
p('valgt træning: også træningscrawlere får Allow', str_contains($b, "User-agent: GPTBot\nAllow: /") && !str_contains($b, "Disallow: /\n\n"));
saet();

/* ---------- 3. Omdirigeringer ---------- */
echo "3. Omdirigeringer\n";
$kort = Asite_Site_Lancering::kort();
p('gamle adresser læst (med skråstreg i begge ender)', ($kort['/start/'] ?? null) === '/' && ($kort['/projektor-losning/'] ?? null) === '/moderum-auditorier-og-sale/');

/** Gamle adresser: kør template_redirect-delen, som WordPress ville (wp_redirect kaster i attrappen). */
$gaa = function (string $uri, string $vaert, string $home) {
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['HTTP_HOST'] = $vaert;
    $GLOBALS['OPT']['home'] = $home;
    $GLOBALS['HOME'] = $home;
    unset($GLOBALS['REDIRECT']);
    try { Asite_Site_Lancering::gamle_adresser(); } catch (RuntimeException $e) {}
    return $GLOBALS['REDIRECT'] ?? null;
};
p('udviklingsadressen → domænet, når sitet er lanceret',
    Asite_Site_Lancering::maal_for('ny.test.invalid', 'https://test.invalid', '/en-side/') === 'https://test.invalid/en-side/');
p('FØR lanceringen røres udviklingsadressen ikke (den er byggepladsen)',
    Asite_Site_Lancering::maal_for('ny.test.invalid', 'https://ny.test.invalid', '/en-side/') === null);
p('domænet selv omdirigeres aldrig (ingen løkke)',
    Asite_Site_Lancering::maal_for('test.invalid', 'https://test.invalid', '/en-side/') === null);
p('port i værtsnavnet forstyrrer ikke',
    Asite_Site_Lancering::maal_for('ny.test.invalid:8080', 'https://test.invalid', '/') === 'https://test.invalid/');
p('gammel adresse → ny side (301)',
    $gaa('/projektor-losning/', 'test.invalid', 'https://test.invalid') === ['https://test.invalid/moderum-auditorier-og-sale/', 301]);
p('gammel adresse uden skråstreg til sidst virker også',
    $gaa('/start', 'test.invalid', 'https://test.invalid') === ['https://test.invalid/', 301]);
p('ukendt adresse omdirigeres ikke', $gaa('/findes-ikke/', 'test.invalid', 'https://test.invalid') === null);
$GLOBALS['HOME'] = 'https://test.invalid';

/* ---------- 4. FAQ-schema fra Breakdance-træet ---------- */
echo "4. FAQ-schema og forfatter\n";
$attr = fn($navn, $vaerdi) => ['settings' => ['advanced' => ['attributes' => [['name' => $navn, 'value' => $vaerdi]]]]];
$tekst = fn($t) => ['content' => ['content' => ['text' => $t]]];
$traeet = ['root' => ['id' => 1, 'data' => ['properties' => []], 'children' => [
    ['id' => 2, 'data' => ['properties' => $attr('data-hm-modul', 'faq')], 'children' => [
        ['id' => 3, 'data' => ['properties' => $attr('data-hm-loop', 'punkter')], 'children' => [
            ['id' => 4, 'data' => ['properties' => []], 'children' => [
                ['id' => 5, 'data' => ['properties' => array_merge($attr('data-hm-felt', 'spoergsmaal'), $tekst('Kan I levere på en uge?'))], 'children' => []],
                ['id' => 6, 'data' => ['properties' => array_merge($attr('data-hm-felt', 'svar'), $tekst('Ja. <b>Oftest</b> på  3 dage.'))], 'children' => []],
            ]],
            ['id' => 7, 'data' => ['properties' => []], 'children' => [
                ['id' => 8, 'data' => ['properties' => array_merge($attr('data-hm-felt', 'spoergsmaal'), $tekst('Hvad koster det?'))], 'children' => []],
                ['id' => 9, 'data' => ['properties' => array_merge($attr('data-hm-felt', 'svar'), $tekst('Det afhænger af salen.'))], 'children' => []],
            ]],
        ]],
    ]],
]]];
$GLOBALS['META']['42:_breakdance_data'] = json_encode(['tree_json_string' => json_encode($traeet)]);
$GLOBALS['SIDE_ID'] = 42;
$punkter = Asite_Site_Seo::bd_faq(42);
p('to FAQ-punkter fundet i træet', is_array($punkter) && count($punkter) === 2);
p('spørgsmålet er sidens egen tekst', ($punkter[0]['spoergsmaal'] ?? '') === 'Kan I levere på en uge?');
p('svaret er renset for tags og dobbelt mellemrum', ($punkter[0]['svar'] ?? '') === 'Ja. Oftest på 3 dage.');
$ud = kor('wp_head');
Asite_Site_Seo::init();
$ud = kor('wp_head');
p('FAQPage-schema skrevet ud', str_contains($ud, '"@type":"FAQPage"') && str_contains($ud, 'Kan I levere på en uge?'));
p('schemaet peger på sidens egen adresse', str_contains($ud, '"@id":"https://test.invalid/en-side/#faq"'));
$GLOBALS['META']['42:rank_math_schema_FAQPage'] = 'noget';
p('sidens eget FAQ-schema i Rank Math respekteres (intet dobbelt)', !str_contains(kor('wp_head'), 'FAQPage'));
unset($GLOBALS['META']['42:rank_math_schema_FAQPage']);
$GLOBALS['META']['43:_breakdance_data'] = json_encode(['tree_json_string' => json_encode(['root' => ['id' => 1, 'data' => ['properties' => []], 'children' => []]])]);
$GLOBALS['SIDE_ID'] = 43;
p('side uden FAQ-modul får intet schema', !str_contains(kor('wp_head'), 'FAQPage'));
$GLOBALS['SIDE_ID'] = 42;

$jsonld = ['org' => ['@type' => 'Organization', '@id' => 'https://test.invalid/#organization'],
           'tom' => ['@type' => 'Person', '@id' => 'https://test.invalid/author'],
           'side' => ['@type' => 'WebPage', 'author' => ['@id' => 'https://test.invalid/author']]];
$r = Asite_Site_Seo::rankmath_json_ld($jsonld);
p('alternativt navn lagt på virksomheden', ($r['org']['alternateName'] ?? '') === 'Fauth AV');
p('Rank Maths tomme forfatter-Person fjernet', !isset($r['tom']));
p('siden har virksomheden som forfatter', ($r['side']['author']['@id'] ?? '') === 'https://test.invalid/#organization');
saet(['forfatter_navn' => 'Charlotte', 'forfatter_titel' => 'Indehaver']);
$GLOBALS['FORAELDER'][42] = 9;
$GLOBALS['POST_FIELD']['9:post_name'] = 'wiki';
$r = Asite_Site_Seo::rankmath_json_ld($jsonld);
p('wiki-artikel: navngiven forfatter og Article', ($r['side']['author']['@id'] ?? '') === 'https://test.invalid/#forfatter'
    && ($r['side']['@type'] ?? '') === 'Article' && ($r['asite_forfatter']['name'] ?? '') === 'Charlotte');
unset($GLOBALS['FORAELDER'][42]);
saet();

/* ---------- 5. Roller ---------- */
echo "5. Roller\n";
$GLOBALS['POST_TYPE'][100] = 'breakdance_header';
$GLOBALS['POST_TYPE'][101] = 'page';
$GLOBALS['ADMIN'] = false;
p('redaktør må ikke rette Breakdances header', Asite_Site_Roller::laas(['edit_posts'], 'edit_post', 2, [100]) === ['do_not_allow']);
p('redaktør må ikke slette den heller', Asite_Site_Roller::laas(['delete_posts'], 'delete_post', 2, [100]) === ['do_not_allow']);
p('redaktør må stadig rette sider', Asite_Site_Roller::laas(['edit_posts'], 'edit_post', 2, [101]) === ['edit_posts']);
p('visning på siden er uændret', Asite_Site_Roller::laas(['read'], 'read_post', 2, [100]) === ['read']);
p('redaktør må ikke uploade SVG', !isset(Asite_Site_Roller::svg_mime([])['svg']));
$GLOBALS['ADMIN'] = true;
p('administrator må rette alt', Asite_Site_Roller::laas(['edit_posts'], 'edit_post', 1, [100]) === ['edit_posts']);
p('administrator må uploade SVG', (Asite_Site_Roller::svg_mime([])['svg'] ?? '') === 'image/svg+xml');

/* ---------- 6. Billeder: loftet for sizes ---------- */
echo "6. Billeder\n";
p('bredt billede får loftet (1200 px) i stedet for originalbredden',
    Asite_Site_Html::sizes_loft('(max-width: 2000px) 100vw, 2000px', [2000, 1000]) === '(max-width: 1200px) 100vw, 1200px');
p('billede under loftet røres ikke',
    Asite_Site_Html::sizes_loft('(max-width: 800px) 100vw, 800px', [800, 400]) === '(max-width: 800px) 100vw, 800px');
saet(['sizes_loft' => 0]);
p('loft 0 = fra', Asite_Site_Html::sizes_loft('x', [9000, 9000]) === 'x');
saet();

/* ---------- 7. Sidens finish, del for del ---------- */
echo "7. Sidens finish\n";
saet();
$ud = Asite_Site_Html::behandl(proeve_html());
p('hero: loading="lazy" fjernet og fetchpriority="high" sat',
    preg_match('/<img[^>]*fa-lcp[^>]*>/', $ud, $m) && str_contains($m[0], 'fetchpriority="high"') && !str_contains($m[0], 'loading="lazy"'));
p('hero: alt-tekst hentet fra mediebiblioteket', str_contains($ud, 'alt="Et møderum med projektor"'));
p('hero: sizes fra tabellen (100vw), ikke originalbredden',
    preg_match('/<img[^>]*fa-hero-bg[^>]*>/', $ud, $m) && str_contains($m[0], 'sizes="100vw"'));
p('kort: sizes fra tabellen pr. komponent',
    preg_match('/<img[^>]*fa-kort-img[^>]*>/', $ud, $m) && str_contains($m[0], 'sizes="(max-width:767px) 120px, 150px"'));
p('kort: billeder uden hero-klasse beholder lazy load',
    preg_match('/<img[^>]*fa-kort-img[^>]*>/', $ud, $m) && str_contains($m[0], 'loading="lazy"'));
p('mål (width/height) lagt på, så siden ikke hopper', str_contains($ud, 'width="2000"') && str_contains($ud, 'height="1000"'));
p('scenelag: billedets filnavn blev klasse (fa-bold)', str_contains($ud, 'class="fa-lag fa-bold"'));
p('SVG-ikon lagt inline og skjult for skærmlæsere', str_contains($ud, '<svg aria-hidden="true" focusable="false"'));
p('SVG MED SCRIPT lægges ALDRIG inline', str_contains($ud, 'ond.svg') && !str_contains($ud, 'alert(1)'));
p('tomt felt: blanktegnene fjernet, så CSS :empty rammer', str_contains($ud, '<p class="fa-ingress"></p>')
    && str_contains($ud, '<span class="fa-kort-tekst"></span>'));
p('udfyldt felt med samme klasse røres ikke', str_contains($ud, '>Kontakt</a>'));
p('tom billedpladsholder fra builderen fjernet', !str_contains($ud, 'data:image/svg'));
p('billede fra et fremmed domæne røres ikke',
    preg_match('/<img src="https:\/\/fremmed[^>]*>/', $ud, $m) && !str_contains($m[0], 'srcset'));
p('behandlingen kan køres igen uden at ændre noget (idempotent)', Asite_Site_Html::behandl($ud) === $ud);
saet(['praefiks' => '']);
$uden = Asite_Site_Html::behandl(proeve_html());
p('uden præfiks: ingen hero-, SVG- eller scenelag-behandling',
    !str_contains($uden, 'fetchpriority') && !str_contains($uden, '<svg aria-hidden') && !str_contains($uden, 'fa-lag fa-bold'));
p('uden præfiks: billedattributter virker stadig (de er ikke præfiks-afhængige)', str_contains($uden, 'width="2000"'));
saet();

/* ---------- 8. Sporing (B-258) ---------- */
echo "8. Sporing\n";
$spor = function (array $cfg, string $vaert = 'test.invalid') {
    saet(['sporing' => $cfg]);
    $_SERVER['HTTP_HOST'] = $vaert;
    ob_start();
    Asite_Site_Sporing::samtykke();
    Asite_Site_Sporing::klikid();
    Asite_Site_Sporing::google_tag();
    $head = ob_get_clean();
    ob_start();
    Asite_Site_Sporing::haendelser();
    return $head . "\n==FOOT==\n" . ob_get_clean();
};
$CFG = ['tag' => 'GT-TEST1234', 'ads' => 'AW-123456789', 'konverteringer' => "kontakt = abcDEF123", 'meta' => '1234567890'];
$ud = $spor($CFG);
p('samtykke (alt afvist) står FØR Google-tagget',
    ($a = strpos($ud, 'id="asite-samtykke"')) !== false && ($b = strpos($ud, 'id="asite-google-tag"')) !== false && $a < $b);
p('Consent Mode v2: alt afvist som standard',
    str_contains($ud, "analytics_storage:'denied'") && str_contains($ud, "ad_user_data:'denied'") && str_contains($ud, "ad_personalization:'denied'"));
p('biblioteket hentes efter load – ikke mens siden tegnes',
    !str_contains($ud, '<script async src="https://www.googletagmanager') && str_contains($ud, "addEventListener('load'"));
p('loft: senest 3 s efter at HTML\'en er læst (langsom mobil måles alligevel)',
    str_contains($ud, "DOMContentLoaded',t") && str_contains($ud, 'setTimeout(h,3000)'));
p('Ads konfigureret som sin egen konto', str_contains($ud, 'gtag(\'config\',"AW-123456789")'));
p('klik-id og UTM fanges i browseren (sidecachen starter ikke WordPress)',
    str_contains($ud, 'id="asite-klikid"') && str_contains($ud, 'gbraid') && str_contains($ud, 'as_klikid') === false && str_contains($ud, '"as_"'));
p('hændelser og Meta i hver sit script',
    str_contains($ud, 'id="asite-haendelser"') && str_contains($ud, 'id="asite-meta"')
    && !str_contains(substr($ud, strpos($ud, 'id="asite-haendelser"'), strpos($ud, 'id="asite-meta"') - strpos($ud, 'id="asite-haendelser"')), 'facebook'));
p('Meta hentes først efter marketing-samtykke', str_contains($ud, "cmplz_has_consent('marketing')"));
p('egne hændelser: både asite:lead og husmodellens hm:lead lyttes der efter',
    str_contains($ud, "addEventListener('asite:lead'") && str_contains($ud, "addEventListener('hm:lead'"));
$ud = $spor(['tag' => 'GT-TEST1234', 'ads' => 'AW-123456789', 'ads_i_tag' => true, 'indlaes' => 'straks']);
p('»straks« + Ads i tagget: én indlæsning, ingen ekstra Ads-config, ingen Meta',
    str_contains($ud, '<script async src="https://www.googletagmanager.com/gtag/js?id=GT-TEST1234"')
    && !str_contains($ud, 'config\',"AW-') && !str_contains($ud, 'asite-meta'));
p('intet på udviklingsadressen (tallene må ikke forurenes)', !str_contains($spor($CFG, 'ny.test.invalid'), '<script'));
p('kører også på www.', str_contains($spor($CFG, 'www.test.invalid'), 'asite-samtykke'));
p('intet uden opsætning (slået fra som standard)', !str_contains($spor([]), '<script'));
p('ugyldigt id ignoreres i stedet for at blive skrevet ud', !str_contains($spor(['tag' => 'noget vrøvl']), '<script'));

/* lead-stempling: klik-id fra cookien på et nyt lead, og de gamle navne læses også */
saet(['sporing' => $CFG]);
$_COOKIE = ['as_klikid' => str_repeat('a', 20), 'as_kliktype' => 'gclid', 'cmplz_marketing' => 'allow'];
Asite_Site_Sporing::stempl_lead(500, (object) ['post_type' => 'breakdance_form_res'], false);
p('nyt lead får klik-id og samtykke med', get_post_meta(500, '_as_klikid', true) === str_repeat('a', 20)
    && get_post_meta(500, '_as_samtykke_marketing', true) === 'granted');
$_COOKIE = ['hm_klikid' => str_repeat('b', 20)];
Asite_Site_Sporing::stempl_lead(501, (object) ['post_type' => 'breakdance_form_res'], false);
p('en cookie fra husmodellen læses også (intet klik-id tabes ved flytning)',
    get_post_meta(501, '_as_klikid', true) === str_repeat('b', 20));
$_COOKIE = ['as_klikid' => 'kort'];
Asite_Site_Sporing::stempl_lead(502, (object) ['post_type' => 'breakdance_form_res'], false);
p('et klik-id, der ikke ser rigtigt ud, gemmes ikke', get_post_meta(502, '_as_klikid', true) === '');
$_COOKIE = [];
saet();

/* ---------- 9. Overgangen ---------- */
echo "9. Overgangen fra mu-plugins\n";
p('pluginet er aktivt, når husmodellens mu-plugins er væk', !Asite_Site::i_overgang());
$GLOBALS['MENU'] = [];
Asite_Site_Admin::menu();
p('indstillingssiden ligger under Værktøjer og kræver administrator',
    ($GLOBALS['MENU'][0][0] ?? '') === 'Asite Site' && ($GLOBALS['MENU'][0][2] ?? '') === 'manage_options');
$GLOBALS['ADMIN'] = false;
ob_start(); Asite_Site_Admin::side(); $uden_adgang = ob_get_clean();
p('ingen indstillinger vises for andre end administratorer', $uden_adgang === '');
$GLOBALS['ADMIN'] = true;
ob_start(); Asite_Site_Admin::side(); $siden = ob_get_clean();
p('indstillingssiden tegnes med alle felterne',
    substr_count($siden, 'name="') > 25 && str_contains($siden, 'Klassepræfiks') && str_contains($siden, 'Sporing og leads'));

echo $fejl ? "\n$fejl FEJL\n" : "\nalt ok\n";
exit($fejl ? 1 : 0);
