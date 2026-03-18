/**
 * @file defines InsertMediaDownloadLinkCommand, which is executed when the mediaDownloadLink
 * toolbar button is pressed.
 */


import { Command } from 'ckeditor5/src/core';

export default class InsertMediaDownloadLinkCommand extends Command {
  execute(attributes) {
    const { model } = this.editor;
    const range = model.document.selection.getFirstRange();

    var text;

    for (const item of range.getItems()) {
      text = item.data;
    }  

    console.log(text);
    model.change((writer) => {
      const url = "/media/[dlcke:" + attributes['data-entity-uuid'] + "]/download?inline";
      var link;

      var linkAttributes = {
        "data-entity-loader": "dlcke",
        "data-uuid": attributes['data-entity-uuid'],
        "rel": "no-follow",
        "target": "_blank"
      };

      if (text) {
      }
      else {
        text = "Download";
        // console.log("here");
        linkAttributes.class, "button";
        // linkAttributes[class] = "button";
        Object.assign(linkAttributes,{class : "button"});
        // console.log(test);
       }

      link = writer.createText(text, {
        linkHref: url,
        htmlA: {attributes: linkAttributes}
      });

      model.insertContent(link);
    });
  }

  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;

    // Determine if the cursor (selection) is in a position where adding a
    // mediaDownloadLink is permitted. This is based on the schema of the model(s)
    // currently containing the cursor.
    const allowedIn = true;

    // If the cursor is not in a location where a mediaDownloadLink can be added, return
    // null so the addition doesn't happen.
    this.isEnabled = allowedIn !== null;
  }
}

