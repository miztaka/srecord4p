<?php
require_once 'simpletestconfig.php';

class CreateAllTest extends UnitTestCase {
    
    public function testCreateAll() {
        
        $j = new Sobject_Jyugyoin__c();
        $j->Name = "佐藤".time();
        
        $j2 = new Sobject_Jyugyoin__c();
        $j2->Name = "Sato".time();
        $j2->JyugyoinBango__c = substr(time(), 5);
        
        // fail.
        Srecord_Schema::createAll(array($j, $j2));
        
        $this->assertEqual($j->getState(), Srecord_ActiveRecord::STATE_FAIL);
        $this->assertEqual($j->getErrors()->statusCode, "REQUIRED_FIELD_MISSING");
        $this->assertEqual($j->Id, "");
        
        $this->assertEqual($j2->getState(), Srecord_ActiveRecord::STATE_SUCCESS);
        $this->assertTrue(strlen($j2->Id) > 1);
        print $j2->Id;
    }
    
}

?>