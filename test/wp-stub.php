<?php
/**
 * WordPress-attrapper til prøvekørslen (test.sh). Nok af WordPress til at pluginets moduler kan køre uden database:
 * hooks, indstillinger og de billedfunktioner, kernen ellers leverer.
 *
 * Den samme attrap bruges til husmodellens hm-*.php, så de to kan sammenlignes på PRÆCIS samme HTML – det er
 * prøvestenen for, at pluginet gør det samme som mu-plugins gjorde.
 */
define('ABSPATH', '/');
if (!defined('HOUR_IN_SECONDS')) { define('HOUR_IN_SECONDS', 3600); }

$GLOBALS['H'] = [];            // hooks: navn → prioritet → [callback]
$GLOBALS['OPT'] = [];          // options
$GLOBALS['META'] = [];         // post_meta: "id:nøgle" → værdi
$GLOBALS['HOME'] = 'https://test.invalid';
$GLOBALS['ADMIN'] = true;      // current_user_can('manage_options')

function add_action($h, $c, $p = 10, $a = 1) { $GLOBALS['H'][$h][$p][] = $c; }
function add_filter($h, $c, $p = 10, $a = 1) { add_action($h, $c, $p, $a); }
function remove_all_filters($h) { unset($GLOBALS['H'][$h]); }
function has_filter($h) { return !empty($GLOBALS['H'][$h]); }
function apply_filters($h, $v, ...$rest) {
    $l = $GLOBALS['H'][$h] ?? [];
    ksort($l);
    foreach ($l as $cs) { foreach ($cs as $c) { $v = $c($v, ...$rest); } }
    return $v;
}
function do_action($h, ...$a) { $l = $GLOBALS['H'][$h] ?? []; ksort($l); foreach ($l as $cs) { foreach ($cs as $c) { $c(...$a); } } }
/** Kør et action-hook og fang det, det skriver ud (som WordPress gør i <head>/footer). */
function kor($h, ...$a) { ob_start(); do_action($h, ...$a); return ob_get_clean(); }

function get_option($n, $d = false) { return $GLOBALS['OPT'][$n] ?? $d; }
function update_option($n, $v, $a = null) { $GLOBALS['OPT'][$n] = $v; return true; }
function delete_option($n) { unset($GLOBALS['OPT'][$n]); return true; }
function get_site_transient($n) { return false; }
function set_site_transient($n, $v, $t = 0) { return true; }
function get_transient($n) { return false; }
function set_transient($n, $v, $t = 0) { return true; }
function delete_transient($n) { return true; }

function home_url($p = '') { return $GLOBALS['HOME'] . $p; }
function get_permalink($id = 0) { return $GLOBALS['HOME'] . '/en-side/'; }
function trailingslashit($s) { return rtrim((string) $s, '/\\') . '/'; }
function untrailingslashit($s) { return rtrim((string) $s, '/\\'); }
function wp_parse_url($u, $c = -1) { return $c === -1 ? parse_url($u) : parse_url($u, $c); }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function is_preview() { return false; }
function is_singular($t = '') { return $GLOBALS['SINGULAR'] ?? true; }
function get_queried_object_id() { return $GLOBALS['SIDE_ID'] ?? 1; }
function current_user_can($c) { return !empty($GLOBALS['ADMIN']); }
function user_can($u, $c) { return !empty($GLOBALS['ADMIN']); }
function get_post_type($id) { return $GLOBALS['POST_TYPE'][$id] ?? 'page'; }
function wp_get_post_parent_id($id) { return $GLOBALS['FORAELDER'][$id] ?? 0; }
function is_author() { return !empty($GLOBALS['FORFATTERSIDE']); }
function status_header($s) { $GLOBALS['STATUS'] = $s; }
function nocache_headers() {}
class Proeve_Query { public $er_404 = false; function set_404() { $this->er_404 = true; } }
$GLOBALS['wp_query'] = new Proeve_Query();
function get_bloginfo($n = '') { return $n === 'language' ? 'da-DK' : ($n === 'name' ? 'TestNu' : ''); }
function get_post_modified_time($f = 'U', $gmt = false, $id = 0) { return '2026-09-05T07:26:34+00:00'; }
function wp_get_attachment_caption($id) { return $GLOBALS['BILLED_TEKST'][$id] ?? ''; }
function wp_get_attachment_image_src($id, $size = 'thumbnail') {
    $m = $GLOBALS['BILLED_META'][$id] ?? null;
    return $m ? [$GLOBALS['HOME'] . '/wp-content/uploads/' . $m['file'], $m['width'], $m['height'], false] : false;
}
function get_post_thumbnail_id($id = 0) { return $GLOBALS['THUMB'][$id] ?? 0; }
function get_post_field($f, $id) { return $GLOBALS['POST_FIELD']["$id:$f"] ?? ''; }
function get_post_time($f, $gmt = false, $id = 0) { return '2026-11-10T12:00:00+00:00'; }
function remove_menu_page($s) { $GLOBALS['FJERNET_MENU'][] = $s; }
function add_management_page(...$a) { $GLOBALS['MENU'][] = $a; return 'tools_page_asite'; }
function wp_nonce_field(...$a) {}
function wp_nonce_url($u, $a) { return $u; }
function check_admin_referer($a) { return true; }
function wp_safe_redirect($u, $s = 302) { $GLOBALS['REDIRECT'] = [$u, $s]; }
function wp_redirect($u, $s = 302, $x = '') { $GLOBALS['REDIRECT'] = [$u, $s]; throw new RuntimeException('redirect'); }
function wp_die($m = '') { throw new RuntimeException('die: ' . $m); }
function get_current_user_id() { return 1; }
function wp_clear_scheduled_hook($h) {}
function size_format($b) { return $b . ' B'; }
function submit_button($t = '') { echo '<button>' . $t . '</button>'; }
function checked($a, $b = true, $echo = true) { $s = (string) $a === (string) $b || ($a && $b) ? ' checked' : ''; if ($echo) { echo $s; } return $s; }
function selected($a, $b = true, $echo = true) { $s = (string) $a === (string) $b ? ' selected' : ''; if ($echo) { echo $s; } return $s; }
function add_query_arg($a, $u = '') { return $u; }
function admin_url($p = '') { return $GLOBALS['HOME'] . '/wp-admin/' . $p; }
function plugin_dir_path($f) { return dirname($f) . '/'; }
function is_wp_error($x) { return $x instanceof WP_Error; }
class WP_Error { public function get_error_message() { return 'fejl'; } public function get_error_data() { return null; } }
function wp_remote_get($u, $a = []) { return new WP_Error(); }
function wp_remote_retrieve_response_code($r) { return 0; }
function wp_remote_retrieve_body($r) { return ''; }
function register_activation_hook($f, $c) {}
function register_deactivation_hook($f, $c) {}

/* escaping / sanitering */
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return (string) $s; }
function esc_url_raw($s) { return (string) $s; }
function esc_textarea($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function wp_kses_post($s) { return (string) $s; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f | JSON_UNESCAPED_SLASHES); }
function wp_unslash($v) { return is_array($v) ? array_map('wp_unslash', $v) : stripslashes((string) $v); }
function sanitize_text_field($s) { return trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags((string) $s))); }
function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
function sanitize_key($s) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $s)); }
function wp_strip_all_tags($s) { return strip_tags((string) $s); }
function is_email($m) { return (bool) filter_var($m, FILTER_VALIDATE_EMAIL); }

/* post_meta */
function get_post_meta($id, $k, $single = false) { return $GLOBALS['META']["$id:$k"] ?? ''; }
function update_post_meta($id, $k, $v) { $GLOBALS['META']["$id:$k"] = $v; return true; }
function add_post_meta($id, $k, $v, $unique = false) {
    if ($unique && isset($GLOBALS['META']["$id:$k"])) { return false; }
    $GLOBALS['META']["$id:$k"] = $v;
    return true;
}

/* billeder: kernen beregner srcset og mål ud fra mediebibliotekets metadata */
function wp_get_upload_dir() { return ['basedir' => $GLOBALS['UPLOADS'] ?? '/tmp', 'baseurl' => $GLOBALS['HOME'] . '/wp-content/uploads']; }
function attachment_url_to_postid($url) { return isset($GLOBALS['BILLEDER'][$url]) ? $GLOBALS['BILLEDER'][$url] : 0; }
function wp_get_attachment_metadata($id) { return $GLOBALS['BILLED_META'][$id] ?? false; }
function wp_image_src_get_dimensions($src, $meta, $id) {
    return empty($meta['width']) ? false : [$meta['width'], $meta['height']];
}
function wp_image_add_srcset_and_sizes($tag, $meta, $id) {
    if (preg_match('/\ssrcset\s*=/i', $tag) || empty($meta['width'])) { return $tag; }
    $srcset = $GLOBALS['SRCSET'][$id] ?? 'foto-800.avif 800w, foto.avif 2000w';
    $sizes = sprintf('(max-width: %1$dpx) 100vw, %1$dpx', $meta['width']);
    $sizes = apply_filters('wp_calculate_image_sizes', $sizes, [$meta['width'], $meta['height']], $tag, $meta, $id);
    return preg_replace('/<img\b/i', '<img srcset="' . $srcset . '" sizes="' . $sizes . '"', $tag, 1);
}

/* billedlister: ét databaseopslag for hele siden (mediebibliotekets _wp_attached_file → id) */
class Proeve_WPDB {
    public $postmeta = 'wp_postmeta';
    public function prepare($q, ...$a) { return "'" . addslashes((string) $a[0]) . "'"; }
    public function get_results($sql) {
        $ud = [];
        foreach ($GLOBALS['BILLEDER'] as $url => $id) {
            $rel = substr($url, strlen($GLOBALS['HOME'] . '/wp-content/uploads/'));
            if (strpos($sql, "'" . addslashes($rel) . "'") !== false) { $ud[] = (object) ['post_id' => $id, 'meta_value' => $rel]; }
        }
        return $ud;
    }
    public function get_col($sql) { return []; }
    public function get_var($sql) { foreach ($GLOBALS['BILLEDER'] as $url => $id) { if (strpos($sql, basename($url)) !== false) { return $id; } } return null; }
    public function esc_like($s) { return addcslashes((string) $s, '_%\\'); }
}
$GLOBALS['wpdb'] = new Proeve_WPDB();
function update_postmeta_cache($ids) { return true; }
function wp_get_attachment_image_url($id, $size = 'thumbnail') {
    $m = $GLOBALS['BILLED_META'][$id] ?? null;
    if (!$m) { return false; }
    $navn = preg_replace('/\.(\w+)$/', '-1024x512.$1', $m['file']);
    return $GLOBALS['HOME'] . '/wp-content/uploads/' . $navn . ($size !== 'large' ? '?str=' . $size : '');
}
/** Kør HTML'en gennem de output-buffere, template_redirect har startet – præcis som på et rigtigt site. */
function gennem_bufferne(string $html): string {
    ob_start();                              // opsamleren: her lander resultatet
    $niveau = ob_get_level();
    do_action('template_redirect');          // pluginets/mu-plugins' egne ob_start(callback)
    echo $html;
    // ob_end_flush kører hver buffers callback og sender resultatet ét niveau ud – den inderste (startet sidst) først,
    // som når PHP afslutter en side. (ob_get_clean ville give det RÅ indhold uden callbacken.)
    while (ob_get_level() > $niveau) { ob_end_flush(); }
    return ob_get_clean();
}

// SEO-tags (class-meta.php, trin 2) – en side er $GLOBALS['POSTS'][id] = [titel, dato, ændret, indhold, status].
function get_the_title($id = 0) { return $GLOBALS['POSTS'][$id]['titel'] ?? ''; }
function get_locale() { return 'da_DK'; }
function get_post($id = 0) { $p = $GLOBALS['POSTS'][$id] ?? []; return (object) ['ID' => $id, 'post_date' => $p['dato'] ?? '2026-09-01 10:00:00',
    'post_modified' => $p['aendret'] ?? '2026-09-01 10:00:00', 'post_type' => 'page', 'post_content' => $p['indhold'] ?? '']; }
function mysql2date($f, $d, $t = true) { return (new DateTime($d, new DateTimeZone('Europe/Copenhagen')))->format($f); }
function post_password_required($p = null) { return false; }
function get_post_status($id = 0) { return $GLOBALS['POSTS'][$id]['status'] ?? 'publish'; }
function wp_attachment_is_image($id) { return isset($GLOBALS['BILLED_META'][$id]); }
function get_post_mime_type($id) { return 'image/avif'; }
function wp_date($f, $t = null) { return date($f); }
function get_the_excerpt($id = 0) { return ''; }
function delete_post_meta($id, $k, $v = '') { unset($GLOBALS['META']["$id:$k"]); return true; }
function get_post_ancestors($id) { $ud = []; while ($f = ($GLOBALS['FORAELDER'][$id] ?? 0)) { $ud[] = $f; $id = $f; } return $ud; }
// Sitemap (class-sitemap.php): sider i $GLOBALS['POSTS'] med 'gmt' (post_modified_gmt); rækkefølge efter ændring, nyeste først.
function get_post_types($a = [], $o = 'names') { return ['post' => 'post', 'page' => 'page', 'attachment' => 'attachment']; }
function get_posts($a = []) {
    $ider = array_keys(array_filter($GLOBALS['POSTS'] ?? [], fn($p) => ($p['type'] ?? 'page') === ($a['post_type'] ?? 'page') && ($p['status'] ?? 'publish') === 'publish' && isset($p['gmt'])));
    usort($ider, fn($x, $y) => strcmp($GLOBALS['POSTS'][$y]['gmt'], $GLOBALS['POSTS'][$x]['gmt']));
    return $ider;
}
