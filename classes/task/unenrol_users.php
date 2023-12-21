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
 * Handles unenrolling users.
 *
 * @package    enrol_ltiadv
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_ltiadv\task;

use stdClass;

/**
 * Task for unenrolling LTI users.
 *
 * @package    enrol_ltiadv
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unenrol_users extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasksunenrolusers', 'enrol_ltiadv');
    }

    /**
     * Performs the unenrollment of expired users.
     *
     * @return bool|void
     */
    public function execute() {
        global $DB;
        $ltiplugin = enrol_get_plugin('ltiadv');

        // Check if the authentication plugin is disabled.
        if (!is_enabled_auth('lti')) {
            mtrace('Skipping task - ' . get_string('pluginnotenabled', 'auth', get_string('pluginname', 'auth_lti')));
            return true;
        }

        // Check if the enrolment plugin is disabled - isn't really necessary as the task should not run if
        // the plugin is disabled, but there is no harm in making sure core hasn't done something wrong.
        if (!enrol_is_enabled('ltiadv')) {
            mtrace('Skipping task - ' . get_string('enrolisdisabled', 'enrol_ltiadv'));
            return true;
        }

        // Get all the enabled tools.
        if ($tools = \enrol_ltiadv\helper::get_lti_tools(array('status' => ENROL_INSTANCE_ENABLED))) {
            foreach ($tools as $tool) {
                if (!$tool->enrolperiod) {
                  mtrace("Skipping - Skipping unenrollment of expired users for tool '$tool->id' for the course '$tool->courseid'. Enrol period disabled.");
                  continue;
                }
                mtrace("Starting - Unenrollment of expired users for tool '$tool->id' for the course '$tool->courseid'.");
                // Variables to keep track of information to display later.
                $usercount = 0;

                // We check for all the users - users can access the same tool from different consumers.
                if ($ltiusers = \enrol_ltiadv\helper::get_lti_user_enrollments(array('toolid' => $tool->id))) {
                    foreach ($ltiusers as $ltiuser) {
                        if ($ltiuser->timeend && $ltiuser->timeend < time()) {
                          // Unenrol expired user
                          $usercount++;
                          $instance = new stdClass();
                          $instance->id = $tool->enrolid;
                          $instance->courseid = $tool->courseid;
                          $instance->enrol = 'ltiadv';
                          $ltiplugin->unenrol_user($instance, $ltiuser->userid);
                        }
                    }
                }
                mtrace("Completed - Unenrolled expired users for tool '$tool->id' in the course '$tool->courseid'. " .
                    "Unenrolled $usercount users.");
                mtrace("");
            }
        }
    }
}
