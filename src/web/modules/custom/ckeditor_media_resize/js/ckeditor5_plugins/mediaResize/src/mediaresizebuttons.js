import { Plugin, icons } from 'ckeditor5/src/core';
import { ButtonView, DropdownButtonView, ViewModel, createDropdown, addListToDropdown } from 'ckeditor5/src/ui'
import { CKEditorError, Collection } from 'ckeditor5/src/utils';

import MediaResizeEditing from './mediaresizeediting';

const RESIZE_ICONS = {
	small: icons.objectSizeSmall,
	medium: icons.objectSizeMedium,
	large: icons.objectSizeLarge,
	original: icons.objectSizeFull
};

/**
 * The image resize buttons plugin.
 *
 * It adds a possibility to resize images using the toolbar dropdown or individual buttons, depending on the plugin configuration.
 *
 * @extends module:core/plugin~Plugin
 */
export default class MediaResizeButtons extends Plugin {
	/**
	 * @inheritDoc
	 */
	static get requires() {
		return [ MediaResizeEditing ];
	}

	/**
	 * @inheritDoc
	 */
	static get pluginName() {
		return 'MediaResizeButtons';
	}

	/**
	 * @inheritDoc
	 */
	constructor( editor ) {
		super( editor );

		/**
		 * The resize unit.
		 *
		 * @readonly
		 * @private
		 * @type {module:image/image~ImageConfig#resizeUnit}
		 * @default '%'
		 */
		this._resizeUnit = editor.config.get( 'drupalMedia.resizeUnit' );
	}

	/**
	 * @inheritDoc
	 */
	init() {
		const editor = this.editor;
		const options = editor.config.get( 'drupalMedia.resizeOptions' );
		const command = editor.commands.get( 'resizeMediaImage' );

		this.bind( 'isEnabled' ).to( command );

		for ( const option of options ) {
			this._registerImageResizeButton( option );
		}

		this._registerImageResizeDropdown( options );
	}

	/**
	 * A helper function that creates a standalone button component for the plugin.
	 */
	_registerImageResizeButton( option ) {
		const editor = this.editor;
		const { name, value, icon } = option;
		const optionValueWithUnit = value ? value + this._resizeUnit : null;

		editor.ui.componentFactory.add( name, locale => {
			const button = new ButtonView( locale );
			const command = editor.commands.get( 'resizeMediaImage' );
			const labelText = this._getOptionLabelValue( option, true );

			if ( !RESIZE_ICONS[ icon ] ) {
				throw new CKEditorError(
					'mediaresizebuttons-missing-icon',
					editor,
					option
				);
			}

			button.set( {
				// Use the `label` property for a verbose description (because of ARIA).
				label: labelText,
				icon: RESIZE_ICONS[ icon ],
				tooltip: labelText,
				isToggleable: true
			} );

			// Bind button to the command.
			button.bind( 'isEnabled' ).to( this );
			button.bind( 'isOn' ).to( command, 'value', getIsOnButtonCallback( optionValueWithUnit ) );

			this.listenTo( button, 'execute', () => {
				editor.execute( 'resizeMediaImage', { width: optionValueWithUnit } );
			} );

			return button;
		} );
	}

	/**
	 * A helper function that creates a dropdown component for the plugin
   * containing all the resize options defined in the editor configuration media.
   *
   *  @see ckeditor_media_resize.ckeditor5.yml
	 */
	_registerImageResizeDropdown( options ) {
		const editor = this.editor;
		const t = editor.t;
		const originalSizeOption = options.find( option => !option.value );

		const componentCreator = locale => {
			const command = editor.commands.get( 'resizeMediaImage' );
			const dropdownView = createDropdown( locale, DropdownButtonView );
			const dropdownButton = dropdownView.buttonView;

			dropdownButton.set( {
				tooltip: t( 'Resize media image' ),
				commandValue: originalSizeOption.value,
				icon: RESIZE_ICONS.medium,
				isToggleable: true,
				label: this._getOptionLabelValue( originalSizeOption ),
				withText: true,
				class: 'ck-resize-image-button'
			} );

			dropdownButton.bind( 'label' ).to( command, 'value', commandValue => {
				if ( commandValue && commandValue.width ) {
					return commandValue.width;
				} else {
					return this._getOptionLabelValue( originalSizeOption );
				}
			} );
			dropdownView.bind( 'isOn' ).to( command );
			dropdownView.bind( 'isEnabled' ).to( this );

			addListToDropdown( dropdownView, this._getResizeDropdownListItemDefinitions( options, command ) );

      // The listView property seems to be undefined in some cases, prevent the
      // editor from breaking in such cases.
      if (dropdownView.listView) {
        dropdownView.listView.ariaLabel = t( 'Media image resize list' );
      }

			// Execute command when an item from the dropdown is selected.
			this.listenTo( dropdownView, 'execute', evt => {
				editor.execute( evt.source.commandName, { width: evt.source.commandValue } );
				editor.editing.view.focus();
			} );

			return dropdownView;
		};

		// Register `resizeMediaImage` dropdown..
		editor.ui.componentFactory.add( 'resizeMediaImage', componentCreator );
	}

	/**
	 * A helper function for creating an option label value string.
	 *
	 * @private
	 * @param {module:image/imageresize/mediaresizebuttons~ImageResizeOption} option A resize option object.
	 * @param {Boolean} [forTooltip] An optional flag for creating a tooltip label.
	 * @returns {String} A user-defined label combined from the numeric value and the resize unit or the default label
	 * for reset options (`Original`).
	 */
	_getOptionLabelValue( option, forTooltip ) {
		const t = this.editor.t;

		if ( option.label ) {
			return option.label;
		} else if ( forTooltip ) {
			if ( option.value ) {
				return t( 'Resize image to %0', option.value + this._resizeUnit );
			} else {
				return t( 'Resize image to the original size' );
			}
		} else {
			if ( option.value ) {
				return option.value + this._resizeUnit;
			} else {
				return t( 'Original' );
			}
		}
	}

	/**
	 * A helper function that parses the resize options and returns list item definitions ready for use in the dropdown.
	 *
	 * @private
	 * @param {Array.<module:image/imageresize/mediaresizebuttons~ImageResizeOption>} options The resize options.
	 * @param {module:image/imageresize/resizemediacommand~ResizeMediaCommand} command The resize image command.
	 * @returns {Iterable.<module:ui/dropdown/utils~ListDropdownItemDefinition>} Dropdown item definitions.
	 */
	_getResizeDropdownListItemDefinitions( options, command ) {
		const itemDefinitions = new Collection();

		options.map( option => {
			const optionValueWithUnit = option.value ? option.value + this._resizeUnit : null;
			const definition = {
				type: 'button',
				model: new ViewModel( {
					commandName: 'resizeMediaImage',
					commandValue: optionValueWithUnit,
					label: this._getOptionLabelValue( option ),
					withText: true,
					icon: null
				} )
			};

			definition.model.bind( 'isOn' ).to( command, 'value', getIsOnButtonCallback( optionValueWithUnit ) );

			itemDefinitions.add( definition );
		} );

		return itemDefinitions;
	}
}

// A helper function for setting the `isOn` state of buttons in value bindings.
function getIsOnButtonCallback( value ) {
	return commandValue => {
		if ( value === null && commandValue === value ) {
			return true;
		}

		return commandValue && commandValue.width === value;
	};
}
