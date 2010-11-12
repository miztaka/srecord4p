<?php
require_once 'simpletestconfig.php';

class ActiveRecordOtherTest extends UnitTestCase {
    
    public function testGetMeta() {
        $j = new Sobject_Jyugyoin__c();
        $meta = $j->getMeta();
        print("first get.\n");
        
        $meta = $j->getMeta();
        print("second get.\n");
        
        $j2 = new Sobject_Jyugyoin__c();
        $meta = $j2->getMeta();
        print("another instance.\n");
        
        $j3 = new Sobject_Account();
        $meta = $j3->getMeta();
        print("another class.\n");
        
    }
    
}

?>