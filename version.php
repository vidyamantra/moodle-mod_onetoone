<?php

////////////////////////////////////////////////////////////////////////////////
//  Code fragment to define the module version etc.
//  This fragment is called by /admin/index.php
////////////////////////////////////////////////////////////////////////////////

$module->version   = 2014021300;  // use minor version bumps until 2013 then use YYYYMMDDxx
$module->requires  = 2013111802;  // Requires this Moodle version
$module->release   = '2.6.1 (20140207)'; // User-friendly version number
$module->component = 'mod_onetoone';
$module->maturity  = MATURITY_STABLE;
$module->cron      = 60;
$module->dependencies = array('local_getkey' => ANY_VERSION); 
