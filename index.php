<?php

require_once '../../config.php';
require_once 'lib.php';

global $DB;

$id = required_param('id', PARAM_INT); // Course Module ID

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('error:coursemisconfigured', 'onetoone');
}

require_course_login($course);
$context = context_course::instance($course->id);
require_capability('mod/onetoone:view', $context);

add_to_log($course->id, 'onetoone', 'view all', "index.php?id=$course->id");

$stronetoones = get_string('modulenameplural', 'onetoone');
$stronetoone = get_string('modulename', 'onetoone');
$stronetoonename = get_string('onetoonename', 'onetoone');
$strweek = get_string('week');
$strtopic = get_string('topic');
$strcourse = get_string('course');
$strname = get_string('name');

$pagetitle = format_string($stronetoones);

$PAGE->set_url('/mod/onetoone/index.php', array('id' => $id));

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

if (!$onetoones = get_all_instances_in_course('onetoone', $course)) {
    notice(get_string('noonetoones', 'onetoone'), "../../course/view.php?id=$course->id");
    die;
}

$timenow = time();

$table = new html_table();
$table->width = '100%';

if ($course->format == 'weeks' && has_capability('mod/onetoone:viewattendees', $context)) {
    $table->head  = array ($strweek, $stronetoonename, get_string('sign-ups', 'onetoone'));
    $table->align = array ('center', 'left', 'center');
}
elseif ($course->format == 'weeks') {
    $table->head  = array ($strweek, $stronetoonename);
    $table->align = array ('center', 'left', 'center', 'center');
}
elseif ($course->format == 'topics' && has_capability('mod/onetoone:viewattendees', $context)) {
    $table->head  = array ($strcourse, $stronetoonename, get_string('sign-ups', 'onetoone'));
    $table->align = array ('center', 'left', 'center');
}
elseif ($course->format == 'topics') {
    $table->head  = array ($strcourse, $stronetoonename);
    $table->align = array ('center', 'left', 'center', 'center');
}
else {
    $table->head  = array ($stronetoonename);
    $table->align = array ('left', 'left');
}

$currentsection = '';

foreach ($onetoones as $onetoone) {

    $submitted = get_string('no');

    if (!$onetoone->visible) {
        //Show dimmed if the mod is hidden
        $link = html_writer::link("view.php?f=$onetoone->id", $onetoone->name, array('class' => 'dimmed'));
    }
    else {
        //Show normal if the mod is visible
        $link = html_writer::link("view.php?f=$onetoone->id", $onetoone->name);
    }

    $printsection = '';
    if ($onetoone->section !== $currentsection) {
        if ($onetoone->section) {
            $printsection = $onetoone->section;
        }
        $currentsection = $onetoone->section;
    }

    $totalsignupcount = 0;
    if ($sessions = onetoone_get_sessions($onetoone->id)) {
        foreach ($sessions as $session) {
            if (!onetoone_has_session_started($session, $timenow)) {
                $signupcount = onetoone_get_num_attendees($session->id);
                $totalsignupcount += $signupcount;
            }
        }
    }
    $url = new moodle_url('/course/view.php', array('id' => $course->id));
    $courselink = html_writer::link($url, $course->shortname, array('title' => $course->shortname));
    if ($course->format == 'weeks' or $course->format == 'topics') {
        if (has_capability('mod/onetoone:viewattendees', $context)) {
            $table->data[] = array ($courselink, $link, $totalsignupcount);
        }
        else {
            $table->data[] = array ($courselink, $link);
        }
    }
    else {
        $table->data[] = array ($link, $submitted);
    }
}

echo html_writer::empty_tag('br');

echo html_writer::table($table);
echo $OUTPUT->footer($course);
