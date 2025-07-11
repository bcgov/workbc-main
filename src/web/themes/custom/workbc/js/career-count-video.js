(function ($, Drupal) {
  Drupal.behaviors.updateResultSummary = {
    attach: function (context, settings) {
      function updateSummaryAndMove() {
        const $view = $('.view-id-career_trek_video_library.view-display-id-block_1');
        if (!$view.length) {
          return;
        }

        setTimeout(function () {
          const $rows = $view.find('.view-row');
          const count = $rows.length;
          const $summary = $('.result-summary .update-result');
          if (!$summary.length) {
            return;
          }

          $summary.text(count);

          const $summaryWrapper = $view.find('.result-summary');
          const $loadMoreLink = $view.find('a.btn-primary[title="Load more items"]').last();

          if ($summaryWrapper.length && $loadMoreLink.length) {
            $loadMoreLink.after($summaryWrapper);
          }
        }, 100);
      }

      $(document).ready(updateSummaryAndMove);

      $(document).ajaxComplete(function () {
        updateSummaryAndMove();
      });
    }
  };
})(jQuery, Drupal);
