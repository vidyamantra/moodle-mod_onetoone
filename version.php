<?php

////////////////////////////////////////////////////////////////////////////////
//  Code fragment to define the module version etc.
//  This fragment is called by /admin/index.php
////////////////////////////////////////////////////////////////////////////////

$plugin->version   = 2014021300;  // use minor version bumps until 2013 then use YYYYMMDDxx
$plugin->requires  = 2013111800.00;  // Requires this Moodle version
$plugin->release   = '1.0.0 (20140625)'; // User-friendly version number
$plugin->component = 'mod_onetoone';
$plugin->maturity  = MATURITY_STABLE;
$plugin->cron      = 60;
$plugin->dependencies = array('local_getkey' => ANY_VERSION); 
