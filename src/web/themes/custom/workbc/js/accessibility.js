(function ($, Drupal, once) {
  ("use strict");

  Drupal.behaviors.accessibility = {
    attach: function (context, settings) {
      // Ignore Enter when focused on radiobuttons and checkboxes.
      const $inputs = $(once('a11yEnter', 'input[type="radio"], input[type="checkbox"]', context));
      $inputs.on('keypress keyup keydown', function(event) {
        if (event.key == "Enter") {
          return false;
        }
      });

      // Treat groups of checkboxes like radiobuttons:
      // - Tab/Shift-Tab moves to next/previous group instead of next/previous checkbox
      // - Up/Down arrow moves to next/previous checkbox within group
      const $checkboxes = $(once('a11yCheckboxes', '.form-checkboxes input[type="checkbox"]', context));
      $checkboxes.on('keydown', function(event) {
        switch (event.key) {
          case "ArrowUp":
            $(this).parent().prev('.form-check').children('input[type="checkbox"]').focus();
            return false;
          case "ArrowDown":
            $(this).parent().next('.form-check').children('input[type="checkbox"]').focus();
            return false;
          case "Tab":
            const $current = $(this);
            const $parents = $current.add($current.parentsUntil('form'));
            let $candidates;
            if (event.shiftKey) {
              $candidates = $.merge($current.parents('.form-item'), $parents.map(function() { return $(this).prevAll().get(); }));
            }
            else {
              $candidates = $parents.map(function() { return $(this).nextAll().get(); });
            }
            $candidates
              .has('.form-item')
              .first()
              .find(':focusable, summary')
              .first()
              .focus();
            return false;
        }
      });
    }
  }
})(jQuery, Drupal, once);
