<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    //--- settings ------------------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_maxpay_settings', '', get_string('pluginname_desc', 'enrol_maxpay')));

    $settings->add(new admin_setting_configtext('enrol_maxpay/mrh_login', 'Идентификатор магазина', '', ''));
    $settings->add(new admin_setting_configtext('enrol_maxpay/mrh_pass1', 'Пароль 1', '', ''));
    $settings->add(new admin_setting_configtext('enrol_maxpay/mrh_url', 'URL', '', ''));

    $settings->add(new admin_setting_configtext('enrol_maxpay/maxpaybusiness', get_string('businessemail', 'enrol_maxpay'), get_string('businessemail_desc', 'enrol_maxpay'), '', PARAM_EMAIL));

    $settings->add(new admin_setting_configcheckbox('enrol_maxpay/mailstudents', get_string('mailstudents', 'enrol_maxpay'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_maxpay/mailteachers', get_string('mailteachers', 'enrol_maxpay'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_maxpay/mailadmins', get_string('mailadmins', 'enrol_maxpay'), '', 0));

    // Note: let's reuse the ext sync constants and strings here, internally it is very similar,
    //       it describes what should happen when users are not supposed to be enrolled any more.
    $options = array(
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    );
    $settings->add(new admin_setting_configselect('enrol_maxpay/expiredaction', get_string('expiredaction', 'enrol_maxpay'), get_string('expiredaction_help', 'enrol_maxpay'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));

    //--- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_maxpay_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                     ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_maxpay/status',
        get_string('status', 'enrol_maxpay'), get_string('status_desc', 'enrol_maxpay'), ENROL_INSTANCE_DISABLED, $options));

    $settings->add(new admin_setting_configtext('enrol_maxpay/cost', get_string('cost', 'enrol_maxpay'), '', 0, PARAM_FLOAT, 4));
	
	/*
    $maxpaycurrencies = enrol_get_plugin('maxpay')->get_currencies();
    $settings->add(new admin_setting_configselect('enrol_maxpay/currency', get_string('currency', 'enrol_maxpay'), '', 'USD', $maxpaycurrencies));
	*/
	
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_maxpay/roleid',
            get_string('defaultrole', 'enrol_maxpay'), get_string('defaultrole_desc', 'enrol_maxpay'), $student->id, $options));
    }

    $settings->add(new admin_setting_configduration('enrol_maxpay/enrolperiod',
        get_string('enrolperiod', 'enrol_maxpay'), get_string('enrolperiod_desc', 'enrol_maxpay'), 0));
}