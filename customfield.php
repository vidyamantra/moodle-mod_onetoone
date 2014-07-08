<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * @author(current)  Pinky <http://www.vidyamantra.com>
 * @author(previous) Francois Marier <francois@catalyst.net.nz>
 * @author(previous) Aaron Barnes <aaronb@catalyst.net.nz>
 * @package mod
 * @subpackage onetoone
 */

global $DB;
require_once('../../config.php');
require_once('customfield_form.php');

$id      = required_param('id', PARAM_INT); // ID in onetoone_session_field.
$d       = optional_param('d', false, PARAM_BOOL); // Set to true to delete the given field.
$confirm = optional_param('confirm', false, PARAM_BOOL); // Delete confirmation.

$field = null;
if ($id > 0) {
    if (!$field = $DB->get_record('onetoone_session_field', array('id' => $id))) {
        print_error('error:fieldidincorrect', 'onetoone', '', $id);
    }
}

$PAGE->set_url('/mod/onetoone/customfield.php', array('id' => $id, 'd' => $d, 'confirm' => $confirm));

admin_externalpage_setup('managemodules'); // This is hacky, tehre should be a special hidden page for it.

$contextsystem = context_system::instance();

require_capability('moodle/site:config', $contextsystem);

$returnurl = "$CFG->wwwroot/admin/settings.php?section=modsettingonetoone";

// Header.

$title = get_string('addnewfield', 'onetoone');
if ($field != null) {
    $title = $field->name;
}

$PAGE->set_title($title);

// Handle deletions.
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
    } else {
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

if ($fromform = $mform->get_data()) { // Form submitted.
    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'onetoone', $returnurl);
    }

    // Post-process the input.
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

    $valueslist = explode("\n", trim($fromform->possiblevalues));
    $posvals = array();
    foreach ($valueslist as $val) {
        $trimmedval = trim($val);
        if (strlen($trimmedval) != 0) {
            $posvals[] = $trimmedval;
        }
    }

    $todb = new stdClass();
    $todb->name = trim($fromform->name);
    $todb->shortname = trim($fromform->shortname);
    $todb->type = $fromform->type;
    $todb->defaultvalue = trim($fromform->defaultvalue);
    $todb->possiblevalues = implode(CUSTOMFIELD_DELIMITER, $posvals);
    $todb->required = $fromform->required;
    $todb->isfilter = $fromform->isfilter;
    $todb->showinsummary = $fromform->showinsummary;

    if ($field != null) {
        $todb->id = $field->id;
        if (!$DB->update_record('onetoone_session_field', $todb)) {
            print_error('error:couldnotupdatefield', 'onetoone', $returnurl);
        }
    } else {
        if (!$DB->insert_record('onetoone_session_field', $todb)) {
            print_error('error:couldnotaddfield', 'onetoone', $returnurl);
        }
    }

    redirect($returnurl);
} else if ($field != null) {// Edit mode
    // Set values for the form.
    $toform = new stdClass();
    $toform->name = $field->name;
    $toform->shortname = $field->shortname;
    $toform->type = $field->type;
    $toform->defaultvalue = $field->defaultvalue;
    $valuearray = explode(CUSTOMFIELD_DELIMITER, $field->possiblevalues);
    $possiblevalues = implode(PHP_EOL, $valuearray);
    $toform->possiblevalues = $possiblevalues;
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
