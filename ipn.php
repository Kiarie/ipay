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
 * Adds new instance of enrol_ipay to specified course or edits current instance.
 *
 * @package    enrol_ipay
 * @copyright  2015 Ipay Ltd, Kiarie Mburu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/eventslib.php');
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir.'/filelib.php');

if(empty($_GET)){
	print_error("Script cannot be used that way");
}

//Get the data from Ipay 
//that will be used to verify and be used to enroll the user 

$data = new StdClass();

$val = 'demo'; //assigned iPay Vendor ID... hard code it here.
$data->id = $_GET['id'];//id for you to authenticate the order id again and map it to the order transaction again.
$data->inv = $_GET['ivm'];//ivm the invoice number is returned as an MD5 hash for you to process if you need to.
$val3 = $_GET['qwh'];
$val4 = $_GET['afd'];
$val5 = $_GET['poi'];
$val6 = $_GET['uyt'];
$val7 = $_GET['ifd'];
$data->mc = $_GET['mc'];
$data->userid = $_GET['p1'];//user ID
$data->courseid= $_GET['p2'];//course id
$data->instanceid = $_GET['p3']; //instance id
$ipnurl = "https://www.ipayafrica.com/ipn/?vendor=".$val."&id=".$data->id."&ivm=".$data->inv."&qwh=".$val3."&afd=".$val4."&poi=".$val5."&uyt=".$val6."&ifd=".$val7;
$fp = fopen($ipnurl, "rb");
$status = stream_get_contents($fp, -1, -1);
fclose($fp);

if(! $user = $DB->get_record("user", array("id"=>$data->userid)))
{
	print_error("Invalid User or wrong user id");
	die;

}
if(! $course = $DB->get_record("course", array('id' =>$data->courseid  )))
{
	print_error("Ivalid Course or wrong course id");
	die;
}
if(! $context =  context_course::instance($course->id, IGNORE_MISSING))
{
	print_error("Wrong context!");
	die;
}
if (! $instance = $DB->get_record("enrol", array('id'=>$data->instanceid, 'status'=>0)))
{
	print_error("Wrong instance for Enrolment ");
	die;
}
if($exist = $DB->get_record("enrol_ipay", array('userid' => $data->userid, 'inv' => $data->inv)))
{
	print_error("This transaction has been done before, (Fake Payment?)");
	die;
}




$plugin = enrol_get_plugin('ipay');
$ipayaddr = 'https://www.ipayafrica.com/payments/';

if($status == 'bdi6p2yy76etrs')
{
	$data->payment_status = "is pending please contact the administrator about this";
}
if ($status == 'fe2707etr5s4wq') {
	$data->paymentstatus = "failed it did not get processed you can retry again by clicking continue";
}
if($status == 'aei7p7yrx4ae34'){
	$data->paymentstatus = "successful";
}
if ($status =='cr5i3pgy9867e1') {
	$data->paymentstatus = "is already used";	# code...
}
if ($status =='dtfi4p7yty45wq'){
	$data->paymentstatus= "Ammount is less than required contact administator concerning what to do";
}
if ($status == 'eq3i7p5yt7645e') {
	$data->paymentstatus= "Ammount is more than required contact administator concerning what to do";
}


$PAGE->set_context($context);
$destinaton = $CFG->wwwroot.'/enrol/index.php?id='.$data->courseid;
//check the status die if not completed
if($data->paymentstatus != 'successful'){
	//message_to_admin($data->paymentstatus, $data->mc);
	$PAGE->set_url($destinaton);
	echo $OUTPUT->header();
	notice('The Transaction '.$data->paymentstatus, $destinaton);
}
//AllClear

	$DB->insert_record("enrol_ipay", $data);

	if($instance->enrolperiod){
	$timestart = time();
	$timend = $timestart+$instance->enrolperiod;
	}else{
	$timestart = 0;
	$timend = 0;
	}

	$plugin->enrol_user($instance, $user->id, $instance->roleid, $timestart, $timend);

//redirect($CFG->wwwroot.'/enrol/ipay/return.php');
	echo '<script type="text/javascript">
     window.location.href="'.$CFG->wwwroot.'/enrol/ipay/return.php?id='.$data->courseid.'";
     </script>';
	die;


//--------------------------------------------------------------------
//Helper functions to process when client has overpaid or underpaid to alert the admin
//that he can take the necessary actions
function message_to_admin($subject, $data)
{
	$admin = get_admin();

	$msg  = 'The Transaction was'. $subject. 'for id:'.$data->userid;
	$msg .= '\n they paid '.$data->mc.' as opposed to the correct Ammount';
	$msgdata = new StdClass();
	$eventdata->modulename        = 'enrol';
    $eventdata->component         = 'enrol_ipay';
    $eventdata->name              = 'ipay_enrolment';
	$msgdata->userfrom = $admin;
	$msgdata->userto = $admin;
	$msgdata->subject = "Enrolment Error".$subject;
	$msgdata->fullmessage = $msg;
	$msgdata->fullmessageformat = '';
	$msgdata->fullmessagehtml = '';
	$msgdata->smallmessage = '';
	message_send($msgdata);
}
