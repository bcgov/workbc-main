(function ($, Drupal, once) {
  Drupal.behaviors.validationToggle = {
    attach: function (context, settings) {
      toggleValidations(false, context);
      $(once('validationToggle', '#edit-toggle-warnings', context)).on('change', (e) => {
        toggleValidations($(e.target).is(':checked'), context);
      });
    }
  }

  function toggleValidations(toggle, context) {
    const $status_messages = $('.messages-list__item.messages--status', context);
    if (toggle) $($status_messages).show(); else $($status_messages).hide();
  }
})(jQuery, Drupal, once);
