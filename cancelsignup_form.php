<?php

require_once "$CFG->dirroot/lib/formslib.php";

class mod_onetoone_cancelsignup_form extends moodleform {

    function definition()
    {
        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('cancelbooking', 'onetoone'));

        $mform->addElement('hidden', 's', $this->_customdata['s']);
        $mform->setType('s', PARAM_INT);
        
        $mform->addElement('hidden', 'backtoallsessions', $this->_customdata['backtoallsessions']);
        $mform->setType('backtoallsessions', PARAM_INT);
        
        $mform->addElement('html', get_string('cancellationconfirm', 'onetoone')); // instructions

        $mform->addElement('text', 'cancelreason', get_string('cancelreason', 'onetoone'), 'size="60" maxlength="255"');
        $mform->setType('cancelreason', PARAM_TEXT);

        $buttonarray=array();
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('yes'));
        $buttonarray[] = &$mform->createElement('cancel', 'cancelbutton', get_string('no'));
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
    }
}
