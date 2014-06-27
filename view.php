<?php

require_once '../../config.php';
require_once 'lib.php';
require_once 'renderer.php';
    
    //global $DB, $OUTPUT;

    $id = optional_param('id', 0, PARAM_INT); // Course Module ID
    $f = optional_param('f', 0, PARAM_INT); // onetoone ID
    $location = optional_param('location', '', PARAM_TEXT); // location
    $download = optional_param('download', '', PARAM_ALPHA); // download attendance

    if ($id) {
        if (!$cm = $DB->get_record('course_modules', array('id' => $id))) {
            print_error('error:incorrectcoursemoduleid', 'onetoone');
        }
        if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
            print_error('error:coursemisconfigured', 'onetoone');
        }
        if (!$onetoone = $DB->get_record('onetoone', array('id' => $cm->instance))) {
            print_error('error:incorrectcoursemodule', 'onetoone');
        }
    }
    elseif ($f) {
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
    else {
        print_error('error:mustspecifycoursemoduleonetoone', 'onetoone');
    }

    // Check login and get context.
    require_login($course, false, $cm);
    $context = context_module::instance($cm->id);
    require_capability('mod/onetoone:view', $context);
    $PAGE->set_url('/mod/onetoone/view.php', array('id' => $cm->id));

    if (!empty($download)) {
        require_capability('mod/onetoone:viewattendees', $context);
        onetoone_download_attendance($onetoone->name, $onetoone->id, $location, $download);
        exit();
    }

    require_course_login($course, true, $cm);
    require_capability('mod/onetoone:view', $context);

    add_to_log($course->id, 'onetoone', 'view', "view.php?id=$cm->id", $onetoone->id, $cm->id);

    $title = $course->shortname . ': ' . format_string($onetoone->name);

    $PAGE->set_title($title);
    $PAGE->set_heading($course->fullname);
    $PAGE->set_button(update_module_button($cm->id, '', get_string('modulename', 'onetoone')));

    $pagetitle = format_string($onetoone->name);

    //$f2f_renderer = $PAGE->get_renderer('mod_onetoone');
    $PAGE->get_renderer('mod_onetoone');
    $completion=new completion_info($course);
    $completion->set_module_viewed($cm);

    echo $OUTPUT->header();

    if (empty($cm->visible) and !has_capability('mod/onetoone:viewemptyactivities', $context)) {
        notice(get_string('activityiscurrentlyhidden'));
    }
    echo $OUTPUT->box_start();
    
    $result= $DB->get_field('config_plugins', 'value', array ('plugin' => 'local_getkey', 'name' => 'keyvalue'), $strictness=IGNORE_MISSING);
     if(empty($result)){
        $getkey_path = $CFG->wwwroot."/local/getkey/index.php";
        $msg =  get_string('unvalidkeymsg', 'onetoone', $getkey_path);
        echo "<div id='keyerrormsg'>$msg</div>";
    }else{
         echo $OUTPUT->heading(get_string('allsessionsin', 'onetoone', $onetoone->name), 2);
        if ($onetoone->intro) {
            echo $OUTPUT->box_start('generalbox','description');
            echo format_module_intro('onetoone', $onetoone, $cm->id);
            echo $OUTPUT->box_end();
        }

        onetoone_print_session_list($course->id, $onetoone->id,$cm->id, $location);

        if (has_capability('mod/onetoone:viewattendees', $context)) {
            echo $OUTPUT->heading(get_string('exportattendance', 'onetoone'));
            echo html_writer::start_tag('form', array('action' => 'view.php', 'method' => 'get'));
            echo html_writer::start_tag('div') . html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'f', 'value' => $onetoone->id));
            echo get_string('format', 'onetoone') . '&nbsp;';
            $formats = array('excel' => get_string('excelformat', 'onetoone'),
                             'ods' => get_string('odsformat', 'onetoone'));
            echo html_writer::select($formats, 'download', 'excel', '');
            echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('exporttofile', 'onetoone')));
            echo html_writer::end_tag('div'). html_writer::end_tag('form');
        }
    }
   

    echo $OUTPUT->box_end();
    //echo $OUTPUT->footer($course);
    echo $OUTPUT->footer();

//}

function onetoone_print_session_list($courseid, $onetooneid,$cmid, $location) {
    global $CFG, $USER, $DB, $OUTPUT, $PAGE;
    $o2o_renderer = $PAGE->get_renderer('mod_onetoone');
    $timenow = time();

    $context = context_course::instance($courseid);
    $viewattendees = has_capability('mod/onetoone:viewattendees', $context);
    $editsessions = has_capability('mod/onetoone:editsessions', $context);

    $bookedsession = null;
    if ($submissions = onetoone_get_user_submissions($onetooneid, $USER->id)) {
        $submission = array_shift($submissions);
        $bookedsession = $submission;
    }

    $customfields = onetoone_get_session_customfields();

    $upcomingarray = array();
    $previousarray = array();
    $upcomingtbdarray = array();

    if ($sessions = onetoone_get_sessions($onetooneid, $location) ) {
        foreach ($sessions as $session) {

            $sessionstarted = false;
            $sessionfull = false;
            $sessionwaitlisted = false;
            $isbookedsession = false;

            $sessiondata = $session;
            $sessiondata->bookedsession = $bookedsession;

            // Add custom fields to sessiondata
            $customdata = $DB->get_records('onetoone_session_data', array('sessionid' => $session->id), '', 'fieldid, data');
            $sessiondata->customfielddata = $customdata;

            // Is session waitlisted
            if (!$session->datetimeknown) {
                $sessionwaitlisted = true;
            }

            // Check if session is started
            if ($session->datetimeknown && onetoone_has_session_started($session, $timenow) && onetoone_is_session_in_progress($session, $timenow)) {
                $sessionstarted = true;
            }
            elseif ($session->datetimeknown && onetoone_has_session_started($session, $timenow)) {
                $sessionstarted = true;
            }

            // Put the row in the right table
            if ($sessionstarted) {
                $previousarray[] = $sessiondata;
            }
            elseif ($sessionwaitlisted) {
                $upcomingtbdarray[] = $sessiondata;
            }
            else { // Normal scheduled session
                $upcomingarray[] = $sessiondata;
            }
        }
    }

    // Upcoming sessions
    echo $OUTPUT->heading(get_string('upcomingsessions', 'onetoone'));
    if (empty($upcomingarray) && empty($upcomingtbdarray)) {
        print_string('noupcoming', 'onetoone');
    }
    else {
    	
        $upcomingarray = array_merge($upcomingarray, $upcomingtbdarray);
        echo $o2o_renderer->print_session_list_table($customfields, $upcomingarray, $viewattendees, $editsessions,$cmid);
    }

    if ($editsessions) {
        echo html_writer::tag('p', html_writer::link(new moodle_url('sessions.php', array('f' => $onetooneid)), get_string('addsession', 'onetoone')));
    }

    // Previous sessions
    if (!empty($previousarray)) {
        echo $OUTPUT->heading(get_string('previoussessions', 'onetoone'));
        echo $o2o_renderer->print_session_list_table($customfields, $previousarray, $viewattendees, $editsessions,$cmid);
    }
}

/**
 * Get onetoone locations
 *
 * @param   interger    $onetooneid
 * @return  array
 */
function onetoone_get_locations($onetooneid) {
    global $CFG, $DB;

    $locationfieldid = $DB->get_field('onetoone_session_field', 'id', array('shortname' => 'location'));
    if (!$locationfieldid) {
        return array();
    }

    $sql = "SELECT DISTINCT d.data AS location
              FROM {onetoone} f
              JOIN {onetoone_sessions} s ON s.onetoone = f.id
              JOIN {onetoone_session_data} d ON d.sessionid = s.id
             WHERE f.id = ? AND d.fieldid = ?";

    if ($records = $DB->get_records_sql($sql, array($onetooneid, $locationfieldid))) {
        $locationmenu[''] = get_string('alllocations', 'onetoone');

        $i=1;
        foreach ($records as $record) {
            $locationmenu[$record->location] = $record->location;
            $i++;
        }

        return $locationmenu;
    }

    return array();
}
