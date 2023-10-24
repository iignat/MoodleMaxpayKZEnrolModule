<?php

    require("../../config.php");

    require_once("lib.php");

    $PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));
    $PAGE->set_url('/enrol/maxpay/enrol.php');

    
    $course_id = required_param('course_id',PARAM_INT);
    $user_id = required_param('user_id',PARAM_INT);
    
    $rec = new \stdClass();
    $rec->time = (int)time();
    $rec->userid = $user_id;
    $rec->courseid = $course_id;
    
    $inv_id = $DB->insert_record('invid_robokassa', $rec);
    $rec->id = $inv_id;
    
    $ans = json_decode(maxpay_init_payment($inv_id));

    if($ans->success===true){
	//{"success":true,"data":{"redirect_url":"https:\/\/payform.maxpay.kz?payment_id=1001419&payment_hash=bf3fc0fe873f4da259e9c247de276c97&version=stable","transaction_id":1001419,"referenceId":346},"message":"","error_code":0}
	$Location=$ans->data->redirect_url;
	$rec->transid = $ans->data->transaction_id;
	$DB->update_record('invid_robokassa',$rec);
?>

<html>
<head>
<meta http-equiv="refresh" content="0;URL=<?php echo $Location; ?>" />
</head>
<body>
<p>Переход в банк на страницу оплаты. <a href="<?php echo $Location; ?>">Кликните здесь что бы продолжить.</a></p>
</body>
</html>

<?php
    } else {
	//{"success":false,"error":{"message":"The user id field must be an integer.","code":422}}
	echo $OUTPUT->header();
        echo "<H1>Ошибка при подключении к платежному шлюзу.</H1>";
	echo "<p>Код ошибки:".$ans->error->code."</p>";
	echo "<p>Сообщение:".$ans->error->message."</p>";
	echo $OUTPUT->footer();
    }
?>