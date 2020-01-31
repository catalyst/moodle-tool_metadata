<?php


use tool_metadata\task\file_extraction_task;

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'type' => '',
        'id' => 0,
        'plugin' => '',
        'help' => false,
        'showdebugging' => false,
        'json' => false,
    ], [
        'h' => 'help',
        'j' => 'json',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    mtrace(
<<<HELP
Extract metadata from a resource using the command line.

Options:
-h, --help            Print out this help
--type (required)     The resource type (example: 'file', 'url)
--plugin (required)   The name of the metadataextractor subplugin to use for extraction
--id (required)       The id of the resource
-j, --json            Extract metadata as a JSON string
--showdebugging       Print debugging statements

Example:
\$ php admin/tool/metadata/cli/extractmetadata.php --type='file' --plugin='tika' --id=10311 -j
HELP
    );
    exit(0);
}

if ($options['showdebugging']) {
    set_debugging(DEBUG_DEVELOPER, true);
}

if (empty($options['type'])) {
    mtrace('No resource type, you must pass in a type option.');
    exit(1);
} elseif (!in_array($options['type'], \tool_metadata\api::get_supported_resource_types())) {
    mtrace(get_string('error:unsupportedresourcetype', 'tool_metadata'));
    exit(1);
} else {
    $type = $options['type'];
}

if (!empty($options['id'] && !empty($options['plugin']))) {

    if (!in_array($options['plugin'], \tool_metadata\plugininfo\metadataextractor::get_enabled_plugins())) {
        mtrace(get_string('error:pluginnotenabled', 'tool_metadata', $options['plugin']));
        exit(1);
    } else {
        $plugin = $options['plugin'];
    }

    if (!is_numeric($options['id'])) {
        mtrace('ID must be a number.');
        exit(1);
    } else {
        $id = (int) $options['id'];
    }

    $resource = \tool_metadata\helper::get_resource($id, $type);
    $extractor = \tool_metadata\api::get_extractor($plugin);
    $extraction = \tool_metadata\api::get_resource_extraction($resource, $type, $extractor);

    mtrace('Initial status code: ' . $extraction->get('status'));
    mtrace('Extracting metadata for ' . $type . ' id: ' . $id);

    $task = new \tool_metadata\task\metadata_extraction_task();
    $task->set_custom_data(['resourceid' => $id, 'type' => $type, 'plugin' => $plugin]);

    try {
        $task->execute();
    } catch (\tool_metadata\extraction_exception $ex) {
        mtrace('Extraction failed:');
        mtrace($ex->getMessage());
        mtrace($ex->getTraceAsString());
        exit(1);
    }

    $extracted = \tool_metadata\api::get_resource_extraction($resource, $type, $extractor);

    if ($extracted->get('status') != \tool_metadata\extraction::STATUS_COMPLETE) {
        mtrace('Extraction task could not be queued.');
        mtrace('Extraction status: ' . $extracted->get('status'));
        mtrace('Extraction reason: ' . $extracted->get('reason'));
        exit(1);
    } else {
        mtrace('Extraction complete:');
        if ($options['json']) {
            mtrace($extracted->get_metadata()->get_json());
        } else {
            mtrace(var_dump($extracted->get_metadata()->get_associative_array()));
        }
        exit(0);
    }
}

exit(0);
