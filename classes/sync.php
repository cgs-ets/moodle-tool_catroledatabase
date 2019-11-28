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
 * Category role database sync plugin
 *
 * This plugin synchronises category roles with an external database table.
 *
 * @package   tool_catroledatabase
 * @copyright 2019 Michael Vangelovski, Canberra Grammar School <michael.vangelovski@cgs.act.edu.au>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * catroledatabase tool class
 *
 * @package   tool_catroledatabase
 * @copyright 2019 Michael Vangelovski, Canberra Grammar School <michael.vangelovski@cgs.act.edu.au>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_catroledatabase_sync {
    /**
     * @var stdClass config for this plugin
     */
    protected $config;

    /**
     * @var array The current groups.
     */
    protected $roleassignments = [];

    /**
     * Performs a full sync with external database.
     *
     * @param progress_trace $trace
     * @return int 0 means success, 1 db connect failure, 4 db read failure
     */
    public function sync(progress_trace $trace) {
        global $DB;

        $this->config = get_config('tool_catroledatabase');

        // Check if it is configured.
        if (empty($this->config->dbtype) || empty($this->config->dbhost)) {
            $trace->finished();
            return 1;
        }

        $trace->output('Starting category role synchronisation...');

        // We may need a lot of memory here.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        // Set some vars for better code readability.
        $catroletable       = trim($this->config->remotetable);
        $localuserfield     = trim($this->config->localuserfield);

        $userfield          = strtolower(trim($this->config->userfield));
        $idnumberfield      = strtolower(trim($this->config->idnumberfield));
        $rolefield          = strtolower(trim($this->config->rolefield));
        $removeaction       = trim($this->config->removeaction); // 0 = remove, 1 = keep.
        // Get the roles we're going to sync.
        $syncroles = $this->config->syncroles;

        if (empty($catroletable) || empty($localuserfield) || empty($userfield) ||
            empty($idnumberfield) || empty($rolefield) || empty($syncroles)) {
            $trace->output('Plugin config not complete.');
            $trace->finished();
            return 1;
        }

        if (!$extdb = $this->db_init()) {
            $trace->output('Error while communicating with external database');
            $trace->finished();
            return 1;
        }

        // Sanity check - make sure external table has the expected number of records before we trigger the sync.
        $hasenoughrecords = false;
        $count = 0;
        $minrecords = $this->config->minrecords;
        if (!empty($minrecords)) {
            $sql = "SELECT count(*) FROM $catroletable";
            if ($rs = $extdb->Execute($sql)) {
                if (!$rs->EOF) {
                    while ($fields = $rs->FetchRow()) {
                        $count = array_pop($fields);
                        if ($count > $minrecords) {
                            $hasenoughrecords = true;
                        }
                    }
                }
            }
        }
        if (!$hasenoughrecords) {
            $trace->output("Failed to sync because the external db returned $count records and the minimum
                required is $minrecords");
            $trace->finished();
            return 1;
        }

        // Get list of current category role assignments.
        $trace->output('Indexing current role assignments');
        list($rolesql, $params) = $DB->get_in_or_equal($syncroles);
        $sql = "SELECT ra.userid as userid, c.instanceid as catid, ra.roleid
                  FROM {role_assignments} ra
            INNER JOIN {context} c ON ra.contextid = c.id
                 WHERE ra.roleid $rolesql
                   AND c.contextlevel = ".CONTEXT_COURSECAT;
        $rs = $DB->get_recordset_sql($sql, $params);

        // Cache the role assignments in an associative array.
        foreach ($rs as $row) {
            $this->roleassignments[$row->catid][$row->userid][$row->roleid] = $row->userid;
        }
        $rs->close();

        // Get records from the external database and assign roles.
        $trace->output('Starting database sync');
        $sql = $this->db_get_sql($catroletable);
        if ($rs = $extdb->Execute($sql)) {
            if (!$rs->EOF) {
                while ($fields = $rs->FetchRow()) {
                    $fields = array_change_key_case($fields, CASE_LOWER);
                    $fields = $this->db_decode($fields);
                    $fields[$userfield] = trim($fields[$userfield]);
                    $fields[$idnumberfield] = trim($fields[$idnumberfield]);
                    $fields[$rolefield] = trim($fields[$rolefield]);

                    if (empty($fields[$userfield]) || 
                        empty($fields[$idnumberfield]) || 
                        empty($fields[$rolefield]) ) {
                        $trace->output('error: invalid external record, missing mandatory fields: '
                            . json_encode($fields), 1);
                        continue;
                    }

                    $rowdesc = $fields[$idnumberfield] . " => " . $fields[$userid] . " => " . $fields[$rolefield];

                    $rolesearch['shortname'] = $fields[$rolefield];
                    if (!$role = $DB->get_record('role', $rolesearch, 'id', IGNORE_MULTIPLE)) {
                        $err = "error: skipping '$rowdesc' due to unknown role shortname '$fields[$rolefield]'";
                        $trace->output($err, 1);
                        continue;
                    }

                    $catsearch['idnumber'] = $fields[$idnumberfield];
                    if (!$category = $DB->get_record('course_categories', $catsearch, 'id', IGNORE_MULTIPLE)) {
                        $err = "error: skipping '$rowdesc' due to unknown category idnumber '$fields[$idnumberfield]'";
                        $trace->output($err, 1);
                        continue;
                    }

                    $usersearch[$localuserfield] = $fields[$userfield];
                    if (!$user = $DB->get_record('user', $usersearch, 'id', IGNORE_MULTIPLE)) {
                        $err = "error: skipping '$rowdesc' due to unknown user $localuserfield '$fields[$userfield]'";
                        $trace->output($err, 1);
                        continue;
                    }

                    if (isset($this->roleassignments[$category->id][$user->id][$role->id])) {
                        // This role already exists.
                        $trace->output("Category role already assigned: $rowdesc");
                        unset($this->roleassignments[$category->id][$user->id][$role->id]);
                    } else {
                        // Create the role.
                        $trace->output("Assigning category role: $rowdesc");
                        $catcontext = context_coursecat::instance($category->id);
                        role_assign($role->id, $user->id, $catcontext->id);
                    }
                }
            }
        }
        $extdb->Close();

        if (empty($removeaction) && !empty($this->roleassignments)) {
            // Unassign remaining category roles.
            $trace->output('Unassigning removed category roles');
            foreach ($this->roleassignments as $catid => $user) {
                foreach ($user as $userid => $roleid) {
                    $rowdesc = $catid . " => " . $userid . " => " . $roleid;
                    $trace->output("Unassigning: $rowdesc");
                    $catcontext = context_coursecat::instance($catid);
                    role_unassign($roleid, $userid, $catcontext->id);
                }
            }
        }

        $trace->finished();

        return 0;
    }

    /**
     * Test plugin settings, print info to output.
     */
    public function test_settings() {
        global $CFG, $OUTPUT;

        // NOTE: this is not localised intentionally, admins are supposed to understand English at least a bit...

        raise_memory_limit(MEMORY_HUGE);

        $this->config = get_config('tool_catroledatabase');

        $catroletable = $this->config->remotetable;

        if (empty($catroletable)) {
            echo $OUTPUT->notification('External table not specified.', 'notifyproblem');
            return;
        }

        $olddebug = $CFG->debug;
        $olddisplay = ini_get('display_errors');
        ini_set('display_errors', '1');
        $CFG->debug = DEBUG_DEVELOPER;
        $olddebugdb = $this->config->debugdb;
        $this->config->debugdb = 1;
        error_reporting($CFG->debug);

        $adodb = $this->db_init();

        if (!$adodb or !$adodb->IsConnected()) {
            $this->config->debugdb = $olddebugdb;
            $CFG->debug = $olddebug;
            ini_set('display_errors', $olddisplay);
            error_reporting($CFG->debug);
            ob_end_flush();

            echo $OUTPUT->notification('Cannot connect the database.', 'notifyproblem');
            return;
        }

        if (!empty($catroletable)) {
            $rs = $adodb->Execute("SELECT *
                                     FROM $catroletable");
            if (!$rs) {
                echo $OUTPUT->notification('Can not read external table.', 'notifyproblem');

            } else if ($rs->EOF) {
                echo $OUTPUT->notification('External table is empty.', 'notifyproblem');
                $rs->Close();

            } else {
                $fieldsobj = $rs->FetchObj();
                $columns = array_keys((array)$fieldsobj);

                echo $OUTPUT->notification('External table contains following columns:<br />'.
                    implode(', ', $columns), 'notifysuccess');
                $rs->Close();
            }
        }

        $adodb->Close();

        $this->config->debugdb = $olddebugdb;
        $CFG->debug = $olddebug;
        ini_set('display_errors', $olddisplay);
        error_reporting($CFG->debug);
        ob_end_flush();
    }

    /**
     * Tries to make connection to the external database.
     *
     * @return null|ADONewConnection
     */
    public function db_init() {
        global $CFG;

        require_once($CFG->libdir.'/adodb/adodb.inc.php');

        // Connect to the external database (forcing new connection).
        $extdb = ADONewConnection($this->config->dbtype);
        if ($this->config->debugdb) {
            $extdb->debug = true;
            ob_start(); // Start output buffer to allow later use of the page headers.
        }

        // The dbtype my contain the new connection URL, so make sure we are not connected yet.
        if (!$extdb->IsConnected()) {
            $result = $extdb->Connect($this->config->dbhost, $this->config->dbuser, $this->config->dbpass,
                $this->config->dbname, true);
            if (!$result) {
                return null;
            }
        }

        $extdb->SetFetchMode(ADODB_FETCH_ASSOC);
        if ($this->config->dbsetupsql) {
            $extdb->Execute($this->config->dbsetupsql);
        }
        return $extdb;
    }

    /**
     * Encode text.
     *
     * @param string $text
     * @return string
     */
    protected function db_encode($text) {
        $dbenc = $this->config->dbencoding;
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach ($text as $k => $value) {
                $text[$k] = $this->db_encode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, 'utf-8', $dbenc);
        }
    }

    /**
     * Decode text.
     *
     * @param string $text
     * @return string
     */
    protected function db_decode($text) {
        $dbenc = $this->config->dbencoding;
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach ($text as $k => $value) {
                $text[$k] = $this->db_decode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, $dbenc, 'utf-8');
        }
    }

    /**
     * Generate SQL required based on params.
     *
     * @param string $table - name of table
     * @param array $conditions - conditions for select.
     * @param array $fields - fields to return
     * @param boolean $distinct
     * @param string $sort
     * @return string
     */
    protected function db_get_sql($table, $conditions = array(), $fields = array(), $distinct = false, $sort = "") {
        $fields = $fields ? implode(',', $fields) : "*";
        $where = array();
        if ($conditions) {
            foreach ($conditions as $key => $value) {
                $value = $this->db_encode($this->db_addslashes($value));

                $where[] = "$key = '$value'";
            }
        }
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";
        $sort = $sort ? "ORDER BY $sort" : "";
        $distinct = $distinct ? "DISTINCT" : "";
        $sql = "SELECT $distinct $fields
                  FROM $table
                 $where
                  $sort";

        return $sql;
    }

    /**
     * Add slashes to text.
     *
     * @param string $text
     * @return string
     */
    protected function db_addslashes($text) {
        // Use custom made function for now - it is better to not rely on adodb or php defaults.
        if ($this->config->dbsybasequoting) {
            $text = str_replace('\\', '\\\\', $text);
            $text = str_replace(array('\'', '"', "\0"), array('\\\'', '\\"', '\\0'), $text);
        } else {
            $text = str_replace("'", "''", $text);
        }
        return $text;
    }
}



