/**
 * @file
 * Media entity download link plugin.
 * PVS Notes: This uses the same editor configuration that the media_library
 * button uses:
 * editor.config.DrupalMediaLibrary_dialogOptions
 * and
 * editor.config.DrupalMediaLibrary_url
 *
 * We use this to open the full media browser. A selection there gives us
 * back the media uuid which we use a Filter to replace when the page loads
 * So you need to set the filter as well when placing the button for this.
 */

(function ($, Drupal, CKEDITOR) {

  'use strict';

  CKEDITOR.plugins.add('mediaentitydlcke', {
    icons: 'mediaentitydlcke',

    beforeInit: function (editor) {
      // Add the commands for link and unlink.
      editor.addCommand('mediaentitydlcke', {
        allowedContent: {
          a: {
            attributes: {
              '!href': true
            },
            classes: {}
          }
        },
        requiredContent: new CKEDITOR.style({
          element: 'a',
          attributes: {
            href: ''
          }
        }),
        modes: {wysiwyg: 1},
        canUndo: true,
        exec: function (editor) {
          var selectedText = editor.getSelection().getSelectedText();

          var saveCallback = function(values) {
            editor.fire('saveSnapshot');
            var attributes = values.attributes;
            var uuid = null;
            Object.keys(attributes).forEach(function (key) {
              // console.log(attributes['data-entity-uuid']);
              uuid = attributes['data-entity-uuid'];
            });

            if(uuid) {
              if(!selectedText.length) {
                selectedText = 'Download';
                editor.insertHtml('<a data-entity-loader="dlcke" data-uuid="'+uuid+'" class="button" target="_blank" rel="no-follow" href="/media/[dlcke:'+uuid+']/download?inline">' + selectedText + '</a>');
              } else {
                editor.insertHtml('<a data-entity-loader="dlcke"  data-uuid="'+uuid+'" target="_blank" rel="no-follow" href="/media/[dlcke:'+uuid+']/download?inline">' + selectedText + '</a>');
              }
            }

            editor.fire('saveSnapshot');
          }

          // var saveCallback = function (links) {
          //   links.forEach(function (link, index) {
          //     if(selectedText.length) {
          //       editor.insertHtml('<a target="'+link.target+'" rel="'+link.rel+'" href="' + link.url + '">' + selectedText + '</a>');
          //     } else {
          //       editor.insertHtml('<a target="'+link.target+'" rel="'+link.rel+'" href="' + link.url + '">' + link.text + '</a>');
          //     }
          //   });
          // };
          // var dialogSettings = {
          //   dialogClass: 'entity-select-dialog',
          //   resizable: false
          // };
          var existingValues = {};



          // Drupal.ckeditor.openDialog(editor, Drupal.url('media-entity-dlcke/dialog/' + editor.config.drupal.format), existingValues, saveCallback, dialogSettings);
          Drupal.ckeditor.openDialog(editor, editor.config.DrupalMediaLibrary_url, {}, saveCallback, editor.config.DrupalMediaLibrary_dialogOptions);
        }
      });

      // Add buttons for link and unlink.
      if (editor.ui.addButton) {
        editor.ui.addButton('MediaEntityDLCKE', {
          label: Drupal.t('Media Library download link'),
          command: 'mediaentitydlcke'
        });
      }
    }
  });

})(jQuery, Drupal, CKEDITOR);
