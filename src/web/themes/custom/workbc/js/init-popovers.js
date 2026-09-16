(function (Drupal, $, once) {
  ("use strict");

  // this set of functions is intended to cause popovers to close if the user clicks anywhere outside of them
  // see https://stackoverflow.com/a/69602400/495000
  const managePopoverClosure = function (context) {
    $(document).on('click', function (event) {
      const $target = $(event.target);
      // Do nothing if there was a click on popover content
      if ($target.hasClass('popover') || $target.closest('.popover').length) {
        return;
      }
      $('[data-bs-toggle="popover"]', context).each(function () {
        const $popover = $(this);
        if (
          !$popover.is(event.target) &&
          $popover.has(event.target).length === 0 &&
          $('.popover').has(event.target).length === 0
        ) {
          $popover.popover('hide');
        }
      });
    });

    $(document).on('keyup', function(event) {
      if (event.key == "Escape") {
        $('[data-bs-toggle="popover"]', context).each(function () {
          $(this).popover('hide');
        });
      }
      if (event.key == "Enter" && $(event.target).is('[data-bs-toggle="popover"]') && $(event.target).children('div.popover').length == 0) {
        $(event.target).popover('show');
      }
    });

    $(document).on('blur', '[data-bs-toggle="popover"]', function(event) {
      if (!event.relatedTarget || $(event.relatedTarget).parents('.popover').length == 0) {
        $(this).popover('hide');
      }
    });
  }

  Drupal.behaviors.initPopoverBehaviour = {
    attach: function (context, settings) {
      $(once('initPopoverBehaviour', '.info-tooltip', context)).each(function() {
        const $element = $(this);
        const role = $element.data('bs-content').includes('<a href') ? 'dialog' : 'tooltip';
        $(document).ready(function() {
          managePopoverClosure(context);
          $element.on('shown.bs.popover', function (event) {
            const $target = $(event.target);
            $('#tooltip-live-region').html($target.attr('data-bs-original-title') + $target.attr('data-bs-content'));
          }).on('hidden.bs.popover', function (event) {
            $('#tooltip-live-region').text('');
          }).popover({
            template: `<div class="popover" role="${role}"><div class="popover-arrow"></div><h3 class="popover-header"></h3><div class="popover-body"></div></div>`
          });
        });
      });
    },
  };

})(Drupal, jQuery, once);
