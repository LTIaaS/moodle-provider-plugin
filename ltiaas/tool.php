<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The main entry point for the external system.
 *
 * @package enrol_ltiaas
 * @copyright 2024 GatherAct LLC (LTIAAS)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$toolid = required_param('id', PARAM_INT);
$ltik = required_param('ltik', PARAM_TEXT);

$PAGE->set_context(context_system::instance());
$url = new moodle_url('/enrol/ltiaas/tool.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('opentool', 'enrol_ltiaas'));

// Get the tool.
$tool = \enrol_ltiaas\helper::get_lti_tool($toolid);

// Check if the authentication plugin is disabled.
if (!is_enabled_auth('lti')) {
    print_error('pluginnotenabled', 'auth', '', get_string('pluginname', 'auth_lti'));
    exit();
}

// Check if the enrolment plugin is disabled.
if (!enrol_is_enabled('ltiaas')) {
    print_error('enrolisdisabled', 'enrol_ltiaas');
    exit();
}

// Check if the enrolment instance is disabled.
if ($tool->status != ENROL_INSTANCE_ENABLED) {
    print_error('enrolisdisabled', 'enrol_ltiaas');
    exit();
}

// Initialize tool provider.
$toolprovider = new \enrol_ltiaas\tool_provider($toolid);
// Handle the request.
$response = $toolprovider->launch($ltik, $username);

echo $OUTPUT->header();
if (!$response) echo $OUTPUT->notification($toolprovider->errmessage);
echo $OUTPUT->footer();
