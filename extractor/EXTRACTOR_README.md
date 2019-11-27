# Metadata extractors

Within this directory path (`/extractors`) 'metadataextractor' subplugin code is stored for each of the installed subplugins.

## Naming convention

All 'metadataextractor' subplugins must follow the Moodle frankenstyle naming conventions for their component name, that is the plugin type `metadataextractor` followed by an underscore and the name of the subplugin. Example: `metadataextractor_tika`

The directory name for the code base of a subplugin should be the name of the plugin only, in the above example this would be simply `tika`.

## Settings

Each metadataextractor subplugin may specify it's own settings like a normal Moodle plugin, see [Admin Settings](https://docs.moodle.org/dev/Admin_settings) for further information.

## Mandatory classes

In order to function correctly, each subplugin must have it's own `extractor` class extending the abstract class `\tool_metadata\extractor`, within which the method `create_file_metadata` defines how the plugin actually extracts metadata from a file.