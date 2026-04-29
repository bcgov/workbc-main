(function ($, Drupal) {
  Drupal.behaviors.resetSortOnKeyword = {
    attach: function (context, settings) {
      const $keywordInput = $('.view-search-site-content .form-item-search-api-fulltext input', context);
      const $sortSelect = $('.view-search-site-content .form-item-sort-by select', context);

      $keywordInput.on('change keyup', function() {
        $sortSelect.val('search_api_relevance');
      });
    }
  };
})(jQuery, Drupal);
