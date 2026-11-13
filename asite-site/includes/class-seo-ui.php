<?php
/**
 * Asite Site – SEO i wp-admin: en boks på hver side og en oversigt over alle sider.
 *
 * HVORFOR (ejeren 12-11-2026): et site, kunden overtager, skal kunne rettes uden vores scripts – også title og
 * description. Rank Maths UI er mest score og lamper; her er kun det, der ændrer, hvad Google viser: title,
 * description, noindex, canonical og delingsbillede – plus en oversigt, der finder dubletter og for lange tekster
 * (den slags fejl, et SEO-værktøj fandt på terrasse.nu 12-11-2026).
 *
 * DATA – ét princip, så Rank Math og Asite Site aldrig er uenige:
 *  - Boksen og oversigten skriver i BEGGE sæt felter (_asite_seo_* og rank_math_*). Rank Math kan altid slås til igen.
 *  - Skriver noget andet i rank_math_* (Rank Maths egen boks, hm spec_til_bd), slettes Asite Sites kopi af feltet.
 *    Så er kopien enten lig Rank Maths værdi eller tom – aldrig en tredje sandhed.
 */
if (!defined('ABSPATH')) { exit; }

class Asite_Site_Seo_Ui {

    /** Asite-felt => Rank Math-felt. */
    const PAR = [
        Asite_Site_Meta::TITEL => 'rank_math_title',
        Asite_Site_Meta::BESKRIVELSE => 'rank_math_description',
        Asite_Site_Meta::ROBOTS => 'rank_math_robots',
        Asite_Site_Meta::CANONICAL => 'rank_math_canonical_url',
        Asite_Site_Meta::BILLEDE => 'rank_math_facebook_image_id',
    ];

    /** Sat, mens vi selv skriver – så vores egne rank_math_*-skrivninger ikke sletter kopien. */
    private static $skriver = false;

    public static function init() {
        add_action('added_post_meta', [__CLASS__, 'rank_math_rettet'], 10, 3);
        add_action('updated_post_meta', [__CLASS__, 'rank_math_rettet'], 10, 3);
        add_action('deleted_post_meta', [__CLASS__, 'rank_math_rettet'], 10, 3);
        if (!is_admin()) { return; }
        add_action('add_meta_boxes', [__CLASS__, 'boks_til']);
        add_action('save_post', [__CLASS__, 'gem_boks'], 10, 2);
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_asite_site_seo', [__CLASS__, 'gem_oversigt']);
    }

    public static function rank_math_rettet($mid, $post_id, $noegle) {
        if (self::$skriver) { return; }
        $eget = array_search($noegle, self::PAR, true);
        if ($eget !== false) { delete_post_meta((int) $post_id, $eget); }
    }

    private static function typer(): array {
        return array_values(array_filter(get_post_types(['public' => true]), fn($t) => $t !== 'attachment'));
    }

    // ---------- skrivning (fælles for boks og oversigt) ----------

    /** Gem én sides SEO-felter. $v: titel, beskrivelse, robots ('', noindex, noindex,nofollow), canonical, billede. */
    public static function gem_felter(int $id, array $v) {
        self::$skriver = true;
        foreach (['titel' => Asite_Site_Meta::TITEL, 'beskrivelse' => Asite_Site_Meta::BESKRIVELSE] as $k => $eget) {
            if (!array_key_exists($k, $v)) { continue; }
            $x = trim(sanitize_text_field((string) $v[$k]));
            self::saet($id, $eget, $x, $x);
        }
        if (array_key_exists('robots', $v)) {
            $r = in_array($v['robots'], ['noindex', 'noindex,nofollow'], true) ? $v['robots'] : '';
            $rm = $r === '' ? ['index'] : explode(',', $r);
            if ($r === '' && !get_post_meta($id, 'rank_math_robots', true)) { $rm = ''; }   // rør ikke en side uden robots
            self::saet($id, Asite_Site_Meta::ROBOTS, $r, $rm);
        }
        if (array_key_exists('canonical', $v)) {
            $c = esc_url_raw(trim((string) $v['canonical']));
            if ($c === get_permalink($id)) { $c = ''; }   // sidens egen adresse = standarden; gem den ikke
            self::saet($id, Asite_Site_Meta::CANONICAL, $c, $c);
        }
        if (array_key_exists('billede', $v)) {
            $b = (int) $v['billede'];
            if ($b && !wp_attachment_is_image($b)) { $b = 0; }
            self::saet($id, Asite_Site_Meta::BILLEDE, $b ?: '', $b ?: '');
            if ($b) { update_post_meta($id, 'rank_math_facebook_image', wp_get_attachment_url($b)); }
            else { delete_post_meta($id, 'rank_math_facebook_image'); }
        }
        self::$skriver = false;
        if (class_exists('Asite_Cache') && method_exists('Asite_Cache', 'purge_post')) { Asite_Cache::purge_post($id); }
    }

    private static function saet(int $id, string $eget, $vaerdi, $rm_vaerdi) {
        if ($vaerdi === '' || $vaerdi === []) { delete_post_meta($id, $eget); } else { update_post_meta($id, $eget, $vaerdi); }
        $rm = self::PAR[$eget];
        if ($rm_vaerdi === '' || $rm_vaerdi === []) { delete_post_meta($id, $rm); } else { update_post_meta($id, $rm, $rm_vaerdi); }
    }

    /** Sidens nuværende værdier, som boksen og oversigten viser dem (eget felt → Rank Maths). */
    public static function vaerdier(int $id): array {
        $f = function ($eget) use ($id) {
            $v = get_post_meta($id, $eget, true);
            return ($v !== '' && $v !== []) ? $v : get_post_meta($id, self::PAR[$eget], true);
        };
        $r = (array) $f(Asite_Site_Meta::ROBOTS);
        if (is_string($f(Asite_Site_Meta::ROBOTS))) { $r = explode(',', (string) $f(Asite_Site_Meta::ROBOTS)); }
        $robots = in_array('noindex', $r, true) ? (in_array('nofollow', $r, true) ? 'noindex,nofollow' : 'noindex') : '';
        return [
            'titel' => trim((string) $f(Asite_Site_Meta::TITEL)),
            'beskrivelse' => trim((string) $f(Asite_Site_Meta::BESKRIVELSE)),
            'robots' => $robots,
            'canonical' => trim((string) $f(Asite_Site_Meta::CANONICAL)),
            'billede' => (int) $f(Asite_Site_Meta::BILLEDE),
        ];
    }

    // ---------- boksen på siden ----------

    public static function boks_til() {
        foreach (self::typer() as $t) {
            add_meta_box('asite-seo', 'SEO (Asite Site)', [__CLASS__, 'boks'], $t, 'normal', 'high');
        }
    }

    public static function boks($post) {
        $id = (int) $post->ID;
        $v = self::vaerdier($id);
        $skabelon = Asite_Site_Meta::variabler((string) (get_option('rank-math-options-titles')['pt_' . $post->post_type . '_title'] ?? '%title% %sep% %sitename%'), $id);
        $auto_billede = Asite_Site_Meta::billede_id($id);
        wp_nonce_field('asite_seo_boks', 'asite_seo_nonce');
        // Værdierne, boksen viste: kun felter, brugeren har ændret, gemmes (se gem_boks).
        foreach ($v as $k => $x) {
            echo '<input type="hidden" name="asite_seo_opr[' . esc_attr($k) . ']" value="' . esc_attr($k === 'billede' ? ($x ? (string) $x : '') : (string) $x) . '">';
        }
        ?>
        <style>
            .asite-seo label{display:block;font-weight:600;margin:12px 0 4px}
            .asite-seo input[type=text],.asite-seo textarea{width:100%}
            .asite-seo .taeller{font-weight:400;color:#646970;float:right}
            .asite-seo .taeller.for-lang{color:#b32d2e}
            .asite-seo .snippet{border:1px solid #dcdcde;border-radius:8px;padding:12px 16px;max-width:600px;background:#fff;font-family:arial,sans-serif}
            .asite-seo .snippet .u{color:#202124;font-size:12px}
            .asite-seo .snippet .t{color:#1a0dab;font-size:20px;line-height:1.3;margin:4px 0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
            .asite-seo .snippet .d{color:#4d5156;font-size:14px;line-height:1.58;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
            .asite-seo details{margin-top:14px}
        </style>
        <div class="asite-seo">
            <p style="margin-top:0">Sådan kan siden se ud i Google:</p>
            <div class="snippet">
                <div class="u"><?php echo esc_html(preg_replace('#^https?://#', '', (string) get_permalink($id))); ?></div>
                <div class="t" data-standard="<?php echo esc_attr($skabelon); ?>"></div>
                <div class="d"></div>
            </div>
            <label for="asite_seo_titel">Title <span class="taeller" data-for="asite_seo_titel" data-maks="60"></span></label>
            <input type="text" id="asite_seo_titel" name="asite_seo[titel]" value="<?php echo esc_attr($v['titel']); ?>" placeholder="<?php echo esc_attr($skabelon); ?>">
            <p class="description">Overskriften i Google og i browserfanen. Ca. 50–60 tegn – længere bliver skåret af. Tom = <?php echo esc_html($skabelon); ?></p>
            <label for="asite_seo_beskrivelse">Description <span class="taeller" data-for="asite_seo_beskrivelse" data-maks="160"></span></label>
            <textarea id="asite_seo_beskrivelse" name="asite_seo[beskrivelse]" rows="3"><?php echo esc_textarea($v['beskrivelse']); ?></textarea>
            <p class="description">Teksten under overskriften i Google. Ca. 120–160 tegn, skrevet så man får lyst til at klikke.</p>
            <details<?php echo ($v['robots'] || $v['canonical'] || $v['billede']) ? ' open' : ''; ?>>
                <summary>Avanceret</summary>
                <label for="asite_seo_robots">Søgemaskiner</label>
                <select id="asite_seo_robots" name="asite_seo[robots]">
                    <option value="" <?php selected($v['robots'], ''); ?>>Vis siden i Google (index)</option>
                    <option value="noindex" <?php selected($v['robots'], 'noindex'); ?>>Skjul siden i Google (noindex)</option>
                    <option value="noindex,nofollow" <?php selected($v['robots'], 'noindex,nofollow'); ?>>Skjul siden, og følg ikke dens links (noindex, nofollow)</option>
                </select>
                <label for="asite_seo_canonical">Canonical</label>
                <input type="text" id="asite_seo_canonical" name="asite_seo[canonical]" value="<?php echo esc_attr($v['canonical']); ?>" placeholder="<?php echo esc_attr((string) get_permalink($id)); ?>">
                <p class="description">Kun hvis siden er en kopi af en anden side. Tom = sidens egen adresse (det rigtige næsten altid).</p>
                <label for="asite_seo_billede">Delingsbillede (id fra mediebiblioteket)</label>
                <input type="text" id="asite_seo_billede" name="asite_seo[billede]" value="<?php echo $v['billede'] ? (int) $v['billede'] : ''; ?>" placeholder="<?php echo $auto_billede ? esc_attr('automatisk: ' . basename((string) wp_get_attached_file($auto_billede))) : 'intet billede'; ?>" style="max-width:260px">
                <p class="description">Billedet, Facebook, LinkedIn og beskeder viser. Tom = sidens første billede.</p>
            </details>
        </div>
        <script>
        (function(){
            var b=document.querySelector('.asite-seo'); if(!b) return;
            var t=b.querySelector('#asite_seo_titel'), d=b.querySelector('#asite_seo_beskrivelse');
            var st=b.querySelector('.snippet .t'), sd=b.querySelector('.snippet .d');
            function opd(){
                st.textContent=t.value.trim()||st.getAttribute('data-standard');
                sd.textContent=d.value.trim()||'(ingen description – Google vælger selv et uddrag fra siden)';
                b.querySelectorAll('.taeller').forEach(function(x){
                    var f=document.getElementById(x.getAttribute('data-for')), n=f.value.trim().length, m=+x.getAttribute('data-maks');
                    x.textContent=n+' / '+m+' tegn'; x.classList.toggle('for-lang', n>m);
                });
            }
            t.addEventListener('input',opd); d.addEventListener('input',opd); opd();
        })();
        </script>
        <?php
    }

    public static function gem_boks($id, $post) {
        if (!isset($_POST['asite_seo_nonce']) || !wp_verify_nonce($_POST['asite_seo_nonce'], 'asite_seo_boks')) { return; }
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($id) || !current_user_can('edit_post', $id)) { return; }
        $v = wp_unslash((array) ($_POST['asite_seo'] ?? []));
        $opr = wp_unslash((array) ($_POST['asite_seo_opr'] ?? []));
        // Kun de felter, brugeren har ændret i DENNE boks. Rank Maths boks sender sine felter i samme forespørgsel;
        // ellers ville en rettelse dér blive overskrevet af vores boks' gamle værdi.
        $ny = [];
        foreach (['titel', 'beskrivelse', 'robots', 'canonical', 'billede'] as $k) {
            if (array_key_exists($k, $v) && trim((string) $v[$k]) !== trim((string) ($opr[$k] ?? ''))) { $ny[$k] = $v[$k]; }
        }
        if ($ny) { self::gem_felter((int) $id, $ny); }
    }

    // ---------- oversigten ----------

    public static function menu() {
        add_management_page('SEO-oversigt', 'SEO-oversigt', 'edit_pages', 'asite-site-seo', [__CLASS__, 'oversigt']);
    }

    /** Alle offentliggjorte sider med værdier og fund (dubletter, længder, mangler). */
    public static function raekker(): array {
        $ider = get_posts(['post_type' => self::typer(), 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids',
                           'orderby' => 'menu_order title', 'order' => 'ASC']);
        $r = [];
        foreach ($ider as $id) {
            $v = self::vaerdier((int) $id);
            $r[] = ['id' => (int) $id, 'sti' => (string) wp_parse_url(get_permalink($id), PHP_URL_PATH),
                    'vist_titel' => Asite_Site_Meta::titel((int) $id), 'vist_beskrivelse' => Asite_Site_Meta::beskrivelse((int) $id)] + $v;
        }
        usort($r, fn($a, $b) => strcmp($a['sti'], $b['sti']));
        $tael = fn($felt) => array_count_values(array_filter(array_map(fn($x) => mb_strtolower($x[$felt]), array_filter($r, fn($x) => $x['robots'] === '')))) ;
        $tt = $tael('vist_titel');
        $tb = $tael('vist_beskrivelse');
        foreach ($r as &$x) {
            $f = [];
            $lt = mb_strlen($x['vist_titel']);
            $lb = mb_strlen($x['vist_beskrivelse']);
            if ($x['robots'] === '') {
                if (($tt[mb_strtolower($x['vist_titel'])] ?? 0) > 1) { $f[] = 'samme title som en anden side'; }
                if ($x['vist_beskrivelse'] !== '' && ($tb[mb_strtolower($x['vist_beskrivelse'])] ?? 0) > 1) { $f[] = 'samme description som en anden side'; }
                if ($x['vist_beskrivelse'] === '') { $f[] = 'ingen description'; }
            }
            if ($lt > 60) { $f[] = "title er $lt tegn (over 60)"; }
            if ($lt < 25) { $f[] = "title er kun $lt tegn"; }
            if ($lb > 160) { $f[] = "description er $lb tegn (over 160)"; }
            if ($lb && $lb < 70) { $f[] = "description er kun $lb tegn"; }
            $x['fund'] = $f;
        }
        return $r;
    }

    public static function oversigt() {
        if (!current_user_can('edit_pages')) { return; }
        $r = self::raekker();
        $fund = count(array_filter($r, fn($x) => $x['fund']));
        $k = 'asite_site_seo_msg_' . get_current_user_id();
        $besked = get_transient($k);
        if ($besked) { delete_transient($k); }
        ?>
        <div class="wrap asite-seo-oversigt">
            <h1>SEO-oversigt</h1>
            <?php if ($besked): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($besked); ?></p></div><?php endif; ?>
            <p><?php echo count($r); ?> sider. <?php echo $fund ? "<strong>$fund med noget at se på</strong> (markeret med gult)." : 'Ingen fund.'; ?>
               <?php echo Asite_Site_Meta::rank_math() ? 'Rank Math skriver tags lige nu – rettelser her gemmes også i Rank Maths felter, så de vises med det samme.' : ''; ?></p>
            <p><label><input type="checkbox" id="kun-fund"> Vis kun sider med fund</label></p>
            <style>
                .asite-seo-oversigt table{border-collapse:collapse;width:100%;background:#fff}
                .asite-seo-oversigt th,.asite-seo-oversigt td{border-bottom:1px solid #dcdcde;padding:8px;vertical-align:top;text-align:left}
                .asite-seo-oversigt td.sti{width:18%;word-break:break-all}
                .asite-seo-oversigt input[type=text],.asite-seo-oversigt textarea{width:100%;font-size:13px}
                .asite-seo-oversigt tr.fund td{background:#fcf9e8}
                .asite-seo-oversigt tr.rettet td{background:#edfaef}
                .asite-seo-oversigt .n{color:#646970;font-size:12px}
                .asite-seo-oversigt .n.for-lang{color:#b32d2e}
                .asite-seo-oversigt .f{color:#8a6100;font-size:12px;margin-top:4px}
                .asite-seo-oversigt .gem{position:sticky;bottom:0;background:#f0f0f1;padding:12px 0}
            </style>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="asite_site_seo">
                <?php wp_nonce_field('asite_site_seo'); ?>
                <table>
                    <thead><tr><th>Side</th><th style="width:30%">Title</th><th>Description</th><th style="width:9%">Google</th></tr></thead>
                    <tbody>
                    <?php foreach ($r as $x): $i = $x['id']; ?>
                        <tr class="<?php echo $x['fund'] ? 'fund' : ''; ?>" data-fund="<?php echo $x['fund'] ? 1 : 0; ?>">
                            <td class="sti"><a href="<?php echo esc_url((string) get_edit_post_link($i)); ?>"><?php echo esc_html($x['sti']); ?></a>
                                <?php foreach ($x['fund'] as $f): ?><div class="f">• <?php echo esc_html($f); ?></div><?php endforeach; ?></td>
                            <td><input type="text" name="s[<?php echo $i; ?>][titel]" value="<?php echo esc_attr($x['titel']); ?>" placeholder="<?php echo esc_attr($x['vist_titel']); ?>" data-maks="60" data-oprindelig="<?php echo esc_attr($x['titel']); ?>">
                                <div class="n"></div></td>
                            <td><textarea name="s[<?php echo $i; ?>][beskrivelse]" rows="2" data-maks="160" data-oprindelig="<?php echo esc_attr($x['beskrivelse']); ?>"><?php echo esc_textarea($x['beskrivelse']); ?></textarea>
                                <div class="n"></div></td>
                            <td><select name="s[<?php echo $i; ?>][robots]" data-oprindelig="<?php echo esc_attr($x['robots']); ?>">
                                <option value="" <?php selected($x['robots'], ''); ?>>Vis</option>
                                <option value="noindex" <?php selected($x['robots'], 'noindex'); ?>>Skjul</option>
                                <option value="noindex,nofollow" <?php selected($x['robots'], 'noindex,nofollow'); ?>>Skjul + nofollow</option>
                            </select></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="gem"><button class="button button-primary" type="submit">Gem ændringer</button> <span class="antal"></span></div>
            </form>
        </div>
        <script>
        (function(){
            var f=document.querySelector('.asite-seo-oversigt form'), ant=document.querySelector('.asite-seo-oversigt .antal');
            function tael(el){ var n=el.parentNode.querySelector('.n'); if(!n) return; var l=(el.value||el.placeholder).trim().length, m=+el.getAttribute('data-maks');
                n.textContent=l+' / '+m+' tegn'; n.classList.toggle('for-lang', l>m); }
            function markér(){ var c=0; f.querySelectorAll('tbody tr').forEach(function(tr){
                var r=false; tr.querySelectorAll('[data-oprindelig]').forEach(function(el){ if(el.value!==el.getAttribute('data-oprindelig')) r=true; });
                tr.classList.toggle('rettet', r); if(r) c++; });
                ant.textContent=c? c+' side'+(c>1?'r':'')+' rettet' : ''; }
            f.querySelectorAll('[data-maks]').forEach(function(el){ tael(el); el.addEventListener('input',function(){tael(el);markér();}); });
            f.querySelectorAll('select').forEach(function(el){ el.addEventListener('change',markér); });
            // Kun rettede rækker sendes – så en side, ingen har rørt, aldrig skrives (og 100 sider ikke rammer max_input_vars).
            f.addEventListener('submit',function(){ f.querySelectorAll('tbody tr:not(.rettet) [name]').forEach(function(el){ el.disabled=true; }); });
            document.getElementById('kun-fund').addEventListener('change',function(e){
                f.querySelectorAll('tbody tr').forEach(function(tr){ tr.style.display=(e.target.checked && tr.getAttribute('data-fund')==='0')?'none':''; }); });
        })();
        </script>
        <?php
    }

    public static function gem_oversigt() {
        if (!current_user_can('edit_pages')) { wp_die('Ingen adgang.'); }
        check_admin_referer('asite_site_seo');
        $n = 0;
        foreach ((array) wp_unslash($_POST['s'] ?? []) as $id => $v) {
            $id = (int) $id;
            if (!$id || !current_user_can('edit_post', $id) || !is_array($v)) { continue; }
            self::gem_felter($id, array_intersect_key($v, array_flip(['titel', 'beskrivelse', 'robots'])));
            $n++;
        }
        if ($n && class_exists('Asite_Cache')) { Asite_Cache::purge_all(); }
        set_transient('asite_site_seo_msg_' . get_current_user_id(), $n ? "$n side" . ($n > 1 ? 'r' : '') . ' gemt.' : 'Intet at gemme.', 60);
        wp_safe_redirect(admin_url('tools.php?page=asite-site-seo'));
        exit;
    }
}
