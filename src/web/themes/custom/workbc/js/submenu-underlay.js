(function ($, Drupal) {

    var underlay = $('#submenu-underlay');
    var showUnderlay = function() {
        underlay.show();
    };
    var hideUnderlay = function() {
        underlay.hide();
    };

    $('ul.nav-t1 > li.has-submenu').hover(showUnderlay, hideUnderlay);

})(jQuery, Drupal);