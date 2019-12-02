<?php


use tool_metadata\task\file_extraction_task;

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'fileid' => 0,
        'help' => false,
        'plugin' => '',
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

if ($options['showdebugging']) {
    set_debugging(DEBUG_DEVELOPER, true);
}

if (!empty($options['fileid'] && !empty($options['plugin']))) {
    $fs = get_file_storage();
    $file = $fs->get_file_by_id($options['fileid']);

    $api = new \tool_metadata\api();
    $extraction = $api->get_file_extraction($file, $options['plugin']);

    mtrace('Initial status code: ' . $extraction->get('status'));
    mtrace('Extracting metadata for file ' . $file->get_filename());

    $task = new \tool_metadata\task\file_extraction_task();
    $task->set_custom_data(['fileid' => $file->get_id(), 'plugin' => $options['plugin']]);

    try {
        $task->execute();
    } catch (\tool_metadata\extraction_exception $ex) {
        mtrace('Extraction failed:');
        mtrace($ex->getMessage());
        mtrace($ex->getTraceAsString());
        exit(1);
    }

    $extracted = $api->get_file_extraction($file, $options['plugin']);

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
