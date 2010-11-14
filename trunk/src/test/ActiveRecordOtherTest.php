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
    
    public function testCopyToThis() {
        
        $value = array(
            'Name' => 'Sato',
            'Id' => '11111111111',
            'JyugyoinBango__c' => '12345'
        );
        
        $j = new Sobject_Jyugyoin__c();
        $j->copyToThis($value);
        
        $this->assertEqual($j->Name, $value['Name']);
        $this->assertEqual($j->Id, $value['Id']);
        $this->assertEqual($j->JyugyoinBango__c, $value['JyugyoinBango__c']);
        
    }
    
    public function testCopyFromThis() {
        
        $account = Sobject_Account::neu()->eq("Id", "0018000000UoDxpAAF")->find();
        $obj = new stdClass();
        $account->copyFromThis($obj);
        
        $this->assertEqual($obj->Name, $account->Name);
    }
    
    public function testEscape() {
        
        $account = new Sobject_Account();
        $account->dryrun(TRUE);
        
        $result = $account->eq('Name', "Orei\\lly")->select();
        print($result);
    }
    
}

?>