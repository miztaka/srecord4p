<?php 
// config file for sRecord - Salesforce Active Record for PHP

//
// autoload
//
function srecord_autoload($name) {
    $lower = strtolower($name);
    foreach (array('srecord','sobject','sobjectdef') as $target) {
        $check = $target."_";
        if (strpos($lower, $check) === 0) {
            $filename = substr($name, strlen($check));
            include_once "{$target}/{$filename}.php";
        }
    }
}

function __autoload($clsname) {
    srecord_autoload($clsname);
}

if (function_exists('spl_autoload_register')) {
    spl_autoload_register('srecord_autoload');
}

//ini_set('unserialize_callback_func', 'srecord_autoload');

//
// definition
//
define("PHPTOOLKIT_DIR", dirname(__FILE__).'/phptoolkit-13_1');
Srecord_Schema::$username = 'salesforceusername';
Srecord_Schema::$password = 'salesforcepassword';
Srecord_Schema::$securityToken = 'salesforcesecuritytoken';
Srecord_Schema::$wsdlPartner = dirname(__FILE__)."/wsdl/partner.wsdl";

?>