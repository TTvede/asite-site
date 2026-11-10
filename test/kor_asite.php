<?php
/** Kør prøvesidens HTML gennem Asite Site. Brug: php kor_asite.php <sti til asite-site-mappen> */
require __DIR__ . '/wp-stub.php';
require __DIR__ . '/fixtur.php';
$dir = rtrim($argv[1], '/') . '/';
define('ASITE_SITE_VERSION', 'proeve');
define('ASITE_SITE_DIR', $dir);
define('ASITE_SITE_FILE', $dir . 'asite-site.php');
require $dir . 'includes/class-core.php';
require $dir . 'includes/class-html.php';
update_option(Asite_Site::OPT, proeve_indstillinger());
Asite_Site_Html::init();
echo Asite_Site_Html::behandl(proeve_html());
