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
 * @author(previous) Francois Marier <francois@catalyst.net.nz>
 * @author(previous) Aaron Barnes <aaronb@catalyst.net.nz>
 * @package mod
 * @subpackage onetoone
 */

$plugin->version   = 201412500;  // Use minor version bumps until 2013 then use YYYYMMDDxx
$plugin->requires  = 2013111800.00;  // Requires this Moodle version
$plugin->release   = '1.0.2 (20140711)'; // User-friendly version number.
$plugin->component = 'mod_onetoone';
$plugin->maturity  = MATURITY_STABLE;
$plugin->cron      = 60;
$plugin->dependencies = array('local_getkey' => ANY_VERSION);
