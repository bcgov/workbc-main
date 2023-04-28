# Media Entity DLCKE Module

This module is a shot at modularizing this patch:
https://www.drupal.org/project/media_entity_download/issues/2928318#comment-13234165

You will need to configure your Text Formatter to do the following:

- Enable the Embed Media Button/Plugin and configure that Filters settings as this one will use those settings
- Enable the Media Download Link button and turn on the DLCKE filter. It should run later in the filter list.

To use:

Click the Download Link button and choose your media. Selected text will be used
if selected and if none, the text "Download" will be added.

## Notes

This module has a form for the dialog that it initially used. That form would
do an autocomplete for the media entity and return the title and url. This was
great but I wanted to use the entire library browser as the media_embed does.

When it was switched over in the js to use the DrupalMediaLibrary_url the return
result only included the uuid so we needed a filter to replace that with the media id

If media_entity_download allowed a route like /media/{uuid}/download we wouldn't need the filter.

So there are a few (see: many) things that this module does that are hacky:

1. Ignores it's own form for the button dialog and apes off of whatever is set for
media_library settings at the text formatter level.
2. Uses a Filter and some hardcoded markup in the editor results to replace the uuid
that is returned by the media_library browser to make the link what it needs to be for
media_entity_download.
