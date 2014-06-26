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
 * Prints a particular instance of newmodule
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_newmodule
 * @copyright  2011 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/// (Replace newmodule with the name of your module and remove this line)

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
include('auth.php') ;

/**
 * This(tillImgFolderPath) would be the web path for whiteboard folder
 * Please put your Ip address Or Domain name instead of http://192.168.1.118
 * eg: www.ourmoodle.com/mod/onetoone/bundle/whiteboard
 *  * */
 
echo "<script>
		window.whiteboardPath =  'http://192.168.1.118/moodle/mod/onetoone/bundle/whiteboard';
	  </script>";

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // newmodule instance ID - it should be named as the first character of the module
$sid  = optional_param('s', 0, PARAM_INT);
if ($id) {
    $cm         = get_coursemodule_from_id('onetoone', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $onetoone  = $DB->get_record('onetoone', array('id' => $cm->instance), '*', MUST_EXIST);
} elseif ($n) {
    $onetoone  = $DB->get_record('onetoone', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $onetoone->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('onetoone', $onetoone->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

add_to_log($course->id, 'onetoone', 'view', "view.php?id={$cm->id}", $onetoone->name, $cm->id);

/// Print the page header

$PAGE->set_url('/mod/onetoone/whiteboard.php', array('id' => $cm->id));
$PAGE->set_title(format_string($onetoone->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);


$PAGE->set_pagelayout('popup');

$PAGE->requires->jquery(true);
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css'); 

$PAGE->requires->css(new moodle_url($CFG->wwwroot . '/mod/onetoone/bundle/whiteboard/css/styles.css'));
//include('js.php') ;
include('js.debug.php') ;

// Output starts here
echo $OUTPUT->header();

//checking moodle deugger is on or not
$info = 0;
if($CFG->debug == 32767 && $CFG->debugdisplay == 1){
	$info = 1;
}


if(has_capability('mod/onetoone:editsessions', $context)){
	$r = 't';
}else{
	$r = 's';
}

?>
	
	<script type="text/javascript">
		
	<?php echo "wbUser.name='".$USER->firstname."';"; ?>
	<?php echo "wbUser.id='".$USER->id."';"; ?>
	<?php echo "wbUser.socketOn='$info';"; ?>
	<?php echo "wbUser.dataInfo='$info';"; ?>
	<?php echo "wbUser.room='".$course->id . "_" . $cm->id."_".$sid."';"; ?>
	<?php echo "wbUser.sid='".$sid."';"; ?>
	<?php echo "wbUser.role='".$r."';"; ?>
	
	window.io = io;
	</script>
<?php
echo html_writer::tag('div', '', array('id' => 'clientLength'));	
echo html_writer::start_tag('div',array('id'=>'vcanvas'));
	
	echo html_writer::tag('div', '', array('id' => 'containerWb'));
	echo html_writer::start_tag('div',array('id'=>'videos'));
		echo html_writer::start_tag('div',array('id'=>'videoContainer'));
			echo html_writer::tag('div', '', array('class' => 'dynDiv_resizeDiv_tl'));
				echo html_writer::start_tag('div',array('class'=>'dynDiv_moveParentDiv'));
	
	    		echo  '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
		    	echo '<video id="localVideo" autoplay></video>	    		
		    		 <video id="remoteVideo" class="remoteVideo" autoplay>
		    		 </video>';	    		 	
	    		 
	    		    	    
	echo html_writer::end_tag('div');
		echo html_writer::tag('div', '', array('class' => 'clear'));
		echo html_writer::end_tag('div');
	echo html_writer::end_tag('div');
	echo html_writer::start_tag('div',array('id'=>'mainContainer'));
		echo html_writer::tag('div', '', array('id' => 'packetContainer'));
		echo html_writer::tag('div', '', array('id' => 'informationCont'));
	echo html_writer::end_tag('div'); 
	echo html_writer::tag('div', '', array('class' => 'clear'));
echo html_writer::end_tag('div'); 


// Finish the page
echo $OUTPUT->footer();
