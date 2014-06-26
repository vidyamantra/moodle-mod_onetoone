<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/onetoone/backup/moodle2/restore_onetoone_stepslib.php'); // Because it exists (must)

/**
 * onetoone restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_onetoone_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     *
     *
     */
    protected function define_my_steps() {
        $this->add_step(new restore_onetoone_activity_structure_step('onetoone_structure', 'onetoone.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

//        $contents[] = new restore_decode_content('onetoone', array(), 'onetoone');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('ONETOONEVIEWBYID', '/mod/onetoone/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('ONETOONEINDEX', '/mod/onetoone/index.php?id=$1', 'course');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * onetoone logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('onetoone', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
?>
