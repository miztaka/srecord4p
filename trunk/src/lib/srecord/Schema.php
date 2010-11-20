<?php 

require_once PHPTOOLKIT_DIR."/soapclient/SforcePartnerClient.php";

class Srecord_Schema
{
    public static $username = '';
    public static $password = '';
    public static $securityToken = '';
    public static $wsdlPartner = '';
    
    const SESSION_KEY = '__Srecord_Schema';
    
    /**
     * connect to salesforce, get sforceClient
     * @return SforcePartnerClient
     */
    public static function getClient()
    {
        if (self::$_sForceClient == NULL) {
            
            $sforceClient = new SforcePartnerClient(); // TODO now only partner client supported. 
            $soapClient = $sforceClient->createConnection(self::$wsdlPartner);
            if (PHP_SAPI != 'cli') {
                @session_start();
                if (isset($_SESSION[self::SESSION_KEY])) {
                    $conf = $_SESSION[self::SESSION_KEY];
                    $sforceClient->setEndpoint($conf->location);
                    $sforceClient->setSessionHeader($conf->sessionId);
                } else {
                    $sforceLogin = $sforceClient->login(self::$username, self::$password.self::$securityToken);
                    $conf = new stdClass();
                    $conf->location = $sforceClient->getLocation();
                    $conf->sessionId = $sforceClient->getSessionId();
                    $_SESSION[self::SESSION_KEY] = $conf;
                }
            } else {
                $sforceLogin = $sforceClient->login(self::$username, self::$password.self::$securityToken);
            }
            self::$_sForceClient = $sforceClient;
        }
        return self::$_sForceClient; 
    }
    protected static $_sForceClient = NULL;
    
    /**
     * create all sobjects
     * @param array $sobjects array of Srecord_ActiveRecord
     */
    public static function createAll($records)
    {
        $sObjects = array();
        foreach ($records as $obj) {
            $dryrun = $obj->dryrun();
            $obj->dryrun(TRUE);
            $so = $obj->insert();
            $sObjects[] = $so;
            $obj->dryrun($dryrun);
        }
        
        $client = self::getClient();
        $results = $client->create($sObjects);
        if (! is_array($results)) {
            $results = array($results);
        }
        for($i=0; $i<count($results); $i++) {
            if ($results[$i]->success == 1) {
                $records[$i]->Id = $results[$i]->id;
                $records[$i]->setState(Srecord_ActiveRecord::STATE_SUCCESS);
                $records[$i]->setErrors(NULL);
            } else {
                $records[$i]->setState(Srecord_ActiveRecord::STATE_FAIL);
                $records[$i]->setErrors($results[$i]->errors);
            }
        }
        return;
    }
    
    /**
     * update all sobjects
     * @param array $sobjects array of Srecord_ActiveRecord
     */
    public static function updateAll($records)
    {
        $sObjects = array();
        foreach ($records as $obj) {
            $dryrun = $obj->dryrun();
            $obj->dryrun(TRUE);
            $so = $obj->update();
            $sObjects[] = $so;
            $obj->dryrun($dryrun);
        }
        
        $client = self::getClient();
        $results = $client->update($sObjects);
        if (! is_array($results)) {
            $results = array($results);
        }
        for($i=0; $i<count($results); $i++) {
            if ($results[$i]->success == 1) {
                $records[$i]->setState(Srecord_ActiveRecord::STATE_SUCCESS);
                $records[$i]->setErrors(NULL);
            } else {
                $records[$i]->setState(Srecord_ActiveRecord::STATE_FAIL);
                $records[$i]->setErrors($results[$i]->errors);
            }
        }
        return;
    }
    
    /**
     * upsert all sobjects
     * @param string $externalIDFieldName
     * @param array $sobjects array of Srecord_ActiveRecord
     */
    public static function upsertAll($externalIDFieldName, $records)
    {
        $sObjects = array();
        foreach ($records as $obj) {
            $dryrun = $obj->dryrun();
            $obj->dryrun(TRUE);
            $so = $obj->upsert($externalIDFieldName);
            $sObjects[] = $so;
            $obj->dryrun($dryrun);
        }
        
        $client = self::getClient();
        $results = $client->upsert($externalIDFieldName, $sObjects);
        if (! is_array($results)) {
            $results = array($results);
        }
        for($i=0; $i<count($results); $i++) {
            if ($results[$i]->success == 1) {
                if (isset($results[$i]->id)) {
                    $records[$i]->Id = $results[$i]->id;
                }
                $records[$i]->setState(Srecord_ActiveRecord::STATE_SUCCESS);
                $records[$i]->setErrors(NULL);
            } else {
                $records[$i]->setState(Srecord_ActiveRecord::STATE_FAIL);
                $records[$i]->setErrors($results[$i]->errors);
            }
        }
        return;
    }

    /**
     * delete all sobjects
     * Id must be set.
     * @param array $sobjects array of Srecord_ActiveRecord
     */
    public static function deleteAll($records)
    {
        $ids = array();
        foreach ($records as $obj) {
            $ids[] = $obj->Id;
        }
        
        $client = self::getClient();
        $results = $client->delete($ids);
        if (! is_array($results)) {
            $results = array($results);
        }
        for($i=0; $i<count($results); $i++) {
            if ($results[$i]->success == 1) {
                $records[$i]->setState(Srecord_ActiveRecord::STATE_SUCCESS);
                $records[$i]->setErrors(NULL);
            } else {
                $records[$i]->setState(Srecord_ActiveRecord::STATE_FAIL);
                $records[$i]->setErrors($results[$i]->errors);
            }
        }
        return;
    }

    /**
     * undelete all sobjects
     * Id must be set.
     * @param array $sobjects array of Srecord_ActiveRecord
     */
    public static function undeleteAll($records)
    {
        $ids = array();
        foreach ($records as $obj) {
            $ids[] = $obj->Id;
        }
        
        $client = self::getClient();
        $results = $client->undelete($ids);
        if (! is_array($results)) {
            $results = array($results);
        }
        for($i=0; $i<count($results); $i++) {
            if ($results[$i]->success == 1) {
                $records[$i]->setState(Srecord_ActiveRecord::STATE_SUCCESS);
                $records[$i]->setErrors(NULL);
            } else {
                $records[$i]->setState(Srecord_ActiveRecord::STATE_FAIL);
                $records[$i]->setErrors($results[$i]->errors);
            }
        }
        return;
    }
    
}

?>