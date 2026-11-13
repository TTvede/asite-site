# Asite Site

Det, Breakdance ikke kan selv – på et site, kunden kan overtage. Pluginet afløser husmodellens mu-plugins
(`hm-*.php`), så et husmodel-site kan køre på **ren WordPress + Breakdance** uden vores server, vores scripts og
vores mu-plugins. Kun Asite-plugins følger med: Asite Cache og dette.

Trin 1 af planen i `~/projects/husmodel/docs/PLAN-ASITE-SITE-2026-11-10.md` (ejeren 10-11-2026).

## Hvad den gør

Alt styres fra **Værktøjer → Asite Site**. Intet kræver kode i sitets filer, og intet er bundet til vores server.

| Område | Hvad |
|---|---|
| Billeder | `srcset`, `width`/`height` og `alt` fra mediebiblioteket på alle billeder (Breakdance skriver dem uden mål, og udelader `alt` helt, når mediebibliotekets alt-tekst er tom). Loft for `sizes` + en tabel pr. komponentklasse, så browseren ikke henter varianter, der er langt bredere end pladsen. AVIF-understørrelser i q50. |
| Sidens finish | Hero uden lazy load (`<præfiks>-lcp` får også `fetchpriority="high"`), SVG-ikoner lagt inline (kan farves med CSS), scenelag får billedets filnavn som klasse, tomme valgfrie felter tømmes helt, så CSS `:empty` rammer, og WordPress' `global-styles` fjernes fra footeren. |
| SEO-tags | Title, description, robots, canonical, Open Graph og Twitter – præcis som Rank Math (bevist med `hm seo_facit skygge`: 0 forskelle). Boks på hver side med snippet preview, og **Værktøjer → SEO-oversigt** med alle sider og fund (dubletter, længder). Slås til med »SEO-tags: Asite Site«, når Rank Math er fra; indtil da skriver boksen også i Rank Maths felter. |
| Schema | FAQPage fra sidens eget Breakdance-træ (samme data, som siden viser – også efter kundens rettelser). Alternativt firmanavn og en navngiven forfatter på artikler i Rank Maths schema. |
| Omdirigeringer | 301 fra udviklingsadressen til domænet, når sitet er lanceret (før lanceringen er den byggepladsen og røres ikke). Gamle adresser → nye sider. |
| robots.txt | AI-søgning og -svar ja, ren træning nej. Kan vendes. |
| Roller | Breakdances skabeloner, header, footer, blokke og popups kun for administratorer – kunden (redaktør) retter sider og medier. SVG-upload kun for administratorer. |
| Sporing | Google-tag med Consent Mode v2 (samtykke først, ét tagbibliotek, hentet efter sidens load), konverteringer ved formular/telefon/mail, Meta-pixel efter marketing-samtykke, klik-id og UTM på hvert lead. **Slået fra**, indtil der står et id. |

Det **visuelle** (farver, skrifter, grundlags-CSS) hører ikke til her: det ligger i Breakdance selv – variabler,
globale indstillinger og `custom_code.header_code` – så sitet ser rigtigt ud uden vores kode.

## Installation på et site

1. Installér pluginet (zip fra seneste release) og aktivér.
2. **Værktøjer → Asite Site**: udfyld klassepræfiks, udviklingsadresse, `sizes`-tabel, tomme klasser, gamle adresser
   (og sporing, hvis sitet skal måles). Aktiveringen gætter præfiks og udviklingsadresse, hvis husmodellens
   mu-plugins stadig ligger på sitet.
3. Så længe `hm-*.php` ligger i `wp-content/mu-plugins/`, er pluginet **i dvale** (en note i wp-admin siger det):
   de to må ikke gøre arbejdet samtidig. Fjern mu-plugins – så tager pluginet over.
4. Tjek: en side har `srcset` og `width`/`height`, heroen har `fetchpriority="high"`, `robots.txt` har AI-reglerne,
   en FAQ-side har `FAQPage` i kilden, og en gammel adresse sender videre.

## Udvikling

`./test.sh` er prøvekørslen – **kør den før hver commit**. Den tjekker syntaks, logikken (77 prøver uden database)
og den vigtigste ting: at pluginet giver **præcis samme HTML** som husmodellens `hm-billedattr.php` +
`hm-grundlag.php` på samme side. Den bygger også release-zippen.

Et site kan udvide hvert område med et filter uden at rette i pluginet: `asite_site/html`,
`asite_site/tomme_klasser`, `asite_site/sizes_regler`, `asite_site/gamle_adresser`, `asite_site/robots_traening`,
`asite_site/lead`.

## Ny version

1. Hæv `Version` og `ASITE_SITE_VERSION` i `asite-site/asite-site.php`.
2. `./test.sh` → `./byg.sh` → `dist/asite-site.zip`.
3. GitHub-release med tag `vX.Y.Z` og `asite-site.zip` vedhæftet. Sitene viser opdateringen i wp-admin inden for 6 timer.

Privat repo: sæt `define('ASITE_SITE_GITHUB_TOKEN', '…');` i `wp-config.php` på hvert site (fine-grained token med
læseadgang til repoet).
