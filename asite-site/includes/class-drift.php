<?php
/**
 * Drift: mailafsender og revisionsloft.
 *
 * HVORFOR (terrasse.nu 23-11-2026): det gamle site havde to mu-plugins, som ikke fulgte med til husmodellen –
 *  - terrassenu-mail-from: WordPress' egne mails (formularsvar, notifikationer) kom fra info@terrasse.nu, ikke det
 *    generiske wordpress@terrasse.nu (en rigtig, DKIM/SPF-afstemt postkasse – færre i spam). Efter lanceringen kom
 *    kontaktformularernes mails igen fra wordpress@terrasse.nu.
 *  - terrassenu-revision-limit: 5 revisioner pr. side. Hver Breakdance-revision bærer hele sidetræet, og uden loft
 *    voksede postmeta til ~200 MB. Efter lanceringen: 116 revisioner, op til 78 på én side, postmeta 130 MB.
 *
 * Alt er slået fra som standard (tomt / -1), så et site uden indstillingerne opfører sig som før.
 * Mailafsenderen erstatter KUN WordPress' egen standard (wordpress@…/»WordPress«): en mail, der selv sætter From
 * (fx terrasse.nu's prisberegner), røres ikke.
 */
if (!defined('ABSPATH')) { exit; }

class Asite_Site_Drift {

    public static function init() {
        $mail = trim((string) Asite_Site::i('mail_afsender', ''));
        if ($mail && is_email($mail)) {
            add_filter('wp_mail_from', function ($fra) use ($mail) {
                return self::er_standardafsender($fra) ? $mail : $fra;
            }, 20);
        }
        $navn = trim((string) Asite_Site::i('mail_afsender_navn', ''));
        if ($navn !== '') {
            add_filter('wp_mail_from_name', function ($n) use ($navn) {
                return ($n === '' || $n === 'WordPress') ? $navn : $n;
            }, 20);
        }
        $loft = (int) Asite_Site::i('revisioner', -1);
        if ($loft >= 0) {
            add_filter('wp_revisions_to_keep', function () use ($loft) { return $loft; }, 20);
        }
    }

    /** WordPress' egen afsender: wordpress@<domæne> (wp_mail sætter den, når mailen ikke selv har en From). */
    public static function er_standardafsender($fra): bool {
        return stripos((string) $fra, 'wordpress@') === 0;
    }
}
