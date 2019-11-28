# Category role external database sync tool for Moodle
[![Build Status](https://travis-ci.org/cgs-ets/moodle-tool_catroledatabase.svg?branch=master)](https://travis-ci.org/cgs-ets/moodle-tool_catroledatabase)

This plugin syncs category role assignments using an external database table.

Author
--------
Michael Vangelovski, Canberra Grammar School <michael.vangelovski@cgs.act.edu.au>


Features
--------
* Can be triggered via CLI and/or scheduled task.
* Syncronises category role assignments.


Installation
------------

1. The plugin assumes that a parent/mentor role has been configured on your Moodle instance as defined here: https://docs.moodle.org/en/Parent_role

2. Download the plugin or install it by using git to clone it into your source:

   ```sh
   git clone git@github.com:cgs-ets/moodle-tool_catroledatabase.git admin/tool/catroledatabase
   ```

3. Then run the Moodle upgrade


Support
-------

If you have issues please log them in github here

https://github.com/cgs-ets/moodle-tool_catroledatabase/issues

