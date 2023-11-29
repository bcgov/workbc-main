(function ($, Drupal) {
  Drupal.behaviors.videoLibrary = {
    attach: function (context, settings) {
      $("#edit-video-category option:contains('Industries')", context).attr('disabled', 'disabled');
    }
  }
})(jQuery, Drupal);
