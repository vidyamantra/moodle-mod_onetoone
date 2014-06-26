<?php
global $DB, $OUTPUT, $PAGE;

require_once '../../config.php';
require_once 'lib.php';
require_once 'session_form.php';

$id = optional_param('id', 0, PARAM_INT); // Course Module ID
$f = optional_param('f', 0, PARAM_INT); // onetoone Module ID
$s = optional_param('s', 0, PARAM_INT); // onetoone session ID
$c = optional_param('c', 0, PARAM_INT); // copy session
$d = optional_param('d', 0, PARAM_INT); // delete session
$confirm = optional_param('confirm', false, PARAM_BOOL); // delete confirmation

$nbdays = 1; // default number to show

$session = null;
if ($id && !$s) {
    if (!$cm = $DB->get_record('course_modules', array('id' => $id))) {
        print_error('error:incorrectcoursemoduleid', 'onetoone');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('error:coursemisconfigured', 'onetoone');
    }
    if (!$onetoone =$DB->get_record('onetoone',array('id' => $cm->instance))) {
        print_error('error:incorrectcoursemodule', 'onetoone');
    }
}
elseif ($s) {
     if (!$session = onetoone_get_session($s)) {
         print_error('error:incorrectcoursemodulesession', 'onetoone');
     }
     if (!$onetoone = $DB->get_record('onetoone',array('id' => $session->onetoone))) {
         print_error('error:incorrectonetooneid', 'onetoone');
     }
     if (!$course = $DB->get_record('course', array('id'=> $onetoone->course))) {
         print_error('error:coursemisconfigured', 'onetoone');
     }
     if (!$cm = get_coursemodule_from_instance('onetoone', $onetoone->id, $course->id)) {
         print_error('error:incorrectcoursemoduleid', 'onetoone');
     }

     //$nbdays = count($session->sessiondates);
}
else {
    if (!$onetoone = $DB->get_record('onetoone', array('id' => $f))) {
        print_error('error:incorrectonetooneid', 'onetoone');
    }
    if (!$course = $DB->get_record('course', array('id' => $onetoone->course))) {
        print_error('error:coursemisconfigured', 'onetoone');
    }
    if (!$cm = get_coursemodule_from_instance('onetoone', $onetoone->id, $course->id)) {
        print_error('error:incorrectcoursemoduleid', 'onetoone');
    }
}

require_course_login($course);
$errorstr = '';
$context = context_course::instance($course->id);
$module_context = context_module::instance($cm->id);
//$PAGE->set_url('/mod/onetoone/session.php', array('id' => $cm->id));
$PAGE->set_pagelayout('standard');
require_capability('mod/onetoone:editsessions', $context);

$returnurl = "view.php?f=$onetoone->id";

$editoroptions = array(
    'noclean'  => false,
    'maxfiles' => EDITOR_UNLIMITED_FILES,
    'maxbytes' => $course->maxbytes,
    'context'  => $module_context,
);


// Handle deletions
if ($d and $confirm) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    if (onetoone_delete_session($session)) {
        add_to_log($course->id, 'onetoone', 'delete session', 'sessions.php?s='.$session->id, $onetoone->id, $cm->id);
    }
    else {
        add_to_log($course->id, 'onetoone', 'delete session (FAILED)', 'sessions.php?s='.$session->id, $onetoone->id, $cm->id);
        print_error('error:couldnotdeletesession', 'onetoone', $returnurl);
    }
    redirect($returnurl);
}

$customfields = onetoone_get_session_customfields();

$sessionid = isset($session->id) ? $session->id : 0;

$details = new stdClass();
$details->id = isset($session) ? $session->id : 0;
$details->details = isset($session->details) ? $session->details : '';
$details->detailsformat = FORMAT_HTML;
$details = file_prepare_standard_editor($details, 'details', $editoroptions, $module_context, 'mod_onetoone', 'session', $sessionid);

$mform = new mod_onetoone_session_form(null, compact('id', 'f', 's', 'c', 'nbdays', 'customfields', 'course', 'editoroptions'));

if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) { // Form submitted

    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'onetoone', $returnurl);
    }

    // Pre-process fields
    if (empty($fromform->allowoverbook)) {
        $fromform->allowoverbook = 0;
    }
    if (empty($fromform->duration)) {
        $fromform->duration = 0;
    }
 
    $sessiondates = array();
    $todb = new stdClass();
    $todb->onetoone = $onetoone->id;
    $todb->datetimeknown = $fromform->datetimeknown;
    $todb->capacity = 1;
    $todb->allowoverbook = $fromform->allowoverbook;
    
    $todb->duration = 0;
    
    $todb->timestart = $fromform->timestart;
    $todb->timefinish = $fromform->timefinish;

    $sessionid = null;
    $transaction = $DB->start_delegated_transaction();

    $update = false;
    if (!$c and $session != null) {
        $update = true;
        $sessionid = $session->id;

        $todb->id = $session->id;
        if (!onetoone_update_session($todb)) {
            $transaction->force_transaction_rollback();
            add_to_log($course->id, 'onetoone', 'update session (FAILED)', "sessions.php?s=$session->id", $onetoone->id, $cm->id);
            print_error('error:couldnotupdatesession', 'onetoone', $returnurl);
        }

        // Remove old site-wide calendar entry
        if (!onetoone_remove_session_from_calendar($session, SITEID)) {
            $transaction->force_transaction_rollback();
            print_error('error:couldnotupdatecalendar', 'onetoone', $returnurl);
        }
    }
    else {
        if (!$sessionid = onetoone_add_session($todb)) {
            $transaction->force_transaction_rollback();
            add_to_log($course->id, 'onetoone', 'add session (FAILED)', 'sessions.php?f='.$onetoone->id, $onetoone->id, $cm->id);
            print_error('error:couldnotaddsession', 'onetoone', $returnurl);
        }
    }

    foreach ($customfields as $field) {
        $fieldname = "custom_$field->shortname";
        if (!isset($fromform->$fieldname)) {
            $fromform->$fieldname = ''; // need to be able to clear fields
        }

        if (!onetoone_save_customfield_value($field->id, $fromform->$fieldname, $sessionid, 'session')) {
            $transaction->force_transaction_rollback();
            print_error('error:couldnotsavecustomfield', 'onetoone', $returnurl);
        }
    }

    // Save trainer roles
    if (isset($fromform->trainerrole)) {
        onetoone_update_trainers($sessionid, $fromform->trainerrole);
    }

    // Retrieve record that was just inserted/updated
    if (!$session = onetoone_get_session($sessionid)) {
        $transaction->force_transaction_rollback();
        print_error('error:couldnotfindsession', 'onetoone', $returnurl);
    }

    // Update calendar entries
    onetoone_update_calendar_entries($session, $onetoone);

    if ($update) {
        add_to_log($course->id, 'onetoone', 'updated session', "sessions.php?s=$session->id", $onetoone->id, $cm->id);
    }
    else {
        add_to_log($course->id, 'onetoone', 'added session', 'onetoone', 'sessions.php?f='.$onetoone->id, $onetoone->id, $cm->id);
    }

    $transaction->allow_commit();

    $data = file_postupdate_standard_editor($fromform, 'details', $editoroptions, $module_context, 'mod_onetoone', 'session', $session->id);
    $DB->set_field('onetoone_sessions', 'details', $data->details, array('id' => $session->id));

    redirect($returnurl);
}
elseif ($session != null) { // Edit mode
    // Set values for the form
    $toform = new stdClass();
    $toform = file_prepare_standard_editor($details, 'details', $editoroptions, $module_context, 'mod_onetoone', 'session', $session->id);

    $toform->datetimeknown = (1 == $session->datetimeknown);
    $toform->capacity = $session->capacity;
    $toform->allowoverbook = $session->allowoverbook;

    $toform->timestart = $session->timestart;
    $toform->timefinish = $session->timefinish;

    foreach ($customfields as $field) {
        $fieldname = "custom_$field->shortname";
        $toform->$fieldname = onetoone_get_customfield_value($field, $session->id, 'session');
    }

    $mform->set_data($toform);
}

if ($c) {
    $heading = get_string('copyingsession', 'onetoone', $onetoone->name);
}
else if ($d) {
    $heading = get_string('deletingsession', 'onetoone', $onetoone->name);
}
else if ($id or $f) {
    $heading = get_string('addingsession', 'onetoone', $onetoone->name);
}
else {
    $heading = get_string('editingsession', 'onetoone', $onetoone->name);
}

$pagetitle = format_string($onetoone->name);

$PAGE->set_cm($cm);
$PAGE->set_url('/mod/onetoone/sessions.php', array('f' => $f));
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

echo $OUTPUT->box_start();
echo $OUTPUT->heading($heading);

if (!empty($errorstr)) {
    echo $OUTPUT->container(html_writer::tag('span', $errorstr, array('class' => 'errorstring')), array('class' => 'notifyproblem'));
}

if ($d) {
    $viewattendees = has_capability('mod/onetoone:viewattendees', $context);
    onetoone_print_session($session, $viewattendees);
    $optionsyes = array('sesskey' => sesskey(), 's' => $session->id, 'd' => 1, 'confirm' => 1);
    echo $OUTPUT->confirm(get_string('deletesessionconfirm', 'onetoone', format_string($onetoone->name)),
        new moodle_url('sessions.php', $optionsyes),
        new moodle_url($returnurl));
}
else {
    $mform->display();
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);
