# Metadata API
[![Build Status](https://travis-ci.org/catalyst/moodle-tool_metadata.svg?branch=master)](https://travis-ci.org/catalyst/moodle-tool_metadata)

The Moodle Metadata API aims to create a programming interface for obtaining structured metadata from Moodle resources, the shape and type of metadata is determined by metadataextractor subplugins, the API itself is the framework for extracting this metadata and exposes the methods necessary to do so and associated scheduled tasks to conduct asynchronous extraction of metadata for resources.

## Subplugins

The Metadata API relies on subplugins of various types to populate metadata for consumption by Moodle users, so far this includes the following subplugin types:

__metadataextractor__: Metadata extractors are plugins which extract metadata from Moodle resources for use by the Metadata API, without an installed and enabled metadata extractor subplugin, no metadata can be populated by Moodle.

For developers, to create a __metadataextractor__ plugin, refer to the README in `/extractor` directory.

## API methods

API methods are static methods attached to the base class `tool_metadata\api` and can be called as follows: `\tool_metadata\api::extract_metadata($resourceinstance, $resourcetype, $extractor)`

See `\classes\api` method documentation for API method explanations.

For developers, to create a __metadataextractor__ plugin, refer to the README in `/extractor` directory.

## License ##

2020 Catalyst IT Australia

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.


This plugin was developed by Catalyst IT Australia:

https://www.catalyst-au.net/

<img alt="Catalyst IT" src="https://raw.githubusercontent.com/catalyst/moodle-local_smartmedia/master/pix/catalyst-logo.svg?sanitize=true" width="400">
