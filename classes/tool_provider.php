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
 * Extends the IMS Tool provider library for the LTI enrolment.
 *
 * @package    enrol_ltiadv
 * @copyright  2020 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_ltiadv;

defined('MOODLE_INTERNAL') || die;

use context;
use core\notification;
use core_user;
use html_writer;
use IMSGlobal\LTI\Profile\Item;
use IMSGlobal\LTI\ToolProvider\ToolProvider;
use moodle_exception;
use moodle_url;
use stdClass;

require_once($CFG->dirroot . '/user/lib.php');

/**
 * Extends the IMS Tool provider library for the LTI enrolment.
 *
 * @package    enrol_ltiadv
 * @copyright  2020 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_provider extends ToolProvider {

    /**
     * @var stdClass $tool The object representing the enrol instance providing this LTI tool
     */
    protected $tool;

    /**
     * Remove $this->baseUrl (wwwroot) from a given url string and return it.
     *
     * @param string $url The url from which to remove the base url
     * @return string|null A string of the relative path to the url, or null if it couldn't be determined.
     */
    protected function strip_base_url($url) {
        if (substr($url, 0, strlen($this->baseUrl)) == $this->baseUrl) {
            return substr($url, strlen($this->baseUrl));
        }
        return null;
    }

    /**
     * Create a new instance of tool_provider to handle all the LTI tool provider interactions.
     *
     * @param int $toolid The id of the tool to be provided.
     */
    public function __construct($toolid) {
        global $CFG, $SITE;

        $tool = helper::get_lti_tool($toolid);
        $this->tool = $tool;

        $dataconnector = new data_connector();
        parent::__construct($dataconnector);

        // Override debugMode and set to the configured value.
        $this->debugMode = $CFG->debugdeveloper;

        $this->baseUrl = $CFG->wwwroot;

        $vendorid = $SITE->shortname;
        $vendorname = $SITE->fullname;
        $vendordescription = trim(html_to_text($SITE->summary));
        $this->vendor = new Item($vendorid, $vendorname, $vendordescription, $CFG->wwwroot);

        $name = helper::get_name($tool);
        $description = helper::get_description($tool);
    }

    /**
     * Override onError for custom error handling.
     * @return void
     */
    protected function onError() {
        global $OUTPUT;

        $message = $this->message;
        if ($this->debugMode && !empty($this->reason)) {
            $message = $this->reason;
        }

        // Display the error message from the provider's side if the consumer has not specified a URL to pass the error to.
        if (empty($this->returnUrl)) {
            $this->errorOutput = $OUTPUT->notification(get_string('failedrequest', 'enrol_ltiadv', ['reason' => $message]), 'error');
        }
    }

    /**
     * Performs LTI 1.3 launch.
     * @return void
     */
    public function launch($ltik, $username) {
        global $DB, $SESSION, $CFG;
        
        // Making request to Ltiaas to retrieve user launch information.
        $idtoken = helper::ltiaas_get_idtoken($ltik);
        if (!$idtoken) {
          $this->errmessage = 'Unable to retrieve ID Token.';
          return false;
        }
        $platformInfo = $idtoken['platform'];
        $userInfo = $idtoken['user'];
        $launchInfo = $idtoken['launch'];

        // Before we do anything check that the context is valid.
        $tool = $this->tool;
        $context = context::instance_by_id($tool->contextid);

        
        // Set the user data.
        $user = new stdClass();
        $user->username = $username;

        if (!empty($userInfo['given_name'])) {
            $user->firstname = $userInfo['given_name'];
        }
        if (!empty($userInfo['family_name'])) {
            $user->lastname = $userInfo['family_name'];
        } else {
            $user->lastname = $this->tool->contextid;
        }

        $user->email = core_user::clean_field($userInfo['email'], 'email');

        // Get the user data from the LTI consumer.
        $user = helper::assign_user_tool_data($tool, $user);

        // Check if the user exists.
        if (!$dbuser = $DB->get_record('user', ['username' => $user->username, 'deleted' => 0])) {
            // If the email was stripped/not set then fill it with a default one. This
            // stops the user from being redirected to edit their profile page.
            if (empty($user->email)) {
                $user->email = $user->username .  "@example.com";
            }

            $user->auth = 'lti';
            $user->id = \user_create_user($user);

            // Get the updated user record.
            $user = $DB->get_record('user', ['id' => $user->id]);
        } else {
            if (helper::user_match($user, $dbuser)) {
                $user = $dbuser;
            } else {
                // If email is empty remove it, so we don't update the user with an empty email.
                if (empty($user->email)) {
                    unset($user->email);
                }

                $user->id = $dbuser->id;
                \user_update_user($user);

                // Get the updated user record.
                $user = $DB->get_record('user', ['id' => $user->id]);
            }
        }
        if (!isset($user->firstname)) {
            $user->firstname = $user->id;
        }


        // Check if we need to force the page layout to embedded.
        if (isset($launchInfo['custom']) && isset($launchInfo['custom']['custom_force_embed']) ){
            $isforceembed = $launchInfo['custom']['custom_force_embed'] == 1;
        } else {
            $isforceembed = false;
        }
        

        // Check if we are an instructor.
        $roles = $userInfo['roles'];
        $isinstructor = in_array('http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor', $roles) || in_array('http://purl.imsglobal.org/vocab/lis/v2/institution/person#Administrator', $roles);

        if ($context->contextlevel == CONTEXT_COURSE) {
            $courseid = $context->instanceid;
            $urltogo = new moodle_url('/course/view.php', ['id' => $courseid]);

        } else if ($context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
            $urltogo = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);

            // If we are a student in the course module context we do not want to display blocks.
            if (!$isforceembed && !$isinstructor) {
                $isforceembed = true;
            }
        } else {
            print_error('invalidcontext');
            exit();
        }

        // Force page layout to embedded if necessary.
        $SESSION->forcepagelayout = 'embedded';

        // Enrol the user in the course with no role.
        $result = helper::enrol_user($tool, $user->id);

        // Display an error, if there is one.
        if ($result !== helper::ENROLMENT_SUCCESSFUL) {
            print_error($result, 'enrol_ltiadv');
            exit();
        }

        // Give the user the role in the given context.
        $roleid = $isinstructor ? $tool->roleinstructor : $tool->rolelearner;
        role_assign($roleid, $user->id, $tool->contextid);

        // Login user.

        // Check if we have recorded this user before.
        if ($userlog = $DB->get_record('enrol_ltiadv_users', ['toolid' => $tool->id, 'userid' => $user->id])) {
            $userlog->lastaccess = time();
            $DB->update_record('enrol_ltiadv_users', $userlog);
        } else {
            // Add the user details so we can use it later when syncing grades and members.
            $userlog = new stdClass();
            $userlog->userid = $user->id;
            $userlog->toolid = $tool->id;
            $userlog->lastgrade = null;
            $userlog->lastaccess = time();
            $userlog->timecreated = time();
            $DB->insert_record('enrol_ltiadv_users', $userlog);
        }

        // Finalise the user log in.
        complete_user_login($user);

        // Add cookie flag to allow cross origin cookies
        $cookies = headers_list();
        header_remove('Set-Cookie');
        $setcookiesession = 'Set-Cookie: ' . session_name() . '=';

        foreach ($cookies as $cookie) {
            if (strpos($cookie, $setcookiesession) === 0) {
                $cookie .= '; SameSite=None';
            }
            header($cookie, false);
        }

        // Everything's good. Set appropriate OK flag and message values.
        $this->ok = true;
        $this->message = get_string('success');

        if (empty($CFG->allowframembedding)) {
            // Provide an alternative link.
            $stropentool = get_string('opentool', 'enrol_ltiadv');
            echo html_writer::tag('p', get_string('frameembeddingnotenabled', 'enrol_ltiadv'));
            echo html_writer::link($urltogo, $stropentool, ['target' => '_blank']);
            return true;
        } else {
            // All done, redirect the user to where they want to go.
            redirect($urltogo);
            return true;
        }
    }
}