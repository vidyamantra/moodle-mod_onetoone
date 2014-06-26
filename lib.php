<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010, 2011 Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Francois Marier <francois@catalyst.net.nz>
 * @package modules
 * @subpackage onetoone
 */
defined('MOODLE_INTERNAL') || die();


require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once($CFG->dirroot.'/lib/adminlib.php');
require_once($CFG->dirroot . '/user/selector/lib.php');
if (file_exists($CFG->libdir.'/completionlib.php')) {
    require_once($CFG->libdir.'/completionlib.php');
}

/**
 * Definitions for setting notification types
 */
/**
 * Utility definitions
 */
define('MDL_O2O_ICAL',          1);
define('MDL_O2O_TEXT',          2);
define('MDL_O2O_BOTH',          3);
define('MDL_O2O_INVITE',        4);
define('MDL_O2O_CANCEL',        8);

/**
 * Definitions for use in forms
 */
define('MDL_O2O_INVITE_BOTH',        7);     // Send a copy of both 4+1+2
define('MDL_O2O_INVITE_TEXT',        6);     // Send just a plain email 4+2
define('MDL_O2O_INVITE_ICAL',        5);     // Send just a combined text/ical message 4+1
define('MDL_O2O_CANCEL_BOTH',        11);    // Send a copy of both 8+2+1
define('MDL_O2O_CANCEL_TEXT',        10);    // Send just a plan email 8+2
define('MDL_O2O_CANCEL_ICAL',        9);     // Send just a combined text/ical message 8+1

// Name of the custom field where the manager's email address is stored
define('O2O_MDL_MANAGERSEMAIL_FIELD', 'managersemail');

// Custom field related constants
define('O2O_CUSTOMFIELD_DELIMITER', '##SEPARATOR##');
define('O2O_CUSTOMFIELD_TYPE_TEXT',        0);
define('O2O_CUSTOMFIELD_TYPE_SELECT',      1);
define('O2O_CUSTOMFIELD_TYPE_MULTISELECT', 2);

// Calendar-related constants
define('O2O_CALENDAR_MAX_NAME_LENGTH', 15);
define('O2O_CAL_NONE',      0);
define('O2O_CAL_COURSE',    1);
define('O2O_CAL_SITE',      2);

// Signup status codes (remember to update $MDL_O2O_STATUS)
define('MDL_O2O_STATUS_USER_CANCELLED',     10);
// SESSION_CANCELLED is not yet implemented
define('MDL_O2O_STATUS_SESSION_CANCELLED',  20);
define('MDL_O2O_STATUS_DECLINED',           30);
define('MDL_O2O_STATUS_REQUESTED',          40);
define('MDL_O2O_STATUS_APPROVED',           50);
define('MDL_O2O_STATUS_WAITLISTED',         60);
define('MDL_O2O_STATUS_BOOKED',             70);
define('MDL_O2O_STATUS_NO_SHOW',            80);
define('MDL_O2O_STATUS_PARTIALLY_ATTENDED', 90);
define('MDL_O2O_STATUS_FULLY_ATTENDED',     100);

// This array must match the status codes above, and the values
// must equal the end of the constant name but in lower case
global $MDL_O2O_STATUS;
$MDL_O2O_STATUS = array(
    MDL_O2O_STATUS_USER_CANCELLED       => 'user_cancelled',
//  SESSION_CANCELLED is not yet implemented
//    MDL_O2O_STATUS_SESSION_CANCELLED    => 'session_cancelled',
    MDL_O2O_STATUS_DECLINED             => 'declined',
    MDL_O2O_STATUS_REQUESTED            => 'requested',
    MDL_O2O_STATUS_APPROVED             => 'approved',
    MDL_O2O_STATUS_WAITLISTED           => 'waitlisted',
    MDL_O2O_STATUS_BOOKED               => 'booked',
    MDL_O2O_STATUS_NO_SHOW              => 'no_show',
    MDL_O2O_STATUS_PARTIALLY_ATTENDED   => 'partially_attended',
    MDL_O2O_STATUS_FULLY_ATTENDED       => 'fully_attended',
);

/**
 * Returns the human readable code for a face-to-face status
 *
 * @param int $statuscode One of the MDL_O2O_STATUS* constants
 * @return string Human readable code
 */
function onetoone_get_status($statuscode) {
    global $MDL_O2O_STATUS;
    // Check code exists
    if (!isset($MDL_O2O_STATUS[$statuscode])) {
        print_error('O2O status code does not exist: '.$statuscode);
    }

    // Get code
    $string = $MDL_O2O_STATUS[$statuscode];

    // Check to make sure the status array looks to be up-to-date
    if (constant('MDL_O2O_STATUS_'.strtoupper($string)) != $statuscode) {
        print_error('O2O status code array does not appear to be up-to-date: '.$statuscode);
    }

    return $string;
}

/**
 * Prints the cost amount along with the appropriate currency symbol.
 *
 * To set your currency symbol, set the appropriate 'locale' in
 * lang/en_utf8/langconfig.php (or the equivalent file for your
 * language).
 *
 * @param $amount      Numerical amount without currency symbol
 * @param $htmloutput  Whether the output is in HTML or not
 */
function onetoone_format_cost($amount, $htmloutput=true) {
    setlocale(LC_MONETARY, get_string('locale', 'langconfig'));
    $localeinfo = localeconv();

    $symbol = $localeinfo['currency_symbol'];
    if (empty($symbol)) {
        // Cannot get the locale information, default to en_US.UTF-8
        return '$' . $amount;
    }

    // Character between the currency symbol and the amount
    $separator = '';
    if ($localeinfo['p_sep_by_space']) {
        $separator = $htmloutput ? '&nbsp;' : ' ';
    }

    // The symbol can come before or after the amount
    if ($localeinfo['p_cs_precedes']) {
        return $symbol . $separator . $amount;
    }
    else {
        return $amount . $separator . $symbol;
    }
}

/**
 * Returns the effective cost of a session depending on the presence
 * or absence of a discount code.
 *
 * @param class $sessiondata contains the discountcost and normalcost
 */
function onetoone_cost($userid, $sessionid, $sessiondata, $htmloutput=true) {

    global $CFG,$DB;

    $count = $DB->count_records_sql("SELECT COUNT(*)
                               FROM {onetoone_signups} su,
                                    {onetoone_sessions} se
                              WHERE su.sessionid = ?
                                AND su.userid = ?
                                AND su.discountcode IS NOT NULL
                                AND su.sessionid = se.id", array($sessionid, $userid));
    if ($count > 0) {
        return onetoone_format_cost($sessiondata->discountcost, $htmloutput);
    } else {
        return onetoone_format_cost($sessiondata->normalcost, $htmloutput);
    }
}

/**
 * Human-readable version of the duration field used to display it to
 * users
 *
 * @param   integer $duration duration in hours
 * @return  string
 */
function onetoone_format_duration($duration) {

    $components = explode(':', $duration);

    // Default response
    $string = '';

    // Check for bad characters
    if (trim(preg_match('/[^0-9:\.\s]/', $duration))) {
        return $string;
    }

    if ($components and count($components) > 1) {
        // e.g. "1:30" => "1 hour and 30 minutes"
        $hours = round($components[0]);
        $minutes = round($components[1]);
    }
    else {
        // e.g. "1.5" => "1 hour and 30 minutes"
        $hours = floor($duration);
        $minutes = round(($duration - floor($duration)) * 60);
    }

    // Check if either minutes is out of bounds
    if ($minutes >= 60) {
        return $string;
    }

    if (1 == $hours) {
        $string = get_string('onehour', 'onetoone');
    } elseif ($hours > 1) {
        $string = get_string('xhours', 'onetoone', $hours);
    }

    // Insert separator between hours and minutes
    if ($string != '') {
        $string .= ' ';
    }

    if (1 == $minutes) {
        $string .= get_string('oneminute', 'onetoone');
    } elseif ($minutes > 0) {
        $string .= get_string('xminutes', 'onetoone', $minutes);
    }

    return $string;
}

/**
 * Converts minutes to hours
 */
function onetoone_minutes_to_hours($minutes) {

    if (!intval($minutes)) {
        return 0;
    }

    if ($minutes > 0) {
        $hours = floor($minutes / 60.0);
        $mins = $minutes - ($hours * 60.0);
        return "$hours:$mins";
    }
    else {
        return $minutes;
    }
}

/**
 * Converts hours to minutes
 */
function onetoone_hours_to_minutes($hours)
{
    $components = explode(':', $hours);
    if ($components and count($components) > 1) {
        // e.g. "1:45" => 105 minutes
        $hours = $components[0];
        $minutes = $components[1];
        return $hours * 60.0 + $minutes;
    }
    else {
        // e.g. "1.75" => 105 minutes
        return round($hours * 60.0);
    }
}

/**
 * Turn undefined manager messages into empty strings and deal with checkboxes
 */
function onetoone_fix_settings($onetoone) {

    if (empty($onetoone->emailmanagerconfirmation)) {
        $onetoone->confirmationinstrmngr = null;
    }
    if (empty($onetoone->emailmanagerreminder)) {
        $onetoone->reminderinstrmngr = null;
    }
    if (empty($onetoone->emailmanagercancellation)) {
        $onetoone->cancellationinstrmngr = null;
    }
    if (empty($onetoone->usercalentry)) {
        $onetoone->usercalentry = 0;
    }
    if (empty($onetoone->thirdpartywaitlist)) {
        $onetoone->thirdpartywaitlist = 0;
    }
    if (empty($onetoone->approvalreqd)) {
        $onetoone->approvalreqd = 0;
    }
}

/**
 * Given an object containing all the necessary data, (defined by the
 * form in mod.html) this function will create a new instance and
 * return the id number of the new instance.
 */
function onetoone_add_instance($onetoone) {
    //added by suman
    
    // CONFIRMATION MESSAGE
    $onetoone->confirmationsubject = get_string('setting:defaultconfirmationsubjectdefault', 'onetoone');
    $onetoone->confirmationinstrmngr =  get_string('setting:defaultconfirmationinstrmngrdefault', 'onetoone');
    $onetoone->confirmationmessage = get_string('setting:defaultconfirmationmessagedefault', 'onetoone');
   
    //CANCEL
    $onetoone->cancellationsubject	= get_string('setting:defaultcancellationsubjectdefault', 'onetoone');
    $onetoone->cancellationinstrmngr = get_string('setting:defaultcancellationinstrmngrdefault', 'onetoone');
    $onetoone->cancellationmessage = get_string('setting:defaultcancellationmessagedefault', 'onetoone');
    
    //WAITLIST
    $onetoone->waitlistedsubject = get_string('setting:defaultwaitlistedsubjectdefault', 'onetoone');
    $onetoone-> waitlistedmessage = get_string('setting:defaultwaitlistedmessagedefault', 'onetoone');
    
    //REMINDER
    $onetoone-> remindersubject	 = get_string('setting:defaultremindersubjectdefault', 'onetoone');
    $onetoone-> reminderinstrmngr = get_string('setting:defaultreminderinstrmngrdefault', 'onetoone');
    $onetoone-> remindermessage	= get_string('setting:defaultremindermessagedefault', 'onetoone');
    $onetoone-> reminderperiod = 2;
    
    $onetoone->requestsubject = get_string('setting:defaultrequestsubjectdefault', 'onetoone');
    $onetoone->requestinstrmngr	= get_string('setting:defaultrequestinstrmngrdefault', 'onetoone');
    $onetoone->requestmessage = get_string('setting:defaultrequestmessagedefault', 'onetoone');
            
    global $DB;
    $onetoone->timemodified = time();
    onetoone_fix_settings($onetoone);
    
    if ($onetoone->id = $DB->insert_record('onetoone', $onetoone)) {
        onetoone_grade_item_update($onetoone);
    }

    // Update any calendar entries
    if ($sessions = onetoone_get_sessions($onetoone->id)) {
        foreach ($sessions as $session) {
            onetoone_update_calendar_entries($session, $onetoone);
        }
    }

    return $onetoone->id;
}

/**
 * Given an object containing all the necessary data, (defined by the
 * form in mod.html) this function will update an existing instance
 * with new data.
 */
function onetoone_update_instance($onetoone, $instanceflag = true) {
    global $DB;

    if ($instanceflag) {
        $onetoone->id = $onetoone->instance;
    }

    onetoone_fix_settings($onetoone);
    if ($return = $DB->update_record('onetoone', $onetoone)) {
        onetoone_grade_item_update($onetoone);

        //update any calendar entries
        if ($sessions = onetoone_get_sessions($onetoone->id)) {
            foreach ($sessions as $session) {
                onetoone_update_calendar_entries($session, $onetoone);
            }
        }
    }
    return $return;
}

/**
 * Given an ID of an instance of this module, this function will
 * permanently delete the instance and any data that depends on it.
 */
function onetoone_delete_instance($id) {
    global $CFG, $DB;

    if (!$onetoone = $DB->get_record('onetoone', array('id' => $id))) {
        return false;
    }

    $result = true;

    $transaction = $DB->start_delegated_transaction();

    $DB->delete_records_select(
        'onetoone_signups_status',
        "signupid IN
        (
            SELECT
            id
            FROM
    {onetoone_signups}
    WHERE
    sessionid IN
    (
        SELECT
        id
        FROM
    {onetoone_sessions}
    WHERE
    onetoone = ? ))
    ", array($onetoone->id));

    $DB->delete_records_select('onetoone_signups', "sessionid IN (SELECT id FROM {onetoone_sessions} WHERE onetoone = ?)", array($onetoone->id));

    //$DB->delete_records_select('onetoone_sessions_dates', "sessionid in (SELECT id FROM {onetoone_sessions} WHERE onetoone = ?)", array($onetoone->id));

    $DB->delete_records('onetoone_sessions', array('onetoone' => $onetoone->id));

    $DB->delete_records('onetoone', array('id' => $onetoone->id));

    $DB->delete_records('event', array('modulename' => 'onetoone', 'instance' => $onetoone->id));

    onetoone_grade_item_delete($onetoone);

    $transaction->allow_commit();

    return $result;
}

/**
 * Prepare the user data to go into the database.
 */
function onetoone_cleanup_session_data($session) {

    // Convert hours (expressed like "1.75" or "2" or "3.5") to minutes
    $session->duration = onetoone_hours_to_minutes($session->duration);

    // Only numbers allowed here
    $session->capacity = preg_replace('/[^\d]/', '', $session->capacity);
    $MAX_CAPACITY = 100000;
    if ($session->capacity < 1) {
        $session->capacity = 1;
    }
    elseif ($session->capacity > $MAX_CAPACITY) {
        $session->capacity = $MAX_CAPACITY;
    }

    // Get the decimal point separator
    setlocale(LC_MONETARY, get_string('locale', 'langconfig'));
    $localeinfo = localeconv();
    $symbol = $localeinfo['decimal_point'];
    if (empty($symbol)) {
        // Cannot get the locale information, default to en_US.UTF-8
        $symbol = '.';
    }

    // Only numbers or decimal separators allowed here
    //commented by suman
//    $session->normalcost = round(preg_replace("/[^\d$symbol]/", '', $session->normalcost));
//    $session->discountcost = round(preg_replace("/[^\d$symbol]/", '', $session->discountcost));

    return $session;
}

/**
 * Create a new entry in the onetoone_sessions table
 */
function onetoone_add_session($session) {
    global $USER, $DB;

    $session->timecreated = time();
    $session = onetoone_cleanup_session_data($session);

    $eventname = $DB->get_field('onetoone', 'name,id', array('id' => $session->onetoone));

    $session->id = $DB->insert_record('onetoone_sessions', $session);

    /*if (empty($sessiondates)) {
        // Insert a dummy date record
        $date = new stdClass();
        $date->sessionid = $session->id;
        $date->timestart = 0;
        $date->timefinish = 0;

        $DB->insert_record('onetoone_sessions_dates', $date);
    }
    else {
        foreach ($sessiondates as $date) {
            $date->sessionid = $session->id;

            $DB->insert_record('onetoone_sessions_dates', $date);
        }
    }*/

    //create any calendar entries
   // $session->sessiondates = $sessiondates;
    onetoone_update_calendar_entries($session);

    return $session->id;
}

/**
 * Modify an entry in the onetoone_sessions table
 */
function onetoone_update_session($session) {
    global $DB;

    $session->timemodified = time();
    $session = onetoone_cleanup_session_data($session);
    $transaction = $DB->start_delegated_transaction();
    $DB->update_record('onetoone_sessions', $session);
    onetoone_update_calendar_entries($session);
    $transaction->allow_commit();
    return onetoone_update_attendees($session);
}

/**
 * Update calendar entries for a given session
 *
 * @param int $session ID of session to update event for
 * @param int $onetoone ID of onetoone activity (optional)
 */
function onetoone_update_calendar_entries($session, $onetoone = null){
    global $USER, $DB;

    if (empty($onetoone)) {
        $onetoone = $DB->get_record('onetoone', array('id' => $session->onetoone));
    }

    //remove from all calendars
    onetoone_delete_user_calendar_events($session, 'booking');
    onetoone_delete_user_calendar_events($session, 'session');
    onetoone_remove_session_from_calendar($session, $onetoone->course);
    onetoone_remove_session_from_calendar($session, SITEID);

    if (empty($onetoone->showoncalendar) && empty($onetoone->usercalentry)) {
        return true;
    }

    //add to NEW calendartype
    if ($onetoone->usercalentry) {
    //get ALL enrolled/booked users
        $users  = onetoone_get_attendees($session->id);
        if (!in_array($USER->id, $users)) {
            onetoone_add_session_to_calendar($session, $onetoone, 'user', $USER->id, 'session');
        }

        foreach ($users as $user) {
            $eventtype = $user->statuscode == MDL_O2O_STATUS_BOOKED ? 'booking' : 'session';
            onetoone_add_session_to_calendar($session, $onetoone, 'user', $user->id, $eventtype);
        }
    }

    if ($onetoone->showoncalendar == O2O_CAL_COURSE) {
        onetoone_add_session_to_calendar($session, $onetoone, 'course');
    } else if ($onetoone->showoncalendar == O2O_CAL_SITE) {
        onetoone_add_session_to_calendar($session, $onetoone, 'site');
    }

    return true;
}

/**
 * Update attendee list status' on booking size change
 */
function onetoone_update_attendees($session) {
    global $USER, $DB;

    // Get onetoone
    $onetoone = $DB->get_record('onetoone', array('id' => $session->onetoone));

    // Get course
    $course = $DB->get_record('course', array('id' => $onetoone->course));

    // Update user status'
    $users = onetoone_get_attendees($session->id);

    if ($users) {
        // No/deleted session dates
        if (empty($session->datetimeknown)) {

            // Convert any bookings to waitlists
            foreach ($users as $user) {
                if ($user->statuscode == MDL_O2O_STATUS_BOOKED) {

                    if (!onetoone_user_signup($session, $onetoone, $course, $user->discountcode, $user->notificationtype, MDL_O2O_STATUS_WAITLISTED, $user->id)) {
                        // rollback_sql();
                        return false;
                    }
                }
            }

        // Session dates exist
        } else {
            // Convert earliest signed up users to booked, and make the rest waitlisted
            $capacity = $session->capacity;

            // Count number of booked users
            $booked = 0;
            foreach ($users as $user) {
                if ($user->statuscode == MDL_O2O_STATUS_BOOKED) {
                    $booked++;
                }
            }

            // If booked less than capacity, book some new users
            if ($booked < $capacity) {
                foreach ($users as $user) {
                    if ($booked >= $capacity) {
                        break;
                    }

                    if ($user->statuscode == MDL_O2O_STATUS_WAITLISTED) {

                        if (!onetoone_user_signup($session, $onetoone, $course, $user->discountcode, $user->notificationtype, MDL_O2O_STATUS_BOOKED, $user->id)) {
                            // rollback_sql();
                            return false;
                        }
                        $booked++;
                    }
                }
            }
        }
    }

    return $session->id;
}

/**
 * Return an array of all onetoone activities in the current course
 */
function onetoone_get_onetoone_menu() {
    global $CFG, $DB;
    if ($onetoones = $DB->get_records_sql("SELECT f.id, c.shortname, f.name
                                            FROM {course} c, {onetoone} f
                                            WHERE c.id = f.course
                                            ORDER BY c.shortname, f.name")) {
        $i=1;
        foreach ($onetoones as $onetoone) {
            $f = $onetoone->id;
            $onetoonemenu[$f] = $onetoone->shortname.' --- '.$onetoone->name;
            $i++;
        }

        return $onetoonemenu;

    } else {

        return '';

    }
}

/**
 * Delete entry from the onetoone_sessions table along with all
 * related details in other tables
 *
 * @param object $session Record from onetoone_sessions
 */
function onetoone_delete_session($session) {
    global $CFG, $DB;

    $onetoone = $DB->get_record('onetoone', array('id' => $session->onetoone));

    // Cancel user signups (and notify users)
    $signedupusers = $DB->get_records_sql(
        "
            SELECT DISTINCT
                userid
            FROM
                {onetoone_signups} s
            LEFT JOIN
                {onetoone_signups_status} ss
             ON ss.signupid = s.id
            WHERE
                s.sessionid = ?
            AND ss.superceded = 0
            AND ss.statuscode >= ?
        ", array($session->id, MDL_O2O_STATUS_REQUESTED));

    if ($signedupusers and count($signedupusers) > 0) {
        foreach ($signedupusers as $user) {
            if (onetoone_user_cancel($session, $user->userid, true)) {
                onetoone_send_cancellation_notice($onetoone, $session, $user->userid);
            }
            else {
                return false; // Cannot rollback since we notified users already
            }
        }
    }

    $transaction = $DB->start_delegated_transaction();

    // Remove entries from the teacher calendars
    $DB->delete_records_select('event', "modulename = 'onetoone' AND
                                         eventtype = 'onetoonesession' AND
                                         instance = ? AND description LIKE ?",
                                         array($onetoone->id, "%attendees.php?s={$session->id}%"));

    if ($onetoone->showoncalendar == O2O_CAL_COURSE) {
        // Remove entry from course calendar
        onetoone_remove_session_from_calendar($session, $onetoone->course);
    } else if ($onetoone->showoncalendar == O2O_CAL_SITE) {
        // Remove entry from site-wide calendar
        onetoone_remove_session_from_calendar($session, SITEID);
    }

    // Delete session details
    $DB->delete_records('onetoone_sessions', array('id' => $session->id));

    $DB->delete_records_select(
        'onetoone_signups_status',
        "signupid IN
        (
            SELECT
                id
            FROM
                {onetoone_signups}
            WHERE
                sessionid = {$session->id}
        )
        ");

    $DB->delete_records('onetoone_signups', array('sessionid' => $session->id));

    $transaction->allow_commit();

    return true;
}

/**
 * Subsitute the placeholders in email templates for the actual data
 *
 * Expects the following parameters in the $data object:
 * - datetimeknown
 * - details
 * - discountcost
 * - duration
 * - normalcost
 * - sessiondates
 *
 * @access  public
 * @param   string  $msg            Email message
 * @param   string  $onetoonename O2O name
 * @param   int     $reminderperiod Num business days before event to send reminder
 * @param   obj     $user           The subject of the message
 * @param   obj     $data           Session data
 * @param   int     $sessionid      Session ID
 * @return  string
 */
function onetoone_email_substitutions($msg, $onetoonename, $reminderperiod, $user, $data, $sessionid) {
    global $CFG, $DB;

    if (empty($msg)) {
        return '';
    }

    if ($data->datetimeknown) {
        // Scheduled session
        $sessiondate = userdate($data->timestart, get_string('strftimedate'));
        $starttime = userdate($data->timestart, get_string('strftimetime'));
        $finishtime = userdate($data->timefinish, get_string('strftimetime'));

        $alldates = '';
        //foreach ($data->sessiondates as $date) {
            if ($alldates != '') {
                $alldates .= "\n";
            }
            $alldates .= userdate($data->timestart, get_string('strftimedate')).', ';
            $alldates .= userdate($data->timestart, get_string('strftimetime')).
                ' to '.userdate($data->timefinish, get_string('strftimetime'));
       // }
    }
    else {
        // Wait-listed session
        $sessiondate = get_string('unknowndate', 'onetoone');
        $alldates    = get_string('unknowndate', 'onetoone');
        $starttime   = get_string('unknowntime', 'onetoone');
        $finishtime  = get_string('unknowntime', 'onetoone');
    }

    $msg = str_replace(get_string('placeholder:onetoonename', 'onetoone'), $onetoonename, $msg);
    $msg = str_replace(get_string('placeholder:firstname', 'onetoone'), $user->firstname, $msg);
    $msg = str_replace(get_string('placeholder:lastname', 'onetoone'), $user->lastname, $msg);
    $msg = str_replace(get_string('placeholder:cost', 'onetoone'), onetoone_cost($user->id, $sessionid, $data, false), $msg);
    $msg = str_replace(get_string('placeholder:alldates', 'onetoone'), $alldates, $msg);
    $msg = str_replace(get_string('placeholder:sessiondate', 'onetoone'), $sessiondate, $msg);
    $msg = str_replace(get_string('placeholder:starttime', 'onetoone'), $starttime, $msg);
    $msg = str_replace(get_string('placeholder:finishtime', 'onetoone'), $finishtime, $msg);
    $msg = str_replace(get_string('placeholder:duration', 'onetoone'), onetoone_format_duration($data->duration), $msg);
    if (empty($data->details)) {
        $msg = str_replace(get_string('placeholder:details', 'onetoone'), '', $msg);
    }
    else {
        $msg = str_replace(get_string('placeholder:details', 'onetoone'), html_to_text($data->details), $msg);
    }
    $msg = str_replace(get_string('placeholder:reminderperiod', 'onetoone'), $reminderperiod, $msg);

    // Replace more meta data
    $msg = str_replace(get_string('placeholder:attendeeslink', 'onetoone'), $CFG->wwwroot.'/mod/onetoone/attendees.php?s='.$data->id, $msg);

    // Custom session fields (they look like "session:shortname" in the templates)
    $customfields = onetoone_get_session_customfields();
    $customdata = $DB->get_records('onetoone_session_data', array('sessionid' => $data->id), '', 'fieldid, data');
    foreach ($customfields as $field) {
        $placeholder = "[session:{$field->shortname}]";
        $value = '';
        if (!empty($customdata[$field->id])) {
            if (O2O_CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                $value = str_replace(O2O_CUSTOMFIELD_DELIMITER, ', ', $customdata[$field->id]->data);
            } else {
                $value = $customdata[$field->id]->data;
            }
        }

        $msg = str_replace($placeholder, $value, $msg);
    }

    return $msg;
}

/**
 * Function to be run periodically according to the moodle cron
 * Finds all onetoone notifications that have yet to be mailed out, and mails them.
 */
function onetoone_cron() {
    global $CFG, $USER,$DB;

    $signupsdata = onetoone_get_unmailed_reminders();
    if (!$signupsdata) {
        echo "\n".get_string('noremindersneedtobesent', 'onetoone')."\n";
        return true;
    }

    $timenow = time();

    foreach ($signupsdata as $signupdata) {
        if (onetoone_has_session_started($signupdata, $timenow)) {
            // Too late, the session already started
            // Mark the reminder as being sent already
            $newsubmission = new stdClass();
            $newsubmission->id = $signupdata->id;
            $newsubmission->mailedreminder = 1; // magic number to show that it was not actually sent
            if (!$DB->update_record('onetoone_signups', $newsubmission)) {
                echo "ERROR: could not update mailedreminder for submission ID $signupdata->id";
            }
            continue;
        }

        //$earlieststarttime = $signupdata->sessiondates[0]->timestart;
        $earlieststarttime = $signupdata->timestart;
        //foreach ($signupdata->sessiondates as $date) {
            if ($signupdata->timestart < $earlieststarttime) {
                $earlieststarttime = $signupdata->timestart;
            }
        //}

        $reminderperiod = $signupdata->reminderperiod;

        // Convert the period from business days (no weekends) to calendar days
        for ($reminderday = 0; $reminderday < $reminderperiod + 1; $reminderday++ ) {
            $reminderdaytime = $earlieststarttime - ($reminderday * 24 * 3600);
            //use %w instead of %u for Windows compatability
            $reminderdaycheck = userdate($reminderdaytime, '%w');
            // note w runs from Sun=0 to Sat=6
            if ($reminderdaycheck == 0 || $reminderdaycheck == 6) {
                // Saturdays and Sundays are not included in the
                // reminder period as entered by the user, extend
                // that period by 1
                $reminderperiod++;
            }
        }

        $remindertime = $earlieststarttime - ($reminderperiod * 24 * 3600);
        if ($timenow < $remindertime) {
            // Too early to send reminder
            continue;
        }

        if (!$user = $DB->get_record('user', array('id' => $signupdata->userid))) {
            continue;
        }

        // Hack to make sure that the timezone and languages are set properly in emails
        // (i.e. it uses the language and timezone of the recipient of the email)
        $USER->lang = $user->lang;
        $USER->timezone = $user->timezone;

        if (!$course = $DB->get_record('course', array('id' => $signupdata->course))) {
            continue;
        }
        if (!$onetoone = $DB->get_record('onetoone', array('id' => $signupdata->onetooneid))) {
            continue;
        }

        $postsubject = '';
        $posttext = '';
        $posttextmgrheading = '';

        if (empty($signupdata->mailedreminder)) {
            $postsubject = $onetoone->remindersubject;
            $posttext = $onetoone->remindermessage;
            $posttextmgrheading = $onetoone->reminderinstrmngr;
        }

        if (empty($posttext)) {
            // The reminder message is not set, don't send anything
            continue;
        }

        $postsubject = onetoone_email_substitutions($postsubject, $signupdata->onetoonename, $signupdata->reminderperiod,
                                                      $user, $signupdata, $signupdata->sessionid);
        $posttext = onetoone_email_substitutions($posttext, $signupdata->onetoonename, $signupdata->reminderperiod,
                                                   $user, $signupdata, $signupdata->sessionid);
        $posttextmgrheading = onetoone_email_substitutions($posttextmgrheading, $signupdata->onetoonename, $signupdata->reminderperiod,
                                                             $user, $signupdata, $signupdata->sessionid);

        $posthtml = ''; // FIXME
        if ($fromaddress = get_config(NULL, 'onetoone_fromaddress')) {
            $from = new stdClass();
            $from->maildisplay = true;
            $from->email = $fromaddress;
        } else {
            $from = null;
        }

        if (email_to_user($user, $from, $postsubject, $posttext, $posthtml)) {
            echo "\n".get_string('sentreminderuser', 'onetoone').": $user->firstname $user->lastname $user->email";

            $newsubmission = new stdClass();
            $newsubmission->id = $signupdata->id;
            $newsubmission->mailedreminder = $timenow;
            if (!$DB->update_record('onetoone_signups', $newsubmission)) {
                echo "ERROR: could not update mailedreminder for submission ID $signupdata->id";
            }

            if (empty($posttextmgrheading)) {
                continue; // no manager message set
            }

            $managertext = $posttextmgrheading.$posttext;
            $manager = $user;
            $manager->email = onetoone_get_manageremail($user->id);

            if (empty($manager->email)) {
                continue; // don't know who the manager is
            }

            // Send email to mamager
            if (email_to_user($manager, $from, $postsubject, $managertext, $posthtml)) {
                echo "\n".get_string('sentremindermanager', 'onetoone').": $user->firstname $user->lastname $manager->email";
            }
            else {
                $errormsg = array();
                $errormsg['submissionid'] = $signupdata->id;
                $errormsg['userid'] = $user->id;
                $errormsg['manageremail'] = $manager->email;
                echo get_string('error:cronprefix', 'onetoone').' '.get_string('error:cannotemailmanager', 'onetoone', $errormsg)."\n";
            }
        }
        else {
            $errormsg = array();
            $errormsg['submissionid'] = $signupdata->id;
            $errormsg['userid'] = $user->id;
            $errormsg['useremail'] = $user->email;
            echo get_string('error:cronprefix', 'onetoone').' '.get_string('error:cannotemailuser', 'onetoone', $errormsg)."\n";
        }
    }

    print "\n";
    return true;
}

/**
 * Returns true if the session has started, that is if one of the
 * session dates is in the past.
 *
 * @param class $session record from the onetoone_sessions table
 * @param integer $timenow current time
 */
function onetoone_has_session_started($session, $timenow) {
    if (!$session->datetimeknown) {
        return false; // no date set
    }
    if ($session->timestart < $timenow) {
        return true;
    }
    return false;
}

/**
 * Returns true if the session has started and has not yet finished.
 *
 * @param class $session record from the onetoone_sessions table
 * @param integer $timenow current time
 */
function onetoone_is_session_in_progress($session, $timenow) {
    if (!$session->datetimeknown) {
        return false;
    }
    if ($session->timefinish > $timenow && $session->timestart < $timenow) {
        return true;
    }
    
    return false;
}

/**
 * Get a record from the onetoone_sessions table
 *
 * @param integer $sessionid ID of the session
 */
function onetoone_get_session($sessionid) {
    global $DB;
    $session = $DB->get_record('onetoone_sessions', array('id' => $sessionid));

    if ($session) {
        //$session->sessiondates = onetoone_get_session_dates($sessionid);
        $session->duration = onetoone_minutes_to_hours($session->duration);
    }

    return $session;
}

/**
 * Get all records from onetoone_sessions for a given onetoone activity and location
 *
 * @param integer $onetooneid ID of the activity
 * @param string $location location filter (optional)
 */
function onetoone_get_sessions($onetooneid, $location='') {
    global $CFG,$DB;

    $fromclause = "FROM {onetoone_sessions} s";
    $locationwhere = '';
    $locationparams = array();
    if (!empty($location)) {
        $fromclause = "FROM {onetoone_session_data} d
                       JOIN {onetoone_sessions} s ON s.id = d.sessionid";
        $locationwhere .= " AND d.data = ?";
        $locationparams[] = $location;
    }
    $sessions = $DB->get_records_sql("SELECT s.*
                                   $fromclause
                                  WHERE s.onetoone = ?
                                        $locationwhere
                               ORDER BY s.datetimeknown, s.timestart", array_merge(array($onetooneid), $locationparams));

    if ($sessions) {
        foreach ($sessions as $key => $value) {
            $sessions[$key]->duration = onetoone_minutes_to_hours($sessions[$key]->duration);
            //$sessions[$key]->sessiondates = onetoone_get_session_dates($value->id);
        }
    }
    return $sessions;
}

/**
 * Get a grade for the given user from the gradebook.
 *
 * @param integer $userid        ID of the user
 * @param integer $courseid      ID of the course
 * @param integer $onetooneid  ID of the Face-to-face activity
 *
 * @returns object String grade and the time that it was graded
 */
function onetoone_get_grade($userid, $courseid, $onetooneid) {

    $ret = new stdClass();
    $ret->grade = 0;
    $ret->dategraded = 0;

    $grading_info = grade_get_grades($courseid, 'mod', 'onetoone', $onetooneid, $userid);
    if (!empty($grading_info->items)) {
        $ret->grade = $grading_info->items[0]->grades[$userid]->str_grade;
        $ret->dategraded = $grading_info->items[0]->grades[$userid]->dategraded;
    }

    return $ret;
}

/**
 * Get list of users attending a given session
 *
 * @access public
 * @param integer Session ID
 * @return array
 */
function onetoone_get_attendees($sessionid) {
    global $CFG,$DB;
    $records = $DB->get_records_sql("
        SELECT
            u.id,
            su.id AS submissionid,
            u.firstname,
            u.lastname,
            u.email,
            u.firstnamephonetic,
            u.lastnamephonetic,
            u.middlename,
            u.alternatename,
            s.discountcost,
            su.discountcode,
            su.notificationtype,
            f.id AS onetooneid,
            f.course,
            ss.grade,
            ss.statuscode,
            sign.timecreated
        FROM
            {onetoone} f
        JOIN
            {onetoone_sessions} s
         ON s.onetoone = f.id
        JOIN
            {onetoone_signups} su
         ON s.id = su.sessionid
        JOIN
            {onetoone_signups_status} ss
         ON su.id = ss.signupid
        LEFT JOIN
            (
            SELECT
                ss.signupid,
                MAX(ss.timecreated) AS timecreated
            FROM
                {onetoone_signups_status} ss
            INNER JOIN
                {onetoone_signups} s
             ON s.id = ss.signupid
            AND s.sessionid = ?
            WHERE
                ss.statuscode IN (?,?)
            GROUP BY
                ss.signupid
            ) sign
         ON su.id = sign.signupid
        JOIN
            {user} u
         ON u.id = su.userid
        WHERE
            s.id = ?
        AND ss.superceded != 1
        AND ss.statuscode >= ?
        ORDER BY
            sign.timecreated ASC,
            ss.timecreated ASC
    ", array ($sessionid, MDL_O2O_STATUS_BOOKED, MDL_O2O_STATUS_WAITLISTED, $sessionid, MDL_O2O_STATUS_APPROVED));

    return $records;
}

/**
 * Get a single attendee of a session
 *
 * @access public
 * @param integer Session ID
 * @param integer User ID
 * @return false|object
 */
function onetoone_get_attendee($sessionid, $userid) {
    global $CFG, $DB;
    $record = $DB->get_record_sql("
        SELECT
            u.id,
            su.id AS submissionid,
            u.firstname,
            u.lastname,
            u.email,
            s.discountcost,
            su.discountcode,
            su.notificationtype,
            f.id AS onetooneid,
            f.course,
            ss.grade,
            ss.statuscode
        FROM
            {onetoone} f
        JOIN
            {onetoone_sessions} s
         ON s.onetoone = f.id
        JOIN
            {onetoone_signups} su
         ON s.id = su.sessionid
        JOIN
            {onetoone_signups_status} ss
         ON su.id = ss.signupid
        JOIN
            {user} u
         ON u.id = su.userid
        WHERE
            s.id = ?
        AND ss.superceded != 1
        AND u.id = ?
    ", array($sessionid, $userid));

    if (!$record) {
        return false;
    }

    return $record;
}

/**
 * Return all user fields to include in exports
 */
function onetoone_get_userfields() {
    global $CFG;

    static $userfields = null;
    if (null == $userfields) {
        $userfields = array();

        if (function_exists('grade_export_user_fields')) {
            $fieldnames = grade_export_user_fields();
            foreach ($fieldnames as $key => $obj) {
                $userfields[$obj->shortname] = $obj->fullname;
            }
        }
        else {
            // Set default fields if the grade export patch is not
            // detected (see MDL-17346)
            $fieldnames = array('firstname', 'lastname', 'email', 'city',
                                'idnumber', 'institution', 'department', 'address');
            foreach ($fieldnames as $shortname) {
                $userfields[$shortname] = get_string($shortname);
            }
            $userfields['managersemail'] = get_string('manageremail', 'onetoone');
        }
    }

    return $userfields;
}

/**
 * Download the list of users attending at least one of the sessions
 * for a given onetoone activity
 */
function onetoone_download_attendance($onetoonename, $onetooneid, $location, $format) {
    global $CFG;

    $timenow = time();
    $timeformat = str_replace(' ', '_', get_string('strftimedate', 'langconfig'));
    $downloadfilename = clean_filename($onetoonename.'_'.userdate($timenow, $timeformat));

    $dateformat = 0;
    if ('ods' === $format) {
        // OpenDocument format (ISO/IEC 26300)
        require_once($CFG->dirroot.'/lib/odslib.class.php');
        $downloadfilename .= '.ods';
        $workbook = new MoodleODSWorkbook('-');
    } else {
        // Excel format
        require_once($CFG->dirroot.'/lib/excellib.class.php');
        $downloadfilename .= '.xls';
        $workbook = new MoodleExcelWorkbook('-');
        $dateformat =$workbook->add_format();
        $dateformat->set_num_format('d mmm yy'); // TODO: use format specified in language pack
    }

    $workbook->send($downloadfilename);
    $worksheet =$workbook->add_worksheet('attendance');
    onetoone_write_worksheet_header($worksheet);
    onetoone_write_activity_attendance($worksheet, 1, $onetooneid, $location, '', '', $dateformat);
    $workbook->close();
    exit;
}

/**
 * Add the appropriate column headers to the given worksheet
 *
 * @param object $worksheet  The worksheet to modify (passed by reference)
 * @returns integer The index of the next column
 */
function onetoone_write_worksheet_header(&$worksheet)
{
    $pos=0;
    $customfields = onetoone_get_session_customfields();
    foreach ($customfields as $field) {
        if (!empty($field->showinsummary)) {
            $worksheet->write_string(0, $pos++, $field->name);
        }
    }
    $worksheet->write_string(0, $pos++, get_string('date', 'onetoone'));
    $worksheet->write_string(0, $pos++, get_string('timestart', 'onetoone'));
    $worksheet->write_string(0, $pos++, get_string('timefinish', 'onetoone'));
    $worksheet->write_string(0, $pos++, get_string('duration', 'onetoone'));
    $worksheet->write_string(0, $pos++, get_string('status', 'onetoone'));

    if ($trainerroles = onetoone_get_trainer_roles()) {
        foreach ($trainerroles as $role) {
            $worksheet->write_string(0, $pos++, get_string('role').': '.$role->name);
        }
    }

    $userfields = onetoone_get_userfields();
    foreach ($userfields as $shortname => $fullname) {
        $worksheet->write_string(0, $pos++, $fullname);
    }

    $worksheet->write_string(0, $pos++, get_string('attendance', 'onetoone'));
    $worksheet->write_string(0, $pos++, get_string('datesignedup', 'onetoone'));

    return $pos;
}

/**
 * Write in the worksheet the given onetoone attendance information
 * filtered by location.
 *
 * This function includes lots of custom SQL because it's otherwise
 * way too slow.
 *
 * @param object  $worksheet    Currently open worksheet
 * @param integer $startingrow  Index of the starting row (usually 1)
 * @param integer $onetooneid ID of the onetoone activity
 * @param string  $location     Location to filter by
 * @param string  $coursename   Name of the course (optional)
 * @param string  $activityname Name of the onetoone activity (optional)
 * @param object  $dateformat   Use to write out dates in the spreadsheet
 * @returns integer Index of the last row written
 */
function onetoone_write_activity_attendance(&$worksheet, $startingrow, $onetooneid, $location,
                                              $coursename, $activityname, $dateformat)
{
    global $CFG, $DB;

    $trainerroles = onetoone_get_trainer_roles();
    $userfields = onetoone_get_userfields();
    $customsessionfields = onetoone_get_session_customfields();
    $timenow = time();
    $i = $startingrow;

    $locationcondition = '';
    $locationparam = array();
    if (!empty($location)) {
        $locationcondition = "AND s.location = ?";
        $locationparam = array($location);
    }

    // Fast version of "onetoone_get_attendees()" for all sessions
    $sessionsignups = array();
    $signups = $DB->get_records_sql("
        SELECT
            su.id AS submissionid,
            s.id AS sessionid,
            u.*,
            f.course AS courseid,
            ss.grade,
            sign.timecreated
        FROM
            {onetoone} f
        JOIN
            {onetoone_sessions} s
         ON s.onetoone = f.id
        JOIN
            {onetoone_signups} su
         ON s.id = su.sessionid
        JOIN
            {onetoone_signups_status} ss
         ON su.id = ss.signupid
        LEFT JOIN
            (
            SELECT
                ss.signupid,
                MAX(ss.timecreated) AS timecreated
            FROM
                {onetoone_signups_status} ss
            INNER JOIN
                {onetoone_signups} s
             ON s.id = ss.signupid
            INNER JOIN
                {onetoone_sessions} se
             ON s.sessionid = se.id
            AND se.onetoone = $onetooneid
            WHERE
                ss.statuscode IN (?,?)
            GROUP BY
                ss.signupid
            ) sign
         ON su.id = sign.signupid
        JOIN
            {user} u
         ON u.id = su.userid
        WHERE
            f.id = ?
        AND ss.superceded != 1
        AND ss.statuscode >= ?
        ORDER BY
            s.id, u.firstname, u.lastname
    ", array(MDL_O2O_STATUS_BOOKED, MDL_O2O_STATUS_WAITLISTED, $onetooneid, MDL_O2O_STATUS_APPROVED));

    if ($signups) {
        // Get all grades at once
        $userids = array();
        foreach ($signups as $signup) {
            if ($signup->id > 0) {
                $userids[] = $signup->id;
            }
        }
        $grading_info = grade_get_grades(reset($signups)->courseid, 'mod', 'onetoone',
                                         $onetooneid, $userids);

        foreach ($signups as $signup) {
            $userid = $signup->id;

            if ($customuserfields = onetoone_get_user_customfields($userid, $userfields)) {
                foreach ($customuserfields as $fieldname => $value) {
                    if (!isset($signup->$fieldname)) {
                        $signup->$fieldname = $value;
                    }
                }
            }

            // Set grade
            if (!empty($grading_info->items) and !empty($grading_info->items[0]->grades[$userid])) {
                $signup->grade = $grading_info->items[0]->grades[$userid]->str_grade;
            }

            $sessionsignups[$signup->sessionid][$signup->id] = $signup;
        }
    }
  
    $sql = "SELECT s.id as dateid, s.id, s.datetimeknown, s.capacity,
                   s.duration, s.timestart, s.timefinish
               FROM {onetoone_sessions} s              
               WHERE
                 s.onetoone = ?              
                    $locationcondition
                    ORDER BY s.datetimeknown, s.timestart";
    
    $sessions = $DB->get_records_sql($sql, array_merge(array($onetooneid), $locationparam));
    
    $i = $i - 1; // will be incremented BEFORE each row is written

    foreach ($sessions as $session) {
        $customdata = $DB->get_records('onetoone_session_data', array('sessionid' => $session->id), '', 'fieldid, data');

        $sessiondate = false;
        $starttime   = get_string('wait-listed', 'onetoone');
        $finishtime  = get_string('wait-listed', 'onetoone');
        $status      = get_string('wait-listed', 'onetoone');

        $sessiontrainers = onetoone_get_trainers($session->id);

        if ($session->datetimeknown) {
            // Display only the first date
            if (method_exists($worksheet, 'write_date')) {
                // Needs the patch in MDL-20781
                $sessiondate = (int)$session->timestart;
            }
            else {
                $sessiondate = userdate($session->timestart, get_string('strftimedate', 'langconfig'));
            }
            $starttime   = userdate($session->timestart, get_string('strftimetime', 'langconfig'));
            $finishtime  = userdate($session->timefinish, get_string('strftimetime', 'langconfig'));

            if ($session->timestart < $timenow) {
                $status = get_string('sessionover', 'onetoone');
            }
            else {
                $signupcount = 0;
                if (!empty($sessionsignups[$session->id])) {
                    $signupcount = count($sessionsignups[$session->id]);
                }

                if ($signupcount >= $session->capacity) {
                    $status = get_string('bookingfull', 'onetoone');
                } else {
                    $status = get_string('bookingopen', 'onetoone');
                }
            }
        }

        if (!empty($sessionsignups[$session->id])) {
            foreach ($sessionsignups[$session->id] as $attendee) {
                $i++; $j=0;

                // Custom session fields
                foreach ($customsessionfields as $field) {
                    if (empty($field->showinsummary)) {
                        continue; // skip
                    }

                    $data = '-';
                    if (!empty($customdata[$field->id])) {
                        if (O2O_CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                            $data = str_replace(O2O_CUSTOMFIELD_DELIMITER, "\n", $customdata[$field->id]->data);
                        } else {
                            $data = $customdata[$field->id]->data;
                        }
                    }
                    $worksheet->write_string($i, $j++, $data);
                }

                if (empty($sessiondate)) {
                    $worksheet->write_string($i, $j++, $status); // session date
                }
                else {
                    if (method_exists($worksheet, 'write_date')) {
                        $worksheet->write_date($i, $j++, $sessiondate, $dateformat);
                    }
                    else {
                        $worksheet->write_string($i, $j++, $sessiondate);
                    }
                }
                $worksheet->write_string($i,$j++,$starttime);
                $worksheet->write_string($i,$j++,$finishtime);
                $worksheet->write_number($i,$j++,(int)$session->duration);
                $worksheet->write_string($i,$j++,$status);

                if ($trainerroles) {
                    foreach (array_keys($trainerroles) as $roleid) {
                        if (!empty($sessiontrainers[$roleid])) {
                            $trainers = array();
                            foreach ($sessiontrainers[$roleid] as $trainer) {
                                $trainers[] = fullname($trainer);
                            }

                            $trainers = implode(', ', $trainers);
                        }
                        else {
                            $trainers = '-';
                        }

                        $worksheet->write_string($i, $j++, $trainers);
                    }
                }

                foreach ($userfields as $shortname => $fullname) {
                    $value = '-';
                    if (!empty($attendee->$shortname)) {
                        $value = $attendee->$shortname;
                    }

                    if ('firstaccess' == $shortname or 'lastaccess' == $shortname or
                        'lastlogin' == $shortname or 'currentlogin' == $shortname) {

                            if (method_exists($worksheet, 'write_date')) {
                                $worksheet->write_date($i, $j++, (int)$value, $dateformat);
                            }
                            else {
                                $worksheet->write_string($i, $j++, userdate($value, get_string('strftimedate', 'langconfig')));
                            }
                        }
                    else {
                        $worksheet->write_string($i,$j++,$value);
                    }
                }
                $worksheet->write_string($i,$j++,$attendee->grade);

                if (method_exists($worksheet,'write_date')) {
                    $worksheet->write_date($i, $j++, (int)$attendee->timecreated, $dateformat);
                } else {
                    $signupdate = userdate($attendee->timecreated, get_string('strftimedatetime', 'langconfig'));
                    if (empty($signupdate)) {
                        $signupdate = '-';
                    }
                    $worksheet->write_string($i,$j++, $signupdate);
                }

                if (!empty($coursename)) {
                    $worksheet->write_string($i, $j++, $coursename);
                }
                if (!empty($activityname)) {
                    $worksheet->write_string($i, $j++, $activityname);
                }
            }
        }
        else {
            // no one is sign-up, so let's just print the basic info
            $i++; $j=0;

            // Custom session fields
            foreach ($customsessionfields as $field) {
                if (empty($field->showinsummary)) {
                    continue; // skip
                }

                $data = '-';
                if (!empty($customdata[$field->id])) {
                    if (O2O_CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                        $data = str_replace(O2O_CUSTOMFIELD_DELIMITER, "\n", $customdata[$field->id]->data);
                    } else {
                        $data = $customdata[$field->id]->data;
                    }
                }
                $worksheet->write_string($i, $j++, $data);
            }

            if (empty($sessiondate)) {
                $worksheet->write_string($i, $j++, $status); // session date
            }
            else {
                if (method_exists($worksheet, 'write_date')) {
                    $worksheet->write_date($i, $j++, $sessiondate, $dateformat);
                }
                else {
                    $worksheet->write_string($i, $j++, $sessiondate);
                }
            }
            $worksheet->write_string($i,$j++,$starttime);
            $worksheet->write_string($i,$j++,$finishtime);
            $worksheet->write_number($i,$j++,(int)$session->duration);
            $worksheet->write_string($i,$j++,$status);
            foreach ($userfields as $unused) {
                $worksheet->write_string($i,$j++,'-');
            }
            $worksheet->write_string($i,$j++,'-');

            if (!empty($coursename)) {
                $worksheet->write_string($i, $j++, $coursename);
            }
            if (!empty($activityname)) {
                $worksheet->write_string($i, $j++, $activityname);
            }
        }
    }

    return $i;
}

/**
 * Return an object with all values for a user's custom fields.
 *
 * This is about 15 times faster than the custom field API.
 *
 * @param array $fieldstoinclude Limit the fields returned/cached to these ones (optional)
 */
function onetoone_get_user_customfields($userid, $fieldstoinclude=false)
{
    global $CFG, $DB;

    // Cache all lookup
    static $customfields = null;
    if (null == $customfields) {
        $customfields = array();
    }

    if (!empty($customfields[$userid])) {
        return $customfields[$userid];
    }

    $ret = new stdClass();

    $sql = "SELECT uif.shortname, id.data
              FROM {user_info_field} uif
              JOIN {user_info_data} id ON id.fieldid = uif.id
              WHERE id.userid = ?";

    $customfields = $DB->get_records_sql($sql, array($userid));
    foreach ($customfields as $field) {
        $fieldname = $field->shortname;
        if (false === $fieldstoinclude or !empty($fieldstoinclude[$fieldname])) {
            $ret->$fieldname = $field->data;
        }
    }

    $customfields[$userid] = $ret;
    return $ret;
}

/**
 * Return list of marked submissions that have not been mailed out for currently enrolled students
 */
function onetoone_get_unmailed_reminders() {
    global $CFG, $DB;

    $submissions = $DB->get_records_sql("
        SELECT
            su.*,
            f.course,
            f.id as onetooneid,
            f.name as onetoonename,
            f.reminderperiod,
            se.duration,
            se.normalcost,
            se.discountcost,
            se.details,
            se.datetimeknown
        FROM
            {onetoone_signups} su
        INNER JOIN
            {onetoone_signups_status} sus
         ON su.id = sus.signupid
        AND sus.superceded = 0
        AND sus.statuscode = ?
        JOIN
            {onetoone_sessions} se
         ON su.sessionid = se.id
        JOIN
            {onetoone} f
         ON se.onetoone = f.id
        WHERE
            su.mailedreminder = 0
        AND se.datetimeknown = 1
    ", array(MDL_O2O_STATUS_BOOKED));

    if ($submissions) {
        foreach ($submissions as $key => $value) {
            $submissions[$key]->duration = onetoone_minutes_to_hours($submissions[$key]->duration);
            //$submissions[$key]->sessiondates = onetoone_get_session_dates($value->sessionid);
        }
    }

    return $submissions;
}

/**
 * Add a record to the onetoone submissions table and sends out an
 * email confirmation
 *
 * @param class $session record from the onetoone_sessions table
 * @param class $onetoone record from the onetoone table
 * @param class $course record from the course table
 * @param string $discountcode code entered by the user
 * @param integer $notificationtype type of notifications to send to user
 * @see {{MDL_O2O_INVITE}}
 * @param integer $statuscode Status code to set
 * @param integer $userid user to signup
 * @param bool $notifyuser whether or not to send an email confirmation
 * @param bool $displayerrors whether or not to return an error page on errors
 */
function onetoone_user_signup($session, $onetoone, $course, $discountcode,
                                $notificationtype, $statuscode, $userid = false,
                                $notifyuser = true) {

    global $CFG, $DB;

    // Get user id
    if (!$userid) {
        global $USER;
        $userid = $USER->id;
    }

    $return = false;
    $timenow = time();

    // Check to see if a signup already exists
    if ($existingsignup = $DB->get_record('onetoone_signups', array('sessionid' => $session->id, 'userid' => $userid))) {
        $usersignup = $existingsignup;
    } else {
        // Otherwise, prepare a signup object
        $usersignup = new stdclass;
        $usersignup->sessionid = $session->id;
        $usersignup->userid = $userid;
    }

    $usersignup->mailedreminder = 0;
    $usersignup->notificationtype = $notificationtype;

    $usersignup->discountcode = trim(strtoupper($discountcode));
    if (empty($usersignup->discountcode)) {
        $usersignup->discountcode = null;
    }

 //   begin_sql();

    // Update/insert the signup record
    if (!empty($usersignup->id)) {
        $success =  $DB->update_record('onetoone_signups', $usersignup);
        
    } else {
        $usersignup->id =  $DB->insert_record('onetoone_signups', $usersignup);
        $success = (bool)$usersignup->id;
   
    }

    if (!$success) {
        //rollback_sql();
        print_error('error:couldnotupdateo2orecord', 'onetoone');
        return false;
    }

    // Work out which status to use

    // If approval not required
    if (!$onetoone->approvalreqd) {
        $new_status = $statuscode;
    } else {
        // If approval required

        // Get current status (if any)
        $current_status =  $DB->get_field('onetoone_signups_status', 'statuscode', array('signupid' => $usersignup->id, 'superceded' => 0));

        // If approved, then no problem
        if ($current_status == MDL_O2O_STATUS_APPROVED) {
            $new_status = $statuscode;
        } else if ($session->datetimeknown) {
        // Otherwise, send manager request
            $new_status = MDL_O2O_STATUS_REQUESTED;
        } else {
            $new_status = MDL_O2O_STATUS_WAITLISTED;
        }
    }

    // Update status
    if (!onetoone_update_signup_status($usersignup->id, $new_status, $userid)) {
        //rollback_sql();
        print_error('error:f2ffailedupdatestatus', 'onetoone');
        return false;
    }

    // Add to user calendar -- if onetoone usercalentry is set to true
    if ($onetoone->usercalentry) {
        if (in_array($new_status, array(MDL_O2O_STATUS_BOOKED, MDL_O2O_STATUS_WAITLISTED))) {
            onetoone_add_session_to_calendar($session, $onetoone, 'user', $userid, 'booking');
        }
    }

    // Course completion
    if (in_array($new_status, array(MDL_O2O_STATUS_BOOKED, MDL_O2O_STATUS_WAITLISTED))) {

        $completion = new completion_info($course);
        if ($completion->is_enabled()) {

            $ccdetails = array(
                'course'        => $course->id,
                'userid'        => $userid,
            );

            $cc = new completion_completion($ccdetails);
            $cc->mark_inprogress($timenow);
        }
    }

    // If session has already started, do not send a notification
    if (onetoone_has_session_started($session, $timenow)) {
        $notifyuser = false;
    }

    // Send notification
    if ($notifyuser) {
        // If booked/waitlisted
        switch ($new_status) {
            case MDL_O2O_STATUS_BOOKED:
                $error = onetoone_send_confirmation_notice($onetoone, $session, $userid, $notificationtype, false);
                break;

            case MDL_O2O_STATUS_WAITLISTED:
                $error = onetoone_send_confirmation_notice($onetoone, $session, $userid, $notificationtype, true);
                break;

            case MDL_O2O_STATUS_REQUESTED:
                $error = onetoone_send_request_notice($onetoone, $session, $userid);
                break;
        }

        if (!empty($error)) {
            // rollback_sql();
            print_error($error, 'onetoone');
            return false;
        }

        if (!$DB->update_record('onetoone_signups', $usersignup)) {
            //rollback_sql();
            print_error('error:couldnotupdateo2orecord', 'onetoone');
            return false;
        }
    }

    //commit_sql();
    return true;
}

/**
 * Send booking request notice to user and their manager
 *
 * @param   object  $onetoone onetoone instance
 * @param   object  $session    Session instance
 * @param   int     $userid     ID of user requesting booking
 * @return  string  Error string, empty on success
 */
function onetoone_send_request_notice($onetoone, $session, $userid) {
    global $DB;
    if (!$manageremail = onetoone_get_manageremail($userid)) {
        return 'error:nomanagersemailset';
    }

    $user = $DB->get_record('user', array('id' => $userid));
    if (!$user) {
        return 'error:invaliduserid';
    }

    if ($fromaddress = get_config(NULL, 'onetoone_fromaddress')) {
        $from = new stdClass();
        $from->maildisplay = true;
        $from->email = $fromaddress;
    } else {
        $from = null;
    }

    $postsubject = onetoone_email_substitutions(
            $onetoone->requestsubject,
            $onetoone->name,
            $onetoone->reminderperiod,
            $user,
            $session,
            $session->id
    );

    $posttext = onetoone_email_substitutions(
            $onetoone->requestmessage,
            $onetoone->name,
            $onetoone->reminderperiod,
            $user,
            $session,
            $session->id
    );

    $posttextmgrheading = onetoone_email_substitutions(
            $onetoone->requestinstrmngr,
            $onetoone->name,
            $onetoone->reminderperiod,
            $user,
            $session,
            $session->id
    );

    // Send to user
    if (!email_to_user($user, $from, $postsubject, $posttext)) {
        return 'error:cannotsendrequestuser';
    }

    // Send to manager
    $user->email = $manageremail;

    if (!email_to_user($user, $from, $postsubject, $posttextmgrheading.$posttext)) {
        return 'error:cannotsendrequestmanager';
    }

    return '';
}


/**
 * Update the signup status of a particular signup
 *
 * @param integer $signupid ID of the signup to be updated
 * @param integer $statuscode Status code to be updated to
 * @param integer $createdby User ID of the user causing the status update
 * @param string $note Cancellation reason or other notes
 * @param int $grade Grade
 * @param bool $usetransaction Set to true if database transactions are to be used
 *
 * @returns integer ID of newly created signup status, or false
 *
 */
function onetoone_update_signup_status($signupid, $statuscode, $createdby, $note='', $grade=NULL) {
    global $DB;
    $timenow = time();

    $signupstatus = new stdclass;
    $signupstatus->signupid = $signupid;
    $signupstatus->statuscode = $statuscode;
    $signupstatus->createdby = $createdby;
    $signupstatus->timecreated = $timenow;
    $signupstatus->note = $note;
    $signupstatus->grade = $grade;
    $signupstatus->superceded = 0;
    $signupstatus->mailed = 0;

    $transaction = $DB->start_delegated_transaction();

    if ($statusid = $DB->insert_record('onetoone_signups_status', $signupstatus)) {
        // mark any previous signup_statuses as superceded
        $where = "signupid = ? AND ( superceded = 0 OR superceded IS NULL ) AND id != ?";
        $whereparams = array($signupid, $statusid);
        $DB->set_field_select('onetoone_signups_status', 'superceded', 1, $where, $whereparams);
        $transaction->allow_commit();
        return $statusid;
    } else {
        return false;
    }
}

/**
 * Cancel a user who signed up earlier
 *
 * @param class $session       Record from the onetoone_sessions table
 * @param integer $userid      ID of the user to remove from the session
 * @param bool $forcecancel    Forces cancellation of sessions that have already occurred
 * @param string $errorstr     Passed by reference. For setting error string in calling function
 * @param string $cancelreason Optional justification for cancelling the signup
 */
function onetoone_user_cancel($session, $userid=false, $forcecancel=false, &$errorstr=null, $cancelreason='') {
    if (!$userid) {
        global $USER;
        $userid = $USER->id;
    }

    // if $forcecancel is set, cancel session even if already occurred
    // used by facetotoface_delete_session()
    if (!$forcecancel) {
        $timenow = time();
        // don't allow user to cancel a session that has already occurred
        if (onetoone_has_session_started($session, $timenow)) {
            $errorstr = get_string('error:eventoccurred', 'onetoone');
            return false;
        }
    }

    if (onetoone_user_cancel_submission($session->id, $userid, $cancelreason)) {
        onetoone_remove_session_from_calendar($session, 0, $userid);

        onetoone_update_attendees($session);

        return true;
    }

    $errorstr = get_string('error:cancelbooking', 'onetoone');
    return false;
}

/**
 * Common code for sending confirmation and cancellation notices
 *
 * @param string $postsubject Subject of the email
 * @param string $posttext Plain text contents of the email
 * @param string $posttextmgrheading Header to prepend to $posttext in manager email
 * @param string $notificationtype The type of notification to send
 * @see {{MDL_O2O_INVITE}}
 * @param class $onetoone record from the onetoone table
 * @param class $session record from the onetoone_sessions table
 * @param integer $userid ID of the recipient of the email
 * @returns string Error message (or empty string if successful)
 */
function onetoone_send_notice($postsubject, $posttext, $posttextmgrheading,
                                $notificationtype, $onetoone, $session, $userid) {
    global $CFG, $DB;

    $user = $DB->get_record('user', array('id' => $userid));
    if (!$user) {
        return 'error:invaliduserid';
    }

    if (empty($postsubject) || empty($posttext)) {
        return '';
    }

    // If no notice type is defined (TEXT or ICAL)
    if (!($notificationtype & MDL_O2O_BOTH)) {
        // If none, make sure they at least get a text email
        $notificationtype |= MDL_O2O_TEXT;
    }

    // If we are cancelling, check if ical cancellations are disabled
    if (($notificationtype & MDL_O2O_CANCEL) &&
        get_config(NULL, 'onetoone_disableicalcancel')) {
        $notificationtype |= MDL_O2O_TEXT; // add a text notification
        $notificationtype &= ~MDL_O2O_ICAL; // remove the iCalendar notification
    }

    // If we are sending an ical attachment, set file name
    if ($notificationtype & MDL_O2O_ICAL) {
        if ($notificationtype & MDL_O2O_INVITE) {
            $attachmentfilename = 'invite.ics';
        } else if ($notificationtype & MDL_O2O_CANCEL) {
            $attachmentfilename = 'cancel.ics';
        }
    }

    // Do iCal attachement stuff
    $icalattachments = array();
    if ($notificationtype & MDL_O2O_ICAL) {
        if (get_config(NULL, 'onetoone_oneemailperday')) {
            // Keep track of all sessiondates
            //$sessiondates = $session->sessiondates;

            //foreach ($sessiondates as $sessiondate) {
                //$session->sessiondates = array($sessiondate); // one day at a time

                $filename = onetoone_get_ical_attachment($notificationtype, $onetoone, $session, $user);
                $subject = onetoone_email_substitutions($postsubject, $onetoone->name, $onetoone->reminderperiod,
                                                          $user, $session, $session->id);
                $body = onetoone_email_substitutions($posttext, $onetoone->name, $onetoone->reminderperiod,
                                                       $user, $session, $session->id);
                $htmlbody = ''; // TODO
                $icalattachments[] = array('filename' => $filename, 'subject' => $subject,
                                           'body' => $body, 'htmlbody' => $htmlbody);
            //}

            // Restore session dates
            //$session->sessiondates = $sessiondates;
        } else {
            $filename = onetoone_get_ical_attachment($notificationtype, $onetoone, $session, $user);
            $subject = onetoone_email_substitutions($postsubject, $onetoone->name, $onetoone->reminderperiod,
                                                      $user, $session, $session->id);
            $body = onetoone_email_substitutions($posttext, $onetoone->name, $onetoone->reminderperiod,
                                                   $user, $session, $session->id);
            $htmlbody = ''; // FIXME
            $icalattachments[] = array('filename' => $filename, 'subject' => $subject,
                                       'body' => $body, 'htmlbody' => $htmlbody);
        }
    }

    // Fill-in the email placeholders
    $postsubject = onetoone_email_substitutions($postsubject, $onetoone->name, $onetoone->reminderperiod,
                                                  $user, $session, $session->id);
    $posttext = onetoone_email_substitutions($posttext, $onetoone->name, $onetoone->reminderperiod,
                                               $user, $session, $session->id);

    $posttextmgrheading = onetoone_email_substitutions($posttextmgrheading, $onetoone->name, $onetoone->reminderperiod,
                                                         $user, $session, $session->id);

    $posthtml = ''; // FIXME
    if ($fromaddress = get_config(NULL, 'onetoone_fromaddress')) {
        $from = new stdClass();
        $from->maildisplay = true;
        $from->email = $fromaddress;
    } else {
        $from = null;
    }

    $usercheck = $DB->get_record('user', array('id' => $userid));

    // Send email with iCal attachment
    if ($notificationtype & MDL_O2O_ICAL) {
        foreach ($icalattachments as $attachment) {
            if (!email_to_user($user, $from, $attachment['subject'], $attachment['body'],
                    $attachment['htmlbody'], $attachment['filename'], $attachmentfilename)) {

                return 'error:cannotsendconfirmationuser';
            }
            unlink($CFG->dataroot . '/' . $attachment['filename']);
        }
    }

    // Send plain text email
    if ($notificationtype & MDL_O2O_TEXT) {
        if (!email_to_user($user, $from, $postsubject, $posttext, $posthtml)) {
            return 'error:cannotsendconfirmationuser';
        }
    }

    // Manager notification
    $manageremail = onetoone_get_manageremail($userid);
    if (!empty($posttextmgrheading) and !empty($manageremail) and $session->datetimeknown) {
        $managertext = $posttextmgrheading.$posttext;
        $manager = $user;
        $manager->email = $manageremail;

        // Leave out the ical attachments in the managers notification
        if (!email_to_user($manager, $from, $postsubject, $managertext, $posthtml)) {
            return 'error:cannotsendconfirmationmanager';
        }
    }

    // Third-party notification
    if (!empty($onetoone->thirdparty) &&
        ($session->datetimeknown || !empty($onetoone->thirdpartywaitlist))) {

        $thirdparty = $user;
        $recipients = explode(',', $onetoone->thirdparty);
        foreach ($recipients as $recipient) {
            $thirdparty->email = trim($recipient);

            // Leave out the ical attachments in the 3rd parties notification
            if (!email_to_user($thirdparty, $from, $postsubject, $posttext, $posthtml)) {
                return 'error:cannotsendconfirmationthirdparty';
            }
        }
    }
    return '';
}

/**
 * Send a confirmation email to the user and manager
 *
 * @param class $onetoone record from the onetoone table
 * @param class $session record from the onetoone_sessions table
 * @param integer $userid ID of the recipient of the email
 * @param integer $notificationtype Type of notifications to be sent @see {{MDL_O2O_INVITE}}
 * @param boolean $iswaitlisted If the user has been waitlisted
 * @returns string Error message (or empty string if successful)
 */
function onetoone_send_confirmation_notice($onetoone, $session, $userid, $notificationtype, $iswaitlisted) {

    $posttextmgrheading = $onetoone->confirmationinstrmngr;

    if (!$iswaitlisted) {
        $postsubject = $onetoone->confirmationsubject;
        $posttext = $onetoone->confirmationmessage;
    } else {
        $postsubject = $onetoone->waitlistedsubject;
        $posttext = $onetoone->waitlistedmessage;

        // Don't send an iCal attachement when we don't know the date!
        $notificationtype |= MDL_O2O_TEXT; // add a text notification
        $notificationtype &= ~MDL_O2O_ICAL; // remove the iCalendar notification
    }

    // Set invite bit
    $notificationtype |= MDL_O2O_INVITE;

    return onetoone_send_notice($postsubject, $posttext, $posttextmgrheading,
                                  $notificationtype, $onetoone, $session, $userid);
}

/**
 * Send a confirmation email to the user and manager regarding the
 * cancellation
 *
 * @param class $onetoone record from the onetoone table
 * @param class $session record from the onetoone_sessions table
 * @param integer $userid ID of the recipient of the email
 * @returns string Error message (or empty string if successful)
 */
function onetoone_send_cancellation_notice($onetoone, $session, $userid) {
    global $DB;

    $postsubject = $onetoone->cancellationsubject;
    $posttext = $onetoone->cancellationmessage;
    $posttextmgrheading = $onetoone->cancellationinstrmngr;

    // Lookup what type of notification to send
    $notificationtype = $DB->get_field('onetoone_signups', 'notificationtype',
                                  array('sessionid' => $session->id, 'userid' => $userid));

    // Set cancellation bit
    $notificationtype |= MDL_O2O_CANCEL;

    return onetoone_send_notice($postsubject, $posttext, $posttextmgrheading,
                                  $notificationtype, $onetoone, $session, $userid);
}

/**
 * Returns true if the user has registered for a session in the given
 * onetoone activity
 *
 * @global class $USER used to get the current userid
 * @returns integer The session id that we signed up for, false otherwise
 */
function onetoone_check_signup($onetooneid) {
    global $USER;
    if ($submissions = onetoone_get_user_submissions($onetooneid, $USER->id)) {
        return reset($submissions)->sessionid;
    } else {
        return false;
    }
}

/**
 * Return the email address of the user's manager if it is
 * defined. Otherwise return an empty string.
 *
 * @param integer $userid User ID of the staff member
 */
function onetoone_get_manageremail($userid) {
    global $DB;
    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => O2O_MDL_MANAGERSEMAIL_FIELD));
    if ($fieldid) {
        return $DB->get_field('user_info_data', 'data', array('userid' => $userid, 'fieldid' => $fieldid));
    }
    else {
        return ''; // No custom field => no manager's email
    }
}

/**
 * Human-readable version of the format of the manager's email address
 */
function onetoone_get_manageremailformat() {

    $addressformat = get_config(NULL, 'onetoone_manageraddressformat');

    if (!empty($addressformat)) {
        $readableformat = get_config(NULL, 'onetoone_manageraddressformatreadable');
        return get_string('manageremailformat', 'onetoone', $readableformat);
    }

    return '';
}

/**
 * Returns true if the given email address follows the format
 * prescribed by the site administrator
 *
 * @param string $manageremail email address as entered by the user
 */
function onetoone_check_manageremail($manageremail) {

    $addressformat = get_config(NULL, 'onetoone_manageraddressformat');

    if (empty($addressformat) || strpos($manageremail, $addressformat)) {
        return true;
    } else {
        return false;
    }
}

/**
 * Mark the fact that the user attended the onetoone session by
 * giving that user a grade of 100
 *
 * @param array $data array containing the sessionid under the 's' key
 *                    and every submission ID to mark as attended
 *                    under the 'submissionid_XXXX' keys where XXXX is
 *                     the ID of the signup
 */
function onetoone_take_attendance($data) {
    global $USER;

    $sessionid = $data->s;

    // Load session
    if (!$session = onetoone_get_session($sessionid)) {
        error_log('O2O: Could not load onetoone session');
        return false;
    }

    // Check onetoone has finished
    if ($session->datetimeknown && !onetoone_has_session_started($session, time())) {
        error_log('O2O: Can not take attendance for a session that has not yet started');
        return false;
    }

    // Record the selected attendees from the user interface - the other attendees will need their grades set
    // to zero, to indicate non attendance, but only the ticked attendees come through from the web interface.
    // Hence the need for a diff
    $selectedsubmissionids = array();

    // FIXME: This is not very efficient, we should do the grade
    // query outside of the loop to get all submissions for a
    // given Face-to-face ID, then call
    // onetoone_grade_item_update with an array of grade
    // objects.
    foreach ($data as $key => $value) {

        $submissionidcheck = substr($key, 0, 13);
        if ($submissionidcheck == 'submissionid_') {
            $submissionid = substr($key, 13);
            $selectedsubmissionids[$submissionid]=$submissionid;

            // Update status
            switch ($value) {

                case MDL_O2O_STATUS_NO_SHOW:
                    $grade = 0;
                    break;

                case MDL_O2O_STATUS_PARTIALLY_ATTENDED:
                    $grade = 50;
                    break;

                case MDL_O2O_STATUS_FULLY_ATTENDED:
                    $grade = 100;
                    break;

                default:
                    // This use has not had attendance set
                    // Jump to the next item in the foreach loop
                    continue 2;
            }

            onetoone_update_signup_status($submissionid, $value, $USER->id, '', $grade);

            if (!onetoone_take_individual_attendance($submissionid, $grade)) {
                error_log("O2O: could not mark '$submissionid' as ".$value);
                return false;
            }
        }
    }

    return true;
}

/**
 * Mark users' booking requests as declined or approved
 *
 * @param array $data array containing the sessionid under the 's' key
 *                    and an array of request approval/denies
 */
function onetoone_approve_requests($data) {
    global $USER, $DB;

    // Check request data
    if (empty($data->requests) || !is_array($data->requests)) {
        error_log('O2O: No request data supplied');
        return false;
    }

    $sessionid = $data->s;

    // Load session
    if (!$session = onetoone_get_session($sessionid)) {
        error_log('O2O: Could not load onetoone session');
        return false;
    }

    // Load onetoone
    if (!$onetoone = $DB->get_record('onetoone', array('id' => $session->onetoone))) {
        error_log('O2O: Could not load onetoone instance');
        return false;
    }

    // Load course
    if (!$course = $DB->get_record('course', array('id' => $onetoone->course))) {
        error_log('O2O: Could not load course');
        return false;
    }

    // Loop through requests
    foreach ($data->requests as $key => $value) {

        // Check key/value
        if (!is_numeric($key) || !is_numeric($value)) {
            continue;
        }

        // Load user submission
        if (!$attendee = onetoone_get_attendee($sessionid, $key)) {
            error_log('O2O: User '.$key.' not an attendee of this session');
            continue;
        }

        // Update status
        switch ($value) {
            // Decline
            case 1:
                onetoone_update_signup_status(
                        $attendee->submissionid,
                        MDL_O2O_STATUS_DECLINED,
                        $USER->id
                );

                // Send a cancellation notice to the user
                onetoone_send_cancellation_notice($onetoone, $session, $attendee->id);

                break;

            // Approve
            case 2:
                onetoone_update_signup_status(
                        $attendee->submissionid,
                        MDL_O2O_STATUS_APPROVED,
                        $USER->id
                );

                if (!$cm = get_coursemodule_from_instance('onetoone', $onetoone->id, $course->id)) {
                    print_error('error:incorrectcoursemodule', 'onetoone');
                }

                $contextmodule = context_module::instance($cm->id);

                // Check if there is capacity
                if (onetoone_session_has_capacity($session, $contextmodule)) {
                    $status = MDL_O2O_STATUS_BOOKED;
                } else {
                    if ($session->allowoverbook) {
                        $status = MDL_O2O_STATUS_WAITLISTED;
                    }
                }

                // Signup user
                if (!onetoone_user_signup(
                        $session,
                        $onetoone,
                        $course,
                        $attendee->discountcode,
                        $attendee->notificationtype,
                        $status,
                        $attendee->id
                    )) {
                    continue;
                }

                break;

            case 0:
            default:
                // Change nothing
                continue;
        }
    }

    return true;
}

/*
 * Set the grading for an individual submission, to either 0 or 100 to indicate attendance
 * @param $submissionid The id of the submission in the database
 * @param $grading Grade to set
 */
function onetoone_take_individual_attendance($submissionid, $grading) {
    global $USER, $CFG, $DB;

    $timenow = time();

    $record = $DB->get_record_sql("SELECT f.*, s.userid
                                FROM {onetoone_signups} s
                                JOIN {onetoone_sessions} fs ON s.sessionid = fs.id
                                JOIN {onetoone} f ON f.id = fs.onetoone
                                JOIN {course_modules} cm ON cm.instance = f.id
                                JOIN {modules} m ON m.id = cm.module
                                WHERE s.id = ? AND m.name='onetoone'",
                            array($submissionid));

    $grade = new stdclass();
    $grade->userid = $record->userid;
    $grade->rawgrade = $grading;
    $grade->rawgrademin = 0;
    $grade->rawgrademax = 100;
    $grade->timecreated = $timenow;
    $grade->timemodified = $timenow;
    $grade->usermodified = $USER->id;

    return onetoone_grade_item_update($record, $grade);
}

/**
 * Used by course/lib.php to display a few sessions besides the
 * onetoone activity on the course page
 *
 * @global class $USER used to get the current userid
 * @global class $CFG used to get the path to the module
 */
function onetoone_print_coursemodule_info($coursemodule) {
    global $CFG, $USER, $DB, $OUTPUT;

    $contextmodule = context_module::instance($coursemodule->id);
    if (!has_capability('mod/onetoone:view', $contextmodule)) {
        return ''; // not allowed to view this activity
    }
    $contextcourse = context_course::instance($coursemodule->course);
    // can view attendees
    $viewattendees = has_capability('mod/onetoone:viewattendees', $contextcourse);

    $table = '';
    $timenow = time();
    $onetoonepath = "$CFG->wwwroot/mod/onetoone";

    $onetooneid = $coursemodule->instance;
    $onetoone = $DB->get_record('onetoone', array('id' => $onetooneid));
    if (!$onetoone) {
        error_log("onetoone: ask to print coursemodule info for a non-existent activity ($onetooneid)");
        return '';
    }

    $htmlactivitynameonly = $OUTPUT->pix_icon('icon', $onetoone->name, 'onetoone', array('class' => 'activityicon')) . $onetoone->name;
    $strviewallsessions = get_string('viewallsessions', 'onetoone');
    $sessions_url = new moodle_url('/mod/onetoone/view.php', array('f' => $onetooneid));
    $htmlviewallsessions = html_writer::link($sessions_url, $strviewallsessions, array('class' => 'f2fsessionlinks f2fviewallsessions', 'title' => $strviewallsessions));

    if ($submissions = onetoone_get_user_submissions($onetooneid, $USER->id)) {
        // User has signedup for the instance
        $submission = array_shift($submissions);

        if ($session = onetoone_get_session($submission->sessionid)) {
            $sessiondate = '';
            $sessiontime = '';

            if ($session->datetimeknown) {
                //foreach ($session->sessiondates as $date) {
                    if (!empty($sessiondate)) {
                        $sessiondate .= html_writer::empty_tag('br');
                    }
                    $sessiondate .= userdate($session->timestart, get_string('strftimedate'));
                    if (!empty($sessiontime)) {
                        $sessiontime .= html_writer::empty_tag('br');
                    }
                    $sessiontime .= userdate($session->timestart, get_string('strftimetime')) .
                        ' - ' . userdate($session->timefinish, get_string('strftimetime'));
                //}
            }
            else {
                $sessiondate = get_string('wait-listed', 'onetoone');
                $sessiontime = get_string('wait-listed', 'onetoone');
            }

            // don't include the link to cancel a session if it has already occurred
            $cancellink = '';
            if (!onetoone_has_session_started($session, $timenow)) {
                $strcancelbooking = get_string('cancelbooking', 'onetoone');
                $cancel_url = new moodle_url('/mod/onetoone/cancelsignup.php', array('s' => $session->id));
                $cancellink = html_writer::tag('tr', html_writer::tag('td', html_writer::link($cancel_url, $strcancelbooking, array('title' => $strcancelbooking))));
            }

            $strmoreinfo = get_string('moreinfo', 'onetoone');
            $strseeattendees = get_string('seeattendees', 'onetoone');

            $location = '&nbsp;';
            $venue = '&nbsp;';
            $customfielddata = onetoone_get_customfielddata($session->id);
            if (!empty($customfielddata['location'])) {
                $location = $customfielddata['location']->data;
            }
            if (!empty($customfielddata['venue'])) {
                $venue = $customfielddata['venue']->data;
            }

            // don't include the link to view attendees if user is lacking capability
            $attendeeslink = '';
            if ($viewattendees) {
                $attendees_url = new moodle_url('/mod/onetoone/attendees.php', array('s' => $session->id));
                $attendeeslink = html_writer::tag('tr', html_writer::tag('td', html_writer::link($attendees_url, $strseeattendees, array('class' => 'f2fsessionlinks f2fviewattendees', 'title' => $strseeattendees))));
            }

            $signup_url = new moodle_url('/mod/onetoone/signup.php', array('s' => $session->id));

            $table = html_writer::start_tag('table', array('class' => 'table90 inlinetable'))
                .html_writer::start_tag('tr', array('class' => 'f2factivityname'))
                .html_writer::tag('td', $htmlactivitynameonly, array('class' => 'f2fsessionnotice', 'colspan' => '4'))
                .html_writer::end_tag('tr')
                .html_writer::start_tag('tr')
                .html_writer::tag('td', get_string('bookingstatus', 'onetoone'), array('class' => 'f2fsessionnotice', 'colspan' => '4'))
                .html_writer::tag('td', html_writer::tag('span', get_string('options', 'onetoone').':', array('class' => 'f2fsessionnotice')))
                .html_writer::end_tag('tr')
                .html_writer::start_tag('tr', array('class' => 'f2fsessioninfo'))
                .html_writer::tag('td', $location)
                .html_writer::tag('td', $venue)
                .html_writer::tag('td', $sessiondate)
                .html_writer::tag('td', $sessiontime)
                .html_writer::tag('td', html_writer::start_tag('table', array('border' => '0')) . html_writer::start_tag('tr') . html_writer::tag('td', html_writer::link($signup_url, $strmoreinfo, array('class' => 'f2fsessionlinks f2fsessioninfolink', 'title' => $strmoreinfo))))
                .html_writer::end_tag('tr')
                .$attendeeslink
                .$cancellink
                .html_writer::start_tag('tr')
                .html_writer::tag('td', $htmlviewallsessions)
                .html_writer::end_tag('tr')
                .html_writer::end_tag('table') . html_writer::end_tag('td') . html_writer::end_tag('tr')
                .html_writer::end_tag('table');
        }
    } else if ($onetoone->display > 0 && $sessions = onetoone_get_sessions($onetooneid) ) {

        $table = html_writer::start_tag('table', array('class' => 'f2fsession inlinetable'))
            .html_writer::start_tag('tr', array('class' => 'f2factivityname'))
            .html_writer::tag('td', $htmlactivitynameonly, array('class' => 'f2fsessionnotice', 'colspan' => '2'))
            .html_writer::end_tag('tr')
            .html_writer::start_tag('tr')
            .html_writer::tag('td', get_string('signupforsession', 'onetoone'), array('class' => 'f2fsessionnotice', 'colspan' => '2'))
            .html_writer::end_tag('tr');

        $i=0;
        foreach ($sessions as $session) {
            if ($session->datetimeknown && (onetoone_has_session_started($session, $timenow))) {
                continue;
            }

            if (!onetoone_session_has_capacity($session, $contextmodule)) {
                continue;
            }

            $multiday = '';
            $sessiondate = '';
            $sessiontime = '';

            if ($session->datetimeknown) {
                if (!$session->timestart) {
                    $sessiondate = get_string('unknowndate', 'onetoone');
                    $sessiontime = get_string('unknowntime', 'onetoone');
                }
                else {
                    $sessiondate = userdate($session->timestart, get_string('strftimedate'));
                    $sessiontime = userdate($session->timestart, get_string('strftimetime')).
                        ' - '.userdate($session->timefinish, get_string('strftimetime'));
                    /*if (count($session->sessiondates) > 1) {
                        $multiday = ' ('.get_string('multiday', 'onetoone').')';
                    }*/
                }
            }
            else {
                $sessiondate = get_string('wait-listed', 'onetoone');
            }

            if ($i == 0) {
                $table .= html_writer::start_tag('tr');
                $i++;
            }
            else if ($i++ % 2 == 0) {
                if ($i > $onetoone->display) {
                    break;
                }
                $table .= html_writer::end_tag('tr');
                $table .= html_writer::start_tag('tr');
            }

            $locationstring = '';
            $customfielddata = onetoone_get_customfielddata($session->id);
            if (!empty($customfielddata['location']) && trim($customfielddata['location']->data) != '') {
                $locationstring = $customfielddata['location']->data . ', ';
            }

            if ($coursemodule->uservisible) {
                $signup_url = new moodle_url('/mod/onetoone/signup.php', array('s' => $session->id));
                $table .= html_writer::tag('td', html_writer::link($signup_url, $locationstring . $sessiondate . html_writer::empty_tag('br') . $sessiontime . $multiday, array('class' => 'f2fsessiontime')));
            } else {
                $table .= html_writer::tag('td', html_writer::tag('span', $locationstring . $sessiondate . html_writer::empty_tag('br') . $sessiontime . $multiday, array('class' => 'f2fsessiontime')));
            }

        }
        if ($i++ % 2 == 0) {
            $table .= html_writer::tag('td', "&nbsp;");
        }

        $table .= html_writer::end_tag('tr')
            .html_writer::start_tag('tr')
            .html_writer::tag('td', $coursemodule->uservisible ? $htmlviewallsessions : $strviewallsessions, array('colspan' => '2'))
            .html_writer::end_tag('tr')
            .html_writer::end_tag('table');
    }
    elseif (has_capability('mod/onetoone:viewemptyactivities', $contextmodule)) {
        return html_writer::tag('span', $htmlactivitynameonly . html_writer::empty_tag('br') . $htmlviewallsessions, array('class' => 'f2fsessionnotice f2factivityname f2fonepointfive'));
    }
    else {
        // Nothing to display to this user
    }

    return $table;
}

/**
 * Returns the ICAL data for a onetoone meeting.
 *
 * @param integer $method The method, @see {{MDL_O2O_INVITE}}
 * @param object $onetoone A face-to-face object containing activity details
 * @param object $session A session object containing session details
 * @return string Filename of the attachment in the temp directory
 */
function onetoone_get_ical_attachment($method, $onetoone, $session, $user) {
    global $CFG, $DB;

    // First, generate all the VEVENT blocks
    $VEVENTS = '';
    //foreach ($session->sessiondates as $date) {
        // Date that this representation of the calendar information was created -
        // we use the time the session was created
        // http://www.kanzaki.com/docs/ical/dtstamp.html
        $DTSTAMP = onetoone_ical_generate_timestamp($session->timecreated);

        // UIDs should be globally unique
        $urlbits = parse_url($CFG->wwwroot);
        $sql = "SELECT COUNT(*)
            FROM {onetoone_signups} su
            INNER JOIN {onetoone_signups_status} sus ON su.id = sus.signupid
            WHERE su.userid = ?
                AND su.sessionid = ?
                AND sus.superceded = 1
                AND sus.statuscode = ? ";
        $params = array($user->id, $session->id, MDL_O2O_STATUS_USER_CANCELLED);


        $UID =
            $DTSTAMP .
            '-' . substr(md5($CFG->siteidentifier . $session->id ), -6) .   // Unique identifier, salted with site identifier
            '-' . $DB->count_records_sql($sql, $params) .                              // New UID if this is a re-signup
            '@' . $urlbits['host'];                                                    // Hostname for this moodle installation

        $DTSTART = onetoone_ical_generate_timestamp($session->timestart);
        $DTEND   = onetoone_ical_generate_timestamp($session->timefinish);

        // FIXME: currently we are not sending updates if the times of the
        // sesion are changed. This is not ideal!
        $SEQUENCE = ($method & MDL_O2O_CANCEL) ? 1 : 0;

        $SUMMARY     = onetoone_ical_escape($onetoone->name);
        $DESCRIPTION = onetoone_ical_escape($session->details, true);

        // Get the location data from custom fields if they exist
        $customfielddata = onetoone_get_customfielddata($session->id);
        $locationstring = '';
        if (!empty($customfielddata['room'])) {
            $locationstring .= $customfielddata['room']->data;
        }
        if (!empty($customfielddata['venue'])) {
            if (!empty($locationstring)) {
                $locationstring .= "\n";
            }
            $locationstring .= $customfielddata['venue']->data;
        }
        if (!empty($customfielddata['location'])) {
            if (!empty($locationstring)) {
                $locationstring .= "\n";
            }
            $locationstring .= $customfielddata['location']->data;
        }

        // NOTE: Newlines are meant to be encoded with the literal sequence
        // '\n'. But evolution presents a single line text field for location,
        // and shows the newlines as [0x0A] junk. So we switch it for commas
        // here. Remember commas need to be escaped too.
        $LOCATION    = str_replace('\n', '\, ', onetoone_ical_escape($locationstring));

        $ORGANISEREMAIL = get_config(NULL, 'onetoone_fromaddress');

        $ROLE = 'REQ-PARTICIPANT';
        $CANCELSTATUS = '';
        if ($method & MDL_O2O_CANCEL) {
            $ROLE = 'NON-PARTICIPANT';
            $CANCELSTATUS = "\nSTATUS:CANCELLED";
        }

        $icalmethod = ($method & MDL_O2O_INVITE) ? 'REQUEST' : 'CANCEL';

        // FIXME: if the user has input their name in another language, we need
        // to set the LANGUAGE property parameter here
        $USERNAME = fullname($user);
        $MAILTO   = $user->email;

        // The extra newline at the bottom is so multiple events start on their
        // own lines. The very last one is trimmed outside the loop
        $VEVENTS .= <<<EOF
BEGIN:VEVENT
UID:{$UID}
DTSTAMP:{$DTSTAMP}
DTSTART:{$DTSTART}
DTEND:{$DTEND}
SEQUENCE:{$SEQUENCE}
SUMMARY:{$SUMMARY}
LOCATION:{$LOCATION}
DESCRIPTION:{$DESCRIPTION}
CLASS:PRIVATE
TRANSP:OPAQUE{$CANCELSTATUS}
ORGANIZER;CN={$ORGANISEREMAIL}:MAILTO:{$ORGANISEREMAIL}
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE={$ROLE};PARTSTAT=NEEDS-ACTION;
 RSVP=FALSE;CN={$USERNAME};LANGUAGE=en:MAILTO:{$MAILTO}
END:VEVENT

EOF;
    //}

    $VEVENTS = trim($VEVENTS);

    // TODO: remove the hard-coded timezone!
    $template = <<<EOF
BEGIN:VCALENDAR
CALSCALE:GREGORIAN
PRODID:-//Moodle//NONSGML onetoone//EN
VERSION:2.0
METHOD:{$icalmethod}
BEGIN:VTIMEZONE
TZID:/softwarestudio.org/Tzfile/Pacific/Auckland
X-LIC-LOCATION:Pacific/Auckland
BEGIN:STANDARD
TZNAME:NZST
DTSTART:19700405T020000
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=1SU;BYMONTH=4
TZOFFSETFROM:+1300
TZOFFSETTO:+1200
END:STANDARD
BEGIN:DAYLIGHT
TZNAME:NZDT
DTSTART:19700928T030000
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=9
TZOFFSETFROM:+1200
TZOFFSETTO:+1300
END:DAYLIGHT
END:VTIMEZONE
{$VEVENTS}
END:VCALENDAR
EOF;

    $tempfilename = md5($template);
    $tempfilepathname = $CFG->dataroot . '/' . $tempfilename;
    file_put_contents($tempfilepathname, $template);
    return $tempfilename;
}

function onetoone_ical_generate_timestamp($timestamp) {
    return gmdate('Ymd', $timestamp) . 'T' . gmdate('His', $timestamp) . 'Z';
}

/**
 * Escapes data of the text datatype in ICAL documents.
 *
 * See RFC2445 or http://www.kanzaki.com/docs/ical/text.html or a more readable definition
 */
function onetoone_ical_escape($text, $converthtml=false) {
    if (empty($text)) {
        return '';
    }

    if ($converthtml) {
        $text = html_to_text($text);
    }

    $text = str_replace(
        array('\\',   "\n", ';',  ','),
        array('\\\\', '\n', '\;', '\,'),
        $text
    );

    // Text should be wordwrapped at 75 octets, and there should be one
    // whitespace after the newline that does the wrapping
    $text = wordwrap($text, 75, "\n ", true);

    return $text;
}

/**
 * Update grades by firing grade_updated event
 *
 * @param object $onetoone null means all onetoone activities
 * @param int $userid specific user only, 0 mean all (not used here)
 */
function onetoone_update_grades($onetoone=null, $userid=0) {
    global $DB;
    if ($onetoone != null) {
            onetoone_grade_item_update($onetoone);
    } else {
        $sql = "SELECT f.*, cm.idnumber as cmidnumber
                  FROM {onetoone} f
                  JOIN {course_modules} cm ON cm.instance = f.id
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name='onetoone'";
        if ($rs = $DB->get_recordset_sql($sql)) {
            foreach ($rs as $onetoone) {
                onetoone_grade_item_update($onetoone);
            }
            $rs->close();
        }
    }

    return true;
}

/**
 * Create grade item for given Face-to-face session
 *
 * @param int onetoone  Face-to-face activity (not the session) to grade
 * @param mixed grades    grades objects or 'reset' (means reset grades in gradebook)
 * @return int 0 if ok, error code otherwise
 */
function onetoone_grade_item_update($onetoone, $grades=NULL) {
    global $CFG, $DB;

    if (!isset($onetoone->cmidnumber)) {

        $sql = "SELECT cm.idnumber as cmidnumber
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name='onetoone' AND cm.instance = ?";
        $onetoone->cmidnumber = $DB->get_field_sql($sql, array($onetoone->id));
    }

    $params = array('itemname' => $onetoone->name,
                    'idnumber' => $onetoone->cmidnumber);

    $params['gradetype'] = GRADE_TYPE_VALUE;
    $params['grademin']  = 0;
    $params['gradepass'] = 100;
    $params['grademax']  = 100;

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    $retcode = grade_update('mod/onetoone', $onetoone->course, 'mod', 'onetoone',
                            $onetoone->id, 0, $grades, $params);
    return ($retcode === GRADE_UPDATE_OK);
}

/**
 * Delete grade item for given onetoone
 *
 * @param object $onetoone object
 * @return object onetoone
 */
function onetoone_grade_item_delete($onetoone) {
    $retcode = grade_update('mod/onetoone', $onetoone->course, 'mod', 'onetoone',
                            $onetoone->id, 0, NULL, array('deleted' => 1));
    return ($retcode === GRADE_UPDATE_OK);
}

/**
 * Return number of attendees signed up to a onetoone session
 *
 * @param integer $session_id
 * @param integer $status MDL_O2O_STATUS_* constant (optional)
 * @return integer
 */
function onetoone_get_num_attendees($session_id, $status=MDL_O2O_STATUS_BOOKED) {
    global $CFG, $DB;

    $sql = 'SELECT count(ss.id)
        FROM
            {onetoone_signups} su
        JOIN
            {onetoone_signups_status} ss
        ON
            su.id = ss.signupid
        WHERE
            sessionid = ?
        AND
            ss.superceded=0
        AND
        ss.statuscode >= ?';

    // for the session, pick signups that haven't been superceded, or cancelled
    return (int) $DB->count_records_sql($sql, array($session_id, $status));
}

/**
 * Return all of a users' submissions to a onetoone
 *
 * @param integer $onetooneid
 * @param integer $userid
 * @param boolean $includecancellations
 * @return array submissions | false No submissions
 */
function onetoone_get_user_submissions($onetooneid, $userid, $includecancellations=false) {
    global $CFG,$DB;

    $whereclause = "s.onetoone = ? AND su.userid = ? AND ss.superceded != 1";
    $whereparams = array($onetooneid, $userid);

    // If not show cancelled, only show requested and up status'
    if (!$includecancellations) {
        $whereclause .= ' AND ss.statuscode >= ? AND ss.statuscode < ?';
        $whereparams = array_merge($whereparams, array(MDL_O2O_STATUS_REQUESTED, MDL_O2O_STATUS_NO_SHOW));
    }
//echo "SELECT * FROM `mdl_onetoone_sessions_dates` where timestart >now()";exit;//
    //TODO fix mailedconfirmation, timegraded, timecancelled, etc
    $timenow = time();
    return $DB->get_records_sql("
        SELECT
            su.id,
            s.onetoone,
            s.id as sessionid,
            su.userid,
            0 as mailedconfirmation,
            su.mailedreminder,
            su.discountcode,
            ss.timecreated,
            ss.timecreated as timegraded,
            s.timemodified,
            0 as timecancelled,
            su.notificationtype,
            ss.statuscode
        FROM
            {onetoone_sessions} s
        JOIN
            {onetoone_signups} su
         ON su.sessionid = s.id
        JOIN
            {onetoone_signups_status} ss
         ON su.id = ss.signupid
        WHERE
            {$whereclause}
            AND s.timestart > $timenow

        ORDER BY
            s.timecreated
    ", $whereparams);
    //added by pinky to make sing up form available for those who had registered for session 
    //but did not attend it and session has expaired
    //AND s.id In (SELECT sessionid FROM {onetoone_sessions_dates} where timestart > unix_timestamp())
}

/**
 * Cancel users' submission to a onetoone session
 *
 * @param integer $sessionid   ID of the onetoone_sessions record
 * @param integer $userid      ID of the user record
 * @param string $cancelreason Short justification for cancelling the signup
 * @return boolean success
 */
function onetoone_user_cancel_submission($sessionid, $userid, $cancelreason='') {
    global $DB;

    $signup = $DB->get_record('onetoone_signups', array('sessionid' => $sessionid, 'userid' => $userid));
    if (!$signup) {
        return true; // not signed up, nothing to do
    }

    return onetoone_update_signup_status($signup->id, MDL_O2O_STATUS_USER_CANCELLED, $userid, $cancelreason);
}

/**
 * A list of actions in the logs that indicate view activity for participants
 */
function onetoone_get_view_actions() {
    return array('view', 'view all');
}

/**
 * A list of actions in the logs that indicate post activity for participants
 */
function onetoone_get_post_actions() {
    return array('cancel booking', 'signup');
}

/**
 * Return a small object with summary information about what a user
 * has done with a given particular instance of this module (for user
 * activity reports.)
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 */
function onetoone_user_outline($course, $user, $mod, $onetoone) {

    $result = new stdClass;

    $grade = onetoone_get_grade($user->id, $course->id, $onetoone->id);
    if ($grade->grade > 0) {
        $result = new stdClass;
        $result->info = get_string('grade') . ': ' . $grade->grade;
        $result->time = $grade->dategraded;
    }
    elseif ($submissions = onetoone_get_user_submissions($onetoone->id, $user->id)) {
        $result->info = get_string('usersignedup', 'onetoone');
        $result->time = reset($submissions)->timecreated;
    }
    else {
        $result->info = get_string('usernotsignedup', 'onetoone');
    }

    return $result;
}

/**
 * Print a detailed representation of what a user has done with a
 * given particular instance of this module (for user activity
 * reports).
 */
function onetoone_user_complete($course, $user, $mod, $onetoone) {

    $grade = onetoone_get_grade($user->id, $course->id, $onetoone->id);

    if ($submissions = onetoone_get_user_submissions($onetoone->id, $user->id, true)) {
        print get_string('grade').': '.$grade->grade . html_writer::empty_tag('br');
        if ($grade->dategraded > 0) {
            $timegraded = trim(userdate($grade->dategraded, get_string('strftimedatetime')));
            print '('.format_string($timegraded).')'. html_writer::empty_tag('br');
        }
        echo html_writer::empty_tag('br');

        foreach ($submissions as $submission) {
            $timesignedup = trim(userdate($submission->timecreated, get_string('strftimedatetime')));
            print get_string('usersignedupon', 'onetoone', format_string($timesignedup)) . html_writer::empty_tag('br');

            if ($submission->timecancelled > 0) {
                $timecancelled = userdate($submission->timecancelled, get_string('strftimedatetime'));
                print get_string('usercancelledon', 'onetoone', format_string($timecancelled)) . html_writer::empty_tag('br');
            }
        }
    }
    else {
        print get_string('usernotsignedup', 'onetoone');
    }

    return true;
}

/**
 * Add a link to the session to the courses calendar.
 *
 * @param class   $session          Record from the onetoone_sessions table
 * @param class   $eventname        Name to display for this event
 * @param string  $calendartype     Which calendar to add the event to (user, course, site)
 * @param int     $userid           Optional param for user calendars
 * @param string  $eventtype        Optional param for user calendar (booking/session)
 */
function onetoone_add_session_to_calendar($session, $onetoone, $calendartype = 'none', $userid = 0, $eventtype = 'session') {
    global $CFG, $DB;

    if (empty($session->datetimeknown)) {
        return true; //date unkown, can't add to calendar
    }

    if (empty($onetoone->showoncalendar) && empty($onetoone->usercalentry)) {
        return true; //onetoone calendar settings prevent calendar
    }

    $description = '';
    if (!empty($onetoone->description)) {
        $description .= html_writer::tag('p', clean_param($onetoone->description, PARAM_CLEANHTML));
    }
    $description .= onetoone_print_session($session, false, true, true);
    $linkurl = new moodle_url('/mod/onetoone/signup.php', array('s' => $session->id));
    $linktext = get_string('signupforthissession', 'onetoone');

    if ($calendartype == 'site' && $onetoone->showoncalendar == O2O_CAL_SITE) {
        $courseid = SITEID;
        $description .= html_writer::link($linkurl, $linktext);
    } else if ($calendartype == 'course' && $onetoone->showoncalendar == O2O_CAL_COURSE) {
        $courseid = $onetoone->course;
        $description .= html_writer::link($linkurl, $linktext);
    } else if ($calendartype == 'user' && $onetoone->usercalentry) {
        $courseid = 0;
        $urlvar = ($eventtype == 'session') ? 'attendees' : 'signup';
        $linkurl = $CFG->wwwroot . "/mod/onetoone/" . $urlvar . ".php?s=$session->id";
        $description .= get_string("calendareventdescription{$eventtype}", 'onetoone', $linkurl);
    } else {
        return true;
    }

    $shortname = $onetoone->shortname;
    if (empty($shortname)) {
        $shortname = substr($onetoone->name, 0, O2O_CALENDAR_MAX_NAME_LENGTH);
    }

    $result = true;
    
    $newevent = new stdClass();
    $newevent->name = $shortname;
    $newevent->description = $description;
    $newevent->format = FORMAT_HTML;
    $newevent->courseid = $courseid;
    $newevent->groupid = 0;
    $newevent->userid = $userid;
    $newevent->uuid = "{$session->id}";
    $newevent->instance = $session->onetoone;
    $newevent->modulename = 'onetoone';
    $newevent->eventtype = "onetoone{$eventtype}";
    $newevent->timestart = $session->timestart;
    $newevent->timeduration = $session->timefinish - $session->timestart;
    $newevent->visible = 1;
    $newevent->timemodified = time();

    if ($calendartype == 'user' && $eventtype == 'booking') {
        //Check for and Delete the 'created' calendar event to reduce multiple entries for the same event
        $DB->delete_records_select('event', 'name=? AND userid=? AND instance =? AND eventtype=?',array($shortname,$userid,$session->onetoone, 'onetoonesession'));
    }

    $result = $result && $DB->insert_record('event', $newevent);
    return $result;
}

/**
 * Remove all entries in the course calendar which relate to this session.
 *
 * @param class $session    Record from the onetoone_sessions table
 * @param integer $userid   ID of the user
 */
function onetoone_remove_session_from_calendar($session, $courseid = 0, $userid = 0) {
    global $DB;

    $params = array($session->onetoone, $userid, $courseid, $session->id);

    return $DB->delete_records_select('event', "modulename = 'onetoone' AND
                                                instance = ? AND
                                                userid = ? AND
                                                courseid = ? AND
                                                uuid = ?", $params);
}

/**
 * Update the date/time of events in the Moodle Calendar when a
 * session's dates are changed.
 *
 * @param object $session       Record from the onetoone_sessions table
 * @param string $eventtype     Type of event to update
 */
function onetoone_update_user_calendar_events($session, $eventtype) {
    global $DB;

    $onetoone = $DB->get_record('onetoone', array('id' => $session->onetoone));

    if (empty($onetoone->usercalentry) || $onetoone->usercalentry == 0) {
        return true;
    }

    $users = onetoone_delete_user_calendar_events($session, $eventtype);

    // Add this session to these users' calendar
    foreach ($users as $user) {
        onetoone_add_session_to_calendar($session, $onetoone, 'user', $user->userid, $eventtype);
    }
    return true;
}

/**
 * Delete all user level calendar events for a face to face session
 *
 * @param class     $session    Record from the onetoone_sessions table
 * @param string    $eventtype  Type of the event (booking or session)
 *
 * @return array    $users      Array of users who had the event deleted
 */
function onetoone_delete_user_calendar_events($session, $eventtype) {
    global $CFG, $DB;

    $whereclause = "modulename = 'onetoone' AND
                    eventtype = 'onetoone$eventtype' AND
                    instance = ?";

    $whereparams = array($session->onetoone);

    if ('session' == $eventtype) {
        $likestr = "%attendees.php?s={$session->id}%";
        $like = $DB->sql_like('description', '?');
        $whereclause .= " AND $like";

        $whereparams[] = $likestr;
    }

    // Users calendar
    $users = $DB->get_records_sql("SELECT DISTINCT userid
        FROM {event}
        WHERE $whereclause", $whereparams);

    if ($users && count($users) > 0) {
        // Delete the existing events
        $DB->delete_records_select('event', $whereclause, $whereparams);
    }

    return $users;
}

/**
 * Confirm that a user can be added to a session.
 *
 * @param class  $session Record from the onetoone_sessions table
 * @param object $context (optional) A context object (record from context table)
 * @return bool True if user can be added to session
 **/
function onetoone_session_has_capacity($session, $context = false) {

    if (empty($session)) {
        return false;
    }

    $signupcount = onetoone_get_num_attendees($session->id);
    if ($signupcount >= $session->capacity) {
        // if session is full, check if overbooking is allowed for this user
        if (!$context || !has_capability('mod/onetoone:overbook', $context)) {
            return false;
        }
    }

    return true;
}

/**
 * Print the details of a session
 *
 * @param object $session         Record from onetoone_sessions
 * @param boolean $showcapacity   Show the capacity (true) or only the seats available (false)
 * @param boolean $calendaroutput Whether the output should be formatted for a calendar event
 * @param boolean $return         Whether to return (true) the html or print it directly (true)
 * @param boolean $hidesignup     Hide any messages relating to signing up
 */
function onetoone_print_session($session, $showcapacity, $calendaroutput=false, $return=false, $hidesignup=false) {
    global $CFG, $DB;

    $table = new html_table();
    $table->summary = get_string('sessionsdetailstablesummary', 'onetoone');
    $table->attributes['class'] = 'generaltable f2fsession';
    $table->align = array('right', 'left');
    if ($calendaroutput) {
        $table->tablealign = 'left';
    }

    $customfields = onetoone_get_session_customfields();
    $customdata = $DB->get_records('onetoone_session_data', array('sessionid' => $session->id), '', 'fieldid, data');
    foreach ($customfields as $field) {
        $data = '';
        if (!empty($customdata[$field->id])) {
            if (O2O_CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                $values = explode(O2O_CUSTOMFIELD_DELIMITER, format_string($customdata[$field->id]->data));
                $data = implode(html_writer::empty_tag('br'), $values);
            }
            else {
                $data = format_string($customdata[$field->id]->data);
            }
        }
        $table->data[] = array(str_replace(' ', '&nbsp;', format_string($field->name)), $data);
    }

    $strdatetime = str_replace(' ', '&nbsp;', get_string('sessiondatetime', 'onetoone'));
    if ($session->datetimeknown) {
        $html = '';
        //foreach ($session->sessiondates as $date) {
            if (!empty($html)) {
                $html .= html_writer::empty_tag('br');
            }
            $timestart = userdate($session->timestart, get_string('strftimedatetime'));
            $timefinish = userdate($session->timefinish, get_string('strftimedatetime'));
            $html .= "$timestart &ndash; $timefinish";
        //}
        $table->data[] = array($strdatetime, $html);
    }
    else {
        $table->data[] = array($strdatetime, html_writer::tag('i', get_string('wait-listed', 'onetoone')));
    }

    $signupcount = onetoone_get_num_attendees($session->id);
    $placesleft = $session->capacity - $signupcount;

    if ($showcapacity) {
        if ($session->allowoverbook) {
            $table->data[] = array(get_string('capacity', 'onetoone'), $session->capacity . ' ('.strtolower(get_string('allowoverbook', 'onetoone')).')');
        } else {
            $table->data[] = array(get_string('capacity', 'onetoone'), $session->capacity);
        }
    }
    elseif (!$calendaroutput) {
        $table->data[] = array(get_string('seatsavailable', 'onetoone'), max(0, $placesleft));
    }

    // Display requires approval notification
    $onetoone = $DB->get_record('onetoone', array('id' => $session->onetoone));

    if ($onetoone->approvalreqd) {
        $table->data[] = array('', get_string('sessionrequiresmanagerapproval', 'onetoone'));
    }

    // Display waitlist notification
    if (!$hidesignup && $session->allowoverbook && $placesleft < 1) {
        $table->data[] = array('', get_string('userwillbewaitlisted', 'onetoone'));
    }

    if (!empty($session->duration)) {
        $table->data[] = array(get_string('duration', 'onetoone'), onetoone_format_duration($session->duration));
    }
    if (!empty($session->normalcost)) {
        $table->data[] = array(get_string('normalcost', 'onetoone'), onetoone_format_cost($session->normalcost));
    }
    if (!empty($session->discountcost)) {
        $table->data[] = array(get_string('discountcost', 'onetoone'), onetoone_format_cost($session->discountcost));
    }
    if (!empty($session->details)) {
        $details = clean_text($session->details, FORMAT_HTML);
        $table->data[] = array(get_string('details', 'onetoone'), $details);
    }

    // Display trainers
    $trainerroles = onetoone_get_trainer_roles();

    if ($trainerroles) {
        // Get trainers
        $trainers = onetoone_get_trainers($session->id);

        foreach ($trainerroles as $role => $rolename) {
            $rolename = $rolename->name;

            if (empty($trainers[$role])) {
                continue;
            }

            $trainer_names = array();
            foreach ($trainers[$role] as $trainer) {
                $trainer_url = new moodle_url('/user/view.php', array('id' => $trainer->id));
                $trainer_names[] = html_writer::link($trainer_url, fullname($trainer));
            }

            $table->data[] = array($rolename, implode(', ', $trainer_names));
        }
    }

    return html_writer::table($table, $return);
}

/**
 * Update the value of a customfield for the given session/notice.
 *
 * @param integer $fieldid    ID of a record from the onetoone_session_field table
 * @param string  $data       Value for that custom field
 * @param integer $otherid    ID of a record from the onetoone_(sessions|notice) table
 * @param string  $table      'session' or 'notice' (part of the table name)
 * @returns true if it succeeded, false otherwise
 */
function onetoone_save_customfield_value($fieldid, $data, $otherid, $table) {
    global $DB;

    $dbdata = null;
    if (is_array($data)) {
        $dbdata = trim(implode(O2O_CUSTOMFIELD_DELIMITER, $data), ';');
    }
    else {
        $dbdata = trim($data);
    }

    $newrecord = new stdClass();
    $newrecord->data = $dbdata;

    $fieldname = "{$table}id";
    if ($record = $DB->get_record("onetoone_{$table}_data", array('fieldid' => $fieldid, $fieldname => $otherid))) {
        if (empty($dbdata)) {
            // Clear out the existing value
            return $DB->delete_records("onetoone_{$table}_data", array('id' => $record->id));
        }

        $newrecord->id = $record->id;
        return $DB->update_record("onetoone_{$table}_data", $newrecord);
    }
    else {
        if (empty($dbdata)) {
            return true; // no need to store empty values
        }

        $newrecord->fieldid = $fieldid;
        $newrecord->$fieldname = $otherid;
        return $DB->insert_record("onetoone_{$table}_data", $newrecord);
    }
}

/**
 * Return the value of a customfield for the given session/notice.
 *
 * @param object  $field    A record from the onetoone_session_field table
 * @param integer $otherid  ID of a record from the onetoone_(sessions|notice) table
 * @param string  $table    'session' or 'notice' (part of the table name)
 * @returns string The data contained in this custom field (empty string if it doesn't exist)
 */
function onetoone_get_customfield_value($field, $otherid, $table) {
    global $DB;

    if ($record = $DB->get_record("onetoone_{$table}_data", array('fieldid' => $field->id, "{$table}id" => $otherid))) {
        if (!empty($record->data)) {
            if (O2O_CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                return explode(O2O_CUSTOMFIELD_DELIMITER, $record->data);
            }
            return $record->data;
        }
    }
    return '';
}

/**
 * Return the values stored for all custom fields in the given session.
 *
 * @param integer $sessionid  ID of onetoone_sessions record
 * @returns array Indexed by field shortnames
 */
function onetoone_get_customfielddata($sessionid) {
    global $CFG, $DB;

    $sql = "SELECT f.shortname, d.data
              FROM {onetoone_session_field} f
              JOIN {onetoone_session_data} d ON f.id = d.fieldid
              WHERE d.sessionid = ?";

    $records = $DB->get_records_sql($sql, array($sessionid));

    return $records;
}

/**
 * Return a cached copy of all records in onetoone_session_field
 */
function onetoone_get_session_customfields() {
    global $DB;

    static $customfields = null;
    if (null == $customfields) {
        if (!$customfields = $DB->get_records('onetoone_session_field')) {
            $customfields = array();
        }
    }
    return $customfields;
}

/**
 * Display the list of custom fields in the site-wide settings page
 */
function onetoone_list_of_customfields() {
    global $CFG, $USER, $DB, $OUTPUT;

    if ($fields = $DB->get_records('onetoone_session_field', array(), 'name', 'id, name')) {
        $table = new html_table();
        $table->attributes['class'] = 'halfwidthtable';
        foreach ($fields as $field) {
            $fieldname = format_string($field->name);
            $edit_url = new moodle_url('/mod/onetoone/customfield.php', array('id' => $field->id));
            $editlink = $OUTPUT->action_icon($edit_url, new pix_icon('t/edit', get_string('edit')));
            $delete_url = new moodle_url('/mod/onetoone/customfield.php', array('id' => $field->id, 'd' => '1', 'sesskey' => $USER->sesskey));
            $deletelink = $OUTPUT->action_icon($delete_url, new pix_icon('t/delete', get_string('delete')));
            $table->data[] = array($fieldname, $editlink, $deletelink);
        }
        return html_writer::table($table, true);
    }

    return get_string('nocustomfields', 'onetoone');
}

function onetoone_update_trainers($sessionid, $form) {
    global $DB;

    // If we recieved bad data
    if (!is_array($form)) {
        return false;
    }

    // Load current trainers
    $old_trainers = onetoone_get_trainers($sessionid);

    $transaction = $DB->start_delegated_transaction();

    // Loop through form data and add any new trainers
    foreach ($form as $roleid => $trainers) {

        // Loop through trainers in this role
        foreach ($trainers as $trainer) {

            if (!$trainer) {
                continue;
            }

            // If the trainer doesn't exist already, create it
            if (!isset($old_trainers[$roleid][$trainer])) {

                $newtrainer = new stdClass();
                $newtrainer->userid = $trainer;
                $newtrainer->roleid = $roleid;
                $newtrainer->sessionid = $sessionid;

                if (!$DB->insert_record('onetoone_session_roles', $newtrainer)) {
                    print_error('error:couldnotaddtrainer', 'onetoone');
                    $transaction->force_transaction_rollback();
                    return false;
                }
            } else {
                unset($old_trainers[$roleid][$trainer]);
            }
        }
    }

    // Loop through what is left of old trainers, and remove
    // (as they have been deselected)
    if ($old_trainers) {
        foreach ($old_trainers as $roleid => $trainers) {
            // If no trainers left
            if (empty($trainers)) {
                continue;
            }

            // Delete any remaining trainers
            foreach ($trainers as $trainer) {
                if (!$DB->delete_records('onetoone_session_roles', array('sessionid' => $sessionid, 'roleid' => $roleid, 'userid' => $trainer->id))) {
                    print_error('error:couldnotdeletetrainer', 'onetoone');
                    $transaction->force_transaction_rollback();
                    return false;
                }
            }
        }
    }

    $transaction->allow_commit();

    return true;
}


/**
 * Return array of trainer roles configured for face-to-face
 *
 * @return  array
 */
function onetoone_get_trainer_roles() {
    global $CFG, $DB;

    // Check that roles have been selected
    if (empty($CFG->onetoone_session_roles)) {
        return false;
    }

    // Parse roles
    $cleanroles = clean_param($CFG->onetoone_session_roles, PARAM_SEQUENCE);
    $roles = explode(',', $cleanroles);
    list($rolesql, $params) = $DB->get_in_or_equal($roles);

    // Load role names
    $rolenames = $DB->get_records_sql("
        SELECT
            r.id,
            r.name
        FROM
            {role} r
        WHERE
            r.id {$rolesql}
        AND r.id <> 0
    ", $params);

    // Return roles and names
    if (!$rolenames) {
        return array();
    }

    return $rolenames;
}


/**
 * Get all trainers associated with a session, optionally
 * restricted to a certain roleid
 *
 * If a roleid is not specified, will return a multi-dimensional
 * array keyed by roleids, with an array of the chosen roles
 * for each role
 *
 * @param   integer     $sessionid
 * @param   integer     $roleid (optional)
 * @return  array
 */
function onetoone_get_trainers($sessionid, $roleid = null) {
    global $CFG, $DB;

    $sql = "
        SELECT
            u.id,
            u.firstname,
            u.lastname,
            r.roleid
        FROM
            {onetoone_session_roles} r
        LEFT JOIN
            {user} u
         ON u.id = r.userid
        WHERE
            r.sessionid = ?
        ";
    $params = array($sessionid);

    if ($roleid) {
        $sql .= "AND r.roleid = ?";
        $params[] = $roleid;
    }

    $rs = $DB->get_recordset_sql($sql , $params);
    $return = array();
    foreach ($rs as $record) {
        // Create new array for this role
        if (!isset($return[$record->roleid])) {
            $return[$record->roleid] = array();
        }
        $return[$record->roleid][$record->id] = $record;
    }
    $rs->close();

    // If we are only after one roleid
    if ($roleid) {
        if (empty($return[$roleid])) {
            return false;
        }
        return $return[$roleid];
    }

    // If we are after all roles
    if (empty($return)) {
        return false;
    }

    return $return;
}

/**
 * Determines whether an activity requires the user to have a manager (either for
 * manager approval or to send notices to the manager)
 *
 * @param  object $onetoone A database fieldset object for the onetoone activity
 * @return boolean whether a person needs a manager to sign up for that activity
 */
function onetoone_manager_needed($onetoone) {
    return $onetoone->approvalreqd
        || $onetoone->confirmationinstrmngr
        || $onetoone->reminderinstrmngr
        || $onetoone->cancellationinstrmngr;
}

/**
 * Display the list of site notices in the site-wide settings page
 */
function onetoone_list_of_sitenotices() {
    global $CFG, $USER, $DB, $OUTPUT;

    if ($notices = $DB->get_records('onetoone_notice', array(), 'name', 'id, name')) {
        $table = new html_table();
        $table->width = '50%';
        $table->tablealign = 'left';
        $table->data = array();
        $table->size = array('100%');
        foreach ($notices as $notice) {
            $noticename = format_string($notice->name);
            $edit_url = new moodle_url('/mod/onetoone/sitenotice.php', array('id' => $notice->id));
            $editlink = $OUTPUT->action_icon($edit_url, new pix_icon('t/edit', get_string('edit')));
            $delete_url = new moodle_url('/mod/onetoone/sitenotice.php', array('id' => $notice->id, 'd' => '1', 'sesskey' => $USER->sesskey));
            $deletelink = $OUTPUT->action_icon($delete_url, new pix_icon('t/delete', get_string('delete')));
            $table->data[] = array($noticename, $editlink, $deletelink);
        }
        return html_writer::table($table, true);
    }

    return get_string('nositenotices', 'onetoone');
}

/**
 * Add formslib fields for all custom fields defined site-wide.
 * (used by the session add/edit page and the site notices)
 */
function onetoone_add_customfields_to_form(&$mform, $customfields, $alloptional=false)
{
    foreach ($customfields as $field) {
        $fieldname = "custom_$field->shortname";

        $options = array();
        if (!$field->required) {
            $options[''] = get_string('none');
        }
        foreach (explode(O2O_CUSTOMFIELD_DELIMITER, $field->possiblevalues) as $value) {
            $v = trim($value);
            if (!empty($v)) {
                $options[$v] = $v;
            }
        }

        switch ($field->type) {
        case O2O_CUSTOMFIELD_TYPE_TEXT:
            $mform->addElement('text', $fieldname, $field->name);
            break;
        case O2O_CUSTOMFIELD_TYPE_SELECT:
            $mform->addElement('select', $fieldname, $field->name, $options);
            break;
        case O2O_CUSTOMFIELD_TYPE_MULTISELECT:
            $select = &$mform->addElement('select', $fieldname, $field->name, $options);
            $select->setMultiple(true);
            break;
        default:
            error_log("onetoone: invalid field type for custom field ID $field->id");
            continue;
        }

        $mform->setType($fieldname, PARAM_TEXT);
        $mform->setDefault($fieldname, $field->defaultvalue);
        if ($field->required and !$alloptional) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }
    }
}


/**
 * Get session cancellations
 *
 * @access  public
 * @param   integer $sessionid
 * @return  array
 */
function onetoone_get_cancellations($sessionid) {
    global $CFG, $DB;

    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
    $instatus = array(MDL_O2O_STATUS_BOOKED, MDL_O2O_STATUS_WAITLISTED, MDL_O2O_STATUS_REQUESTED);
    list($insql, $inparams) = $DB->get_in_or_equal($instatus);
    // Nasty SQL follows:
    // Load currently cancelled users,
    // include most recent booked/waitlisted time also
    $sql = "
            SELECT
                u.id,
                su.id AS signupid,
                u.firstname,
                u.lastname,
                u.firstnamephonetic,
                u.lastnamephonetic,
                u.middlename,
                u.alternatename,
                MAX(ss.timecreated) AS timesignedup,
                c.timecreated AS timecancelled,
                " . $DB->sql_compare_text('c.note') . " AS cancelreason
            FROM
                {onetoone_signups} su
            JOIN
                {user} u
             ON u.id = su.userid
            JOIN
                {onetoone_signups_status} c
             ON su.id = c.signupid
            AND c.statuscode = ?
            AND c.superceded = 0
            LEFT JOIN
                {onetoone_signups_status} ss
             ON su.id = ss.signupid
             AND ss.statuscode $insql
            AND ss.superceded = 1
            WHERE
                su.sessionid = ?
            GROUP BY
                su.id,
                u.id,
                u.firstname,
                u.lastname,
                c.timecreated,
                " . $DB->sql_compare_text('c.note') . "
            ORDER BY
                {$fullname},
                c.timecreated
    ";
    $params = array_merge(array(MDL_O2O_STATUS_USER_CANCELLED), $inparams);
    $params[] = $sessionid;
    return $DB->get_records_sql($sql, $params);
}


/**
 * Get session unapproved requests
 *
 * @access  public
 * @param   integer $sessionid
 * @return  array
 */
function onetoone_get_requests($sessionid) {
    global $CFG, $DB;

    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');

    $params = array($sessionid, MDL_O2O_STATUS_REQUESTED);

    $sql = "SELECT u.id, su.id AS signupid, u.firstname, u.lastname,
                   ss.timecreated AS timerequested
              FROM {onetoone_signups} su
              JOIN {onetoone_signups_status} ss ON su.id=ss.signupid
              JOIN {user} u ON u.id = su.userid
             WHERE su.sessionid = ? AND ss.superceded != 1 AND ss.statuscode = ?
          ORDER BY $fullname, ss.timecreated";

    return $DB->get_records_sql($sql, $params);
}


/**
 * Get session declined requests
 *
 * @access  public
 * @param   integer $sessionid
 * @return  array
 */
function onetoone_get_declines($sessionid) {
    global $CFG, $DB;

    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');

    $params = array($sessionid, MDL_O2O_STATUS_DECLINED);

    $sql = "SELECT u.id, su.id AS signupid, u.firstname, u.lastname,
                   ss.timecreated AS timerequested
              FROM {onetoone_signups} su
              JOIN {onetoone_signups_status} ss ON su.id=ss.signupid
              JOIN {user} u ON u.id = su.userid
             WHERE su.sessionid = ? AND ss.superceded != 1 AND ss.statuscode = ?
          ORDER BY $fullname, ss.timecreated";
    return $DB->get_records_sql($sql, $params);
}


/**
 * Returns all other caps used in module
 * @return array
 */
function onetoone_get_extra_capabilities() {
    return array('moodle/site:viewfullnames');
}


/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function onetoone_supports($feature) {
    switch($feature) {
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;

        default: return null;
    }
}

/**
* onetoone assignment candidates
*/
class onetoone_candidate_selector extends user_selector_base {
    protected $sessionid;

    public function __construct($name, $options) {
        $this->sessionid = $options['sessionid'];
        parent::__construct($name, $options);
    }

    /**
     * Candidate users
     * @param <type> $search
     * @return array
     */
    public function find_users($search) {
        global $DB;
        /// All non-signed up system users
        list($wherecondition, $params) = $this->search_sql($search, '{user}');

        $fields      = 'SELECT id, firstname, lastname, email, firstnamephonetic, lastnamephonetic, middlename, alternatename';
        $countfields = 'SELECT COUNT(1)';
        $sql = "
                  FROM {user}
                 WHERE $wherecondition
                   AND id NOT IN
                       (
                       SELECT u.id
                         FROM {onetoone_signups} s
                         JOIN {onetoone_signups_status} ss ON s.id = ss.signupid
                         JOIN {user} u ON u.id=s.userid
                        WHERE s.sessionid = :sessid
                          AND ss.statuscode >= :statusbooked
                          AND ss.superceded = 0
                       )
               ";
        $order = " ORDER BY lastname ASC, firstname ASC";
        $params = array_merge($params, array('sessid' => $this->sessionid, 'statusbooked' => MDL_O2O_STATUS_BOOKED));

        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > 100) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $order, $params);

        if (empty($availableusers)) {
            return array();
        }

        $groupname = get_string('potentialusers', 'role', count($availableusers));

        return array($groupname => $availableusers);
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['sessionid'] = $this->sessionid;
        $options['file'] = 'mod/onetoone/lib.php';
        return $options;
    }
}

/**
 * onetoone assignment candidates
 */
class onetoone_existing_selector extends user_selector_base {
    protected $sessionid;

    public function __construct($name, $options) {
        $this->sessionid = $options['sessionid'];
        parent::__construct($name, $options);
    }

    /**
     * Candidate users
     * @param <type> $search
     * @return array
     */
    public function find_users($search) {
        global $DB;
        //by default wherecondition retrieves all users except the deleted, not confirmed and guest
        list($wherecondition, $whereparams) = $this->search_sql($search, 'u');

        $fields = 'SELECT
                        u.id,
                        su.id AS submissionid,
                        u.firstname,
                        u.lastname,
                        u.email,
                        u.firstnamephonetic,
                u.lastnamephonetic,
                u.middlename,
                u.alternatename,
                        s.discountcost,
                        su.discountcode,
                        su.notificationtype,
                        f.id AS onetooneid,
                        f.course,
                        ss.grade,
                        ss.statuscode,
                        sign.timecreated';
        $countfields = 'SELECT COUNT(1)';
        $sql = "
            FROM
                {onetoone} f
            JOIN
                {onetoone_sessions} s
             ON s.onetoone = f.id
            JOIN
                {onetoone_signups} su
             ON s.id = su.sessionid
            JOIN
                {onetoone_signups_status} ss
             ON su.id = ss.signupid
            LEFT JOIN
                (
                SELECT
                    ss.signupid,
                    MAX(ss.timecreated) AS timecreated
                FROM
                    {onetoone_signups_status} ss
                INNER JOIN
                    {onetoone_signups} s
                 ON s.id = ss.signupid
                AND s.sessionid = :sessid1
                WHERE
                    ss.statuscode IN (:statusbooked, :statuswaitlisted)
                GROUP BY
                    ss.signupid
                ) sign
             ON su.id = sign.signupid
            JOIN
                {user} u
             ON u.id = su.userid
            WHERE
                $wherecondition
            AND s.id = :sessid2
            AND ss.superceded != 1
            AND ss.statuscode >= :statusapproved
        ";
        $order = " ORDER BY sign.timecreated ASC, ss.timecreated ASC";
        $params = array ('sessid1' => $this->sessionid, 'statusbooked' => MDL_O2O_STATUS_BOOKED, 'statuswaitlisted' => MDL_O2O_STATUS_WAITLISTED);
        $params = array_merge($params, $whereparams);
        $params['sessid2'] = $this->sessionid;
        $params['statusapproved'] = MDL_O2O_STATUS_APPROVED;
        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > 100) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $order, $params);

        if (empty($availableusers)) {
            return array();
        }

        $groupname = get_string('existingusers', 'role', count($availableusers));
        return array($groupname => $availableusers);
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['sessionid'] = $this->sessionid;
        $options['file'] = 'mod/onetoone/lib.php';
        return $options;
    }
}


/**
 * Event that is triggered when a user is deleted.
 *
 * Cancels a user from any future sessions when they are deleted
 * this make sure deleted users aren't using space is sessions when
 * there is limited capacity.
 *
 * @param object $user
 *
 */
function onetoone_eventhandler_user_deleted($user) {
    global $DB;

    if ($signups = $DB->get_records('onetoone_signups', array('userid' => $user->id))) {
        foreach ($signups as $signup) {
            $session = onetoone_get_session($signup->sessionid);
            // using $null, null fails because of passing by reference
            onetoone_user_cancel($session, $user->id, false, $null, get_string('userdeletedcancel', 'onetoone'));
        }
    }
    return true;
}
