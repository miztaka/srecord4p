<?php
require_once 'simpletestconfig.php';

class DeleteAllTest extends UnitTestCase {
    
    public function testDeleteAll() {
        
        $list = Sobject_Jyugyoin__c::neu()->limit(3)->select();
        Srecord_Schema::deleteAll($list);
        $this->assertEqual($list[0]->getState(), Srecord_ActiveRecord::STATE_SUCCESS);
        $this->assertEqual($list[1]->getState(), Srecord_ActiveRecord::STATE_SUCCESS);
        $this->assertEqual($list[2]->getState(), Srecord_ActiveRecord::STATE_SUCCESS);
        
        print(" 0: ".$list[0]->Id);
        print(" 1: ".$list[1]->Id);
        print(" 2: ".$list[2]->Id);
        
        array_pop($list);
        Srecord_Schema::undeleteAll($list);
        $this->assertEqual($list[0]->getState(), Srecord_ActiveRecord::STATE_SUCCESS);
        $this->assertEqual($list[1]->getState(), Srecord_ActiveRecord::STATE_SUCCESS);
    }
    
}

?>