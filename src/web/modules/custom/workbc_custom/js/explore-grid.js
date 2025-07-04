let currentCatId = null;

(function ($, Drupal, once) {
  function closePreviousAreaOfInterest(area) {
    const cat = document.querySelectorAll(`#category-${currentCatId}`);
    $(cat).removeClass('is-selected');
    $(cat).find('.tile-expand img').attr('src', '/modules/custom/workbc_custom/icons/expand.svg');
    const areas = document.querySelectorAll(`#selector-${currentCatId}`);
    $(areas).addClass('is-hidden');
    $(areas).find('.error').addClass('hidden');
    $(area).siblings(`#selector-${currentCatId}`).find('input:checkbox').prop('checked', false);
    $(cat).find('.tile-expand img').focus();
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

      $(once('explore-grid', '#workbc-custom-explore-careers-grid-form .occupational-category')).on('click', function() {
        const catId = $(this).data('category-id');
        const top = this.getBoundingClientRect().top;
        if (currentCatId !== null && currentCatId !== catId) {
          closePreviousAreaOfInterest(this);
        }

        const cat = document.querySelectorAll(`#category-${catId}`);
        const areas = document.querySelectorAll(`#selector-${catId}`);
        $(this).toggleClass('is-selected');
        $(areas).toggleClass('is-hidden');
        if ($(areas).hasClass('is-hidden')) {
          $(cat).find('.tile-expand img').attr('src', '/modules/custom/workbc_custom/icons/expand.svg');
        }
        else {
          $(cat).find('.tile-expand img').attr('src', '/modules/custom/workbc_custom/icons/collapse.svg');
          $(cat).find('.tile-expand img').focus();
        }

        window.scrollTo({ top: window.scrollY - (top - this.getBoundingClientRect().top), behavior: 'instant' });
        currentCatId = catId;
      });

      $(once('explore-grid', '#workbc-custom-explore-careers-grid-form .areas-of-interest-close')).on('click', function() {
        closePreviousAreaOfInterest(this);
      });

      $(once('explore-grid', '#workbc-custom-explore-careers-grid-form')).on('submit', function(e) {
        const parent = $(this).find(`#selector-${currentCatId}`);
        if (parent.length > 0) {
          if (!parent.find('input:checkbox:checked').length) {
            parent.find('.error').removeClass('hidden');
            e.preventDefault();
          }
        }
      });

      $(once('explore-grid', document.body)).on('keydown', function (e) {
        if ([13, 32].includes(e.keyCode) && $(document.activeElement).data('category-id')) {
          $(document.activeElement).closest('.occupational-category').trigger('click');
          e.preventDefault();
        }
        if (e.keyCode === 27 && currentCatId !== null) {
          closePreviousAreaOfInterest($(`#category-${currentCatId}`));
        }
      });
    }
  }
})(jQuery, Drupal, once);
