<?php
/**
 * Asite Site – Rank Maths skabelonfunktioner, når Rank Math er slået fra (seo: asite).
 *
 * Indlæses KUN fra plugins_loaded, når Asite_Site_Meta::aktiv() (Rank Math ikke indlæst i denne forespørgsel),
 * så navnene aldrig støder sammen med Rank Maths egne. Breakdances Breadcrumbs-element og evt. skabeloner, der
 * kalder rank_math_the_breadcrumbs(), virker dermed uændret efter skiftet.
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('rank_math_get_breadcrumbs')) {
    function rank_math_get_breadcrumbs($args = []) {
        return Asite_Site_Brodkrummer::html(is_array($args) ? $args : []);
    }
}

if (!function_exists('rank_math_the_breadcrumbs')) {
    function rank_math_the_breadcrumbs($args = []) {
        echo wp_kses_post(rank_math_get_breadcrumbs($args));
    }
}
