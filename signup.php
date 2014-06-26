<?php

require_once '../../config.php';
require_once 'lib.php';
require_once 'signup_form.php';

global $DB;

$s = required_param('s', PARAM_INT); // onetoone session ID
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

require_course_login($course, true, $cm);
$context = context_course::instance($course->id);
require_capability('mod/onetoone:view', $context);	

$returnurl = "$CFG->wwwroot/course/view.php?id=$course->id";
if ($backtoallsessions) {
    $returnurl = "$CFG->wwwroot/mod/onetoone/view.php?f=$backtoallsessions";
}

$pagetitle = format_string($onetoone->name);

$PAGE->set_cm($cm);
$PAGE->set_url('/mod/onetoone/signup.php', array('s' => $s, 'backtoallsessions' => $backtoallsessions));

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

// Guests can't signup for a session, so offer them a choice of logging in or going back.
if (isguestuser()) {
    $loginurl = $CFG->wwwroot.'/login/index.php';
    if (!empty($CFG->loginhttps)) {
        $loginurl = str_replace('http:','https:', $loginurl);
    }

    echo $OUTPUT->header();
    $out = html_writer::tag('p', get_string('guestsno', 'onetoone')) .
        html_writer::empty_tag('br') .
        html_writer::tag('p', get_string('continuetologin', 'onetoone'));
    echo $OUTPUT->confirm($out, $loginurl, get_referer(false));
    echo $OUTPUT->footer();
    exit();
}

$manageremail = false;
if (get_config(NULL, 'onetoone_addchangemanageremail')) {
    $manageremail = onetoone_get_manageremail($USER->id);
}

$showdiscountcode = ($session->discountcost > 0);

$mform = new mod_onetoone_signup_form(null, compact('s', 'backtoallsessions', 'manageremail', 'showdiscountcode'));
if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) { // Form submitted

    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'onetoone', $returnurl);
    }

    // User can not update Manager's email (depreciated functionality)
    if (!empty($fromform->manageremail)) {
        add_to_log($course->id, 'onetoone', 'update manageremail (FAILED)', "signup.php?s=$session->id", $onetoone->id, $cm->id);
    }

    // Get signup type
    if (!$session->datetimeknown) {
        $statuscode = MDL_O2O_STATUS_WAITLISTED;
    } else if (onetoone_get_num_attendees($session->id) < $session->capacity) {
        // Save available
        $statuscode = MDL_O2O_STATUS_BOOKED;
    } else {
        $statuscode = MDL_O2O_STATUS_WAITLISTED;
    }

    if (!onetoone_session_has_capacity($session, $context) && (!$session->allowoverbook)) {
        print_error('sessionisfull', 'onetoone', $returnurl);
    } else if (onetoone_get_user_submissions($onetoone->id, $USER->id)) {
        print_error('alreadysignedup', 'onetoone', $returnurl);
    } else if (onetoone_manager_needed($onetoone) && !onetoone_get_manageremail($USER->id)) {
        print_error('error:manageremailaddressmissing', 'onetoone', $returnurl);
    } else if ($submissionid = onetoone_user_signup($session, $onetoone, $course, $fromform->discountcode, $fromform->notificationtype, $statuscode)) {
        add_to_log($course->id, 'onetoone','signup',"signup.php?s=$session->id", $session->id, $cm->id);

        $message = get_string('bookingcompleted', 'onetoone');
        if ($session->datetimeknown && $onetoone->confirmationinstrmngr) {
            $message .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . get_string('confirmationsentmgr', 'onetoone');
        } else {
            $message .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . get_string('confirmationsent', 'onetoone');
        }

        $timemessage = 4;
        redirect($returnurl, $message, $timemessage);
    } else {
        add_to_log($course->id, 'onetoone','signup (FAILED)',"signup.php?s=$session->id", $session->id, $cm->id);
        print_error('error:problemsigningup', 'onetoone', $returnurl);
    }

    redirect($returnurl);
} else if ($manageremail !== false) {
    // Set values for the form
    $toform = new stdClass();
    $toform->manageremail = $manageremail;
    $mform->set_data($toform);
}

echo $OUTPUT->header();

$heading = get_string('signupfor', 'onetoone', $onetoone->name);

$viewattendees = has_capability('mod/onetoone:viewattendees', $context);
$signedup = onetoone_check_signup($onetoone->id);
print_r($signedup);
if ($signedup and $signedup != $session->id) {
    print_error('error:signedupinothersession', 'onetoone', $returnurl);
}

echo $OUTPUT->box_start();
echo $OUTPUT->heading($heading);

$timenow = time();

if ($session->datetimeknown && onetoone_has_session_started($session, $timenow)) {
    $inprogress_str = get_string('cannotsignupsessioninprogress', 'onetoone');
    $over_str = get_string('cannotsignupsessionover', 'onetoone');

    $errorstring = onetoone_is_session_in_progress($session, $timenow) ? $inprogress_str : $over_str;

    echo html_writer::empty_tag('br') . $errorstring;
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer($course);
    exit;
}

if (!$signedup && !onetoone_session_has_capacity($session, $context) && (!$session->allowoverbook)) {
    print_error('sessionisfull', 'onetoone', $returnurl);
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer($course);
    exit;
}

echo onetoone_print_session($session, $viewattendees);

if ($signedup) {
    if (!($session->datetimeknown && onetoone_has_session_started($session, $timenow))) {
        // Cancellation link
        echo html_writer::link(new moodle_url('cancelsignup.php', array('s' => $session->id, 'backtoallsessions' => $backtoallsessions)), get_string('cancelbooking', 'onetoone'), array('title' => get_string('cancelbooking', 'onetoone')));
        echo ' &ndash; ';
    }
    // See attendees link
    if ($viewattendees) {
        echo html_writer::link(new moodle_url('attendees.php', array('s' => $session->id, 'backtoallsessions' => $backtoallsessions)), get_string('seeattendees', 'onetoone'), array('title' => get_string('seeattendees', 'onetoone')));
    }

    echo html_writer::empty_tag('br') . html_writer::link($returnurl, get_string('goback', 'onetoone'), array('title' => get_string('goback', 'onetoone')));
}
// Don't allow signup to proceed if a manager is required
else if (onetoone_manager_needed($onetoone) && !onetoone_get_manageremail($USER->id)) {
    // Check to see if the user has a managers email set
    echo html_writer::tag('p', html_writer::tag('strong', get_string('error:manageremailaddressmissing', 'onetoone')));
    echo html_writer::empty_tag('br') . html_writer::link($returnurl, get_string('goback', 'onetoone'), array('title' => get_string('goback', 'onetoone')));

} else if (!has_capability('mod/onetoone:signup', $context)) {
    echo html_writer::tag('p', html_writer::tag('strong', get_string('error:nopermissiontosignup', 'onetoone')));
    echo html_writer::empty_tag('br') . html_writer::link($returnurl, get_string('goback', 'onetoone'), array('title' => get_string('goback', 'onetoone')));
} else {
    // Signup form
    $mform->display();
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);
