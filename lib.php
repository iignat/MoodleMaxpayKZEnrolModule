<?php
defined('MOODLE_INTERNAL') || die();


function  maxpay_init_payment($inv_id, $out_summ=false,$currency=false){
	
	global $DB,$CFG; 

	$res=$DB->get_record('invid_robokassa',array('id'=>$inv_id));

	if($res===false)return false;
	$course_id=$res->courseid;
	$user_id=$res->userid;
  

	if($out_summ===false || $currency===false) {

	    $instance = $DB->get_record('enrol',array('courseid'=>$course_id,'enrol'=>'maxpay'));
	    if(empty($instance))return false;
    
	    if($out_summ===false)$out_summ = $instance->cost;
	    if($currency===false)$currency = $instance->currency;
	}

	

	if ($instcfg = $DB->get_record('enrol_maxpay_instcfg', ['instanceid' => $instance->id])) {
	    $mrh_login=$instcfg->mrh_login;
	    $mrh_pass1=$instcfg->mrh_pass1;
	    $mrh_url=$instcfg->mrh_url;
	} else {

	    $rec=$DB->get_record('config_plugins',array('name'=>'mrh_login','plugin'=>'enrol_maxpay'));
	    $mrh_login=$rec->value;	
    
	    $rec=$DB->get_record('config_plugins',array('name'=>'mrh_pass1','plugin'=>'enrol_maxpay'));
	    $mrh_pass1=$rec->value;

	    $rec=$DB->get_record('config_plugins',array('name'=>'mrh_url','plugin'=>'enrol_maxpay'));
	    $mrh_url=$rec->value;
	}

        $ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $mrh_url.'/invoices/payin');

        $data = array(
	"merchant_id" => $mrh_login,
	"secret_key" => password_hash($inv_id.$mrh_pass1,PASSWORD_BCRYPT),
	"reference_id" => $inv_id,
	"user_id" => $user_id,
	"amount" => "".$out_summ,
	"currency" => "".$currency,
	"description" => "Invoce N".$inv_id." payment",
	"request_url" => $CFG->wwwroot."/my/courses.php",
	"failure_url" => $CFG->wwwroot."/course/",
	"back_url" => $CFG->wwwroot."/enrol/maxpay/result.php",
        );

	curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($data));
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$resp = curl_exec($ch);
	$httpCode = curl_getinfo($ch);
	curl_close($ch);
	return $resp;
}


function maxpay_enrol($courseid, $userid, $roleid = -1, $enrolmethod = 'manual') {
    global $DB;


    if($userid<0 || $courseid<=0)return false;

    $user = $DB->get_record('user', array('id' => $userid, 'deleted' => 0), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $context = context_course::instance($course->id);
    if (!is_enrolled($context, $user)) {
        $enrol = enrol_get_plugin($enrolmethod);
        if ($enrol === null) {
            return false;
        }
        $instances = enrol_get_instances($course->id, true);
        $manualinstance = null;
	$enrolinstance = null;
        foreach ($instances as $instance) {
            if ($instance->enrol == $enrolmethod) {
                $enrolinstance = $instance;
                break;
            }
	    if($instance->enrol == 'manual'){
		$manualinstance = $instance;
	    }
        }

	if ($enrolinstance === null){

	    $enrolinstance = $manualinstance;
	    $enrol = enrol_get_plugin('manual');;
	}
	if($roleid<0)$roleid=$enrolinstance->roleid;

        $enrol->enrol_user($enrolinstance, $userid, $roleid);
    }
    return true;
}




class enrol_maxpay_plugin extends enrol_plugin {
 
	public function get_currencies() {
        
        
        $codes = array(
            'RUB', 'KZT','USD','EUR','GBP');
        $currencies = array();
        foreach ($codes as $c) {
            $currencies[$c] = new lang_string($c, 'core_currencies');
        }

        return $currencies;
    }
	
	
	 public function roles_protected() {
        // users with role assign cap may tweak the roles later
        return false;
    }
	
	public function allow_unenrol(stdClass $instance) {
        // users with unenrol cap may unenrol other users manually - requires enrol/maxpay:unenrol
        return true;
    }
	
	public function allow_manage(stdClass $instance) {
        // users with manage cap may tweak period and status - requires enrol/maxpay:manage
        return true;
    }
	
	public function show_enrolme_link(stdClass $instance) {
        return ($instance->status == ENROL_INSTANCE_ENABLED);
    }
	
	/**
     * Returns true if the user can add a new instance in this course.
     * @param int $courseid
     * @return boolean
     */
    public function can_add_instance($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/maxpay:config', $context)) {
            return false;
        }

        // multiple instances supported - different cost for different roles
        return true;
    }
	
	/**
     * We are a good plugin and don't invent our own UI/validation code path.
     *
     * @return boolean
     */
    public function use_standard_editing_ui() {
        return true;
    }
	
	/**
     * Add new instance of enrol plugin.
     * @param object $course
     * @param array $fields instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_instance($course, array $fields = null) {
        global $USER, $DB;
        if ($fields && !empty($fields['cost'])) {
            $fields['cost'] = unformat_float($fields['cost']);
        }
        
        if ($instanceid = parent::add_instance($course, $fields)) {
            if ($fields['customconfig']) {
                $DB->insert_record('enrol_maxpay_instcfg', [
                        'instanceid' => $instanceid,
                        'userid' => $USER->id,
                        'mrh_login' => $fields['mrh_login'],
                        'mrh_pass1' => $fields['mrh_pass1'],
                        'mrh_url' => $fields['mrh_url'],
                        'timecreated' => time()
                    ]
                );
                
            }
            
        }
        
        return $instanceid;
    }
	
	/**
     * Update instance of enrol plugin.
     * @param stdClass $instance
     * @param stdClass $data modified instance fields
     * @return boolean
     */
    public function update_instance($instance, $data) {
        global $DB, $USER;
        
        if ($data) {
            $data->cost = unformat_float($data->cost);
        }
        
        if ($result = parent::update_instance($instance, $data)) {
            
            if (!$data->customconfig) {
                $DB->delete_records('enrol_maxpay_instcfg', ['instanceid' => $instance->id]);
            } else {
                
                if ($rec = $DB->get_record('enrol_maxpay_instcfg', ['instanceid' => $instance->id])) {
                    $DB->update_record('enrol_maxpay_instcfg', ['id' => $rec->id, 'instanceid' => $instance->id, 'userid' => $USER->id, 'mrh_login' => $data->mrh_login, 'mrh_pass1' => $data->mrh_pass1, 'mrh_url' => $data->mrh_url, 'timecreated' => time()]);
                } else {
                    $DB->insert_record('enrol_maxpay_instcfg', ['instanceid' => $instance->id, 'userid' => $USER->id, 'mrh_login' => $data->mrh_login, 'mrh_pass1' => $data->mrh_pass1, 'mrh_url' => $data->mrh_url, 'timecreated' => time()]);
                }
            }
        }
        
        return $result;
    }
 
    
    public function delete_instance($instance) {
        global $DB;
        
        parent::delete_instance($instance);
        
        $DB->execute('delete from {enrol_maxpay_instcfg} where instanceid=:instanceid', ['instanceid' => $instance->id]);
    }
 
	/**
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param stdClass $instance
     * @return string html text, usually a form in a text box
     */
    function enrol_page_hook(stdClass $instance) {
        global $CFG, $USER, $OUTPUT, $PAGE, $DB;

        ob_start();

        if ($DB->record_exists('user_enrolments', array('userid'=>$USER->id, 'enrolid'=>$instance->id))) {
            return ob_get_clean();
        }

        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
            return ob_get_clean();
        }

        if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
            return ob_get_clean();
        }

        $course = $DB->get_record('course', array('id'=>$instance->courseid));
        $context = context_course::instance($course->id);

        $shortname = format_string($course->shortname, true, array('context' => $context));
        $strloginto = get_string("loginto", "", $shortname);
        $strcourses = get_string("courses");

        // Pass $view=true to filter hidden caps if the user cannot see them
        if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                             '', '', '', '', false, true)) {
            $users = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        } else {
            $teacher = false;
        }

        if ( (float) $instance->cost <= 0 ) {
            $cost = (float) $this->get_config('cost');
        } else {
            $cost = (float) $instance->cost;
        }

        if (abs($cost) < 0.01) { // no cost, other enrolment methods (instances) should be used
            echo '<p>'.get_string('nocost', 'enrol_maxpay').'</p>';
        } else {

            // Calculate localised and "." cost, make sure we send PayPal the same value,
            // please note PayPal expects amount with 2 decimal places and "." separator.
            $localisedcost = format_float($cost, 2, true);
            $cost = format_float($cost, 2, false);

            if (isguestuser()) { // force login only for guest user, not real users with guest role
                if (empty($CFG->loginhttps)) {
                    $wwwroot = $CFG->wwwroot;
                } else {
                    // This actually is not so secure ;-), 'cause we're
                    // in unencrypted connection...
                    $wwwroot = str_replace("http://", "https://", $CFG->wwwroot);
                }
                echo '<div class="mdl-align"><p>'.get_string('paymentrequired').'</p>';
                echo '<p><b>'.get_string('cost').": $instance->currency $localisedcost".'</b></p>';
                echo '<p><a href="'.$wwwroot.'/login/">'.get_string('loginsite').'</a></p>';
                echo '</div>';
            } else {
                //Sanitise some fields before building the maxpay form
                $coursefullname  = format_string($course->fullname, true, array('context'=>$context));
                $courseshortname = $shortname;
                $userfullname    = fullname($USER);
                $userfirstname   = $USER->firstname;
                $userlastname    = $USER->lastname;
                $useraddress     = $USER->address;
                $usercity        = $USER->city;
                $instancename    = $this->get_instance_name($instance);
                $courseid=$instance->courseid;
                $instanceid=$instance->id;

                include($CFG->dirroot.'/enrol/maxpay/enrol.html');
            }

        }

        return $OUTPUT->box(ob_get_clean());
    }
	
	
	/**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;
        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) {
            $merge = false;
        } else {
            $merge = array(
                'courseid'   => $data->courseid,
                'enrol'      => $this->get_name(),
                'roleid'     => $data->roleid,
                'cost'       => $data->cost,
                'currency'   => $data->currency,
            );
        }
        if ($merge and $instances = $DB->get_records('enrol', $merge, 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course, (array)$data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

		
	/**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $oldinstancestatus
     * @param int $userid
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
    }


	/**
     * Gets an array of the user enrolment actions
     *
     * @param course_enrolment_manager $manager
     * @param stdClass $ue A user enrolment object
     * @return array An array of user_enrolment_actions
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol($instance) && has_capability("enrol/maxpay:unenrol", $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/delete', ''), get_string('unenrol', 'enrol'), $url, array('class'=>'unenrollink', 'rel'=>$ue->id));
        }
        if ($this->allow_manage($instance) && has_capability("enrol/maxpay:manage", $context)) {
            $url = new moodle_url('/enrol/editenrolment.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/edit', ''), get_string('edit'), $url, array('class'=>'editenrollink', 'rel'=>$ue->id));
        }
        return $actions;
    }
	
	public function cron() {
        $trace = new text_progress_trace();
        $this->process_expirations($trace);
    }
 
 
	/**
     * Return an array of valid options for the status.
     *
     * @return array
     */
    protected function get_status_options() {
        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
        return $options;
    }
 
 
	/**
     * Return an array of valid options for the roleid.
     *
     * @param stdClass $instance
     * @param context $context
     * @return array
     */
    protected function get_roleid_options($instance, $context) {
        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $this->get_config('roleid'));
        }
        return $roles;
    }
	
	/**
     * Add elements to the edit instance form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return bool
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {

        global $DB, $COURSE;
        
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $options = $this->get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'enrol_maxpay'), $options);
        $mform->setDefault('status', $this->get_config('status'));

        //$context = context_course::instance($COURSE->id, MUST_EXIST);
        
        if (has_capability('enrol/maxpay:payparamcfg', $context)) {
            $mform->addElement('advcheckbox', 'customconfig', get_string('customconfig', 'enrol_maxpay'), get_string('yes'));
            
            $mform->addElement('text','mrh_login', get_string('mrh_login','enrol_maxpay'));
            $mform->setType('mrh_login', PARAM_TEXT);
            $mform->disabledIf('mrh_login', 'customconfig');
            
            $mform->addElement('text','mrh_pass1', get_string('mrh_pass1','enrol_maxpay'));
            $mform->setType('mrh_pass1', PARAM_TEXT);
            $mform->disabledIf('mrh_pass1', 'customconfig');
                        
        }
        
        $mform->addElement('text', 'cost', get_string('cost', 'enrol_maxpay'), array('size' => 4));
        $mform->setType('cost', PARAM_RAW);
        $mform->setDefault('cost', format_float($this->get_config('cost'), 2, true));

        $maxpaycurrencies = $this->get_currencies();
        $mform->addElement('select', 'currency', get_string('currency', 'enrol_maxpay'), $maxpaycurrencies);
        $mform->setDefault('currency', $this->get_config('currency'));

        $roles = $this->get_roleid_options($instance, $context);
        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_maxpay'), $roles);
        $mform->setDefault('roleid', $this->get_config('roleid'));

        $options = array('optional' => true, 'defaultunit' => 86400);
        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_maxpay'), $options);
        $mform->setDefault('enrolperiod', $this->get_config('enrolperiod'));
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_maxpay');

        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_maxpay'), $options);
        $mform->setDefault('enrolstartdate', 0);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_maxpay');

        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_maxpay'), $options);
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_maxpay');

        if (enrol_accessing_via_instance($instance)) {
            $warningtext = get_string('instanceeditselfwarningtext', 'core_enrol');
            $mform->addElement('static', 'selfwarn', get_string('instanceeditselfwarning', 'core_enrol'), $warningtext);
        }
        
        if ( $customconfig = $DB->get_record('enrol_maxpay_instcfg', ['instanceid' => $instance->id]) ) {
            $instance->customconfig = 1;
            $instance->mrh_login = $customconfig->mrh_login;
            $instance->mrh_pass1 = $customconfig->mrh_pass1;
            $instance->mrh_url = $customconfig->mrh_url;
        }
    }


	/**
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @param object $instance The instance loaded from the DB
     * @param context $context The context of the instance we are editing
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK.
     * @return void
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = array();

        if (!empty($data['enrolenddate']) and $data['enrolenddate'] < $data['enrolstartdate']) {
            $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_maxpay');
        }

        $cost = str_replace(get_string('decsep', 'langconfig'), '.', $data['cost']);
        if (!is_numeric($cost)) {
            $errors['cost'] = get_string('costerror', 'enrol_maxpay');
        }

        $validstatus = array_keys($this->get_status_options());
        $validcurrency = array_keys($this->get_currencies());
        $validroles = array_keys($this->get_roleid_options($instance, $context));
        $tovalidate = array(
            'name' => PARAM_TEXT,
            'status' => $validstatus,
            'currency' => $validcurrency,
            'roleid' => $validroles,
            'enrolperiod' => PARAM_INT,
            'enrolstartdate' => PARAM_INT,
            'enrolenddate' => PARAM_INT
        );

        $typeerrors = $this->validate_param_types($data, $tovalidate);
        $errors = array_merge($errors, $typeerrors);

        return $errors;
    }
	
	/**
     * Execute synchronisation.
     * @param progress_trace $trace
     * @return int exit code, 0 means ok
     */
    public function sync(progress_trace $trace) {
        $this->process_expirations($trace);
        return 0;
    }
	
	
	/**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/maxpay:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/maxpay:config', $context);
    }
 
}