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
 * @author(current)  Pinky Sharma <http://www.vidyamantra.com>
 * @author(current)  Suman Bogati <http://www.vidyamantra.com>
 * @author(previous) Francois Marier <francois@catalyst.net.nz>
 * @author(previous) Aaron Barnes <aaronb@catalyst.net.nz>
 * @package mod
 * @subpackage onetoone
 */

require_once('../../config.php');
require_once('sitenotice_form.php');

global $DB;

$id      = required_param('id', PARAM_INT); // ID in onetoone_notice.
$d       = optional_param('d', false, PARAM_BOOL); // Set to true to delete the given notice.
$confirm = optional_param('confirm', false, PARAM_BOOL); // Delete confirmation.

$notice = null;
if ($id > 0) {
    $notice = $DB->get_record('onetoone_notice', array('id' => $id));
}

$PAGE->set_url('/mod/onetoone/sitenotice.php', array('id' => $id, 'd' => $d, 'confirm' => $confirm));

admin_externalpage_setup('managemodules'); // This is hacky, tehre should be a special hidden page for it.

$contextsystem = context_system::instance();

require_capability('moodle/site:config', $contextsystem);

$returnurl = "$CFG->wwwroot/admin/settings.php?section=modsettingonetoone";

$title = get_string('addnewnotice', 'onetoone');
if ($notice != null) {
    $title = $notice->name;
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

        $info = new stdClass();
        $info->name = format_string($notice->name);
        $info->text = format_text($notice->text, FORMAT_HTML);
        $optionsyes = array('id' => $id, 'sesskey' => $USER->sesskey, 'd' => 1, 'confirm' => 1);
        echo $OUTPUT->confirm(get_string('noticedeleteconfirm', 'onetoone', $info),
            new moodle_url("sitenotice.php", $optionsyes),
            new moodle_url($returnurl));
        echo $OUTPUT->footer();
        exit;
    } else {
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('onetoone_notice', array('id' => $id));
        $DB->delete_records('onetoone_notice_data', array('noticeid' => $id));
        $transaction->allow_commit();
        redirect($returnurl);
    }
}

$customfields = onetoone_get_session_customfields();

$mform = new mod_onetoone_sitenotice_form(null, compact('id', 'customfields'));
if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) { // Form submitted.

    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'onetoone', $returnurl);
    }



    $todb = new stdClass();
    $todb->name = trim($fromform->name);
    $todb->text = trim($fromform->text['text']);

    $transaction = $DB->start_delegated_transaction();
    if ($notice != null) {
        $todb->id = $notice->id;
        $DB->update_record('onetoone_notice', $todb);
    } else {
        $notice = new stdClass();
        $notice->id = $DB->insert_record('onetoone_notice', $todb);
    }

    foreach ($customfields as $field) {
        $fieldname = "custom_$field->shortname";
        if (empty($fromform->$fieldname)) {
            $fromform->$fieldname = ''; // Need to be able to clear fields.
        }
        onetoone_save_customfield_value($field->id, $fromform->$fieldname, $notice->id, 'notice');
    }
    $transaction->allow_commit();
    redirect($returnurl);

} else if ($notice != null) { // Edit mode.
    // Set values for the form.
    $toform = new stdClass();
    $toform->name = $notice->name;
    $toform->text['text'] = $notice->text;

    foreach ($customfields as $field) {
        $fieldname = "custom_$field->shortname";
        $toform->$fieldname = onetoone_get_customfield_value($field, $notice->id, 'notice');
    }

    $mform->set_data($toform);
}

echo $OUTPUT->header();

echo $OUTPUT->box_start();
echo $OUTPUT->heading($title);

$mform->display();

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
