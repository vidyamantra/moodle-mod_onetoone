<?php

require_once "$CFG->dirroot/lib/formslib.php";

class mod_onetoone_signup_form extends moodleform {

    function definition()
    {
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
        }
        else {
            $mform->addElement('html', get_string('manageremailinstructionconfirm', 'onetoone')); // instructions

            $mform->addElement('text', 'manageremail', get_string('manageremail', 'onetoone'), 'size="35"');
            $mform->addRule('manageremail', null, 'required', null, 'client');
            $mform->addRule('manageremail', null, 'email', null, 'client');
            $mform->setType('manageremail', PARAM_TEXT);
        }

        if ($showdiscountcode) {
            $mform->addElement('text', 'discountcode', get_string('discountcode', 'onetoone'), 'size="6"');
            $mform->addRule('discountcode', null, 'required', null, 'client');
            $mform->setType('discountcode', PARAM_TEXT);
        }
        else {
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

    function validation($data, $files)
    {
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
