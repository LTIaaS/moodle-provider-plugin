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
 * Endpoint responsible for returning the registered tools.
 *
 * @package enrol_ltiaas
 * @copyright 2024 GatherAct LLC (LTIAAS)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



require_once(__DIR__ . '/../../config.php');

$ltik = required_param('ltik', PARAM_TEXT);
$content = json_decode(file_get_contents("php://input"), true);
$form = \enrol_ltiaas\helper::ltiaas_get_deeplinking_form($content, $ltik);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($form);
