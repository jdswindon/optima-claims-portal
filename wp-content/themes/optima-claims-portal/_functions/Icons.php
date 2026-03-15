<?php
use Twig\TwigFunction;

/* ========================================================================================================================
  Icon Function (show icon from your icons SVG sprite)
======================================================================================================================== */
function icon($iconName, $classes = null) {
    echo '<svg class="icon icon-' . $iconName . ' ' . $classes . '"><use href="' . get_stylesheet_directory_uri() . '/_assets/images/icons-sprite.svg#icon-' . $iconName . '"></use></svg>';
}

// Add custom Twig functions to Timber
add_filter('timber/twig', function ($twig) {
    $twig->addFunction(new TwigFunction('icon', 'icon'));

    return $twig;
});
