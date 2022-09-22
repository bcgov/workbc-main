(function ($, Drupal) {

    let underlay = $('.submenu-underlay');
    let showUnderlay = function() {
        underlay.show();
    };
    let hideUnderlay = function() {
        underlay.hide();
    };

    $('ul.nav-t1 > li.has-submenu').hover(showUnderlay, hideUnderlay);

})(jQuery, Drupal);