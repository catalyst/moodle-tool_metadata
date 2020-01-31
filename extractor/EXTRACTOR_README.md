# Metadata extractors

Within this directory path (`/extractors`) __metadataextractor__ subplugin code is stored for each of the installed subplugins.

## Naming convention

All __metadataextractor__ subplugins must follow the Moodle frankenstyle naming conventions for their component name, that is the plugin type `metadataextractor` followed by an underscore and the name of the subplugin. Example: `metadataextractor_myplugin`

The directory name for the code base of a subplugin should be the lowercase name of the plugin only, in the above example this would be simply `myplugin`.

## Settings

Each __metadataextractor__ subplugin may specify it's own settings like a normal Moodle plugin, see [Admin Settings](https://docs.moodle.org/dev/Admin_settings) for further information.

## Mandatory classes

In order to function correctly, each __metadataextractor__ must have it's own `extractor` class extending the abstract class `\tool_metadata\extractor`, within which is contained methods for the extraction of data for all supported resource types.
 
The format of extraction method names must follow this structure `extract_{resourcetype}_metadata` and must return an instance of the `\toolmetadata\metadata` class or a child instance extending this class.

For example, a method `extract_file_metadata` defines how the plugin actually extracts metadata from a moodle stored_file resource.

Each `extractor` must also overwrite two key constants `METADATAEXTRACTOR_NAME` and `METADATA_TABLE`, the first must coincide with the frankenstyle name (in our example 'myplugin') while the second is the name of the table declared in your subplugins install.xml file for metadata records to be stored. Mandatory fields in this table are as follows:

| fieldname      | type | length | notnull | sequence |
|----------------|:----:|:------:|:-------:|:--------:|
| 'id'           | int  | 10     | true    | true     |
| 'resourcehash' | char | 40     | true    | true     |
| 'timecreated'  | int  | 10     | true    | true     |
| 'timemodified' | int  | 10     | true    | true     |

Some resources are stored multiple times if used in a Moodle instance more than once (for example, moodle files, the file itself is stored once, but there is a file record for every time a file is used.) The 'resourcehash' field is a unique identifier which the Metadata API uses to prevent extraction of metadata for the same resource multiple times. For example, file resources use a SHA1 hash of the file contents.

Each subplugin must also declare it's own `metadata` class extending the abstract class `\tool_metadata\metadata`, within which the `metadata_key_map` method must be overridden to return an array indexed by the custom fields in your plugin's `METADATA_TABLE`. 

Each key's value in the returned array is an array of all potential metadata key's which map to that field, in this manner, when metadata is extracted via your extractor, you pass a raw associative array of the metadata extracted into the constructor of your metadata class and the Metadata API maps these values into your table. (See documentation in the `\tool_metadata\metadata` class for clarification and examples.)