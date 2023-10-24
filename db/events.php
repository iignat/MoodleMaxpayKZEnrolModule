<?php
$observers=array(
    
    array(
        'eventname'   => 'enrol_maxpay\event\payment_success',
        'callback'    => 'enrol_maxpay_observer::payment_success',
        'includefile'=>'/enrol/maxpay/classes/observer.php'
    )
);