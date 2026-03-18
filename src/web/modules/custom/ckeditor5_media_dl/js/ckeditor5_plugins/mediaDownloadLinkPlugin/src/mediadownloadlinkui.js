/**
 * @file registers the mediaDownloadLink toolbar button and binds functionality to it.
 */

import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import icon from '../../../../icons/mediaDL.svg';

export default class MediaDownloadLinkUI extends Plugin {
  init() {
    const editor = this.editor;
    const options = this.editor.config.get('drupalMedia');
    if (!options) {
      return;
    }

    const { libraryURL, openDialog, dialogSettings = {} } = options;
    if (!libraryURL || typeof openDialog !== 'function') {
      return;
    }

    // This will register the mediaDownloadLink toolbar button.
    editor.ui.componentFactory.add('mediaDownloadLink', (locale) => {
      const command = editor.commands.get('insertMediaDownloadLink');
      const buttonView = new ButtonView(locale);

      // Create the toolbar button.
      buttonView.set({
        label: editor.t('Media Download Link'),
        icon,
        tooltip: true,
      });

      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');
      this.listenTo(buttonView, 'execute', () => {
        openDialog(
          libraryURL,
          ({ attributes }) => {
            editor.execute('insertMediaDownloadLink', attributes);
          },
          dialogSettings,
        );
      });

      return buttonView;
    });
  }
}
