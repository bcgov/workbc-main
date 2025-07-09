(function ($, Drupal) {
	Drupal.behaviors.updateResultSummary = {
		attach: function (context, settings) {
			function updateSummary() {
				const $view = $('.view-id-career_trek_video_library.view-display-id-block_1');
				if (!$view.length) {
					console.warn('View not found.');
					return;
				}
				
				setTimeout(function () {
				const $rows = $view.find('.view-row');
				const count = $rows.length;
				
				console.log('Loaded rows after delay:', count);
				
				const $summary = $('.result-summary .update-result');
				if (!$summary.length) {
					console.warn('Result summary span not found.');
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
