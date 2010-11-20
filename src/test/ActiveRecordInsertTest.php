<?php
require_once 'simpletestconfig.php';

class ActiveRecordInsertTest extends UnitTestCase {
    
    public function testInsert() {
        
        $j = new Sobject_Jyugyoin__c();
        $j->Name = "佐藤".time();
        $result = $j->insert();
        $this->assertFalse($result);
        $errors = $j->getErrors();
        $this->assertEqual($errors->statusCode, "REQUIRED_FIELD_MISSING");
        
        $j->JyugyoinBango__c = substr(time(), 5);
        $result = $j->insert();
        $this->assertTrue($result);
        $this->assertNotNull($j->Id);
        
        $id = $j->Id;
        
        $result = $j->delete();
        $this->assertTrue($result);
        
        $result = $j->undelete();
        $this->assertTrue($result);
    }
    
}

?>