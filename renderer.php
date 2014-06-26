<?php


defined('MOODLE_INTERNAL') || die();

class mod_onetoone_renderer extends plugin_renderer_base {
    /**
     * Builds session list table given an array of sessions
     */
    public function print_session_list_table($customfields, $sessions, $viewattendees, $editsessions,$cmid) {
    	global $DB;
        $output = '';
        
        $context = context_module::instance($cmid);

        $tableheader = array();
        foreach ($customfields as $field) {
            if (!empty($field->showinsummary)) {
                $tableheader[] = format_string($field->name);
            }
        }
        $tableheader[] = get_string('date', 'onetoone');
        $tableheader[] = get_string('time', 'onetoone');
        if ($viewattendees) {
            $tableheader[] = get_string('capacity', 'onetoone');
        }
        else {
            $tableheader[] = get_string('seatsavailable', 'onetoone');
        }
        $tableheader[] = get_string('status', 'onetoone');
        $tableheader[] = get_string('options', 'onetoone');
        $tableheader[] = get_string('learningroom', 'onetoone');;

        $timenow = time();

        $table = new html_table();
        $table->summary = get_string('previoussessionslist', 'onetoone');
        $table->head = $tableheader;
        $table->data = array();

        foreach ($sessions as $session) {

            $isbookedsession = false;
            $bookedsession = $session->bookedsession;
            //print_r($bookedsession);
            $sessionstarted = false;
            $sessionfull = false;

            $sessionrow = array();

            // Custom fields
            $customdata = $session->customfielddata;
            foreach ($customfields as $field) {
                if (empty($field->showinsummary)) {
                    continue;
                }

                if (empty($customdata[$field->id])) {
                    $sessionrow[] = '&nbsp;';
                }
                else {
                    if (CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                        $sessionrow[] = str_replace(CUSTOMFIELD_DELIMITER, html_writer::empty_tag('br'), format_string($customdata[$field->id]->data));
                    } else {
                        $sessionrow[] = format_string($customdata[$field->id]->data);
                    }

                }
            }

            // Dates/times
            $allsessiondates = '';
            $allsessiontimes = '';
            if ($session->datetimeknown) {
                //foreach ($session->sessiondates as $date) {
                    if (!empty($allsessiondates)) {
                        $allsessiondates .= html_writer::empty_tag('br');
                    }
                    $allsessiondates .= userdate($session->timestart, get_string('strftimedate'));
                    if (!empty($allsessiontimes)) {
                        $allsessiontimes .= html_writer::empty_tag('br');
                    }
                    $allsessiontimes .= userdate($session->timestart, get_string('strftimetime')).
                        ' - '.userdate($session->timefinish, get_string('strftimetime'));
                //}
            }
            else {
                $allsessiondates = get_string('wait-listed', 'onetoone');
                $allsessiontimes = get_string('wait-listed', 'onetoone');
                $sessionwaitlisted = true;
            }
            $sessionrow[] = $allsessiondates;
            $sessionrow[] = $allsessiontimes;

            // Capacity
            $signupcount = onetoone_get_num_attendees($session->id, MDL_O2O_STATUS_APPROVED);
            $stats = $session->capacity - $signupcount;
            if ($viewattendees) {
                $stats = $signupcount.' / '.$session->capacity;
            }
            else {
                $stats = max(0, $stats);
            }
            $sessionrow[] = $stats;

            // Status
            $status  = get_string('bookingopen', 'onetoone');
            $session_started= onetoone_has_session_started($session, $timenow);
            if ($session->datetimeknown && $session_started && onetoone_is_session_in_progress($session, $timenow)) {
                $status = get_string('sessioninprogress', 'onetoone');
                $sessionstarted = true;
            }
            elseif ($session->datetimeknown && $session_started) {
                $status = get_string('sessionover', 'onetoone');
                $sessionstarted = true;
            }
            elseif ($bookedsession && $session->id == $bookedsession->sessionid) {
                $signupstatus = onetoone_get_status($bookedsession->statuscode);

                $status = get_string('status_'.$signupstatus, 'onetoone');
                $isbookedsession = true;
            }
            elseif ($signupcount >= $session->capacity) {
                $status = get_string('bookingfull', 'onetoone');
                $sessionfull = true;
            }

            $sessionrow[] = $status;

            // Options
            $options = '';
            if ($editsessions) {
                $options .= $this->output->action_icon(new moodle_url('sessions.php', array('s' => $session->id)), new pix_icon('t/edit', get_string('edit', 'onetoone')), null, array('title' => get_string('editsession', 'onetoone'))) . ' ';
                $options .= $this->output->action_icon(new moodle_url('sessions.php', array('s' => $session->id, 'c' => 1)), new pix_icon('t/copy', get_string('copy', 'onetoone')), null, array('title' => get_string('copysession', 'onetoone'))) . ' ';
                $options .= $this->output->action_icon(new moodle_url('sessions.php', array('s' => $session->id, 'd' => 1)), new pix_icon('t/delete', get_string('delete', 'onetoone')), null, array('title' => get_string('deletesession', 'onetoone'))) . ' ';
                $options .= html_writer::empty_tag('br');
            }
            if ($viewattendees) {
                $options .= html_writer::link('attendees.php?s='.$session->id.'&backtoallsessions='.$session->onetoone, get_string('attendees', 'onetoone'), array('title' => get_string('seeattendees', 'onetoone'))) . html_writer::empty_tag('br');
            }
            if ($isbookedsession) {
                $options .= html_writer::link('signup.php?s='.$session->id.'&backtoallsessions='.$session->onetoone, get_string('moreinfo', 'onetoone'), array('title' => get_string('moreinfo', 'onetoone'))) . html_writer::empty_tag('br');

                $options .= html_writer::link('cancelsignup.php?s='.$session->id.'&backtoallsessions='.$session->onetoone, get_string('cancelbooking', 'onetoone'), array('title' => get_string('cancelbooking', 'onetoone')));
            }
            if (empty($options)) {
                $options = get_string('none', 'onetoone');
            }
            $sessionrow[] = $options;
            
            if ($isbookedsession || has_capability('mod/onetoone:editsessions', $context)) {
				$fullurl = 'whiteboard.php?n='.$session->onetoone.'&s='.$session->id.'&backtoallsessions='.$session->onetoone;
				$wh = "width='+window.screen.width +',height='+window.screen.height+', toolbar=no,location=no,menubar=no,copyhistory=no,status=no,directories=no,scrollbars=yes,resizable=yes";
				$extra = "onclick=\"window.open('$fullurl', '', '$wh'); return false;\"";
            	$sessionrow[] = "<a href=\"$fullurl\" $extra>".get_string('join', 'onetoone')."</a>";
            }elseif (!$sessionstarted and !$bookedsession) {
            	if($signupcount >= $session->capacity) {
            		$sessionrow[]="";
            	}else{
                	$sessionrow[]= html_writer::link('signup.php?s='.$session->id.'&backtoallsessions='.$session->onetoone, get_string('signup', 'onetoone'));
                }
            }
            $row = new html_table_row($sessionrow);

            // Set the CSS class for the row
            if ($sessionstarted) {
                $row->attributes = array('class' => 'dimmed_text');
            }
            elseif ($isbookedsession) {
                $row->attributes = array('class' => 'highlight');
            }
            elseif ($sessionfull && !(has_capability('mod/onetoone:editsessions', $context))) {
                $row->attributes = array('class' => 'dimmed_text');
            }

            // Add row to table
            $table->data[] = $row;
        }

        $output .= html_writer::table($table);

        return $output;
    }
}
?>
