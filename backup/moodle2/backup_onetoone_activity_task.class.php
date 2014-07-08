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
require_once($CFG->dirroot . '/mod/onetoone/backup/moodle2/backup_onetoone_stepslib.php'); // Because it exists (must).

/**
 * onetoone backup task that provides all the settings and steps to perform one
 * complete backup of the activity.
 */
class backup_onetoone_activity_task extends backup_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have.
     */
    protected function define_my_steps() {
        // Onetoone only has one structure step.
        $this->add_step(new backup_onetoone_activity_structure_step('onetoone_structure', 'onetoone.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links.
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        // Link to the list of onetoone.
        $search = "/(".$base."\/mod\/onetoone\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@ONETOONEINDEX*$2@$', $content);

        // Link to onetoone view by moduleid.
        $search = "/(".$base."\/mod\/onetoone\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@ONETOONEVIEWBYID*$2@$', $content);

        return $content;
    }
}
