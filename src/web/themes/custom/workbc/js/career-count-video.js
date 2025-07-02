(function ($, Drupal) {
	Drupal.behaviors.updateResultSummary = {
		attach: function (context, settings) {
			function updateSummary() {
				const $view = $('.view-id-career-trek-video-library.view-display-id-default');
				const count = $view.find('.views-row').length;
				$('.result-summary .update-result').text(count);
			}
			
			$(document).ready(updateSummary);
			
			$(document).ajaxComplete(updateSummary);
		}
	};
})(jQuery, Drupal);
