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
// Prefix (»*« til sidst): terrasse.nu's galleri er én side, men /terrassegalleri/page/N/ svarede 200 for ethvert N –
// uendelige næsten-dubletter (live lukkede dem 18-10-2026). En fast adresse vinder over et prefix.
saet(['gamle_adresser' => "/galleri/page/* = /galleri/\n/galleri/page/9/ = /andet/\n/start/ = /"]);
p('prefix: /galleri/page/3/ → /galleri/', $gaa('/galleri/page/3/', 'test.invalid', 'https://test.invalid') === ['https://test.invalid/galleri/', 301]);
p('prefix: selve siden omdirigeres ikke (ingen løkke)', $gaa('/galleri/', 'test.invalid', 'https://test.invalid') === null);
p('en fast adresse vinder over et prefix', $gaa('/galleri/page/9/', 'test.invalid', 'https://test.invalid') === ['https://test.invalid/andet/', 301]);
saet();
// Rank Maths omdirigering af vedhæftede filer kører før template_redirect: en gammel adresse med samme slug som et
// billede endte på forsiden (live's /spansk-trappe/, 15-09-2026). Listen skal derfor køre på parse_request.
$GLOBALS['H'] = [];
Asite_Site_Lancering::init();
p('gamle adresser kører på parse_request (før Rank Maths regler)', !empty($GLOBALS['H']['parse_request'][0]));
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

/* Virksomheden: det, Rank Math ikke har felter til (eller som skal kunne overleve uden Rank Math), flettes ind i dens
   virksomheds-node. terrasse.nu havde det i et site-mu-plugin (terrassenu-localbusiness-reviews.php), 11-11-2026. */
saet(['virksomhed' => ['type' => 'HomeAndConstructionBusiness', 'telefon' => '+45 43 330 530', 'email' => 'info@test.invalid',
    'gade' => 'Vej 1', 'postnr' => '2800', 'by' => 'Kgs. Lyngby', 'region' => 'Region Hovedstaden', 'land' => 'DK',
    'cvr' => '36916702', 'beskrivelse' => 'Vi bygger terrasser.', 'prisniveau' => '10.000–600.000 kr.', 'geo' => '55.793569, 12.5057397',
    'aabningstider' => "Mo,Tu,We,Th 07:00-17:00\nFr 07:00-16:00", 'omraader' => "Nordsjælland\nKøbenhavn",
    'profiler' => "https://www.google.com/maps?cid=1", 'anmeldelser' => "Anne | Flot arbejde. | 5\nBo | Godt. | 4"]]);
$r = Asite_Site_Seo::rankmath_json_ld($jsonld);
$o = $r['org'];
p('virksomhed: typen lagt oven på Organization', $o['@type'] === ['HomeAndConstructionBusiness', 'Organization']);
p('virksomhed: adresse, telefon og e-mail', ($o['address']['postalCode'] ?? '') === '2800' && ($o['address']['@type'] ?? '') === 'PostalAddress'
    && $o['telephone'] === '+45 43 330 530' && $o['email'] === 'info@test.invalid');
p('virksomhed: CVR som identifier', ($o['identifier']['propertyID'] ?? '') === 'CVR' && $o['identifier']['value'] === '36916702');
p('virksomhed: åbningstider, geo, prisniveau og beskrivelse', $o['openingHours'] === ['Mo,Tu,We,Th 07:00-17:00', 'Fr 07:00-16:00']
    && $o['geo']['latitude'] === 55.793569 && $o['priceRange'] === '10.000–600.000 kr.' && $o['description'] === 'Vi bygger terrasser.');
p('virksomhed: dækningsområder og profiler', ($o['areaServed'][1]['name'] ?? '') === 'København' && $o['sameAs'] === ['https://www.google.com/maps?cid=1']);
p('virksomhed: anmeldelser og et samlet gennemsnit', count($o['review']) === 2 && $o['review'][0]['author']['name'] === 'Anne'
    && $o['aggregateRating']['ratingValue'] === '4.5' && $o['aggregateRating']['reviewCount'] === '2');
saet(['virksomhed' => ['telefon' => '+45 1']]);
$o = Asite_Site_Seo::rankmath_json_ld($jsonld)['org'];
p('virksomhed: tomme felter skrives ikke (og typen røres ikke)', $o['@type'] === 'Organization' && !isset($o['address']) && !isset($o['review']));
saet();

/* Service pr. side: sidens egne data (post meta _asite_service, skrevet af hm asite_site ud fra specen). Live's
   terrassenu-service-schema.php byggede det af ACF-felter, som builder-modellen ikke har. */
$GLOBALS['META']['42:_asite_service'] = json_encode(['navn' => 'Træterrasse Dragør', 'type' => 'Træterrasse',
    'omraader' => ['Dragør', 'Søvang'], 'omraade_type' => 'City']);
$ud = kor('wp_head');
p('Service: navn, type og udbyder (virksomhedens @id)', str_contains($ud, '"@type":"Service"') && str_contains($ud, '"serviceType":"Træterrasse"')
    && str_contains($ud, '"provider":{"@id":"https://test.invalid/#organization"}'));
p('Service: dækningsområderne som byer', str_contains($ud, '{"@type":"City","name":"Søvang"}'));
unset($GLOBALS['META']['42:_asite_service']);
p('Service: intet uden sidens data', !str_contains(kor('wp_head'), '"Service"'));

/* Artikler: Rank Math lægger Article på ALLE sider som standard. Live terrasse.nu havde det kun på wiki og cases. */
$graf = ['side' => ['@type' => 'WebPage', '@id' => 'https://test.invalid/en-side/#webpage'],
         'richSnippet' => ['@type' => 'Article', '@id' => 'https://test.invalid/en-side/#richSnippet', 'author' => []]];
p('artikler: tom indstilling rører intet (andre sites uændrede)', isset(Asite_Site_Seo::rankmath_json_ld($graf)['richSnippet']));
saet(['artikler' => "wiki\ncases"]);
p('artikler: Article fjernet på en side uden for wiki/cases', !isset(Asite_Site_Seo::rankmath_json_ld($graf)['richSnippet']));
$GLOBALS['FORAELDER'][42] = 9; $GLOBALS['POST_FIELD']['9:post_name'] = 'cases';
p('artikler: Article bevaret på en case', isset(Asite_Site_Seo::rankmath_json_ld($graf)['richSnippet']));
unset($GLOBALS['FORAELDER'][42]);
saet(['artikler' => "wiki\n/en-side/"]);
p('artikler: en enkelt side (/sti/) beholder Article', isset(Asite_Site_Seo::rankmath_json_ld($graf)['richSnippet']));
p('artikler: uden forfatternavn er virksomheden forfatter', (Asite_Site_Seo::rankmath_json_ld($graf)['richSnippet']['author']['@id'] ?? '') === 'https://test.invalid/#organization');
saet(['artikler' => "wiki\n/en-side/", 'forfatter_navn' => 'Timme']);
$r = Asite_Site_Seo::rankmath_json_ld($graf);
p('artikler: en artikel uden for wiki får den navngivne forfatter (som live)', ($r['richSnippet']['author']['@id'] ?? '') === 'https://test.invalid/#forfatter'
    && ($r['asite_forfatter']['name'] ?? '') === 'Timme');
saet(['artikler' => "wiki", 'forfatter_navn' => 'Timme']);
p('artikler: en side uden for listen får ingen forfatter-Person', !isset(Asite_Site_Seo::rankmath_json_ld($graf)['asite_forfatter']));
saet();

/* Sidetype og ekstra schema pr. side (spec: schema:) – fx ContactPage og prisberegnerens WebApplication. */
$GLOBALS['META']['42:_asite_schema'] = json_encode(['sidetype' => 'ContactPage',
    'ekstra' => [['@type' => 'WebApplication', 'name' => 'Beregner', 'provider' => ['@id' => '{home}/#organization']]]]);
p('sidetype: WebPage-noden får sidens type', Asite_Site_Seo::rankmath_json_ld($graf)['side']['@type'] === 'ContactPage');
$ud = kor('wp_head');
p('ekstra schema skrives, og {home} bliver sitets adresse', str_contains($ud, '"@type":"WebApplication"')
    && str_contains($ud, '"provider":{"@id":"https://test.invalid/#organization"}'));
unset($GLOBALS['META']['42:_asite_schema']);

/* og:image: Rank Math fandt intet billede på ny.terrasse.nu (ingen featured image) – live viste sidens hero.
   Asite Site lægger sidens første billede fra Breakdance-træet ind, når Rank Math intet har. */
class Proeve_OG { public $id = []; private $har; function __construct($har) { $this->har = $har; }
    function has_images() { return $this->har; } function add_image_by_id($i) { $this->id[] = $i; } }
$GLOBALS['META']['42:_breakdance_data'] = json_encode(['tree_json_string' => json_encode(['root' => ['id' => 1, 'data' => ['properties' => []], 'children' => [
    ['id' => 2, 'data' => ['type' => 'EssentialElements\\Section', 'properties' => []], 'children' => [
        ['id' => 3, 'data' => ['type' => 'EssentialElements\\FImage', 'properties' => ['content' => ['image' => ['from' => 'media_library', 'media' => ['id' => 71]]]]], 'children' => []],
        ['id' => 4, 'data' => ['type' => 'EssentialElements\\FImage', 'properties' => ['content' => ['image' => ['media' => ['id' => 99]]]]], 'children' => []],
    ]]]]])]);
$og = new Proeve_OG(false); Asite_Site_Seo::og_billede($og);
p('og:image: sidens første billede fra Breakdance-træet', $og->id === [71]);
$og = new Proeve_OG(true); Asite_Site_Seo::og_billede($og);
p('og:image: Rank Maths eget billede røres ikke', $og->id === []);
saet(['standardbillede' => 'foto.avif']);
$og = new Proeve_OG(false); Asite_Site_Seo::og_billede($og);
p('og:image: sidens eget billede går forud for standardbilledet', $og->id === [71]);
unset($GLOBALS['META']['42:_breakdance_data']);
$og = new Proeve_OG(false); Asite_Site_Seo::og_billede($og);
p('og:image: standardbilledet (filnavn → id) på en side uden billede', $og->id === [7]);
saet(['standardbillede' => '7']);
p('standardbillede: et id virker også', Asite_Site_Seo::standardbillede() === 7);
saet();
p('standardbillede: tomt = intet', Asite_Site_Seo::standardbillede() === 0);

/* Live's Rank Math (med Local SEO og standardbillede) havde felter, ny manglede (QA 11-11-2026). */
$graf = ['org' => ['@type' => 'Organization', '@id' => 'https://test.invalid/#organization', 'logo' => ['@type' => 'ImageObject', '@id' => 'https://test.invalid/#logo']],
         'site' => ['@type' => 'WebSite', '@id' => 'https://test.invalid/#website'],
         'side' => ['@type' => 'WebPage', '@id' => 'https://test.invalid/en-side/#webpage'],
         'richSnippet' => ['@type' => 'Article', '@id' => 'https://test.invalid/en-side/#richSnippet']];
saet(['site_altnavn' => 'Test ApS', 'standardbillede' => 'foto.avif', 'virksomhed' => ['gade' => 'Vej 1', 'by' => 'Byen']]);
$r = Asite_Site_Seo::rankmath_json_ld($graf);
p('WebSite: alternativt navn', ($r['site']['alternateName'] ?? '') === 'Test ApS');
p('virksomhed: url og image (logoet)', ($r['org']['url'] ?? '') === 'https://test.invalid' && ($r['org']['image']['@id'] ?? '') === 'https://test.invalid/#logo');
p('virksomhed: adressen som Place og location', ($r['asite_place']['@type'] ?? '') === 'Place' && ($r['asite_place']['address']['streetAddress'] ?? '') === 'Vej 1'
    && ($r['org']['location']['@id'] ?? '') === 'https://test.invalid/#place');
$u = 'https://test.invalid/wp-content/uploads/2026/11/foto.avif';
p('sidens billede: ImageObject med mål og alt-tekst', ($r['asite_billede']['@id'] ?? '') === $u && $r['asite_billede']['width'] === '2000'
    && $r['asite_billede']['caption'] === 'Et møderum med projektor' && $r['asite_billede']['inLanguage'] === 'da-DK');
p('sidens billede: primaryImageOfPage og Article image', ($r['side']['primaryImageOfPage']['@id'] ?? '') === $u && ($r['richSnippet']['image']['@id'] ?? '') === $u);
$graf['side']['primaryImageOfPage'] = ['@id' => 'rm'];
p('sidens billede: Rank Maths eget billede røres ikke', !isset(Asite_Site_Seo::rankmath_json_ld($graf)['asite_billede']));
saet();
$r = Asite_Site_Seo::rankmath_json_ld($graf);
p('uden indstillinger: intet Place, intet billede, intet alternativt navn', !isset($r['asite_place']) && !isset($r['asite_billede']) && !isset($r['site']['alternateName']));

/* locations.kml + local-sitemap.xml: live havde dem fra Rank Maths Local SEO (slået fra på ny). */
saet();
p('kml: intet uden en adresse', Asite_Site_Seo::kml_svar('/locations.kml') === null);
saet(['virksomhed' => ['gade' => 'Vej 1', 'by' => 'Byen', 'postnr' => '2800', 'land' => 'DK', 'telefon' => '+45 1', 'geo' => '55.79, 12.50']]);
$k = Asite_Site_Seo::kml_svar('/locations.kml');
p('kml: navn, adresse, telefon og koordinater', str_contains($k, '<name><![CDATA[TestNu]]></name>') && str_contains($k, '<![CDATA[Vej 1, Byen, 2800, DK]]>')
    && str_contains($k, '<![CDATA[+45 1]]>') && str_contains($k, '<coordinates>12.50,55.79,0</coordinates>'));
p('kml: gyldig XML', (bool) simplexml_load_string($k));
$sm = Asite_Site_Seo::kml_svar('/local-sitemap.xml');
p('kml: local-sitemap peger på locations.kml', str_contains($sm, '<loc>https://test.invalid/locations.kml</loc>') && (bool) simplexml_load_string($sm));
p('kml: sitemap-indekset får local-sitemap', str_contains(Asite_Site_Seo::kml_index(''), 'https://test.invalid/local-sitemap.xml'));
p('kml: andre adresser røres ikke', Asite_Site_Seo::kml_svar('/kontakt/') === null);
saet();
p('kml: indekset røres ikke uden adresse', Asite_Site_Seo::kml_index('x') === 'x');

/* Forfattersider: live gav 404, ny sendte ?author=1 videre til /author/<brugernavn>/. */
$GLOBALS['FORFATTERSIDE'] = true;
Asite_Site_Seo::forfattersider();
p('forfattersider: 404', $GLOBALS['wp_query']->er_404 && ($GLOBALS['STATUS'] ?? 0) === 404);
$GLOBALS['FORFATTERSIDE'] = false; $GLOBALS['wp_query'] = new Proeve_Query(); unset($GLOBALS['STATUS']);
Asite_Site_Seo::forfattersider();
p('forfattersider: andre sider røres ikke', !$GLOBALS['wp_query']->er_404 && !isset($GLOBALS['STATUS']));

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

/* Google Fonts fra: Breakdance henter skrifter fra Google, når et element eller de globale indstillinger nævner en
   Google-skrift. Husmodellens hm-grundlag.php slog det fra (HM_LOKALE_SKRIFTER) – ny.terrasse.nu hentede Inter, Bree
   Serif og Oswald fra Google, da mu-plugins var væk (11-11-2026). */
$gf = function (array $ekstra, array $get = []) {
    remove_all_filters('breakdance_google_fonts_url');
    saet($ekstra);
    Asite_Site_Html::init();
    $_GET = $get;
    $ud = apply_filters('breakdance_google_fonts_url', 'https://fonts.googleapis.com/css2?family=Inter');
    $_GET = [];
    return $ud;
};
p('Google Fonts fra: Breakdance henter intet fra Google på siderne', $gf(['google_fonts_fra' => true]) === '');
p('Google Fonts fra: i builderen hentes de stadig (redaktøren skal se skrifterne)',
    $gf(['google_fonts_fra' => true], ['breakdance' => 'builder']) !== '');
p('uden indstillingen røres Breakdances Google-skrifter ikke (pluginets standard)', $gf(['google_fonts_fra' => false]) !== '');
remove_all_filters('breakdance_google_fonts_url');
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
// Før lanceringen ER hjemmeadressen udviklingsadressen (ny.<domæne>). Husmodellens hm-sporing.php tillod kun HM_DOMAENE,
// så sporingen var tavs dér; pluginet sporede på hjemmeadressen og ville have sendt testbesøg til live's konti
// (fundet ved flytningen af ny.terrasse.nu 11-11-2026).
$hjem = $GLOBALS['HOME'];
$GLOBALS['HOME'] = 'https://ny.test.invalid';
saet(['sporing' => $CFG, 'dev_vaert' => 'ny.test.invalid']);
$_SERVER['HTTP_HOST'] = 'ny.test.invalid';
p('før lanceringen: intet på udviklingsadressen, selv om den er hjemmeadressen', !Asite_Site_Sporing::aktiv());
$GLOBALS['HOME'] = $hjem;
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

/* ---------- 8b. Forhåndshentning af skrifter og forfatterens adresse (0.1.2) ---------- */
echo "8b. Skrifter og forfatter\n";
@mkdir($GLOBALS['UPLOADS'] . '/fa-filer/fonts', 0777, true);
file_put_contents($GLOBALS['UPLOADS'] . '/fa-filer/fonts/bree-400-latin.woff2', 'x');
saet(['skrifter_forhaand' => "bree-400-latin.woff2\nfindes-ikke.woff2\n../../wp-config.php"]);
ob_start(); Asite_Site_Html::skrifter_forhaand(); $pre = ob_get_clean();
// Præcis den linje, husmodellens hm-grundlag.php skrev for samme fil (hm_skrifter_forhaand):
p('skrift hentes straks – samme <link> som hm-grundlag gav',
    trim($pre) === '<link rel="preload" href="/wp-content/uploads/fa-filer/fonts/bree-400-latin.woff2" as="font" type="font/woff2" crossorigin>');
p('en fil, der ikke findes, giver ingen linje (ingen 404 på hver side)', substr_count($pre, '<link') === 1);
saet(['skrifter_forhaand' => '/wp-content/uploads/fa-filer/fonts/bree-400-latin.woff2']);
p('en fuld sti i uploads virker også', count(Asite_Site_Html::skrifter_adresser()) === 1);
saet();
p('intet sat: ingen forhåndshentning', Asite_Site_Html::skrifter_adresser() === []);

saet(['forfatter_navn' => 'Timme Tvede', 'forfatter_titel' => 'Art director og indehaver', 'forfatter_url' => 'https://tvede.me/']);
$GLOBALS['FORAELDER'][42] = 9; $GLOBALS['POST_FIELD']['9:post_name'] = 'wiki'; $GLOBALS['SIDE_ID'] = 42;
$r = Asite_Site_Seo::rankmath_json_ld(['side' => ['@type' => 'WebPage', 'author' => []]]);
p('forfatteren har sin egen adresse med (som HM_FORFATTER["url"])', ($r['asite_forfatter']['url'] ?? '') === 'https://tvede.me/'
    && ($r['asite_forfatter']['jobTitle'] ?? '') === 'Art director og indehaver');
unset($GLOBALS['FORAELDER'][42]);
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
