let currentOpen = null;

(function ($, Drupal, once) {

  function closePreviousAreaOfInterest(area) {
    const cat = document.querySelectorAll("#category-"+currentOpen);
    $(cat).removeClass('is-selected');
    $(cat).find('.tile-expand img').attr('src', '/modules/custom/workbc_custom/icons/expand.svg');
    const areas = document.querySelectorAll("#selector-"+currentOpen);
    $(areas).addClass('is-hidden');
    $(areas).find('.error').addClass('hidden');
    $(area).siblings("#selector-"+currentOpen).find('input:checkbox').prop('checked', false);
  }

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

      $(once('explore-grid', '#workbc-custom-explore-careers-grid-form .occupational-category')).on("click", function() {
        const catId = $(this).data('category-id');
        const top = this.getBoundingClientRect().top;
        if (currentOpen !== null && currentOpen !== catId) {
          closePreviousAreaOfInterest($(this));
        }
        const cat = document.querySelectorAll("#category-"+catId);
        const areas = document.querySelectorAll("#selector-"+catId);
        $(this).toggleClass('is-selected');
        $(areas).toggleClass('is-hidden');
        if ($(areas).hasClass('is-hidden')) {
          $(cat).find('.tile-expand img').attr('src', '/modules/custom/workbc_custom/icons/expand.svg');
        }
        else {
          $(cat).find('.tile-expand img').attr('src', '/modules/custom/workbc_custom/icons/collapse.svg');
        }
        window.scrollTo({ top: window.scrollY - (top - this.getBoundingClientRect().top), behavior: 'instant' });
        currentOpen = catId;
      });

      $(once('explore-grid', '#workbc-custom-explore-careers-grid-form .areas-of-interest-close')).on("click", function() {
        closePreviousAreaOfInterest($(this));
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
