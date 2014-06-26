<?php

require_once "$CFG->dirroot/lib/formslib.php";
require_once "$CFG->dirroot/mod/onetoone/lib.php";

class mod_onetoone_customfield_form extends moodleform {

    function definition()
    {
        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('hidden', 'id', $this->_customdata['id']);

        $mform->addElement('text', 'name', get_string('name'), 'maxlength="255" size="50"');
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setType('name', PARAM_MULTILANG);

        $mform->addElement('text', 'shortname', get_string('shortname'), 'maxlength="255" size="25"');
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->setType('shortname', PARAM_ALPHANUM);

        $options = array(CUSTOMFIELD_TYPE_TEXT        => get_string('field:text', 'onetoone'),
                         CUSTOMFIELD_TYPE_SELECT      => get_string('field:select', 'onetoone'),
                         CUSTOMFIELD_TYPE_MULTISELECT => get_string('field:multiselect', 'onetoone'),
                         );
        $mform->addElement('select', 'type', get_string('setting:type', 'onetoone'), $options);
        $mform->addRule('type', null, 'required', null, 'client');
        $mform->setDefault('type', 0);

        $mform->addElement('text', 'defaultvalue', get_string('setting:defaultvalue', 'onetoone'), 'maxlength="255" size="30"');
        $mform->setType('defaultvalue', PARAM_MULTILANG);

        $mform->addElement('textarea', 'possiblevalues', get_string('setting:possiblevalues', 'onetoone'), 'rows="5" cols="30"');
        $mform->setType('possiblevalues', PARAM_MULTILANG);
        $mform->disabledIf('possiblevalues', 'type', 'eq', 0);

        $mform->addElement('checkbox', 'required', get_string('required'));
        $mform->setDefault('required', false);
        $mform->addElement('checkbox', 'isfilter', get_string('setting:isfilter', 'onetoone'));
        $mform->setDefault('isfilter', false);
        $mform->addElement('checkbox', 'showinsummary', get_string('setting:showinsummary', 'onetoone'));
        $mform->setDefault('showinsummary', true);

        $this->add_action_buttons();
    }

    function validation($data, $files) {
        global $DB;

        $errors = array();
        $where     = "id <> ? AND shortname = ?";
        $params = array($data['id'], $data['shortname']);

        if ($DB->record_exists_select('onetoone_session_field', $where, $params)) {
            $errors['shortname']= get_string('error:shortnametaken', 'onetoone');
        }

        return $errors;
    }
}
