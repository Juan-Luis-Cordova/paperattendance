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
 * @package    local
 * @subpackage paperattendance
 * @copyright  2016  Matías Queirolo (mqueirolo@alumnos.uai.cl)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once (dirname(dirname(dirname(dirname(__FILE__))))."/config.php");
require_once ($CFG->libdir."/formslib.php");

class editattendance extends moodleform {

	function definition (){

		$mform = $this->_form;
		$instance = $this->_customdata;
		$idattendance = $instance["idattendance"];
		$courseid = $instance["courseid"];
		$idpresence = $instance["idpresence"];

		// Select user input
		$status = array();
		
		//Values 1 for present, 0 for non present and -1 for the initial value
		$status[-1] = get_string('pleaseselectattendance', 'local_paperattendance');
		$status[0] = get_string('absentattendance', 'local_paperattendance');
		$status[1] = get_string('presentattendance', 'local_paperattendance');
		
		$mform->addElement("select", "status", "Asistencia alumno", $status);

		
		// Set action to "edit"
		$mform->addElement("hidden", "action", "edit");
		$mform->setType("action", PARAM_TEXT);
		//Set the required parameters
		$mform->addElement("hidden", "idattendance", $idattendance);
		$mform->setType("idattendance", PARAM_INT);
		$mform->addElement("hidden", "courseid", $courseid);
		$mform->setType("courseid", PARAM_INT);
		$mform->addElement("hidden", "idpresence", $idpresence);
		$mform->setType("idpresence", PARAM_INT);
		

		$this->add_action_buttons(true);
	}

	function validation ($data, $files){

		$errors = array();

		$status = $data["status"];

		if($status == -1){
			$errors["status"] = get_string('required', 'local_paperattendance');
		}

		return $errors;
	}
}