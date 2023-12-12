import { Plugin } from 'ckeditor5/src/core';
import ResizeMediaCommand from './resizemediacommand';

const MEDIA_WIDTH_DATA_ATTRIBUTE = 'data-media-width';

export default class MediaResizeEditing extends Plugin {

  /**
  * @inheritDoc
  */
  static get pluginName() {
    return 'MediaResizeEditing';
  }

  /**
  * @inheritDoc
  */
  init() {
    const editor = this.editor;
    const resizeMediaCommand = new ResizeMediaCommand( editor );

    this._registerSchema();
    this._registerConverters();

    // Register `resizeMediaImage` command.
    editor.commands.add( 'resizeMediaImage', resizeMediaCommand );
  }

  /**
  * @private
  */
  _registerSchema() {
    if ( this.editor.plugins.has( 'DrupalMediaEditing' ) ) {
      this.editor.model.schema.extend( 'drupalMedia', { allowAttributes: ['width', 'drupalMediaWidth', 'style'] } );
    }
  }

  /**
  * Registers converters necessary for media image resizing.
  */
  _registerConverters() {
    const elementType = 'drupalMedia';
    const editor = this.editor;

    // Converts the 'width' property of a drupalMedia model into attributes on a
    // <drupal-media> tag.
    editor.conversion.for( 'downcast' ).add( dispatcher => {
      dispatcher.on( `attribute:width:${ elementType }`, ( evt, data, conversionApi ) => {
        if ( !conversionApi.consumable.consume( data.item, evt.name ) ) {
          return;
        }

        const viewWriter = conversionApi.writer;
        const mediaView = conversionApi.mapper.toViewElement( data.item );

        if ( data.attributeNewValue !== null ) {
          // The width style is just used to reflect the size set using the
          // handles on the element wrapping the rendered media.
          viewWriter.setStyle( 'width', data.attributeNewValue, mediaView );

          // The data attribute is set for applying the width when drupal
          // renders the media via the resize_media_filter. It's also used to
          // set the width on the drupalMedia model when the editor loads via
          // the upcast converter below.
          viewWriter.setAttribute( MEDIA_WIDTH_DATA_ATTRIBUTE, data.attributeNewValue, mediaView );
          viewWriter.addClass( 'image_resized', mediaView );
        } else {
          viewWriter.removeStyle( 'width', mediaView );
          viewWriter.removeAttribute( MEDIA_WIDTH_DATA_ATTRIBUTE, mediaView );
          viewWriter.removeClass( 'image_resized', mediaView );
        }
      } )

      dispatcher.on( `attribute:drupalMediaIsImage:${ elementType }`, ( evt, data, conversionApi ) => {
        const viewWriter = conversionApi.writer;
        const mediaView = conversionApi.mapper.toViewElement( data.item );
        const newWidth = data.item._attrs.get('width');

        if ( newWidth ) {
          // The width style is just used to reflect the size set using the
          // handles on the element wrapping the rendered media.
          viewWriter.setStyle( 'width', newWidth, mediaView );

          // The data attribute is set for applying the width when drupal
          // renders the media via the resize_media_filter. It's also used to
          // set the width on the drupalMedia model when the editor loads via
          // the upcast converter below.
          viewWriter.setAttribute( MEDIA_WIDTH_DATA_ATTRIBUTE, newWidth, mediaView );
          viewWriter.addClass( 'image_resized', mediaView );
        } else {
          viewWriter.removeStyle( 'width', mediaView );
          viewWriter.removeAttribute( MEDIA_WIDTH_DATA_ATTRIBUTE, mediaView );
          viewWriter.removeClass( 'image_resized', mediaView );
        }
      } )
    } );

    // Ensures that the value of the data-media-width attribute is added to the
    // ckeditor model when the editor loads.
    editor.conversion.for( 'upcast' ).add( dispatcher =>
      dispatcher.on( `element:drupal-media`, ( evt, data, conversionApi ) => {
        const { schema, writer } = conversionApi;
        const mediaWidth = data.viewItem.getAttribute( MEDIA_WIDTH_DATA_ATTRIBUTE );

        // Do not go for the model element after data.modelCursor because it might happen
        // that a single view element was converted to multiple model elements. Get all of them.
        for ( const item of data.modelRange.getItems( { shallow: true } ) ) {
          if ( schema.checkAttribute( item, 'width' ) ) {
            writer.setAttribute( 'width', mediaWidth, item );
          }
        }
      })
      );
    }

  }
