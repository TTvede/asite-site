#!/usr/bin/env bash
# Prøvekørsel af Asite Site – uden database og uden server. Kør den før hver commit.
#
#   ./test.sh [sti til husmodellens mu-plugins]     (standard: ~/projects/husmodel/mu-plugins)
#
# Den vigtigste prøve er nr. 3: PRÆCIS samme HTML gennem pluginet og gennem husmodellens hm-*.php skal give
# PRÆCIS samme resultat. Det er prøvestenen for, at et site kan flyttes over uden at se anderledes ud.
set -uo pipefail
cd "$(dirname "$0")"
MU="${1:-$HOME/projects/husmodel/mu-plugins}"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
FEJL=0
ok()   { echo "  ok    $1"; }
fejl() { echo "  FEJL  $1"; FEJL=$((FEJL+1)); }

echo "1. Syntaks"
for f in asite-site/*.php asite-site/includes/*.php test/*.php; do
  php -l "$f" >/dev/null 2>&1 && ok "php -l $(basename "$f")" || fejl "php -l $(basename "$f")"
done

echo "2. Logikken (indstillinger, robots, omdirigeringer, schema, roller, finish, sporing)"
if php test/proever.php asite-site > "$T/proever.txt" 2>&1; then
  sed -n 's/^ok   /  ok    /p' "$T/proever.txt" >/dev/null
  grep -c '^ok' "$T/proever.txt" | xargs -I{} echo "  ok    {} prøver bestået"
else
  grep '^FEJL' "$T/proever.txt" | while read -r _ n; do fejl "$n"; done
  FEJL=$((FEJL+1))
  tail -3 "$T/proever.txt"
fi

echo "3. Samme HTML som husmodellens mu-plugins (prøvestenen)"
if [ -f "$MU/hm-grundlag.php" ]; then
  php test/kor_asite.php asite-site > "$T/asite.html" 2>"$T/asite.err"
  php test/kor_hm.php "$MU"        > "$T/hm.html"    2>"$T/hm.err"
  if [ -s "$T/asite.err" ] || [ -s "$T/hm.err" ]; then
    fejl "kørslen gav fejl: $(head -c 300 "$T/asite.err" "$T/hm.err")"
  elif ! [ -s "$T/asite.html" ]; then
    fejl "pluginet gav ingen HTML"
  elif diff -q "$T/hm.html" "$T/asite.html" >/dev/null; then
    ok "pluginets HTML er identisk med hm-billedattr + hm-grundlag"
    # og den skal faktisk GØRE noget – ellers består to tomme resultater prøven
    grep -q 'fetchpriority="high"' "$T/asite.html" && grep -q '<svg aria-hidden' "$T/asite.html" \
      && grep -q 'srcset=' "$T/asite.html" && ok "…og den har gjort arbejdet (hero, srcset, inline-SVG)" \
      || fejl "HTML'en er ubehandlet – prøven beviser ingenting"
  else
    fejl "pluginet giver ANDEN HTML end mu-plugins:"
    diff "$T/hm.html" "$T/asite.html" | head -20
  fi
else
  echo "  – husmodellens mu-plugins blev ikke fundet i $MU (sammenligningen sprunget over)"
fi

echo "4. Overgangsvagten: pluginet må ikke køre oven i husmodellens mu-plugins"
cat > "$T/overgang.php" <<'PHP'
<?php
require $argv[2];   // attrapperne (absolut sti)
define('HM_PREFIKS', 'fa');            // 00-hm-site.php er på sitet: mu-plugins gør arbejdet
define('ASITE_SITE_VERSION', 'proeve');
require $argv[1] . '/includes/class-core.php';
require $argv[1] . '/includes/class-html.php';
require $argv[1] . '/includes/class-seo.php';
require $argv[1] . '/includes/class-lancering.php';
require $argv[1] . '/includes/class-roller.php';
require $argv[1] . '/includes/class-sporing.php';
require $argv[1] . '/includes/class-admin.php';
require $argv[1] . '/includes/class-updater.php';
echo Asite_Site::i_overgang() ? "dvale\n" : "aktiv\n";
// Sådan starter hovedfilen modulerne: kun admin og opdatering, når mu-plugins er på sitet.
Asite_Site_Admin::init();
Asite_Site_Updater::init();
if (!Asite_Site::i_overgang()) { Asite_Site_Html::init(); Asite_Site_Seo::init(); Asite_Site_Lancering::init(); }
echo empty($GLOBALS['H']['template_redirect']) ? "ingen output-buffer\n" : "BUFFER SAT\n";
echo empty($GLOBALS['H']['robots_txt']) ? "ingen robots\n" : "ROBOTS SAT\n";
echo empty($GLOBALS['H']['wp_head']) ? "intet i head\n" : "HEAD SAT\n";
echo empty($GLOBALS['H']['admin_menu']) ? "INGEN MENU\n" : "indstillingsside findes\n";
PHP
ud=$(php "$T/overgang.php" "$PWD/asite-site" "$PWD/test/wp-stub.php" 2>&1)
grep -q '^dvale' <<<"$ud" && ok "HM_PREFIKS fundet → pluginet går i dvale" || fejl "dvalen slår ikke til"
grep -q 'ingen output-buffer' <<<"$ud" && grep -q 'ingen robots' <<<"$ud" && grep -q 'intet i head' <<<"$ud" \
  && ok "intet dobbelt: ingen buffer, ingen robots.txt, intet i <head>" || fejl "pluginet rører sitet i overgangen: $ud"
grep -q 'indstillingsside findes' <<<"$ud" && ok "indstillingerne kan udfyldes FØR mu-plugins fjernes" || fejl "ingen indstillingsside i overgangen"

echo "5. Release-zippen kan bygges"
if ./byg.sh >/dev/null 2>&1 && [ -f dist/asite-site.zip ]; then
  python3 - <<'PY' && ok "dist/asite-site.zip med mappen asite-site/ i roden" || fejl "zippens indhold"
import zipfile, sys
n = zipfile.ZipFile('dist/asite-site.zip').namelist()
sys.exit(0 if 'asite-site/asite-site.php' in n and 'asite-site/includes/class-core.php' in n
         and not any(x.startswith('test/') for x in n) else 1)
PY
else
  fejl "byg.sh"
fi

echo
[ "$FEJL" = 0 ] && echo "Prøvekørsel: alt ok" || { echo "Prøvekørsel: $FEJL FEJL"; exit 1; }
