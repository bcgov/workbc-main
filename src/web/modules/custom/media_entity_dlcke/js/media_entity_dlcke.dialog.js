/**
 * @file
 * Provides JavaScript additions to media entity download dialog.
 */

 (function ($, Drupal) {

   "use strict";

   /**
    * Behaviors for the mediaEntityDownloadDialog iframe.
    */
   Drupal.behaviors.mediaEntityDownloadDialog = {
     attach: function (context, settings) {
       $('body').once('js-media-entity-download-dialog').on('entityBrowserIFrameAppend', function () {
         $('.entity-select-dialog').trigger('resize');
         // Hide the next button, the click is triggered by Drupal.entityEmbedDialog.selectionCompleted.
         $('#drupal-modal').parent().find('.js-button-submit').addClass('visually-hidden');
       });
     }
   };

  /**
   * Media Entity Download dialog utility functions.
   */
  Drupal.mediaEntityDownloadDialog = Drupal.mediaEntityDownloadDialog || {
    /**
     * Open links to entities within forms in a new window.
     */
    selectionCompleted: function(event, uuid, entities) {
      $('.entity-select-dialog .js-button-submit').click();
    }
  };

})(jQuery, Drupal);
