<?php

require_once '../../config.php';
require_once 'lib.php';
require_once 'cancelsignup_form.php';



$s  = required_param('s', PARAM_INT); // onetoone session ID
$confirm           = optional_param('confirm', false, PARAM_BOOL);
$backtoallsessions = optional_param('backtoallsessions', 0, PARAM_INT);

if (!$session = onetoone_get_session($s)) {
    print_error('error:incorrectcoursemodulesession', 'onetoone');
}
if (!$onetoone = $DB->get_record('onetoone', array('id' => $session->onetoone))) {
    print_error('error:incorrectonetooneid', 'onetoone');
}
if (!$course = $DB->get_record('course', array('id' => $onetoone->course))) {
    print_error('error:coursemisconfigured', 'onetoone');
}
if (!$cm = get_coursemodule_from_instance("onetoone", $onetoone->id, $course->id)) {
    print_error('error:incorrectcoursemoduleid', 'onetoone');
}

require_course_login($course);
$context = context_course::instance($course->id);
require_capability('mod/onetoone:view', $context);

$returnurl = "$CFG->wwwroot/course/view.php?id=$course->id";
if ($backtoallsessions) {
    $returnurl = "$CFG->wwwroot/mod/onetoone/view.php?f=$backtoallsessions";
}

$mform = new mod_onetoone_cancelsignup_form(null, compact('s', 'backtoallsessions'));
if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) { // Form submitted

    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'onetoone', $returnurl);
    }

    $timemessage = 4;

    $errorstr = '';
    if (onetoone_user_cancel($session, false, false, $errorstr, $fromform->cancelreason)) {
        add_to_log($course->id, 'onetoone', 'cancel booking', "cancelsignup.php?s=$session->id", $onetoone->id, $cm->id);

        $message = get_string('bookingcancelled', 'onetoone');

        if ($session->datetimeknown) {
            $error = onetoone_send_cancellation_notice($onetoone, $session, $USER->id);
            if (empty($error)) {
                if ($session->datetimeknown && $onetoone->cancellationinstrmngr) {
                    $message .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . get_string('cancellationsentmgr', 'onetoone');
                }
                else {
                    $message .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . get_string('cancellationsent', 'onetoone');
                }
            } else {
                print_error($error, 'onetoone');
            }
        }

        redirect($returnurl, $message, $timemessage);
    }
    else {
        add_to_log($course->id, 'onetoone', "cancel booking (FAILED)", "cancelsignup.php?s=$session->id", $onetoone->id, $cm->id);
        redirect($returnurl, $errorstr, $timemessage);
    }

    redirect($returnurl);
}

$pagetitle = format_string($onetoone->name);

$PAGE->set_cm($cm);
$PAGE->set_url('/mod/onetoone/cancelsignup.php', array('s' => $s, 'backtoallsessions' => $backtoallsessions, 'confirm' => $confirm));

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

$heading = get_string('cancelbookingfor', 'onetoone', $onetoone->name);

$viewattendees = has_capability('mod/onetoone:viewattendees', $context);
$signedup = onetoone_check_signup($onetoone->id);

echo $OUTPUT->box_start();
echo $OUTPUT->heading($heading);

if ($signedup) {
    onetoone_print_session($session, $viewattendees);
    $mform->display();
}
else {
    print_error('notsignedup', 'onetoone', $returnurl);
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);
