import { Command } from 'ckeditor5/src/core';

/**
 * The resize image command. Currently, it only supports the width attribute.
 */
export default class ResizeMediaCommand extends Command {
	/**
	 * @inheritDoc
	 */
	refresh() {
		const editor = this.editor;
    const element = this.getClosestSelectedImageMediaModel( editor.model.document.selection );

		this.isEnabled = !!element;

		if ( !element || !element.hasAttribute( 'width' ) ) {
			this.value = null;
		} else {
			this.value = {
				width: element.getAttribute( 'width' ),
				height: null
			};
		}
	}

	/**
	 * Executes the command.
	 *
	 *		// Sets the width to 50%:
	 *		editor.execute( 'resizeMediaImage', { width: '50%' } );
	 *
	 *		// Removes the width attribute:
	 *		editor.execute( 'resizeMediaImage', { width: null } );
	 *
	 * @param {Object} options
	 * @param {String|null} options.width The new width of the image.
	 * @fires execute
	 */
	execute( options ) {
		const editor = this.editor;
		const model = editor.model;
    const imageElement = this.getClosestSelectedImageMediaModel( model.document.selection );

		this.value = {
			width: options.width,
			height: null
		};

		if ( imageElement ) {
			model.change( writer => {
				writer.setAttribute( 'width', options.width, imageElement );
			} );
		}
	}

  /**
   * Locates a drupalMedia model that contains an image in the ckeditor5 DOM.
   */
  getClosestSelectedImageMediaModel(selection) {
    const elementModel = selection.getSelectedElement();
    if (!elementModel) {
      return null;
    }

    // For now this plugin doesn't support any media type that is not containing
    // images. See upcastDrupalMediaIsImage() for the definition of image media.
    const mediaModel = elementModel.is("element", "drupalMedia")
      ? elementModel
      : selection.getFirstPosition().findAncestor("drupalMedia")

    if (!mediaModel) {
      return null;
    }

    const isImageMedia = mediaModel.getAttribute('drupalMediaIsImage');
    if (isImageMedia === undefined || isImageMedia === false) {
      return null;
    }

    return mediaModel;
  }

}
