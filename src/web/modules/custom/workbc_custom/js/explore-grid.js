(function ($, Drupal, once) {
  Drupal.behaviors.exploreGrid = {
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
          const top = this.getBoundingClientRect().top;
          $(this).siblings('details').removeAttr('open');
          $(this).siblings('details').find('input:checkbox').prop('checked', false);
          window.scrollTo({ top: window.scrollY - (top - this.getBoundingClientRect().top), behavior: 'instant' });
        }
      });
      $(once('explore-grid', '#workbc-custom-explore-careers-grid-form')).on('submit', function(e) {
        const parent = $(this).find('details[open]');
        if (parent.length > 0) {
          if (!parent.find('input:checkbox:checked').length) {
            parent.find('.error').removeClass('hidden');
            e.preventDefault();
          }
        }
      });
    }
  }
})(jQuery, Drupal, once);
