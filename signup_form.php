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
 * @author(previous) Francois Marier <francois@catalyst.net.nz>
 * @author(previous) Aaron Barnes <aaronb@catalyst.net.nz>
 * @package mod
 * @subpackage onetoone
 */


require_once("$CFG->dirroot/lib/formslib.php");

class mod_onetoone_signup_form extends moodleform {

    function definition() {
        $mform =& $this->_form;
        $manageremail = $this->_customdata['manageremail'];
        $showdiscountcode = $this->_customdata['showdiscountcode'];

        $mform->addElement('hidden', 's', $this->_customdata['s']);
        $mform->setType('s', PARAM_INT);
        $mform->addElement('hidden', 'backtoallsessions', $this->_customdata['backtoallsessions']);
        $mform->setType('backtoallsessions', PARAM_INT);
        if ($manageremail === false) {
            $mform->addElement('hidden', 'manageremail', '');
            $mform->setType('manageremail', PARAM_TEXT);
        } else {
            $mform->addElement('html', get_string('manageremailinstructionconfirm', 'onetoone')); // Instructions.

            $mform->addElement('text', 'manageremail', get_string('manageremail', 'onetoone'), 'size="35"');
            $mform->addRule('manageremail', null, 'required', null, 'client');
            $mform->addRule('manageremail', null, 'email', null, 'client');
            $mform->setType('manageremail', PARAM_TEXT);
        }

        if ($showdiscountcode) {
            $mform->addElement('text', 'discountcode', get_string('discountcode', 'onetoone'), 'size="6"');
            $mform->addRule('discountcode', null, 'required', null, 'client');
            $mform->setType('discountcode', PARAM_TEXT);
        } else {
            $mform->addElement('hidden', 'discountcode', '');
            $mform->setType('discountcode', PARAM_TEXT);
        }

        $options = array(MDL_O2O_BOTH => get_string('notificationboth', 'onetoone'),
                         MDL_O2O_TEXT => get_string('notificationemail', 'onetoone'),
                         MDL_O2O_ICAL => get_string('notificationical', 'onetoone'),
                         );
        $mform->addElement('select', 'notificationtype', get_string('notificationtype', 'onetoone'), $options);
        $mform->addHelpButton('notificationtype', 'notificationtype', 'onetoone');
        $mform->addRule('notificationtype', null, 'required', null, 'client');
        $mform->setDefault('notificationtype', 0);

        $this->add_action_buttons(true, get_string('signup', 'onetoone'));
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $manageremail = $data['manageremail'];
        if (!empty($manageremail)) {
            if (!onetoone_check_manageremail($manageremail)) {
                $errors['manageremail'] = onetoone_get_manageremailformat();
            }
        }
        return $errors;
    }
}
