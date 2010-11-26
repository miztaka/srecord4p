<?php 

require_once dirname(dirname(__FILE__)).'/lib/config.php';

$eg = new SRecord_Generator();
$eg->execute();

?>