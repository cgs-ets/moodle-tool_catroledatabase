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
 * Category role database plugin settings and presets.
 *
 * @package   tool_catroledatabase
 * @copyright 2019 Michael Vangelovski, Canberra Grammar School <michael.vangelovski@cgs.act.edu.au>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


if ($hassiteconfig) {

    // Add a new category under tools.
    $ADMIN->add('tools',
        new admin_category('tool_catroledatabase', get_string('pluginname', 'tool_catroledatabase')));

    $settings = new admin_settingpage('tool_catroledatabase_settings', new lang_string('settings', 'tool_catroledatabase'),
        'moodle/site:config', false);

    // Add the settings page.
    $ADMIN->add('tool_catroledatabase', $settings);

    // Add the test settings page.
    $ADMIN->add('tool_catroledatabase',
            new admin_externalpage('tool_catroledatabase_test', get_string('testsettings', 'tool_catroledatabase'),
                $CFG->wwwroot . '/' . $CFG->admin . '/tool/catroledatabase/test_settings.php'));

    // General settings.
    $settings->add(new admin_setting_heading('tool_catroledatabase_settings', '',
        get_string('pluginname_desc', 'tool_catroledatabase')));

    $settings->add(new admin_setting_heading('tool_catroledatabase_exdbheader',
        get_string('settingsheaderdb', 'tool_catroledatabase'), ''));

    $options = array('', "pdo", "pdo_mssql", "pdo_sqlsrv", "access", "ado_access", "ado", "ado_mssql", "borland_ibase",
        "csv", "db2", "fbsql", "firebird", "ibase", "informix72", "informix", "mssql", "mssql_n", "mssqlnative", "mysql",
        "mysqli", "mysqlt", "oci805", "oci8", "oci8po", "odbc", "odbc_mssql", "odbc_oracle", "oracle", "postgres64",
        "postgres7", "postgres", "proxy", "sqlanywhere", "sybase", "vfp");
    $options = array_combine($options, $options);
    $settings->add(new admin_setting_configselect('tool_catroledatabase/dbtype',
        get_string('dbtype', 'tool_catroledatabase'),
        get_string('dbtype_desc', 'tool_catroledatabase'), '', $options));

    $settings->add(new admin_setting_configtext('tool_catroledatabase/dbhost',
        get_string('dbhost', 'tool_catroledatabase'),
        get_string('dbhost_desc', 'tool_catroledatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_catroledatabase/dbuser',
        get_string('dbuser', 'tool_catroledatabase'), '', ''));

    $settings->add(new admin_setting_configpasswordunmask('tool_catroledatabase/dbpass',
        get_string('dbpass', 'tool_catroledatabase'), '', ''));

    $settings->add(new admin_setting_configtext('tool_catroledatabase/dbname',
        get_string('dbname', 'tool_catroledatabase'),
        get_string('dbname_desc', 'tool_catroledatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_catroledatabase/dbencoding',
        get_string('dbencoding', 'tool_catroledatabase'), '', 'utf-8'));

    $settings->add(new admin_setting_configtext('tool_catroledatabase/dbsetupsql',
        get_string('dbsetupsql', 'tool_catroledatabase'),
        get_string('dbsetupsql_desc', 'tool_catroledatabase'), ''));

    $settings->add(new admin_setting_configcheckbox('tool_catroledatabase/dbsybasequoting',
        get_string('dbsybasequoting', 'tool_catroledatabase'),
        get_string('dbsybasequoting_desc', 'tool_catroledatabase'), 0));

    $settings->add(new admin_setting_configcheckbox('tool_catroledatabase/debugdb',
        get_string('debugdb', 'tool_catroledatabase'),
        get_string('debugdb_desc', 'tool_catroledatabase'), 0));

    $settings->add(new admin_setting_configtext('tool_catroledatabase/minrecords',
        get_string('minrecords', 'tool_catroledatabase'),
        get_string('minrecords_desc', 'tool_catroledatabase'), 1));

    $settings->add(new admin_setting_heading('tool_catroledatabase_localheader',
        get_string('settingsheaderlocal', 'tool_catroledatabase'), ''));

    // Get all roles that can be assigned at the user context level and put their id's nicely into the configuration.
    $roleids = get_roles_for_contextlevels(CONTEXT_COURSECAT);
    list($insql, $inparams) = $DB->get_in_or_equal($roleids);
    $sql = "SELECT * FROM {role} WHERE id $insql";
    $roles = $DB->get_records_sql($sql, $inparams);
    $i = 1;
    foreach ($roles as $role) {
        $roleid[$i] = $role->id;
        $rolename[$i] = $role->shortname;
        $i++;
    }
    $rolenames = array_combine($roleid, $rolename);
    $settings->add(new admin_setting_configmultiselect('tool_catroledatabase/role',
        get_string('syncroles', 'tool_catroledatabase'), '', array_keys($rolenames), $rolenames));

    $options = array('id' => 'id', 'idnumber' => 'idnumber', 'email' => 'email', 'username' => 'username');
    $settings->add(new admin_setting_configselect('tool_catroledatabase/localuserfield',
        get_string('localuserfield', 'tool_catroledatabase'), '', 'idnumber', $options));

    $settings->add(new admin_setting_heading('tool_catroledatabase_remoteheader',
        get_string('settingsheaderremote', 'tool_catroledatabase'), ''));

    $settings->add(new admin_setting_configtext('tool_catroledatabase/remotetable',
        get_string('remotetable', 'tool_catroledatabase'),
        get_string('remotetable_desc', 'tool_catroledatabase'), ''));

    //e.g. 43563
    $settings->add(new admin_setting_configtext('tool_catroledatabase/userfield',
        get_string('userfield', 'tool_catroledatabase'),
        get_string('userfield_desc', 'tool_catroledatabase'), ''));

    // E.g. DAT
    $settings->add(new admin_setting_configtext('tool_catroledatabase/idnumberfield',
        get_string('idnumberfield', 'tool_catroledatabase'),
        get_string('idnumberfield_desc', 'tool_catroledatabase'), ''));

    // E.g. Role shortname
    $settings->add(new admin_setting_configtext('tool_catroledatabase/rolefield',
        get_string('rolefield', 'tool_catroledatabase'),
        get_string('rolefield_desc', 'tool_catroledatabase'), ''));

    $options = array(0  => get_string('removeroleassignment', 'tool_catroledatabase'),
                     1  => get_string('keeproleassignment', 'tool_catroledatabase'));
    $settings->add(new admin_setting_configselect('tool_catroledatabase/removeaction',
        get_string('removedaction', 'tool_catroledatabase'),
        get_string('removedaction_desc', 'tool_catroledatabase'), 0, $options));

}
