<?php
global $DB;
require_once '../../config.php';
require_once 'customfield_form.php';

$id      = required_param('id', PARAM_INT); // ID in onetoone_session_field
$d       = optional_param('d', false, PARAM_BOOL); // set to true to delete the given field
$confirm = optional_param('confirm', false, PARAM_BOOL); // delete confirmationx

$field = null;
if ($id > 0) {
    if (!$field = $DB->get_record('onetoone_session_field', array('id' => $id))) {
        print_error('error:fieldidincorrect', 'onetoone', '', $id);
    }
}

$PAGE->set_url('/mod/onetoone/customfield.php', array('id' => $id, 'd' => $d, 'confirm' => $confirm));

admin_externalpage_setup('managemodules'); // this is hacky, tehre should be a special hidden page for it

$contextsystem = context_system::instance();

require_capability('moodle/site:config', $contextsystem);

$returnurl = "$CFG->wwwroot/admin/settings.php?section=modsettingonetoone";

// Header

$title = get_string('addnewfield', 'onetoone');
if ($field != null) {
    $title = $field->name;
}

$PAGE->set_title($title);

// Handle deletions
if (!empty($d)) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    if (!$confirm) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading($title);
        $optionsyes = array('id' => $id, 'sesskey' => $USER->sesskey, 'd' => 1, 'confirm' => 1);
        echo $OUTPUT->confirm(get_string('fielddeleteconfirm', 'onetoone', format_string($field->name)),
            new moodle_url("customfield.php", $optionsyes),
            new moodle_url($returnurl));
        echo $OUTPUT->footer();
        exit;
    }
    else {
        $transaction = $DB->start_delegated_transaction();

        try {
            if (!$DB->delete_records('onetoone_session_field', array('id' => $id))) {
                throw new Exception(get_string('error:couldnotdeletefield', 'onetoone'));
            }

            if (!$DB->delete_records('onetoone_session_data', array('fieldid' => $id))) {
                throw new Exception(get_string('error:couldnotdeletefield', 'onetoone'));
            }

            $transaction->allow_commit();
        } catch (Exception $e) {
            $transaction->rollback($e);
        }

        redirect($returnurl);
    }
}

$mform = new mod_onetoone_customfield_form(null, compact('id'));
if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) { // Form submitted

    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'onetoone', $returnurl);
    }

    // Post-process the input
    if (empty($fromform->required)) {
        $fromform->required = 0;
    }
    if (empty($fromform->isfilter)) {
        $fromform->isfilter = 0;
    }
    if (empty($fromform->showinsummary)) {
        $fromform->showinsummary = 0;
    }
    if (empty($fromform->type)) {
        $fromform->possiblevalues = '';
    }

    $values_list = explode("\n", trim($fromform->possiblevalues));
    $pos_vals = array();
    foreach ($values_list as $val) {
        $trimmed_val = trim($val);
        if (strlen($trimmed_val) != 0) {
            $pos_vals[] = $trimmed_val;
        }
    }

    $todb = new stdClass();
    $todb->name = trim($fromform->name);
    $todb->shortname = trim($fromform->shortname);
    $todb->type = $fromform->type;
    $todb->defaultvalue = trim($fromform->defaultvalue);
    $todb->possiblevalues = implode(CUSTOMFIELD_DELIMITER, $pos_vals);
    $todb->required = $fromform->required;
    $todb->isfilter = $fromform->isfilter;
    $todb->showinsummary = $fromform->showinsummary;

    if ($field != null) {
        $todb->id = $field->id;
        if (!$DB->update_record('onetoone_session_field', $todb)) {
            print_error('error:couldnotupdatefield', 'onetoone', $returnurl);
        }
    }
    else {
        if (!$DB->insert_record('onetoone_session_field', $todb)) {
            print_error('error:couldnotaddfield', 'onetoone', $returnurl);
        }
    }

    redirect($returnurl);
}
elseif ($field != null) { // Edit mode
    // Set values for the form
    $toform = new stdClass();
    $toform->name = $field->name;
    $toform->shortname = $field->shortname;
    $toform->type = $field->type;
    $toform->defaultvalue = $field->defaultvalue;
    $value_array = explode(CUSTOMFIELD_DELIMITER, $field->possiblevalues);
    $possible_values = implode(PHP_EOL, $value_array);
    $toform->possiblevalues = $possible_values;
    $toform->required = ($field->required == 1);
    $toform->isfilter = ($field->isfilter == 1);
    $toform->showinsummary = ($field->showinsummary == 1);

    $mform->set_data($toform);
}

echo $OUTPUT->header();

echo $OUTPUT->box_start();
echo $OUTPUT->heading($title);

$mform->display();

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
