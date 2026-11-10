<?php
/**
 * Kør PRÆCIS samme HTML gennem husmodellens hm-billedattr.php + hm-grundlag.php, med fauth.dk's egne filtre.
 * Brug: php kor_hm.php <sti til husmodellens mu-plugins>
 *
 * Bufferne startes af mu-plugins' egen template_redirect-kode, så rækkefølgen og en ændring i dem (fx billedlisterne,
 * f512496) kommer med af sig selv – prøven afhænger ikke af, at nogen har husket at opdatere den.
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

echo gennem_bufferne(proeve_html());
