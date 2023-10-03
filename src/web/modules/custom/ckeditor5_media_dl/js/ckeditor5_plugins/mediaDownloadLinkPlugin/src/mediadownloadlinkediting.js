import { Plugin } from 'ckeditor5/src/core';
import { Widget } from 'ckeditor5/src/widget';
import InsertMediaDownloadLinkCommand from './insertmediadownloadlinkcommand';


export default class MediaDownloadLinkEditing extends Plugin {
  static get requires() {
    return [Widget];
  }

  init() {
    this.editor.commands.add(
      'insertMediaDownloadLink',
      new InsertMediaDownloadLinkCommand(this.editor),
    );
  }

}
