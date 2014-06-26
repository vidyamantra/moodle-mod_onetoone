<?php

require_once "$CFG->dirroot/lib/formslib.php";
require_once "$CFG->dirroot/mod/onetoone/lib.php";

class mod_onetoone_sitenotice_form extends moodleform {

    function definition()
    {
        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('hidden', 'id', $this->_customdata['id']);

        $mform->addElement('text', 'name', get_string('name'), 'maxlength="255" size="50"');
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setType('name', PARAM_MULTILANG);

        $mform->addElement('editor', 'text', get_string('noticetext', 'onetoone'), array('rows'  => 10, 'cols'  => 64));
        $mform->setType('text', PARAM_RAW);
        $mform->addRule('text', null, 'required', null, 'client');

        $mform->addElement('header', 'conditions', get_string('conditions', 'onetoone'));
        $mform->addElement('html', get_string('conditionsexplanation', 'onetoone'));

        // Show all custom fields
        $customfields = $this->_customdata['customfields'];
        onetoone_add_customfields_to_form($mform, $customfields, true);

        $this->add_action_buttons();
    }
}
