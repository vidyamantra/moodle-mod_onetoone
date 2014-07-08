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

class backup_onetoone_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $onetoone = new backup_nested_element('onetoone', array('id'), array(
            'name', 'thirdparty', 'thirdpartywaitlist', 'display', 'confirmationsubject',
            'confirmationinstrmngr', 'confirmationmessage', 'waitlistedsubject', 'waitlistedmessage',
            'cancellationsubject', 'cancellationmessage', 'remindersubject', 'reminderinstrmngr',
            'remindermessage', 'reminderperiod', 'requestsubject', 'requestinstrmngr', 'requestmessage',
            'timecreated', 'timemodified', 'shortname', 'description', 'showoncalendar', 'approvalreqd'));

        $sessions = new backup_nested_element('sessions');

        $session = new backup_nested_element('session', array('id'), array(
            'onetoone', 'capacity', 'allowoverbook', 'details', 'datetimeknown', 'duration', 'normalcost',
            'discountcost', 'timestart', 'timefinish', 'timecreated', 'timemodified'));

        $signups = new backup_nested_element('signups');

        $signup = new backup_nested_element('signup', array('id'), array(
            'sessionid', 'userid', 'mailedreminder', 'discountcode', 'notificationtype'));

        $signupsstatus = new backup_nested_element('signups_status');

        $signupstatus = new backup_nested_element('signup_status', array('id'), array(
            'signupid', 'statuscode', 'superceded', 'grade', 'note', 'advice', 'createdby', 'timecreated'));

        $sessionroles = new backup_nested_element('session_roles');

        $sessionrole = new backup_nested_element('session_role', array('id'), array(
            'sessionid', 'roleid', 'userid'));

        $sessiondata = new backup_nested_element('session_data');

        // May need to replace first item 'data' with better value.
        $sessiondataelement = new backup_nested_element('data', array('id'), array(
            'fieldid', 'sessionid', 'data'));

        // Sessionid, 'timestart', 'timefinish'));.

        // Build the tree.
        $onetoone->add_child($sessions);
        $sessions->add_child($session);

        $session->add_child($signups);
        $signups->add_child($signup);

        $signup->add_child($signupsstatus);
        $signupsstatus->add_child($signupstatus);

        $session->add_child($sessionroles);
        $sessionroles->add_child($sessionrole);

        $session->add_child($sessiondata);
        $sessiondata->add_child($sessiondataelement);

        // Define sources.
        $onetoone->set_source_table('onetoone', array('id' => backup::VAR_ACTIVITYID));

        $session->set_source_table('onetoone_sessions', array('onetoone' => backup::VAR_PARENTID));

        if ($userinfo) {
            $signup->set_source_table('onetoone_signups', array('sessionid' => backup::VAR_PARENTID));

            $signupstatus->set_source_table('onetoone_signups_status', array('signupid' => backup::VAR_PARENTID));

            $sessionrole->set_source_table('onetoone_session_roles', array('sessionid' => backup::VAR_PARENTID));

            $sessiondataelement->set_source_table('onetoone_session_data', array('sessionid' => backup::VAR_PARENTID));
        }

        // Define id annotations.
        $signup->annotate_ids('user', 'userid');

        $sessionrole->annotate_ids('role', 'roleid');

        $sessionrole->annotate_ids('user', 'userid');

        $sessiondataelement->annotate_ids('onetoone_session_field', 'fieldid');

        // Define file annotations
        // None for O2O.

        // Return the root element (onetoone), wrapped into standard activity structure.
        return $this->prepare_activity_structure($onetoone);
    }
}
