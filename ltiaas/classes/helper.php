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
 * LTI enrolment plugin helper.
 *
 * @package enrol_ltiaas
 * @copyright 2016 Mark Nelson <markn@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_ltiaas;

defined('MOODLE_INTERNAL') || die();

/**
 * LTI enrolment plugin helper class.
 *
 * @package enrol_ltiaas
 * @copyright 2016 Mark Nelson <markn@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /*
     * The value used when we want to enrol new members and unenrol old ones.
     */
    const MEMBER_SYNC_ENROL_AND_UNENROL = 1;

    /*
     * The value used when we want to enrol new members only.
     */
    const MEMBER_SYNC_ENROL_NEW = 2;

    /*
     * The value used when we want to unenrol missing users.
     */
    const MEMBER_SYNC_UNENROL_MISSING = 3;

    /**
     * Code for when an enrolment was successful.
     */
    const ENROLMENT_SUCCESSFUL = true;

    /**
     * Error code for enrolment when max enrolled reached.
     */
    const ENROLMENT_MAX_ENROLLED = 'maxenrolledreached';

    /**
     * Error code for enrolment has not started.
     */
    const ENROLMENT_NOT_STARTED = 'enrolmentnotstarted';

    /**
     * Error code for enrolment when enrolment has finished.
     */
    const ENROLMENT_FINISHED = 'enrolmentfinished';

    /**
     * Error code for when an image file fails to upload.
     */
    const PROFILE_IMAGE_UPDATE_SUCCESSFUL = true;

    /**
     * Error code for when an image file fails to upload.
     */
    const PROFILE_IMAGE_UPDATE_FAILED = 'profileimagefailed';

    /**
     * Creates a unique username.
     *
     * @param string $consumerkey Consumer key
     * @param string $ltiuserid External tool user id
     * @return string The new username
     */
    public static function create_username($consumerkey, $ltiuserid) {
        if (!empty($ltiuserid) && !empty($consumerkey)) {
            $userkey = $consumerkey . ':' . $ltiuserid;
        } else {
            $userkey = false;
        }

        return 'enrol_ltiaas' . sha1($consumerkey . '::' . $userkey);
    }

    /**
     * Adds default values for the user object based on the tool provided.
     *
     * @param \stdClass $tool
     * @param \stdClass $user
     * @return \stdClass The $user class with added default values
     */
    public static function assign_user_tool_data($tool, $user) {
        global $CFG;

        $user->city = (!empty($tool->city)) ? $tool->city : "";
        $user->country = (!empty($tool->country)) ? $tool->country : "";
        $user->institution = (!empty($tool->institution)) ? $tool->institution : "";
        $user->timezone = (!empty($tool->timezone)) ? $tool->timezone : "";
        if (isset($tool->maildisplay)) {
            $user->maildisplay = $tool->maildisplay;
        } else if (isset($CFG->defaultpreference_maildisplay)) {
            $user->maildisplay = $CFG->defaultpreference_maildisplay;
        } else {
            $user->maildisplay = 2;
        }
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->confirmed = 1;
        $user->lang = $tool->lang;

        return $user;
    }

    /**
     * Compares two users.
     *
     * @param \stdClass $newuser The new user
     * @param \stdClass $olduser The old user
     * @return bool True if both users are the same
     */
    public static function user_match($newuser, $olduser) {
        if ($newuser->firstname != $olduser->firstname) {
            return false;
        }
        if ($newuser->lastname != $olduser->lastname) {
            return false;
        }
        if ($newuser->email != $olduser->email) {
            return false;
        }
        if ($newuser->city != $olduser->city) {
            return false;
        }
        if ($newuser->country != $olduser->country) {
            return false;
        }
        if ($newuser->institution != $olduser->institution) {
            return false;
        }
        if ($newuser->timezone != $olduser->timezone) {
            return false;
        }
        if ($newuser->maildisplay != $olduser->maildisplay) {
            return false;
        }
        if ($newuser->mnethostid != $olduser->mnethostid) {
            return false;
        }
        if ($newuser->confirmed != $olduser->confirmed) {
            return false;
        }
        if ($newuser->lang != $olduser->lang) {
            return false;
        }

        return true;
    }

    /**
     * Updates the users profile image.
     *
     * @param int $userid the id of the user
     * @param string $url the url of the image
     * @return bool|string true if successful, else a string explaining why it failed
     */
    public static function update_user_profile_image($userid, $url) {
        global $CFG, $DB;

        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/gdlib.php');

        $fs = get_file_storage();

        $context = \context_user::instance($userid, MUST_EXIST);
        $fs->delete_area_files($context->id, 'user', 'newicon');

        $filerecord = array(
            'contextid' => $context->id,
            'component' => 'user',
            'filearea' => 'newicon',
            'itemid' => 0,
            'filepath' => '/'
        );

        $urlparams = array(
            'calctimeout' => false,
            'timeout' => 5,
            'skipcertverify' => true,
            'connecttimeout' => 5
        );

        try {
            $fs->create_file_from_url($filerecord, $url, $urlparams);
        } catch (\file_exception $e) {
            return get_string($e->errorcode, $e->module, $e->a);
        }

        $iconfile = $fs->get_area_files($context->id, 'user', 'newicon', false, 'itemid', false);

        // There should only be one.
        $iconfile = reset($iconfile);

        // Something went wrong while creating temp file - remove the uploaded file.
        if (!$iconfile = $iconfile->copy_content_to_temp()) {
            $fs->delete_area_files($context->id, 'user', 'newicon');
            return self::PROFILE_IMAGE_UPDATE_FAILED;
        }

        // Copy file to temporary location and the send it for processing icon.
        $newpicture = (int) process_new_icon($context, 'user', 'icon', 0, $iconfile);
        // Delete temporary file.
        @unlink($iconfile);
        // Remove uploaded file.
        $fs->delete_area_files($context->id, 'user', 'newicon');
        // Set the user's picture.
        $DB->set_field('user', 'picture', $newpicture, array('id' => $userid));
        return self::PROFILE_IMAGE_UPDATE_SUCCESSFUL;
    }

    /**
     * Enrol a user in a course.
     *
     * @param \stdclass $tool The tool object (retrieved using self::get_lti_tool() or self::get_lti_tools())
     * @param int $userid The user id
     * @return bool|string returns true if successful, else an error code
     */
    public static function enrol_user($tool, $userid) {
        global $DB;

        // Check if the user enrolment exists.
        if (!$DB->record_exists('user_enrolments', array('enrolid' => $tool->enrolid, 'userid' => $userid))) {
            // Check if the maximum enrolled limit has been met.
            if ($tool->maxenrolled) {
                if ($DB->count_records('user_enrolments', array('enrolid' => $tool->enrolid)) >= $tool->maxenrolled) {
                    return self::ENROLMENT_MAX_ENROLLED;
                }
            }
            // Check if the enrolment has not started.
            if ($tool->enrolstartdate && time() < $tool->enrolstartdate) {
                return self::ENROLMENT_NOT_STARTED;
            }
            // Check if the enrolment has finished.
            if ($tool->enrolenddate && time() > $tool->enrolenddate) {
                return self::ENROLMENT_FINISHED;
            }

            $timeend = 0;
            if ($tool->enrolperiod) {
                $timeend = time() + $tool->enrolperiod;
            }

            // Finally, enrol the user.
            $instance = new \stdClass();
            $instance->id = $tool->enrolid;
            $instance->courseid = $tool->courseid;
            $instance->enrol = 'ltiaas';
            $instance->status = $tool->status;
            $ltienrol = enrol_get_plugin('ltiaas');

            // Hack - need to do this to workaround DB caching hack. See MDL-53977.
            $timestart = intval(substr(time(), 0, 8) . '00') - 1;
            $ltienrol->enrol_user($instance, $userid, null, $timestart, $timeend);
        }

        return self::ENROLMENT_SUCCESSFUL;
    }

    /**
     * Returns the LTI tool.
     *
     * @param int $toolid
     * @return \stdClass the tool
     */
    public static function get_lti_tool($toolid) {
        global $DB;

        $sql = "SELECT elt.*, e.name, e.courseid, e.status, e.enrolstartdate, e.enrolenddate, e.enrolperiod
                  FROM {enrol_ltiaas_tools} elt
                  JOIN {enrol} e
                    ON elt.enrolid = e.id
                 WHERE elt.id = :tid";

        return $DB->get_record_sql($sql, array('tid' => $toolid), MUST_EXIST);
    }

    /**
     * Returns the LTI tools requested.
     *
     * @param array $params The list of SQL params (eg. array('columnname' => value, 'columnname2' => value)).
     * @param int $limitfrom return a subset of records, starting at this point (optional).
     * @param int $limitnum return a subset comprising this many records in total
     * @return array of tools
     */
    public static function get_lti_tools($params = array(), $limitfrom = 0, $limitnum = 0) {
        global $DB;

        $sql = "SELECT elt.*, e.name, e.courseid, e.status, e.enrolstartdate, e.enrolenddate, e.enrolperiod
                  FROM {enrol_ltiaas_tools} elt
                  JOIN {enrol} e
                    ON elt.enrolid = e.id";
        if ($params) {
            $where = "WHERE";
            foreach ($params as $colname => $value) {
                $sql .= " $where $colname = :$colname";
                $where = "AND";
            }
        }
        $sql .= " ORDER BY elt.timecreated";

        return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    }

    /**
     * Returns a list of LTI Advantage enrolled users.
     *
     * @param array $params The list of SQL params (eg. array('columnname' => value, 'columnname2' => value)).
     * @return array of users
     */
    public static function get_lti_user_enrollments($params = array()) {
      global $DB;

      $sql = "SELECT elt.*, e.timestart, e.timeend, e.timecreated, e.timemodified
                FROM {enrol_ltiaas_users} elt
                JOIN {user_enrolments} e
                  ON elt.userid = e.userid";
      if ($params) {
          $where = "WHERE";
          foreach ($params as $colname => $value) {
              $sql .= " $where $colname = :$colname";
              $where = "AND";
          }
      }
      $sql .= " ORDER BY elt.timecreated";

      return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns a list of LTI Advantage service key records.
     *
     * @param array $params The list of SQL params (eg. array('columnname' => value, 'columnname2' => value)).
     * @return array of service key records
     */
    public static function get_lti_service_key_records($params = array()) {
        global $DB;
  
        $sql = "SELECT els.*
                  FROM {enrol_ltiaas_servicekeys} els";
        if ($params) {
            $where = "WHERE";
            foreach ($params as $colname => $value) {
                $sql .= " $where $colname = :$colname";
                $where = "AND";
            }
        }
        return $DB->get_records_sql($sql, $params);
      }

    /**
     * Returns the number of LTI tools.
     *
     * @param array $params The list of SQL params (eg. array('columnname' => value, 'columnname2' => value)).
     * @return int The number of tools
     */
    public static function count_lti_tools($params = array()) {
        global $DB;

        $sql = "SELECT COUNT(*)
                  FROM {enrol_ltiaas_tools} elt
                  JOIN {enrol} e
                    ON elt.enrolid = e.id";
        if ($params) {
            $where = "WHERE";
            foreach ($params as $colname => $value) {
                $sql .= " $where $colname = :$colname";
                $where = "AND";
            }
        }

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Create a IMS POX body request for sync grades.
     *
     * @param string $source Sourceid required for the request
     * @param float $grade User final grade
     * @return string
     */
    public static function create_service_body($source, $grade) {
        return '<?xml version="1.0" encoding="UTF-8"?>
            <imsx_POXEnvelopeRequest xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
              <imsx_POXHeader>
                <imsx_POXRequestHeaderInfo>
                  <imsx_version>V1.0</imsx_version>
                  <imsx_messageIdentifier>' . (time()) . '</imsx_messageIdentifier>
                </imsx_POXRequestHeaderInfo>
              </imsx_POXHeader>
              <imsx_POXBody>
                <replaceResultRequest>
                  <resultRecord>
                    <sourcedGUID>
                      <sourcedId>' . $source . '</sourcedId>
                    </sourcedGUID>
                    <result>
                      <resultScore>
                        <language>en-us</language>
                        <textString>' . $grade . '</textString>
                      </resultScore>
                    </result>
                  </resultRecord>
                </replaceResultRequest>
              </imsx_POXBody>
            </imsx_POXEnvelopeRequest>';
    }

    /**
     * Returns the url to launch the lti tool.
     *
     * @param int $toolid the id of the shared tool
     * @return \moodle_url the url to launch the tool
     * @since Moodle 3.2
     */
    public static function get_launch_url($toolid) {
        $url = get_config('enrol_ltiaas', 'ltiaasurl');
        $url_parts = parse_url($url);
        if (!isset($url_parts['path'])) $url_parts['path'] = '';
        $url_parts['path'] .= '/lti/launch';

        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $params);
        } else {
            $params = array();
        }

        $params['id'] = $toolid;

        $url_parts['query'] = http_build_query($params);

        $launch_url = helper::build_url($url_parts);
        
        return $launch_url;
    }

    /**
     * Returns the context's idtoken.
     *
     * @param string $ltik Ltik key.
     * @return - Idtoken
     * @since Moodle 3.10
     */
    public static function ltiaas_get_idtoken($ltik) {
        $ltiaas = get_config('enrol_ltiaas', 'ltiaasurl');
        $api_key = get_config('enrol_ltiaas', 'ltiaasapikey');
        $url_parts = parse_url($ltiaas);
        
        if (!isset($url_parts['path'])) $url_parts['path'] = '';
        $url_parts['path'] .= '/api/idtoken';
        $service_url = helper::build_url($url_parts);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $service_url);
        $customHeaders = array(
          'Authorization: LTIK-AUTH-V1 Token=' . $ltik . ', Additional=Bearer ' . $api_key
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $customHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (intval($httpcode) != 200) return false;
        return json_decode($response, true);
    }

    /**
     * Returns username based on the context.
     *
     * @param object $idtoken ID Token.
     * @return string username
     * @since Moodle 3.10
     */
    public static function build_username($idtoken) {
        if (!empty($idtoken['user']['email'])) {
          return $idtoken['user']['email'];
        }
        $iss = $idtoken['platform']['url'];
        $client_id = $idtoken['platform']['clientId'];
        $deployment_id = $idtoken['platform']['deploymentId'];
        $consumer_key = $iss . $client_id . $deployment_id;
        $user_key = $consumer_key . ':' . $idtoken['user']['id'];
        $username = 'enrol_lti' . sha1($consumer_key . '::' . $user_key);
        return $username;
    }
  
    /**
     * Returns context ID based on the course and resource IDs.
     *
     * @param object $idtoken ID Token.
     * @return string username
     * @since Moodle 3.10
     */
    public static function build_context_id($idtoken) {
        $resource_id = $idtoken['launch']['resource']['id'];
        $context_id = $idtoken['launch']['context']['id'];
        if (isset($context_id)) {
            return $context_id . '::' .$resource_id;
        }
        return 'unknowncontext_' . '::' . $resource_id;
    }
  
    /**
     * Synchronizes grades
     */
    public static function ltiaas_post_score($score, $service_key) {
        $base_url = get_config('enrol_ltiaas', 'ltiaasurl');
        $api_key = get_config('enrol_ltiaas', 'ltiaasapikey');
        
        $lineitems = helper::ltiaas_get_lineitems($base_url, $api_key, $service_key);
        if (isset($lineitems['err'])) {
            $result = [];
            $result['err'] = $lineitems['err'];
            return $result;
        }
        if (sizeof($lineitems) == 0) {
            $result = [];
            $result['err'] = 'NOT_LINEITEM_FOUND';
            return $result;
        }
        $lineitem = $lineitems[0]['id'];
        
        $url_parts = parse_url($base_url); 
        if (!isset($url_parts['path'])) $url_parts['path'] = '';
        $url_parts['path'] .= '/api/lineitems/' . urlencode($lineitem) . '/scores';
        $scores_url = helper::build_url($url_parts);
  
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $scores_url);
        $customHeaders = array(
            'Authorization: SERVICE-AUTH-V1 ' . $api_key . ':' . $service_key,
            'Content-Type: application/json'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $customHeaders);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($score));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result = json_decode($response, true);
        if (intval($httpcode) != 201) {
            $result['err'] = $result['details']['message'];
        }
        return $result;
    }

    /**
     * Retrieves lineitems
     */
    public static function ltiaas_get_lineitems($base_url, $api_key, $service_key) {
        $url_parts = parse_url($base_url);
        
        if (!isset($url_parts['path'])) $url_parts['path'] = '';
        $url_parts['path'] .= '/api/lineitems';
        $lineitems_url = helper::build_url($url_parts);
  
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $lineitems_url);
        $customHeaders = array(
            'Authorization: SERVICE-AUTH-V1 ' . $api_key . ':' . $service_key,
            'Content-Type: application/json'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $customHeaders);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result = json_decode($response, true);
        if (intval($httpcode) != 200) {
            $result['err'] = $result['details']['message'];
        }
        return $result;
      }

     /**
     * Get deep linking form
     *
     * @param object $score Score object.
     * @since Moodle 3.10
     */
    public static function ltiaas_get_deeplinking_form($contentItem, $ltik) {
        $ltiaas = get_config('enrol_ltiaas', 'ltiaasurl');
        $api_key = get_config('enrol_ltiaas', 'ltiaasapikey');
        $url_parts = parse_url($ltiaas);
        
        if (!isset($url_parts['path'])) $url_parts['path'] = '';
        $url_parts['path'] .= '/api/deeplinking/form';
        $service_url = helper::build_url($url_parts);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $service_url);
        $customHeaders = array(
            'Authorization: LTIK-AUTH-V1 Token=' . $ltik . ', Additional=Bearer ' . $api_key,
            'Content-Type: application/json'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $customHeaders);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($contentItem));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result = json_decode($response, true);
        if (intval($httpcode) != 201) {
            if(isset($result['details']['message'])) {
                $result['err'] = $result['details']['message'];
            } else {
                $result['err'] = $result['error'];
            }
        }
        return $result;
      }


    /**
     * Builds an URL.
     *
     * @param array $url_parts URL parts..
     * @return - Built URL.
     * @since Moodle 3.2
     */
    public static function build_url($url_parts) {
        $url = $url_parts['scheme'] . '://';
        if (isset($url_parts['user'])) {
            $url .= $url_parts['user'];
            if (isset($url_parts['pass'])) {
                $url .= ':' . $url_parts['pass'] . '@';
            } else {
                $url .= '@';
            }
        }
        if (isset($url_parts['host'])) {
          $url .= $url_parts['host'];
        }
        if (isset($url_parts['port'])) {
            $url .= ':' . $url_parts['port'];
        }
        if (isset($url_parts['path'])) {
          $url .= $url_parts['path'];
        }
        if (isset($url_parts['query'])) {
            $url .= '?' . $url_parts['query'];
        }
        if (isset($url_parts['anchor'])) {
            $url .= '#' . $url_parts['anchor'];
        }
        return $url;
    }

    /**
     * Returns the name of the lti enrolment instance, or the name of the course/module being shared.
     *
     * @param \stdClass $tool The lti tool
     * @return string The name of the tool
     * @since Moodle 3.2
     */
    public static function get_name($tool) {
        $name = null;

        if (empty($tool->name)) {
            $toolcontext = \context::instance_by_id($tool->contextid);
            $name = $toolcontext->get_context_name();
        } else {
            $name = $tool->name;
        };

        return $name;
    }

    /**
     * Returns a description of the course or module that this lti instance points to.
     *
     * @param \stdClass $tool The lti tool
     * @return string A description of the tool
     * @since Moodle 3.2
     */
    public static function get_description($tool) {
        if (!empty($tool->customdescription)) {
          return $tool->customdescription;
        }
        $context = \context::instance_by_id($tool->contextid);
        return self::get_context_description($context);
    }

    /**
     * Returns a description of context.
     *
     * @param context $context
     * @return string A description of the context
     * @since Moodle 3.2
     */
    public static function get_context_description($context) {
      global $DB;
      $description = '';
      if ($context->contextlevel == CONTEXT_COURSE) {
          $course = $DB->get_record('course', array('id' => $context->instanceid));
          $description = $course->summary;
      } else if ($context->contextlevel == CONTEXT_MODULE) {
          $cmid = $context->instanceid;
          $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
          $module = $DB->get_record($cm->modname, array('id' => $cm->instance));
          $description = $module->intro;
      }
      return trim(html_to_text($description));
    }

    /**
     * Returns a name of context.
     *
     * @param context $context
     * @return string A name of the context
     * @since Moodle 3.2
     */
    public static function get_context_name($context) {
        global $DB;
        $name = '';
        if ($context->contextlevel == CONTEXT_COURSE) {
            $course = $DB->get_record('course', array('id' => $context->instanceid));
            $name = $course->fullname;
        } else if ($context->contextlevel == CONTEXT_BLOCK) {
            $block = $DB->get_record('block', array('id' => $context->instanceid));
            $name = $block->name;
        } else if ($context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
            $module = $DB->get_record($cm->modname, array('id' => $cm->instance));
            $name = $module->name;
        }
        return trim(html_to_text($name));
      } 

    /**
     * Returns the icon of the tool.
     *
     * @param \stdClass $tool The lti tool
     * @return \moodle_url A url to the icon of the tool
     * @since Moodle 3.2
     */
    public static function get_icon($tool) {
        global $OUTPUT;
        return $OUTPUT->favicon();
    }

    /**
     * Returns the url to the cartridge representing the tool.
     *
     * If you have slash arguments enabled, this will be a nice url ending in cartridge.xml.
     * If not it will be a php page with some parameters passed.
     *
     * @param \stdClass $tool The lti tool
     * @return string The url to the cartridge representing the tool
     * @since Moodle 3.2
     */
    public static function get_cartridge_url($tool) {
        global $CFG;
        $url = null;

        $id = $tool->id;
        $token = self::generate_cartridge_token($tool->id);
        if ($CFG->slasharguments) {
            $url = new \moodle_url('/enrol/ltiaas/cartridge.php/' . $id . '/' . $token . '/cartridge.xml');
        } else {
            $url = new \moodle_url('/enrol/ltiaas/cartridge.php',
                    array(
                        'id' => $id,
                        'token' => $token
                    )
                );
        }
        return $url;
    }

    /**
     * Returns the url to the tool proxy registration url.
     *
     * If you have slash arguments enabled, this will be a nice url ending in cartridge.xml.
     * If not it will be a php page with some parameters passed.
     *
     * @param \stdClass $tool The lti tool
     * @return string The url to the cartridge representing the tool
     */
    public static function get_proxy_url($tool) {
        global $CFG;
        $url = null;

        $id = $tool->id;
        $token = self::generate_proxy_token($tool->id);
        if ($CFG->slasharguments) {
            $url = new \moodle_url('/enrol/ltiaas/proxy.php/' . $id . '/' . $token . '/');
        } else {
            $url = new \moodle_url('/enrol/ltiaas/proxy.php',
                    array(
                        'id' => $id,
                        'token' => $token
                    )
                );
        }
        return $url;
    }

    /**
     * Returns a unique hash for this site and this enrolment instance.
     *
     * Used to verify that the link to the cartridge has not just been guessed.
     *
     * @param int $toolid The id of the shared tool
     * @return string MD5 hash of combined site ID and enrolment instance ID.
     * @since Moodle 3.2
     */
    public static function generate_cartridge_token($toolid) {
        $siteidentifier = get_site_identifier();
        $checkhash = md5($siteidentifier . '_enrol_ltiaas_cartridge_' . $toolid);
        return $checkhash;
    }

    /**
     * Returns a unique hash for this site and this enrolment instance.
     *
     * Used to verify that the link to the proxy has not just been guessed.
     *
     * @param int $toolid The id of the shared tool
     * @return string MD5 hash of combined site ID and enrolment instance ID.
     * @since Moodle 3.2
     */
    public static function generate_proxy_token($toolid) {
        $siteidentifier = get_site_identifier();
        $checkhash = md5($siteidentifier . '_enrol_ltiaas_proxy_' . $toolid);
        return $checkhash;
    }

    /**
     * Verifies that the given token matches the cartridge token of the given shared tool.
     *
     * @param int $toolid The id of the shared tool
     * @param string $token hash for this site and this enrolment instance
     * @return boolean True if the token matches, false if it does not
     * @since Moodle 3.2
     */
    public static function verify_cartridge_token($toolid, $token) {
        return $token == self::generate_cartridge_token($toolid);
    }

    /**
     * Verifies that the given token matches the proxy token of the given shared tool.
     *
     * @param int $toolid The id of the shared tool
     * @param string $token hash for this site and this enrolment instance
     * @return boolean True if the token matches, false if it does not
     * @since Moodle 3.2
     */
    public static function verify_proxy_token($toolid, $token) {
        return $token == self::generate_proxy_token($toolid);
    }

    /**
     * Returns the parameters of the cartridge as an associative array of partial xpath.
     *
     * @param int $toolid The id of the shared tool
     * @return array Recursive associative array with partial xpath to be concatenated into an xpath expression
     *     before setting the value.
     * @since Moodle 3.2
     */
    protected static function get_cartridge_parameters($toolid) {
        global $PAGE, $SITE;
        $PAGE->set_context(\context_system::instance());

        // Get the tool.
        $tool = self::get_lti_tool($toolid);

        // Work out the name of the tool.
        $title = self::get_name($tool);
        $launchurl = self::get_launch_url($toolid);
        $launchurl = $launchurl->out(false);
        $iconurl = self::get_icon($tool);
        $iconurl = $iconurl->out(false);
        $securelaunchurl = null;
        $secureiconurl = null;
        $vendorurl = new \moodle_url('/');
        $vendorurl = $vendorurl->out(false);
        $description = self::get_description($tool);

        // If we are a https site, we can add the launch url and icon urls as secure equivalents.
        if (\is_https()) {
            $securelaunchurl = $launchurl;
            $secureiconurl = $iconurl;
        }

        return array(
                "/cc:cartridge_basiclti_link" => array(
                    "/blti:title" => $title,
                    "/blti:description" => $description,
                    "/blti:extensions" => array(
                            "/lticm:property[@name='icon_url']" => $iconurl,
                            "/lticm:property[@name='secure_icon_url']" => $secureiconurl
                        ),
                    "/blti:launch_url" => $launchurl,
                    "/blti:secure_launch_url" => $securelaunchurl,
                    "/blti:icon" => $iconurl,
                    "/blti:secure_icon" => $secureiconurl,
                    "/blti:vendor" => array(
                            "/lticp:code" => $SITE->shortname,
                            "/lticp:name" => $SITE->fullname,
                            "/lticp:description" => trim(html_to_text($SITE->summary)),
                            "/lticp:url" => $vendorurl
                        )
                )
            );
    }

    /**
     * Traverses a recursive associative array, setting the properties of the corresponding
     * xpath element.
     *
     * @param \DOMXPath $xpath The xpath with the xml to modify
     * @param array $parameters The array of xpaths to search through
     * @param string $prefix The current xpath prefix (gets longer the deeper into the array you go)
     * @return void
     * @since Moodle 3.2
     */
    protected static function set_xpath($xpath, $parameters, $prefix = '') {
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                self::set_xpath($xpath, $value, $prefix . $key);
            } else {
                $result = @$xpath->query($prefix . $key);
                if ($result) {
                    $node = $result->item(0);
                    if ($node) {
                        if (is_null($value)) {
                            $node->parentNode->removeChild($node);
                        } else {
                            $node->nodeValue = s($value);
                        }
                    }
                } else {
                    throw new \coding_exception('Please check your XPATH and try again.');
                }
            }
        }
    }

    /**
     * Create an IMS cartridge for the tool.
     *
     * @param int $toolid The id of the shared tool
     * @return string representing the generated cartridge
     * @since Moodle 3.2
     */
    public static function create_cartridge($toolid) {
        $cartridge = new \DOMDocument();
        $cartridge->load(realpath(__DIR__ . '/../xml/imslticc.xml'));
        $xpath = new \DOMXpath($cartridge);
        $xpath->registerNamespace('cc', 'http://www.imsglobal.org/xsd/imslticc_v1p0');
        $parameters = self::get_cartridge_parameters($toolid);
        self::set_xpath($xpath, $parameters);

        return $cartridge->saveXML();
    }

    // Add a parent of a tool to the array of tools (for display only)
    /*protected static function add_parent($context, $tools) {
        foreach ($tools as $t) {
            if ($t["id"] == $context->id) {
                return []; // don't create a duplicate
            }
        }
        $new_tools = array();
        $new_tools = $tools;
        $parent_id = 0;
        $iconurl = "";
        if($context->depth > 3) {
            $parent_context = $context->get_parent_context();
            $parents = self::add_parent($parent_context, $tools);
            $new_tools = array_merge($new_tools, $parents);
            $parent_id = $parent_context->id;
        } else {
            // must be a course
            $course = get_course($context->instanceid);
            $iconurl = \core_course\external\course_summary_exporter::get_course_image($course);
        }

        array_push($new_tools, [
            "url" => "",
            "icon" => $iconurl,
            "name" => \enrol_ltiaas\helper::get_context_name($context),
            "description" => \enrol_ltiaas\helper::get_context_description($context),
            "parent" => $parent_id,
            "depth" => $context->depth,
            "id" => $context->id
        ]);
        return $new_tools;
    }*/
  
    // Add a tool to the array of tools
    protected static function add_tool($tool, $tools) {
        $context = \context::instance_by_id($tool->contextid);
        //$parent_id = 0;
        $new_tools = array();
        $new_tools = $tools;
        $iconurl = "";
        if($context->depth > 3) {
            $parent_context = $context->get_parent_context();
            //$parents = self::add_parent($parent_context, $tools);
            //$new_tools = array_merge($new_tools, $parents);
            //$parent_id = $parent_context->id;
            if($context->contextlevel == CONTEXT_MODULE) {
                $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
                try {
                    $iconurl = get_fast_modinfo($parent_context->instanceid)->get_cm($cm->instance)->get_icon_url()->out(false);
                }catch (\Exception $e) {
                    $iconurl = "";
                }
            }
        }
        if($context->contextlevel == CONTEXT_COURSE) {
            $course = get_course($context->instanceid);
            $iconurl = \core_course\external\course_summary_exporter::get_course_image($course);
        }
        array_push($new_tools, [
            "url" => \enrol_ltiaas\helper::get_launch_url($tool->id),
            "icon" => $iconurl,
            "name" => \enrol_ltiaas\helper::get_context_name($context),
            "description" => \enrol_ltiaas\helper::get_description($tool),
            "type" => ($context->contextlevel == CONTEXT_COURSE) ? "COURSE" : "MODULE",
            //"parent" => $parent_id,
            "depth" => $context->depth,
            "id" => $context->id
        ]);
        return $new_tools;
    }

    public static function get_tools_object($tools) {
        $response = [];
        foreach ($tools as $key => $value) {
            $new_tools = self::add_tool($value, $response);
            $response = array_merge($response, $new_tools);
        }

        // Reverse array, so the first occurrence kept.
        $response = array_reverse($response);
        // remove duplicates
        $result = array_reverse( // Reverse array to the initial order.
            array_values( // Get rid of string keys (make array indexed again).
                array_combine( // Create array taking keys from column and values from the base array.
                    array_column($response, 'url'), 
                    $response
                )
            )
        );
        return $result;


        // Find the parent courses of modules so that the UI can display them hierarhically
        /*$output = [];
        foreach ($response as $k1 => $v1) {
            if($v1["depth"] == 3) {
                //print v1 (Course)
                $course = $v1;
                $course_children = [];
                foreach ($response as $k2 => $v2) {
                    if($v2["parent"] == $v1["id"]) {
                        // print v2 (Module)
                        $module = $v2;
                        $module_children = [];
                        foreach ($response as $k3 => $v3) {
                            if($v3["parent"] == $v2["id"]) {
                                // print v3 (Activity)
                                $activity = $v3;
                                array_push($module_children, $activity);
                            }
                        }
                        $module["children"] = $module_children;
                        array_push($course_children, $module);
                    }
                }
                $course["children"] = $course_children;
                array_push($output, $course);
            }
        }
        return $output;*/
    }
}