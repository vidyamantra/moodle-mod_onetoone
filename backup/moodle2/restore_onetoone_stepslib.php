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
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * @author(current)  Pinky Sharma <http://www.vidyamantra.com>
 * @author(current)  Suman Bogati <http://www.vidyamantra.com>
 * @author(previous) Francois Marier <francois@catalyst.net.nz>
 * @author(previous) Aaron Barnes <aaronb@catalyst.net.nz>
 * @package mod
 * @subpackage onetoone
 */


/**
 * Structure step to restore one onetoone activity
 */
class restore_onetoone_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('onetoone', '/activity/onetoone');
        $paths[] = new restore_path_element('onetoone_session', '/activity/onetoone/sessions/session');
        $paths[] = new restore_path_element('onetoone_session_data',
                '/activity/onetoone/sessions/session/session_data/session_data_element');
        $paths[] = new restore_path_element('onetoone_session_field',
                '/activity/onetoone/sessions/session/session_field/session_field_element');
        if ($userinfo) {
            $paths[] = new restore_path_element('onetoone_signup', '/activity/onetoone/sessions/session/signups/signup');
            $paths[] = new restore_path_element('onetoone_signups_status',
                    '/activity/onetoone/sessions/session/signups/signup/signups_status/signup_status');
            $paths[] = new restore_path_element('onetoone_session_roles',
                    '/activity/onetoone/sessions/session/session_roles/session_role');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_onetoone($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Insert the onetoone record.
        $newitemid = $DB->insert_record('onetoone', $data);
        $this->apply_activity_instance($newitemid);
    }


    protected function process_onetoone_session($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->onetoone = $this->get_new_parentid('onetoone');

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the entry record.
        $newitemid = $DB->insert_record('onetoone_sessions', $data);
        $this->set_mapping('onetoone_session', $oldid, $newitemid, true); // Childs and files by itemname.
    }


    protected function process_onetoone_signup($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('onetoone_session');
        $data->userid = $this->get_mappingid('user', $data->userid);

        // Insert the entry record.
        $newitemid = $DB->insert_record('onetoone_signups', $data);
        $this->set_mapping('onetoone_signup', $oldid, $newitemid, true); // Childs and files by itemname.
    }


    protected function process_onetoone_signups_status($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->signupid = $this->get_new_parentid('onetoone_signup');

        $data->timecreated = $this->apply_date_offset($data->timecreated);

        // Insert the entry record.
        $newitemid = $DB->insert_record('onetoone_signups_status', $data);
    }


    protected function process_onetoone_session_roles($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('onetoone_session');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->roleid = $this->get_mappingid('role', $data->roleid);

        // Insert the entry record.
        $newitemid = $DB->insert_record('onetoone_session_roles', $data);
    }


    protected function process_onetoone_session_data($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('onetoone_session');
        $data->fieldid = $this->get_mappingid('onetoone_session_field');

        // Insert the entry record.
        $newitemid = $DB->insert_record('onetoone_session_data', $data);
        $this->set_mapping('onetoone_session_data', $oldid, $newitemid, true); // Childs and files by itemname.
    }


    protected function process_onetoone_session_field($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Insert the entry record.
        $newitemid = $DB->insert_record('onetoone_session_field', $data);
    }


    /* protected function process_onetoone_sessions_dates($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('onetoone_session');

        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timefinish = $this->apply_date_offset($data->timefinish);

        // insert the entry record
        $newitemid = $DB->insert_record('onetoone_sessions_dates', $data);
    }*/

    protected function after_execute() {
        // One-to-one doesn't have any related files.
        // Add onetoone related files, no need to match by itemname (just internally handled context).
    }
}
