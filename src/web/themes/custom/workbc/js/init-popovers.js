(function (Drupal, $, once) {
  ("use strict");

  // this set of functions is intended to cause popovers to close if the user clicks anywhere outside of them
  // see https://stackoverflow.com/a/69602400/495000
  let managePopoverClosure = function () {
    $(document).on('click', function (e) {
      var $popover,
          $target = $(e.target);
      //do nothing if there was a click on popover content
      if ($target.hasClass('popover') || $target.closest('.popover').length) {
          return;
      }
      $('[data-bs-toggle="popover"]').each(function () {
          $popover = $(this);
  
          if (!$popover.is(e.target) &&
              $popover.has(e.target).length === 0 &&
              $('.popover').has(e.target).length === 0)
          {
              $popover.popover('hide');
          } 
      });
    })
  }

  let initPopovers = function () {
    $(document).ready(function() {
      managePopoverClosure();
      $('[data-bs-toggle="popover"]').popover();
    });
  };

  Drupal.behaviors.initPopoverBehavior = {
    attach: function (context, settings) {
      $(once('initPopoverBehavior', '.info-tooltip', context)).each(initPopovers);
    },
  };

})(Drupal, jQuery, once);
