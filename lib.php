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
 * LTI enrolment plugin main library file.
 *
 * @package enrol_ltiadv
 * @copyright 2020 Carlos Costa
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_ltiadv\data_connector;
use enrol_ltiadv\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * LTI enrolment plugin class.
 *
 * @package enrol_ltiadv
 * @copyright 2016 Mark Nelson <markn@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_ltiadv_plugin extends enrol_plugin {

    /**
     * Return true if we can add a new instance to this course.
     *
     * @param int $courseid
     * @return boolean
     */
    public function can_add_instance($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);
        return has_capability('moodle/course:enrolconfig', $context) && has_capability('enrol/ltiadv:config', $context);
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/ltiadv:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/ltiadv:config', $context);
    }

    /**
     * Returns true if it's possible to unenrol users.
     *
     * @param stdClass $instance course enrol instance
     * @return bool
     */
    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    /**
     * We are a good plugin and don't invent our own UI/validation code path.
     *
     * @return boolean
     */
    public function use_standard_editing_ui() {
        return true;
    }

    /**
     * Add new instance of enrol plugin.
     *
     * @param object $course
     * @param array $fields instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_instance($course, array $fields = null) {
        global $DB;

        $instanceid = parent::add_instance($course, $fields);

        // Add additional data to our table.
        $data = new stdClass();
        $data->enrolid = $instanceid;
        $data->timecreated = time();
        $data->timemodified = $data->timecreated;
        foreach ($fields as $field => $value) {
            $data->$field = $value;
        }

        $DB->insert_record('enrol_ltiadv_tools', $data);

        return $instanceid;
    }

    /**
     * Update instance of enrol plugin.
     *
     * @param stdClass $instance
     * @param stdClass $data modified instance fields
     * @return boolean
     */
    public function update_instance($instance, $data) {
        global $DB;

        parent::update_instance($instance, $data);

        // Remove the fields we don't want to override.
        unset($data->id);
        unset($data->timecreated);
        unset($data->timemodified);

        // Convert to an array we can loop over.
        $fields = (array) $data;

        // Update the data in our table.
        $tool = new stdClass();
        $tool->id = $data->toolid;
        $tool->timemodified = time();
        foreach ($fields as $field => $value) {
            $tool->$field = $value;
        }

        return $DB->update_record('enrol_ltiadv_tools', $tool);
    }

    /**
     * Delete plugin specific information.
     *
     * @param stdClass $instance
     * @return void
     */
    public function delete_instance($instance) {
        global $DB;

        // Get the tool associated with this instance.
        $tool = $DB->get_record('enrol_ltiadv_tools', array('enrolid' => $instance->id), 'id', MUST_EXIST);

        // Delete any users associated with this tool.
        $DB->delete_records('enrol_ltiadv_users', array('toolid' => $tool->id));

        // Delete the lti tool record.
        $DB->delete_records('enrol_ltiadv_tools', array('id' => $tool->id));

        // Time for the parent to do it's thang, yeow.
        parent::delete_instance($instance);
    }

    /**
     * Handles un-enrolling a user.
     *
     * @param stdClass $instance
     * @param int $userid
     * @return void
     */
    public function unenrol_user(stdClass $instance, $userid) {
        global $DB;

        // Get the tool associated with this instance. Note - it may not exist if we have deleted
        // the tool. This is fine because we have already cleaned the 'enrol_ltiadv_users' table.
        if ($tool = $DB->get_record('enrol_ltiadv_tools', array('enrolid' => $instance->id), 'id')) {
            // Need to remove the user from the users table.
            $DB->delete_records('enrol_ltiadv_users', array('userid' => $userid, 'toolid' => $tool->id));
        }

        parent::unenrol_user($instance, $userid);
    }

    /**
     * Add elements to the edit instance form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return bool
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        global $DB;

        $nameattribs = array('size' => '60', 'maxlength' => '255');
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'), $nameattribs);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');

        $descattribs = array('size' => '80', 'maxlength' => '255');
        $mform->addElement('text', 'customdescription', get_string('customdescription', 'enrol_ltiadv'), $descattribs);
        $mform->setType('customdescription', PARAM_TEXT);
        $mform->addHelpButton('customdescription', 'customdescription', 'enrol_ltiadv');
        $mform->addRule('customdescription', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');

        $tools = array();
        $tools[$context->id] = get_string('course');
        $modinfo = get_fast_modinfo($instance->courseid);
        $mods = $modinfo->get_cms();
        foreach ($mods as $mod) {
            $tools[$mod->context->id] = format_string($mod->name);
        }

        $mform->addElement('select', 'contextid', get_string('tooltobeprovided', 'enrol_ltiadv'), $tools);
        $mform->setDefault('contextid', $context->id);

        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_ltiadv'),
            array('optional' => true, 'defaultunit' => DAYSECS));
        $mform->setDefault('enrolperiod', 0);
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_ltiadv');

        $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_ltiadv'),
            array('optional' => true));
        $mform->setDefault('enrolstartdate', 0);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_ltiadv');

        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_ltiadv'),
            array('optional' => true));
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_ltiadv');

        $mform->addElement('text', 'maxenrolled', get_string('maxenrolled', 'enrol_ltiadv'));
        $mform->setDefault('maxenrolled', 0);
        $mform->addHelpButton('maxenrolled', 'maxenrolled', 'enrol_ltiadv');
        $mform->setType('maxenrolled', PARAM_INT);

        $assignableroles = get_assignable_roles($context);

        $mform->addElement('select', 'roleinstructor', get_string('roleinstructor', 'enrol_ltiadv'), $assignableroles);
        $mform->setDefault('roleinstructor', '3');
        $mform->addHelpButton('roleinstructor', 'roleinstructor', 'enrol_ltiadv');

        $mform->addElement('select', 'rolelearner', get_string('rolelearner', 'enrol_ltiadv'), $assignableroles);
        $mform->setDefault('rolelearner', '5');
        $mform->addHelpButton('rolelearner', 'rolelearner', 'enrol_ltiadv');

        $mform->addElement('header', 'defaultheader', get_string('userdefaultvalues', 'enrol_ltiadv'));
        $mform->setExpanded('defaultheader');

        $emaildisplay = get_config('enrol_ltiadv', 'emaildisplay');
        $choices = array(
            0 => get_string('emaildisplayno'),
            1 => get_string('emaildisplayyes'),
            2 => get_string('emaildisplaycourse')
        );
        $mform->addElement('select', 'maildisplay', get_string('emaildisplay'), $choices);
        $mform->setDefault('maildisplay', $emaildisplay);
        $mform->addHelpButton('maildisplay', 'emaildisplay');

        $lang = get_config('enrol_ltiadv', 'lang');
        $mform->addElement('select', 'lang', get_string('preferredlanguage'), get_string_manager()->get_list_of_translations());
        $mform->setDefault('lang', $lang);

        $mform->addElement('header', 'remotesystem', get_string('remotesystem', 'enrol_ltiadv'));
        $mform->setExpanded('remotesystem');

        $mform->addElement('selectyesno', 'gradesync', get_string('gradesync', 'enrol_ltiadv'));
        $mform->setDefault('gradesync', 1);
        $mform->addHelpButton('gradesync', 'gradesync', 'enrol_ltiadv');

        $mform->addElement('selectyesno', 'gradesynccompletion', get_string('requirecompletion', 'enrol_ltiadv'));
        $mform->setDefault('gradesynccompletion', 0);
        $mform->disabledIf('gradesynccompletion', 'gradesync', 0);


        // Check if we are editing an instance.
        if (!empty($instance->id)) {
            // Get the details from the enrol_ltiadv_tools table.
            $ltitool = $DB->get_record('enrol_ltiadv_tools', array('enrolid' => $instance->id), '*', MUST_EXIST);

            $mform->addElement('hidden', 'toolid');
            $mform->setType('toolid', PARAM_INT);
            $mform->setConstant('toolid', $ltitool->id);

            $mform->setDefaults((array) $ltitool);
        }
    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @param object $instance The instance loaded from the DB
     * @param context $context The context of the instance we are editing
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK.
     * @return void
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        global $COURSE, $DB;

        $errors = array();

        if (!empty($data['enrolenddate']) && $data['enrolenddate'] < $data['enrolstartdate']) {
            $errors['enrolenddate'] = get_string('enrolenddateerror', 'enrol_ltiadv');
        }

        if (!empty($data['requirecompletion'])) {
            $completion = new completion_info($COURSE);
            $moodlecontext = $DB->get_record('context', array('id' => $data['contextid']));
            if ($moodlecontext->contextlevel == CONTEXT_MODULE) {
                $cm = get_coursemodule_from_id(false, $moodlecontext->instanceid, 0, false, MUST_EXIST);
            } else {
                $cm = null;
            }

            if (!$completion->is_enabled($cm)) {
                $errors['requirecompletion'] = get_string('errorcompletionenabled', 'enrol_ltiadv');
            }
        }

        return $errors;
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        // We want to call the parent because we do not want to add an enrol_ltiadv_tools row
        // as that is done as part of the restore process.
        $instanceid = parent::add_instance($course, (array)$data);
        $step->set_mapping('enrol', $oldid, $instanceid);
    }
}

/**
 * Display the LTI link in the course administration menu.
 *
 * @param settings_navigation $navigation The settings navigation object
 * @param stdClass $course The course
 * @param stdclass $context Course context
 */
function enrol_ltiadv_extend_navigation_course($navigation, $course, $context) {
    // Check that the LTI plugin is enabled.
    if (enrol_is_enabled('ltiadv')) {
        // Check that they can add an instance.
        $ltiplugin = enrol_get_plugin('ltiadv');
        if ($ltiplugin->can_add_instance($course->id)) {
            $url = new moodle_url('/enrol/ltiadv/index.php', array('courseid' => $course->id));
            $settingsnode = navigation_node::create(get_string('sharedexternaltools', 'enrol_ltiadv'), $url,
                navigation_node::TYPE_SETTING, null, null, new pix_icon('i/settings', ''));

            $navigation->add_node($settingsnode);
        }
    }
}
