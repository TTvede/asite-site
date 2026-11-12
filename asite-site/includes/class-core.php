<?php
/**
 * Asite Site – kernen: indstillingerne (én option) og de hjælpere, alle moduler bruger.
 *
 * HVORFOR ÉN OPTION: husmodellen havde værdierne spredt over .env-konstanter (HM_PREFIKS, HM_DEV_HOST),
 * PHP-konstanter i <præfiks>-site.php (HM_FORFATTER, HM_ALTNAVN, HM_SPORING) og filtre i samme fil
 * (hm_tomme_klasser, hm_sizes_regler, hm_gamle_adresser). Intet af det følger med et site, kunden overtager.
 * Nu står alt i databasen under »asite_site« og rettes på Værktøjer → Asite Site.
 *
 * Hvert modul kan stadig udvides med et filter (asite_site/<navn>), så et site med en specialside kan lægge noget
 * til uden at skulle rette i pluginet – men intet KRÆVER kode mere.
 */
if (!defined('ABSPATH')) { exit; }

class Asite_Site {

    const OPT = 'asite_site';

    /** Standardindstillingerne. Alt, der kan slås til/fra, står som det stod på husmodel-sitet. */
    public static function standard(): array {
        return [
            'praefiks'          => '',        // sitets klassepræfiks (2–4 små bogstaver, fx »fa«) – uden det er klassemodulerne i dvale
            // Billeder (hm-billedattr)
            'billeder'          => true,      // srcset/sizes/width/height/alt fra mediebiblioteket
            'sizes_loft'        => 1200,      // bredere billeder får »(max-width: Npx) 100vw, Npx« i stedet for originalbredden
            'sizes_regler'      => '',        // én pr. linje: »<klasse> = <sizes>«
            'avif_kvalitet'     => 50,        // understørrelser i samme kvalitet som originalerne
            'billedliste_stoerrelse' => 'large', // billedlister (data-hm-billedliste) → denne størrelse fra mediebiblioteket
            // HTML-finish (hm-grundlag, resten)
            'tomme_klasser'     => '',        // én klasse pr. linje: blanktegn fjernes, så CSS :empty rammer
            'hero_ivrig'        => true,      // <præfiks>-lcp / -scene-billede / -ikke-lazy: ingen lazy load
            'inline_svg'        => true,      // <img class="<præfiks>-svg"> → SVG-koden selv
            'scenelag'          => true,      // <præfiks>-lag får billedets filnavn som klasse
            'global_styles_fra'  => true,     // WordPress' global-styles i footeren (følger Breakdances Bloat Eliminator)
            'google_fonts_fra'  => false,    // Breakdance henter ikke skrifter fra Google (sitet har dem selv – GDPR)
            'skrifter_forhaand' => '',        // skriftfiler, der hentes med det samme (preload) – én pr. linje
            // SEO/schema (hm-schema)
            'faq_schema'        => true,      // FAQPage fra Breakdance-træet
            'altnavn'           => '',        // alternateName på virksomheden (gammelt firmanavn/domæne)
            'forfatter_navn'    => '',        // navngiven forfatter på wiki-artikler (tom = altid virksomheden)
            'forfatter_titel'   => '',
            'forfatter_url'     => '',        // forfatterens egen side (Person.url i schemaet)
            'forfatter_forael'  => 'wiki',      // sider under denne forælder-slug er artikler med forfatter
            'artikler'          => '',        // forældre-slugs eller /stier/, hvis sider er artikler; ellers fjernes Rank Maths Article (tom = rør intet)
            'og_billede'        => true,      // delingsbillede: featured image eller sidens første billede, når Rank Math intet har
            'standardbillede'   => '',        // billede til sider uden eget billede (filnavn eller id) – delingsbillede og schema
            'site_altnavn'      => '',        // alternateName på WebSite (fx firmanavnet med ApS)
            'forfattersider_fra' => true,     // /author/… og ?author=N giver 404 (brugernavnene vises ikke)
            'forfattersider_til' => '',       // … eller 301 dertil (fx /om-os/ som live terrasse.nu); vinder over 404
            'locations_kml'     => true,      // /locations.kml + local-sitemap.xml ud fra virksomhedens adresse (som Rank Maths Local SEO)
            'llms'              => true,      // /llms.txt: de indekserbare sider som ren tekst til AI-søgning (llmstxt.org)
            'llms_top'          => '',        // toppen: »# Navn«, »> resumé«, kontakt (tom = sitets navn og slogan)
            'llms_grupper'      => '',        // én pr. linje: »<overskrift> = <sti>, <sti>*« – resten under »Øvrige sider«
            'llms_noter'        => '',        // fri tekst til sidst (fx »## Noter …« med punkter)
            // Lancering (hm-lancering)
            'dev_vaert'         => '',        // udviklingsadressen: 301 til domænet, når sitet er lanceret
            'gamle_adresser'    => '',        // én pr. linje: »<gammel sti> = <ny sti>«
            'robots'            => true,      // robots.txt: AI-søgning ja, træning nej
            'robots_traening'   => false,     // true = tillad også rene træningscrawlere
            // Roller (hm-redaktoer + SVG-upload)
            'redaktoerlaas'     => true,      // Breakdances header/footer/skabeloner/blokke kun for administratorer
            'svg_upload'        => true,      // SVG i mediebiblioteket for administratorer (vores egne ikoner)
            // Virksomheden i schema (flettes ind i Rank Maths Organization-node; i trin 2 skriver Asite Site selv grafen).
            // Kilden på et husmodel-site er registry/site-fakta.yaml (hm asite_site). Tomme felter skrives ikke.
            'virksomhed'        => [
                'type' => '',                 // fx HomeAndConstructionBusiness (schema.org-type ud over Organization)
                'telefon' => '', 'email' => '',
                'gade' => '', 'postnr' => '', 'by' => '', 'region' => '', 'land' => 'DK',
                'cvr' => '', 'beskrivelse' => '', 'prisniveau' => '',
                'geo' => '',                  // »55.79, 12.50«
                'aabningstider' => '',        // én pr. linje, schema.org-format: »Mo,Tu,We,Th 07:00-17:00«
                'omraader' => '',             // dækningsområder, ét pr. linje (areaServed)
                'profiler' => '',             // sameAs: Google Maps, Facebook … én adresse pr. linje
                'anmeldelser' => '',          // én pr. linje: »Navn | tekst | 5«
            ],
            // Sporing (hm-sporing) – slået FRA som standard
            'sporing'           => [
                'tag' => '', 'ga4' => '', 'ads' => '', 'ads_i_tag' => false, 'meta' => '',
                'indlaes' => 'load', 'maks_ventetid' => 3000,
                'konverteringer' => '',       // én pr. linje: »<hændelse> = <Ads-label>«
                'ga4_navne' => '',            // én pr. linje: »<hændelse> = <GA4-navn>«
                'test_domaener' => '',        // ét domæne pr. linje
                'vaert' => '',                // kun til test: kør sporingen på dette værtsnavn i stedet for domænet
            ],
        ];
    }

    /** Alt, der kun skal regnes ud én gang pr. forespørgsel, ligger her – ét sted, så det kan nulstilles ét sted.
     *  (Uden det kunne indstillingerne ikke læses igen, efter de var gemt: hver klasse huskede sin egen kopi.) */
    private static $husket = [];

    public static function husk(string $noegle, callable $lav) {
        if (!array_key_exists($noegle, self::$husket)) { self::$husket[$noegle] = $lav(); }
        return self::$husket[$noegle];
    }

    public static function nulstil_cache() { self::$husket = []; }

    public static function indstillinger(): array {
        return self::husk('indstillinger', function () {
            $gemt = get_option(self::OPT, []);
            $s = array_merge(self::standard(), is_array($gemt) ? $gemt : []);
            $s['sporing'] = array_merge(self::standard()['sporing'], is_array($s['sporing'] ?? null) ? $s['sporing'] : []);
            $s['virksomhed'] = array_merge(self::standard()['virksomhed'], is_array($s['virksomhed'] ?? null) ? $s['virksomhed'] : []);
            return $s;
        });
    }

    public static function i($navn, $standard = null) {
        $s = self::indstillinger();
        return $s[$navn] ?? $standard;
    }

    /** Sitets klassepræfiks (fx »fa«). Tom = ingen af de præfiks-afhængige moduler kører. */
    public static function praefiks(): string {
        $p = strtolower(trim((string) self::i('praefiks')));
        return preg_match('/^[a-z0-9]{2,4}$/', $p) ? $p : '';
    }

    /**
     * Overgangen: husmodellens mu-plugins (hm-*.php) ligger stadig på sitet og gør det samme.
     * HM_PREFIKS sættes af 00-hm-site.php. Så længe den findes, rører pluginet ikke sitet – ellers ville
     * siden få to output-buffere, to FAQ-schemaer og to robots-blokke.
     */
    public static function i_overgang(): bool {
        return defined('HM_PREFIKS');
    }

    /** »a = b« pr. linje → [a => b]. Både »=« og »→« (og bare mellemrum) virker, så en liste kan klistres ind. */
    public static function par(string $tekst): array {
        $ud = [];
        foreach (preg_split('/\R/', $tekst) ?: [] as $linje) {
            $linje = trim($linje);
            if ($linje === '' || $linje[0] === '#') { continue; }
            $dele = preg_split('/\s*(?:=>|=|→|\t|\s)\s*/u', $linje, 2);
            if (count($dele) === 2 && trim($dele[0]) !== '' && trim($dele[1]) !== '') {
                $ud[trim($dele[0])] = trim($dele[1]);
            }
        }
        return $ud;
    }

    /** Én værdi pr. linje (eller adskilt af komma/mellemrum) → liste uden tomme. */
    public static function liste(string $tekst): array {
        $ud = preg_split('/[\s,]+/u', trim($tekst)) ?: [];
        return array_values(array_filter(array_map('trim', $ud), fn($x) => $x !== '' && $x[0] !== '#'));
    }

    /** Aktivering: læg standardindstillingerne, og gæt præfikset fra husmodellen, hvis sitet er i overgangen. */
    public static function ved_aktivering() {
        $gemt = get_option(self::OPT, null);
        if (!is_array($gemt)) { $gemt = []; }
        if (empty($gemt['praefiks']) && defined('HM_PREFIKS')) { $gemt['praefiks'] = HM_PREFIKS; }
        if (empty($gemt['dev_vaert']) && defined('HM_DEV_HOST')) { $gemt['dev_vaert'] = HM_DEV_HOST; }
        update_option(self::OPT, array_merge(self::standard(), $gemt));
    }
}
