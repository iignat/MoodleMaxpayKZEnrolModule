<?php
define('NO_DEBUG_DISPLAY', true);

require("../../config.php");
require_once("lib.php");

openlog("MoodleMaxPayResult", LOG_PID, LOG_USER);

$rec=$DB->get_record('config_plugins',array('plugin'=>'enrol_maxpay','name'=>'mrh_pass1'));
$mrh_pass1 = $rec->value;

$post = file_get_contents('php://input');
//$post = '{"transaction_id":1001407,"type":0,"status":1,"reference_id":"341","secret_key":"$2y$10$Dpz2YjrZmjVQvqaiPDyFx..Twg4x3wCl26RKGCkNWFs0mDM2HlXtm","masked_pan":"4405-63XXXXXX-5096","description":"Invoce N341 payment","bank_id":1}';

$req = json_decode($post);

if(!password_verify($req->reference_id.$mrh_pass1,$req->secret_key)){
    syslog(LOG_INFO,"Sign check fail:".$post);
    die();
}


$rec=$DB->get_record('invid_robokassa',array('transid'=>$req->transaction_id,'id'=>$req->reference_id));

if($rec===FALSE) {
    syslog(LOG_INFO,"Can't find invoice information:".$post);
    die();
}

if($req->status === 1){
    $r = maxpay_enrol($rec->courseid, $rec->userid);
    syslog(LOG_INFO,"Enrol user ".$rec->userid." on course ".$rec->courseid." with result ".$r); 
}

exit();


$courseid=(int)$courseid;
$userid=(int)$userid;

$course=$DB->get_record('course',array('id'=>$courseid));

$plugin = enrol_get_plugin('maxpay');

$data=new \stdClass();

$data->item_name=$course->shortname;
$data->courseid=$courseid;
$data->userid=$userid;
$data->instanceid=$instanceid;
$data->outsum=(float)$outsum;
$data->invid=(int)$invid;
$data->invdesc=$invdesc;
$data->payment_status='completed';
$data->timeupdated=time();


$data->id=$DB->insert_record('enrol_maxpay', $data);


$event=\enrol_maxpay\event\payment_success::create_from_paymentrecord($data);
$event->trigger();


if ($plugin_instance->enrolperiod) {
    $timestart = time();
    $timeend   = $timestart + $plugin_instance->enrolperiod;
} else {
    $timestart = 0;
    $timeend   = 0;
}


// Enrol user
$plugin->enrol_user($plugin_instance, $userid, $plugin_instance->roleid, $timestart, $timeend);

