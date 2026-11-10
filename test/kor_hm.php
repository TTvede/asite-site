<?php
/**
 * Kør PRÆCIS samme HTML gennem husmodellens hm-billedattr.php + hm-grundlag.php, med fauth.dk's egne filtre.
 * Brug: php kor_hm.php <sti til husmodellens mu-plugins>
 *
 * Rækkefølgen er som på et rigtigt site: hm-grundlag starter sin output-buffer på prioritet 4 og hm-billedattr på 5,
 * og output-buffere afvikles omvendt – billederne først, så finishen.
 */
require __DIR__ . '/wp-stub.php';
require __DIR__ . '/fixtur.php';
define('HM_PREFIKS', 'fa');
define('HM_DOMAENE', 'test.invalid');
define('HM_LOKALE_SKRIFTER', false);
$mu = rtrim($argv[1], '/') . '/';
require $mu . 'hm-billedattr.php';
require $mu . 'hm-grundlag.php';

// fauth.dk's egne filtre (mu-plugins/fa-site.php)
$i = proeve_indstillinger();
add_filter('hm_tomme_klasser', fn() => preg_split('/\s+/', trim($i['tomme_klasser'])));
add_filter('hm_sizes_regler', function () use ($i) {
    $ud = [];
    foreach (preg_split('/\R/', $i['sizes_regler']) as $l) {
        if (strpos($l, '=') !== false) { [$k, $v] = explode('=', $l, 2); $ud[trim($k)] = trim($v); }
    }
    return $ud;
});

echo hm_tomme_felter(hm_hero_eager(hm_inline_svg(hm_lagnavn(hm_billedattr(proeve_html())))));
