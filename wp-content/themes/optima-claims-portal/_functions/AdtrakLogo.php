<?php
/* ========================================================================================================================

Get Adtrak Logo.

Acceepts:

======================================================================================================================== */
function get_adtrak_logo_new($option = null, $marketing = false) {
    if ($marketing == true) {
        $end = '-marketing.svg';
    } else {
        $end = '-web.svg';
    }

    switch ($option) {
        case 'white':
            return '<img class="lazyload" data-src="' . get_theme_file_uri('/_assets/images/adtrak-white-badge' . $end) . '" alt="Adtrak Logo">';
            break;
        case 'breeez-navy':
            return '<img class="lazyload" data-src="' . get_theme_file_uri('/_assets/images/breeez-navy-logo.svg') . '" alt="Breeez Logo">';
            break;
        case 'breeez-white':
            return '<img class="lazyload" data-src="' . get_theme_file_uri('/_assets/images/breeez-white-logo.svg') . '" alt="Breeez Logo">';
            break;
        default:
            return '<img class="lazyload" data-src="' . get_theme_file_uri('/_assets/images/adtrak-navy-badge' . $end) . '" alt="Adtrak Logo">';
            break;
    }
}
?>