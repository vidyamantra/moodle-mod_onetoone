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

defined('MOODLE_INTERNAL') || die();

$logs = array(
    array('module' => 'onetoone', 'action' => 'add', 'mtable' => 'onetoone', 'field' => 'name'),
    array('module' => 'onetoone', 'action' => 'delete', 'mtable' => 'onetoone', 'field' => 'name'),
    array('module' => 'onetoone', 'action' => 'update', 'mtable' => 'onetoone', 'field' => 'name'),
    array('module' => 'onetoone', 'action' => 'view', 'mtable' => 'onetoone', 'field' => 'name'),
    array('module' => 'onetoone', 'action' => 'view all', 'mtable' => 'onetoone', 'field' => 'name'),
    array('module' => 'onetoone', 'action' => 'add session', 'mtable' => 'onetoone', 'field' => 'name'),
    array('module' => 'onetoone', 'action' => 'copy session', 'mtable' => 'onetoone', 'field' => 'name'),
    array('module' => 'onetoone', 'action' => 'delete session', 'mtable' => 'onetoone', 'field' => 'name'),
    array('module' => 'onetoone', 'action' => 'update session', 'mtable' => 'onetoone', 'field' => 'name'),
    array('module' => 'onetoone', 'action' => 'view session', 'mtable' => 'onetoone', 'field' => 'name'),
    array('module' => 'onetoone', 'action' => 'view attendees', 'mtable' => 'onetoone', 'field' => 'name'),
    array('module' => 'onetoone', 'action' => 'take attendance', 'mtable' => 'onetoone', 'field' => 'name'),
    array('module' => 'onetoone', 'action' => 'signup', 'mtable' => 'onetoone', 'field' => 'name'),
    array('module' => 'onetoone', 'action' => 'cancel', 'mtable' => 'onetoone', 'field' => 'name'),
);

