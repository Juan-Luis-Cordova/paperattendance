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
*
*
* @package    local
* @subpackage paperattendance
* @copyright  2016 Jorge Cabané (jcabane@alumnos.uai.cl) 
* @copyright  2016 Hans Jeria (hansjeria@gmail.com) 					
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
require_once (dirname(dirname(dirname(__FILE__))) . "/config.php");
require_once ($CFG->dirroot . "/local/paperattendance/forms/print_form.php");
require_once ($CFG->libdir . '/pdflib.php');
require_once ($CFG->dirroot . '/mod/assign/feedback/editpdf/fpdi/fpdi.php');
require_once ($CFG->dirroot . "/mod/assign/feedback/editpdf/fpdi/fpdi_bridge.php");
require_once ($CFG->dirroot . "/mod/emarking/lib/openbub/ans_pdf_open.php");
require_once ($CFG->dirroot . "/mod/emarking/print/locallib.php");
require_once ("locallib.php");
global $DB, $PAGE, $OUTPUT, $USER;

require_login();
if (isguestuser()) {
	die();
}

$courseid = required_param("courseid", PARAM_INT);
$action = optional_param("action", "add", PARAM_INT);

$context = context_course::instance($COURSE->id);

if( !has_capability("local/paperattendance:print", $context) ){
	print_error("ACCESS DENIED");
}

$urlprint = new moodle_url("/local/paperattendance/print.php", array("courseid" => $courseid));
// Page navigation and URL settings.
$pagetitle = get_string('printtitle', 'local_paperattendance');
$PAGE->set_context($context);
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin ( 'ui' );
$PAGE->requires->jquery_plugin ( 'ui-css' );
$PAGE->set_url($urlprint);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($pagetitle);

$course = $DB->get_record("course",array("id" => $courseid));

if($action == "add"){
	// Add the print form 
	$addform = new print_form(null, array("courseid" => $courseid));
	// If the form is cancelled, redirect to course.
	if ($addform->is_cancelled()) {
		$backtocourse = new moodle_url("/course/view.php", array('id' => $courseid));
		redirect($backtocourse);
	}
	else if ($data = $addform->get_data()) {
		// id teacher
		$requestor = $data->requestor;
		$requestorinfo = $DB->get_record("user", array("id" => $requestor));
		// date for session
		$sessiondate = $data->sessiondate;
		// array idmodule => {0 = no checked, 1 = checked}
		$modules = $data->modules;
		
		$path = $CFG -> dataroot. "/temp/local/paperattendance/";		
		//list($path, $filename) = paperattendance_create_qr_image($courseid."*".$requestor."*", $path);
		
		$uailogopath = $CFG->dirroot . '/local/paperattendance/img/uai.jpeg';
		$webcursospath = $CFG->dirroot . '/local/paperattendance/img/webcursos.jpg';
		$timepdf = time();
		$attendancepdffile = $path . "/print/paperattendance_".$courseid."_".$timepdf.".pdf";
		
		if (!file_exists($path . "/print/")) {
			mkdir($path . "/print/", 0777, true);
		}	
		
		$pdf = new PDF();
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);

		// Get student for the list
		$studentinfo = paperattendance_students_list($context->id, $course);

		// We validate the number of students as we are filtering by enrolment.
		// type after getting the data.
		$numberstudents = count($studentinfo);
		if ($numberstudents == 0) {
			throw new Exception('No students to print');
		}
		// Contruction string for QR encode
		$arraymodules = "";
		foreach ($modules as $key => $value){
			if($value == 1){
				$schedule = explode("*", $key);
				if($arraymodules == ""){
					$arraymodules .= $schedule[0];
				}else{
					$arraymodules .= ":".$schedule[0];
				}
			}
		}
		
		$time = strtotime(date("d-m-Y"));		
		$stringqr = $courseid."*".$requestor."*".$arraymodules."*".$time."*";
		
		paperattendance_draw_student_list($pdf, $uailogopath, $course, $studentinfo, $requestorinfo, $modules, $path, $stringqr, $webcursospath, $sessiondate);
		// Created new pdf
		$pdf->Output($attendancepdffile, "F");
		
		$fs = get_file_storage();		
		$file_record = array(
    			'contextid' => $context->id,
    			'component' => 'local_paperattendance',
    			'filearea' => 'draft',
    			'itemid' => 0,
    			'filepath' => '/',
    			'filename' => "paperattendance_".$courseid."_".$timepdf.".pdf",
    			'timecreated' => time(),
    			'timemodified' => time(),
    			'userid' => $USER->id,
    			'author' => $USER->firstname." ".$USER->lastname,
    			'license' => 'allrightsreserved'				
		);
		
		// If the file already exists we delete it
		if ($fs->file_exists($context->id, 'local_paperattendance', 'draft', 0, '/', "paperattendance_".$courseid."_".$timepdf.".pdf")) {
			$previousfile = $fs->get_file($context->id, 'local_paperattendance', 'draft', 0, '/', "paperattendance_".$courseid."_".$timepdf.".pdf");
			$previousfile->delete();
		}		
		// Info for the new file
		$fileinfo = $fs->create_file_from_pathname($file_record, $attendancepdffile);    	
		
		$action = "download";		
	}
}

if($action == "download" && isset($attendancepdffile)){

	$button = html_writer::nonempty_tag(
			"div",
			$OUTPUT->single_button($urlprint, get_string('printgoback', 'local_paperattendance')), 
			array("align" => "left"				
	));
	
	$url = moodle_url::make_pluginfile_url($context->id, 'local_paperattendance', 'draft', 0, '/', "paperattendance_".$courseid."_".$timepdf.".pdf");	
	$viewerpdf = html_writer::nonempty_tag("embed", " ", array(
			"src" => $url,
			"style" => "height:75vh; width:60vw"
	));
}

echo $OUTPUT->header();

if($action == "add"){

	$PAGE->set_heading($pagetitle);
	
	echo html_writer::nonempty_tag("h2", $course->shortname." - ".$course->fullname);	
	$addform->display();
}

if($action == "download" && isset($attendancepdffile)){
	
	//echo $OUTPUT->action_icon($url, new pix_icon('i/grades', "download"), null, array("target" => "_blank"));
	echo html_writer::div('<button style="margin-left:1%" type="button" class="btn btn-primary print">'.get_string("downloadprint", "local_paperattendance").'</button>');
	// Back button
	echo $button;
	// Preview PDF
	echo $viewerpdf;
}
	
echo $OUTPUT->footer();
?>
<script>
$( document ).ready(function() {
	$( ".print" ).on( "click", function() {
		var w = window.open('<?php echo $url ;?>');
		w.print();
	});
});
</script>

