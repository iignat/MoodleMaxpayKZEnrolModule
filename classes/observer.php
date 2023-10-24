<?php
defined('MOODLE_INTERNAL') || die();

class enrol_robokassa_observer
{
    public static function payment_success(\enrol_robokassa\event\payment_success $event)
    {
        global $DB,$CFG;
       
        /*
        $payment=$DB->get_record($event->objecttable,array('id'=>$event->objectid));
        
        
        $userfrom=$DB->get_record('user',array('id'=>$payment->userid));
        $userto=$DB->get_record('user',array('id'=>2));
        
        $course=$DB->get_record('course',array('id'=>$payment->courseid));
        
        $rec=$DB->get_record('config_plugins',array('name'=>'mailteachers','plugin'=>'enrol_maxpay'));
        $mailteachers=$rec->value;	
        
        if ($mailteachers) {
        
            $teacher_role=$DB->get_record('role',array('shortname'=>'editingteacher'));
            
            $course_context=context_course::instance($course->id);
            
            $teachers=get_users_from_role_on_context($teacher_role, $course_context);
            
            $subject='Поступил платеж за курс '.$course->shortname;
            
            
            $message = new \core\message\message();
            $message->component = 'moodle';
            $message->name = 'instantmessage';
            $message->userfrom = $userfrom;
            //$message->userto = $userto;
            $message->subject = $subject;
            $message->fullmessage = 'Послупление платежа за доступ к курсу '.$course->fullname.'. Сумма: '.$payment->outsum;
            $message->fullmessageformat = FORMAT_MARKDOWN;
            $message->fullmessagehtml = 'Послупление платежа за доступ к курсу '.$course->fullname.'.<br> Сумма: '.$payment->outsum.'<br> Пользователь: '.$userfrom->lastname.' '.$userfrom->firstname;
            $message->smallmessage ='Послупление платежа за доступ к курсу '.$course->fullname.'. Сумма: '.$payment->outsum;
            $message->notification = '0';
            $message->contexturl = $CFG->wwwroot.'/user/index.php?id='.$course->id;
            $message->contexturlname = $subject;
            $message->replyto = $userfrom->email;
            $content = array('*' => array('header' => '', 'footer' => ' ')); // Extra content for specific processor
            $message->set_additional_content('email', $content);
            
            foreach($teachers as $t) {
                $message->userto=$t;
            }
            
            
            message_send($message);
                
        }
        
        */
        
        
    }
}