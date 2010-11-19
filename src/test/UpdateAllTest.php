<?php
require_once 'simpletestconfig.php';

class UpdateAllTest extends UnitTestCase {
    
    public function testUpdateAll() {
        
        $j = new Sobject_Jyugyoin__c();
        $j->Name = "佐藤".time();
        $j->JyugyoinBango__c = substr(time(), 5);
        $j->Age__c = 30;
        $result = $j->insert();
        $this->assertTrue($result);
        $this->assertNotNull($j->Id);
        
        $j2 = new Sobject_Jyugyoin__c();
        $j2->Id = $j->Id;
        $j2->Name = "加藤";
        $j2->fieldnull('Age__c');
        
        $j3 = new Sobject_Jyugyoin__c();
        $j3->Name = "Kato";
        $j3->Id = '12345600';
        
        Srecord_Schema::updateAll(array($j2, $j3));
        $this->assertEqual($j2->getState(), Srecord_ActiveRecord::STATE_SUCCESS);
        
        $j4 = Sobject_Jyugyoin__c::neu()->find($j2->Id);
        $this->assertEqual($j4->Age__c, "");
        $this->assertEqual($j4->Name, "加藤");
        
        $this->assertEqual($j3->getState(), SRecord_ActiveRecord::STATE_FAIL);
        $errors = $j3->getErrors();
        print_r($errors);
    }
    
}

?>