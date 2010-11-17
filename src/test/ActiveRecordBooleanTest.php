<?php
require_once 'simpletestconfig.php';

class ActiveRecordBooleanTest extends UnitTestCase {
    
    public function testBooleanField() {
        
        $j = new Sobject_Jyugyoin__c();
        $j->Name = "佐藤".time();
        $j->JyugyoinBango__c = substr(time(), 5);
        $j->Age__c = 30;
        $j->health__c = TRUE;
        
        $result = $j->insert();
        $this->assertTrue($result);
        $this->assertNotNull($j->Id);
        
        $check = Sobject_Jyugyoin__c::neu()->find($j->Id);
        $this->assertTrue(is_bool($check->health__c));
        $this->assertTrue($check->health__c);
        
        $check = Sobject_Jyugyoin__c::neu()
            ->eq('health__c', TRUE)
            ->eq('Id', $j->Id)
            ->find();
        $this->assertTrue($check->health__c);
        
        $check->health__c = FALSE;
        $result = $check->updateEntity();
        $this->assertTrue($result);
        
        $check = Sobject_Jyugyoin__c::neu()->find($j->Id);
        $this->assertTrue(is_bool($check->health__c));
        $this->assertFalse($check->health__c);
    }
    
}

?>