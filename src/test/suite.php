<?php 

require_once 'simpletestconfig.php';
set_time_limit(3000);

class AllTests extends TestSuite {
    function AllTests() {
        $this->TestSuite('All tests');
        $d = dir(dirname(__FILE__));
        while (FALSE !== ($file = $d->read())) {
            if (preg_match('/Test\.php$/', $file)) {
                $this->addFile(dirname(__FILE__)."/{$file}");
            }
        }
    }
}

?>