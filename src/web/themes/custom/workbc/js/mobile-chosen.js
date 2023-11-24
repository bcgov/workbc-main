let allSelected = true;

(function ($, Drupal, once) {
	Drupal.behaviors.mobilechosen = {
    attach: function (context, drupalSettings) {

      once('mobilechosen', '#edit-occupational-interest', context).forEach(function() {
        if (drupalSettings.isMobile) {
          var select = document.getElementById('edit-occupational-interest'); 
          select.options[0].selected = true;
        }

      });
    }
  }
})(jQuery, Drupal, once);
