<?php
/**
 * Fælles prøvedata: ét site med ét billede i mediebiblioteket, én SVG og de indstillinger, fauth.dk har i dag.
 * Både Asite Site og husmodellens hm-*.php måles på PRÆCIS disse data, så forskelle kun kan komme fra koden.
 */

$GLOBALS['UPLOADS'] = sys_get_temp_dir() . '/asite-proeve-uploads';
@mkdir($GLOBALS['UPLOADS'] . '/2026/11', 0777, true);
file_put_contents($GLOBALS['UPLOADS'] . '/2026/11/pil.svg',
    '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0h10v10z"/></svg>');
// En SVG med script må ALDRIG lægges inline – den skal blive stående som <img>.
file_put_contents($GLOBALS['UPLOADS'] . '/2026/11/ond.svg',
    '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

$GLOBALS['BILLEDER'] = [
    'https://test.invalid/wp-content/uploads/2026/11/foto.avif' => 7,
];
$GLOBALS['BILLED_META'] = [7 => ['file' => '2026/11/foto.avif', 'width' => 2000, 'height' => 1000]];
$GLOBALS['META']['7:_wp_attachment_image_alt'] = 'Et møderum med projektor';

/** Indstillingerne: de samme værdier, fauth.dk har som konstanter og filtre i dag. */
function proeve_indstillinger(): array {
    return [
        'praefiks' => 'fa',
        'billeder' => true,
        'sizes_loft' => 1200,
        'sizes_regler' => "fa-hero-bg = 100vw\nfa-kort-img = (max-width:767px) 120px, 150px",
        'avif_kvalitet' => 50,
        'tomme_klasser' => "fa-knap\nfa-over\nfa-ingress\nfa-citat\nfa-undertitel\nfa-kort-tekst\nfa-intro",
        'hero_ivrig' => true, 'inline_svg' => true, 'scenelag' => true, 'global_styles_fra' => true,
        'faq_schema' => true, 'altnavn' => 'Fauth AV', 'forfatter_navn' => '', 'forfatter_titel' => '',
        'forfatter_forael' => 'wiki',
        'dev_vaert' => 'ny.test.invalid',
        'gamle_adresser' => "/start/ = /\n/projektor-losning/ = /moderum-auditorier-og-sale/",
        'robots' => true, 'robots_traening' => false,
        'redaktoerlaas' => true, 'svg_upload' => true,
        'sporing' => [],
    ];
}

/** Prøvesiden: alt det, pluginet skal gøre noget ved, i én HTML. */
function proeve_html(): string {
    return <<<'HTML'
<!doctype html><html><body>
<section class="fa-hero">
  <img class="fa-hero-bg fa-lcp" src="https://test.invalid/wp-content/uploads/2026/11/foto.avif" loading="lazy" alt="">
  <div class="fa-lag"><div><img src="https://test.invalid/wp-content/uploads/2026/11/bold-300x200.avif" alt=""></div></div>
  <p class="fa-ingress">
  </p>
  <a class="fa-knap" href="/kontakt/">Kontakt</a>
</section>
<section class="fa-kort">
  <img class="fa-kort-img" src="https://test.invalid/wp-content/uploads/2026/11/foto.avif" loading="lazy">
  <img class="fa-svg" src="https://test.invalid/wp-content/uploads/2026/11/pil.svg" alt="">
  <img class="fa-svg" src="https://test.invalid/wp-content/uploads/2026/11/ond.svg" alt="">
  <img class="fa-tom" src="data:image/svg+xml;base64,AAAA" alt="">
  <img src="https://fremmed.invalid/wp-content/uploads/2026/11/foto.avif" alt="Udefra">
  <span class="fa-kort-tekst">
  </span>
</section>
<div class="fa-galleri" data-hm-billedliste="data-billeder:billeder" data-billeder="https://test.invalid/wp-content/uploads/2026/11/foto.avif|https://test.invalid/wp-content/uploads/2026/11/ukendt.avif"></div>
</body></html>
HTML;
}
