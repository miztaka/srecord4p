<?php
require_once 'simpletestconfig.php';

class UpsertAllTest extends UnitTestCase {
    
    public function testUpsert() {
        
        $extid = substr(time(), 5);
        
        // create
        $j = new Sobject_Jyugyoin__c();
        $j->Name = "Test Member".time();
        $j->JyugyoinBango__c = $extid;
        $j->Age__c = 30;
        
        Srecord_Schema::upsertAll('JyugyoinBango__c', array($j));
        $this->assertEqual($j->getState(), Srecord_ActiveRecord::STATE_SUCCESS);
        $this->assertTrue(strlen($j->Id) > 1);
    }
    
}

?>