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
defined('MOODLE_INTERNAL') || die();


require_once("{$CFG->libdir}/formslib.php");
require_once("{$CFG->dirroot}/mod/onetoone/lib.php");


class mod_onetoone_session_form extends moodleform {

    function definition() {
        global $CFG, $DB;

        $mform =& $this->_form;

        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'f', $this->_customdata['f']);
        $mform->setType('f', PARAM_INT);
        $mform->addElement('hidden', 's', $this->_customdata['s']);
        $mform->setType('s', PARAM_INT);
        $mform->addElement('hidden', 'c', $this->_customdata['c']);
        $mform->setType('c', PARAM_INT);

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $editoroptions = $this->_customdata['editoroptions'];

        // Show all custom fields.
        $customfields = $this->_customdata['customfields'];
        onetoone_add_customfields_to_form($mform, $customfields);

        // Hack to put help files on these custom fields.
        // TODO: add to the admin page a feature to put help text on custom fields.
        if ($mform->elementExists('custom_location')) {
            $mform->addHelpButton('custom_location', 'location', 'onetoone');
        }
        if ($mform->elementExists('custom_venue')) {
            $mform->addHelpButton('custom_venue', 'venue', 'onetoone');
        }
        if ($mform->elementExists('custom_room')) {
            $mform->addHelpButton('custom_room', 'room', 'onetoone');
        }

        $formarray  = array();
        $formarray[] = $mform->createElement('selectyesno', 'datetimeknown', get_string('sessiondatetimeknown', 'onetoone'));
        $formarray[] = $mform->createElement('static', 'datetimeknownhint', '',
                html_writer::tag('span', get_string('datetimeknownhinttext', 'onetoone'), array('class' => 'hint-text')));
        $mform->addGroup($formarray, 'datetimeknown_group',
                get_string('sessiondatetimeknown', 'onetoone'), array(' '), false);
        $mform->addGroupRule('datetimeknown_group', null, 'required', null, 'client');
        $mform->setDefault('datetimeknown', false);
        $mform->addHelpButton('datetimeknown_group', 'sessiondatetimeknown', 'onetoone');

        $mform->setType('timestart', PARAM_INT);
        $mform->setType('timefinish', PARAM_INT);

        $mform->addElement('hidden', 'sessiondateid', 0);
        $mform->setType('sessiondateid', PARAM_INT);
        $mform->addElement('date_time_selector', 'timestart', get_string("choiceopen", "choice"));
        $mform->disabledIf('timestart', 'datetimeknown', 'eq', 0);

        $mform->addElement('date_time_selector', 'timefinish', get_string("choiceclose", "choice"));
        $mform->disabledIf('timefinish', 'datetimeknown', 'eq', 0);
        $mform->addElement('editor', 'details_editor', get_string('details', 'onetoone'), null, $editoroptions);
        $mform->setType('details_editor', PARAM_RAW);
        $mform->addHelpButton('details_editor', 'details', 'onetoone');

        // Choose users for trainer roles.
        $rolenames = onetoone_get_trainer_roles();

        if ($rolenames) {
            // Get current trainers.
            $currenttrainers = onetoone_get_trainers($this->_customdata['s']);

            // Loop through all selected roles.
            $headershown = false;
            foreach ($rolenames as $role => $rolename) {
                $rolename = $rolename->name;

                // Get course context.
                $context = context_course::instance($this->_customdata['course']->id);

                // Attempt to load users with this role in this course.
                $rs = $DB->get_recordset_sql("
                    SELECT
                        u.id,
                        u.firstname,
                        u.lastname
                    FROM
                        {role_assignments} ra
                    LEFT JOIN
                        {user} u
                      ON ra.userid = u.id
                    WHERE
                        contextid = {$context->id}
                    AND roleid = {$role}
                ");

                if (!$rs) {
                    continue;
                }

                $choices = array();
                foreach ($rs as $roleuser) {
                    $choices[$roleuser->id] = fullname($roleuser);
                }
                $rs->close();

                // Show header (if haven't already).
                if ($choices && !$headershown) {
                    $mform->addElement('header', 'trainerroles', get_string('sessionroles', 'onetoone'));
                    $headershown = true;
                }

                // If only a few, use checkboxes.
                if (count($choices) < 4) {
                    $roleshown = false;
                    foreach ($choices as $cid => $choice) {
                        // Only display the role title for the first checkbox for each role.
                        if (!$roleshown) {
                            $roledisplay = $rolename;
                            $roleshown = true;
                        } else {
                            $roledisplay = '';
                        }

                        $mform->addElement('advcheckbox', 'trainerrole['.$role.']['.$cid.']', $roledisplay, $choice, null,
                                array('', $cid));
                        $mform->setType('trainerrole['.$role.']['.$cid.']', PARAM_INT);
                    }
                } else {
                    $mform->addElement('select', 'trainerrole['.$role.']', $rolename, $choices, array('multiple' => 'multiple'));
                    $mform->setType('trainerrole['.$role.']', PARAM_SEQUENCE);
                }

                // Select current trainers.
                if ($currenttrainers) {
                    foreach ($currenttrainers as $role => $trainers) {
                        $t = array();
                        foreach ($trainers as $trainer) {
                            $t[] = $trainer->id;
                            $mform->setDefault('trainerrole['.$role.']['.$trainer->id.']', $trainer->id);
                        }

                        $mform->setDefault('trainerrole['.$role.']', implode(',', $t));
                    }
                }
            }
        }

        $this->add_action_buttons();
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $dateids = $data['sessiondateid'];
        $starttime = $data["timestart"];
        $endtime = $data["timefinish"];
        if ($starttime > $endtime) {
            $errstr = get_string('error:sessionstartafterend', 'onetoone');
            $errors['timestart'] = $errstr;
            $errors['timefinish'] = $errstr;
            unset($errstr);
        }
        return $errors;
    }
}
