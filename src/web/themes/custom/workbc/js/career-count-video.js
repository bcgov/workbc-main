(function ($, Drupal) {
	Drupal.behaviors.updateResultSummary = {
		attach: function (context, settings) {
			function updateSummary() {
				const $view = $('.view-id-career_trek_video_library.view-display-id-block_1');
        if (!$view.length) {
          console.warn('View not found.');
        }
				const count = $view.find('.views-row').length;
				$('.result-summary .update-result').text(count);
			}
			
			$(document).ready(updateSummary);
			
			$(document).ajaxComplete(updateSummary);
		}
	};
})(jQuery, Drupal);
