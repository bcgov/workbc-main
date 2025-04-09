(function ($, Drupal, once) {
  Drupal.behaviors.feedback = {
    attach: function (context, settings) {
      $(once('explore-grid', '#workbc-custom-explore-careers-grid-form .grid-all')).change(function() {
        const checked = $(this).is(':checked');
        const parent = $(this).closest('.details-wrapper');
        $(parent).find('.grid-term').prop('checked', checked);
      });
      $(once('explore-grid', '#workbc-custom-explore-careers-grid-form .grid-term')).change(function() {
        const parent = $(this).closest('.details-wrapper');
        $(parent).find('.grid-all').prop('checked', false);
      });
      $(once('explore-grid', 'details')).on('toggle', function() {
        if (this.open) {
          $(this).siblings('details').removeAttr('open');
        }
      });
    }
  }
})(jQuery, Drupal, once);
