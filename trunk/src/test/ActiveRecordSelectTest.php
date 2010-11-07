<?php 

require_once 'simpletestconfig.php';

class ActiveRecordSelectTest extends UnitTestCase {
    
    public function testSelectClause() {
        $account = new Sobject_Account();
        $account->dryrun(TRUE);
        
        // simple
        $query = $account->starts('Name', 'G')->select();
        $expected = "SELECT Account.Id, Account.IsDeleted, Account.MasterRecordId, Account.Name, Account.Type, Account.ParentId, Account.BillingStreet, Account.BillingCity, Account.BillingState, Account.BillingPostalCode, Account.BillingCountry, Account.ShippingStreet, Account.ShippingCity, Account.ShippingState, Account.ShippingPostalCode, Account.ShippingCountry, Account.Phone, Account.Fax, Account.AccountNumber, Account.Website, Account.Sic, Account.Industry, Account.AnnualRevenue, Account.NumberOfEmployees, Account.Ownership, Account.TickerSymbol, Account.Description, Account.Rating, Account.Site, Account.OwnerId, Account.CreatedDate, Account.CreatedById, Account.LastModifiedDate, Account.LastModifiedById, Account.SystemModstamp, Account.LastActivityDate, Account.CustomerPriority__c, Account.SLA__c, Account.Active__c, Account.NumberofLocations__c, Account.UpsellOpportunity__c, Account.SLASerialNumber__c, Account.SLAExpirationDate__c, Account.email__c, Account.serviceName__c FROM Account WHERE (Name LIKE 'G%')";
        $this->assertEqual(trim($query), $expected);
        
        // specify columns
        $query = $account->ends('Name', 'G')->select('Id, Name');
        $expected = "SELECT Account.Id, Account.Name FROM Account WHERE (Name LIKE '%G')";
        $this->assertEqual(trim($query), $expected);
        
        // parent relationship
        $query = $account
            ->join('Owner')
            ->select('Name');
        $expected = "SELECT Account.Name, Account.Owner.Id, Account.Owner.Username, Account.Owner.LastName, Account.Owner.FirstName, Account.Owner.Name, Account.Owner.CompanyName, Account.Owner.Division, Account.Owner.Department, Account.Owner.Title, Account.Owner.Street, Account.Owner.City, Account.Owner.State, Account.Owner.PostalCode, Account.Owner.Country, Account.Owner.Email, Account.Owner.Phone, Account.Owner.Fax, Account.Owner.MobilePhone, Account.Owner.Alias, Account.Owner.CommunityNickname, Account.Owner.IsActive, Account.Owner.TimeZoneSidKey, Account.Owner.UserRoleId, Account.Owner.LocaleSidKey, Account.Owner.ReceivesInfoEmails, Account.Owner.ReceivesAdminInfoEmails, Account.Owner.EmailEncodingKey, Account.Owner.ProfileId, Account.Owner.UserType, Account.Owner.LanguageLocaleKey, Account.Owner.EmployeeNumber, Account.Owner.DelegatedApproverId, Account.Owner.ManagerId, Account.Owner.LastLoginDate, Account.Owner.LastPasswordChangeDate, Account.Owner.CreatedDate, Account.Owner.CreatedById, Account.Owner.LastModifiedDate, Account.Owner.LastModifiedById, Account.Owner.SystemModstamp, Account.Owner.OfflineTrialExpirationDate, Account.Owner.OfflinePdaTrialExpirationDate, Account.Owner.UserPermissionsMarketingUser, Account.Owner.UserPermissionsOfflineUser, Account.Owner.UserPermissionsCallCenterAutoLogin, Account.Owner.UserPermissionsMobileUser, Account.Owner.UserPermissionsSupportUser, Account.Owner.ForecastEnabled, Account.Owner.UserPreferencesActivityRemindersPopup, Account.Owner.UserPreferencesEventRemindersCheckboxDefault, Account.Owner.UserPreferencesTaskRemindersCheckboxDefault, Account.Owner.UserPreferencesReminderSoundOff, Account.Owner.UserPreferencesApexPagesDeveloperMode, Account.Owner.ContactId, Account.Owner.AccountId, Account.Owner.CallCenterId, Account.Owner.Extension, Account.Owner.FederationIdentifier FROM Account";
        $this->assertEqual(trim($query), $expected);
        
        // specify columns of parent
        $query = $account
            ->join('Owner', 'Id, Username')
            ->select('Name');
        $expected = "SELECT Account.Name, Account.Owner.Id, Account.Owner.Username FROM Account";
        $this->assertEqual(trim($query), $expected);
        
        // parent criteria
        $query = $account
            ->join('Owner', 'Id, Username')
            ->contains('Name', 'G')
            ->eq('Owner.Username', 'sato')
            ->select('Name');
        $expected = "SELECT Account.Name, Account.Owner.Id, Account.Owner.Username FROM Account WHERE (Name LIKE '%G%') AND (Owner.Username = 'sato')";
        $this->assertEqual(trim($query), $expected);
        
        // child relationship
        $query = $account
            ->child('Notes')
            ->select('Name');
        $expected = "SELECT Account.Name, (SELECT Id, IsDeleted, ParentId, Title, IsPrivate, Body, OwnerId, CreatedDate, CreatedById, LastModifiedDate, LastModifiedById, SystemModstamp FROM Notes) FROM Account";
        $this->assertEqual(trim($query), $expected);
        
        // specify columns of child
        $query = $account
            ->child('Notes', 'Id, Title')
            ->select('Name');
        $expected = "SELECT Account.Name, (SELECT Id, Title FROM Notes) FROM Account";
        $this->assertEqual(trim($query), $expected);
        
        // conbination of parent, child
        $query = $account
            ->join('Owner', 'Id, Username')
            ->child('Notes', 'Id, Title')
            ->select('Name');
        $expected = "SELECT Account.Name, Account.Owner.Id, Account.Owner.Username, (SELECT Id, Title FROM Notes) FROM Account";
        $this->assertEqual(trim($query), $expected);
        //print "#{$query}#";
        
    }
    
    public function testResultSet() {
        
        try {
        $account = new Sobject_Account();
        $result = $account
            ->join('Owner','Id, Username')
            ->join('CreatedBy','Username')
            ->child('Contacts', 'Id, Name')
            ->child('Cases', 'Id, Reason')
            ->like('Name', 'G%')
            ->select('Id, Name');
        //print("<pre>");
        //var_dump($result);
        //print("</pre>");
        //SELECT Account.Id, Account.Name, Account.Owner.Id, Account.Owner.Username, Account.CreatedBy.Username, (SELECT Id, name FROM Contacts), (SELECT Id, reason FROM Cases) FROM Account WHERE Name LIKE 'G%'";
        
        $this->assertEqual(count($result), 3);
        $this->assertEqual($result[0]->Id, '0018000000UoDxpAAF');
        $this->assertEqual($result[0]->Name, 'GenePoint');
        $this->assertEqual($result[0]->Owner->Username, 'miztaka@honestyworks.jp');
        $this->assertEqual($result[0]->Owner->Id, '00580000002fHOEAA2');
        $this->assertEqual($result[0]->CreatedBy->Username, 'miztaka@honestyworks.jp');
        $this->assertEqual(count($result[0]->Contacts), 1);
        $this->assertEqual($result[0]->Contacts[0]->Name, 'Frank Edna');
        $this->assertEqual($result[0]->Contacts[0]->Id, '0038000000gVvdvAAC');
        $this->assertEqual(count($result[0]->Cases), 2);
        $this->assertEqual($result[0]->Cases[0]->Reason, 'Feedback');
        $this->assertEqual($result[0]->Cases[0]->Id, '500800000075hBuAAI');
        $this->assertEqual($result[0]->Cases[1]->Reason, 'Feedback');
        $this->assertEqual($result[0]->Cases[1]->Id, '500800000075hC4AAI');
        } catch (Exception $e) {
            $this->fail($e->getTraceAsString());
        }
        
    }
    
    public function testNestedParent() {
        $case = new Sobject_Case();
        //$case->dryrun(true);
        $result = $case
            ->join('Contact', 'LastName, FirstName')
            ->join('Contact.Account', 'Name')
            ->starts('Contact.Account.Name', 'G')
            ->order('CaseNumber')
            ->select('CaseNumber, Subject');
        //$expected = "SELECT Case.CaseNumber, Case.Subject, Case.Contact.LastName, Case.Contact.FirstName, Case.Contact.Account.Name FROM Case WHERE (Contact.Account.Name LIKE 'G%') ORDER BY CaseNumber";
        //$this->assertEqual(trim($result), $expected); 
        $this->assertEqual(count($result), 6);
        $this->assertEqual('00001006', $result[0]->CaseNumber);
        $this->assertEqual('Frank', $result[0]->Contact->LastName);
        $this->assertEqual('GenePoint', $result[0]->Contact->Account->Name);
    }
    
    public function testNestedParentAllColumn() {
        $case = new Sobject_Case();
        //$case->dryrun(true);
        $result = $case
            ->join('Contact')
            ->join('Contact.Account')
            ->starts('Contact.Account.Name', 'G')
            ->order('CaseNumber')
            ->select();
        //$expected = "SELECT Case.CaseNumber, Case.Subject, Case.Contact.LastName, Case.Contact.FirstName, Case.Contact.Account.Name FROM Case WHERE (Contact.Account.Name LIKE 'G%') ORDER BY CaseNumber";
        //$this->assertEqual(trim($result), $expected); 
        $this->assertEqual(count($result), 6);
        $this->assertEqual('00001006', $result[0]->CaseNumber);
        $this->assertEqual('Frank', $result[0]->Contact->LastName);
        $this->assertEqual('GenePoint', $result[0]->Contact->Account->Name);
    }
    
}

?>