(function ($, Drupal, once) {
  Drupal.behaviors.feedback = {
    attach: function (context, settings) {
      $(once('explore', '#workbc-custom-explore-careers-grid-form .grid-all')).change(function() {
        const checked = $(this).is(':checked');
        const parent = $(this).closest('.details-wrapper');
        $(parent).find('.grid-term').prop('checked', checked);
      });
      $(once('explore', '#workbc-custom-explore-careers-grid-form .grid-term')).change(function() {
        const parent = $(this).closest('.details-wrapper');
        $(parent).find('.grid-all').prop('checked', false);
      });
    }
  }
})(jQuery, Drupal, once);
