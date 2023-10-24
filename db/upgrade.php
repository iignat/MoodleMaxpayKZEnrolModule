<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_enrol_maxpay_upgrade($oldversion) {
    global $DB;
    
    $dbman = $DB->get_manager();
    
    if ($oldversion < 2018091403) {
        
        // Define table enrol_maxpay_instcfg to be created.
        $table = new xmldb_table('enrol_maxpay_instcfg');
        
        // Adding fields to table enrol_maxpay_instcfg.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('mrh_login', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('mrh_pass1', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('mrh_url', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        
        // Adding keys to table enrol_maxpay_instcfg.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        
        // Conditionally launch create table for enrol_maxpay_instcfg.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        
        // maxpay savepoint reached.
        upgrade_plugin_savepoint(true, 2018091403, 'enrol', 'maxpay');
    }
    
    
    
    return true;
}
