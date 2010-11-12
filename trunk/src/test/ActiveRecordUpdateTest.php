<?php
require_once 'simpletestconfig.php';

class ActiveRecordUpdateTest extends UnitTestCase {
    
    public function testUpdate() {
        
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
        $result = $j2->update(array('Age__c'));
        $this->assertTrue($result);
        
        $j3 = new Sobject_Jyugyoin__c();
        $j3 =$j3->eq('Id', $j2->Id)->find();
        $this->assertEqual($j3->Age__c, "");
    }
    
    public function testFieldNull() {
        
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
        $result = $j2->fieldnull('Age__c')->update();
        $this->assertTrue($result);
        
        $j3 = new Sobject_Jyugyoin__c();
        $j3 =$j3->eq('Id', $j2->Id)->find();
        $this->assertEqual($j3->Age__c, "");
        $this->assertEqual($j3->Name, "加藤");
    }
    
    public function testUpdateEntity() {
        
        $j = new Sobject_Jyugyoin__c();
        $j->Name = "Bob".time();
        $j->JyugyoinBango__c = substr(time(), 5);
        $j->Age__c = 30;
        $result = $j->insert();
        $this->assertTrue($result);
        $this->assertNotNull($j->Id);
        
        $j2 = Sobject_Jyugyoin__c::get()->eq('Id', $j->Id)->find();
        $j2->Name = "Cathy";
        $j2->Age__c = "";
        $result = $j2->updateEntity();
        $this->assertTrue($result);
        if ($result == FALSE) {
            print_r($j2->getErrors());
            return;
        }
        
        $j3 = Sobject_Jyugyoin__c::get()->eq('Id', $j->Id)->find();
        $this->assertEqual($j3->Age__c, "");
        $this->assertEqual($j3->Name, "Cathy");
    }
    
}

?>