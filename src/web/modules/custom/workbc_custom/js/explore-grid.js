let currentOpen = null;

(function ($, Drupal, once) {
  Drupal.behaviors.exploreGrid = {
    attach: function (context, settings) {
      $(once('explore-grid', '#workbc-custom-explore-careers-grid-form .grid-all')).change(function() {
        const checked = $(this).is(':checked');
        const parent = $(this).closest('.fieldset-wrapper');
        $(parent).find('.grid-term').prop('checked', checked);
      });
      
      $(once('explore-grid', '#workbc-custom-explore-careers-grid-form .grid-term')).change(function() {
        const parent = $(this).closest('.fieldset-wrapper');
        $(parent).find('.grid-all').prop('checked', false);
      });

      $(once('explore-grid', '#workbc-custom-explore-careers-grid-form .grid-item')).on("click", function() {
        let catId = $(this).data('category-id');
        if (currentOpen !== null && currentOpen !== catId) {
          let cat = document.querySelectorAll("#category-"+currentOpen);
          $(cat).removeClass('is-selected');
          let areas = document.querySelectorAll("#selector-"+currentOpen);
          $(areas).addClass('is-hidden');
          $(this).siblings("#selector-"+currentOpen).find('input:checkbox').prop('checked', false);           
        }
        let areas = document.querySelectorAll("#selector-"+catId);
        $(this).toggleClass('is-selected');
        $(areas).toggleClass('is-hidden');
        currentOpen = catId;
      });

      $(once('explore-grid', '#workbc-custom-explore-careers-grid-form')).on('submit', function(e) {
        const parent = $(this).find("#selector-"+currentOpen);
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
