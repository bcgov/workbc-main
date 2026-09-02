(function ($, Drupal, once) {
  ("use strict");

  Drupal.behaviors.accessibility = {
    attach: function (context, settings) {
      const $inputs = $(once('accessibility', 'input[type="radio"], input[type="checkbox"]', context));
      $inputs.on('keypress keyup keydown', function(event) {
        if (event.key == "Enter") {
          return false;
        }
      });
    }
  }
})(jQuery, Drupal, once);
