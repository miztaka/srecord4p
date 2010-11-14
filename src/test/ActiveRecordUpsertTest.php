<?php
require_once 'simpletestconfig.php';

class ActiveRecordUpsertTest extends UnitTestCase {
    
    public function testUpsert() {
        
        $extid = substr(time(), 5);
        
        // create
        $j = new Sobject_Jyugyoin__c();
        $j->Name = "Test Member".time();
        $j->JyugyoinBango__c = $extid;
        $j->Age__c = 30;
        $result = $j->upsert('JyugyoinBango__c');
        $this->assertTrue($result);
        $this->assertNotNull($j->Id);
        
        // update
        $j2 = new Sobject_Jyugyoin__c();
        $j2->Name = "Test Member 2".time();
        $j2->JyugyoinBango__c = $extid;
        $result = $j2->fieldnull('Age__c')->upsert('JyugyoinBango__c');
        $this->assertTrue($result);
        $this->assertEqual($j->Id, $j2->Id);
        
        $j3 = Sobject_Jyugyoin__c::neu()->eq('Id', $j2->Id)->find();
        $this->assertEqual($j3->Name, $j2->Name);
        $this->assertEqual($j3->Age__c, "");
        
        // delete
        $result = Sobject_Jyugyoin__c::neu()->delete($j2->Id);
        $this->assertTrue($result);
    }
    
}

?>