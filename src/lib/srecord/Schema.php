<?php 

require_once PHPTOOLKIT_DIR."/soapclient/SforcePartnerClient.php";

class Srecord_Schema
{
    public static $username = '';
    public static $password = '';
    public static $securityToken = '';
    public static $wsdlPartner = '';
    
    /**
     * connect to salesforce, get sforceClient
     * @return SforcePartnerClient
     * TODO キャッシュ to Session
     */
    public static function getClient() {
        
        if (self::$_sForceClient == NULL) {
            $sforceClient = new SforcePartnerClient(); // TODO タイプ指定できるように
            $soapClient = $sforceClient->createConnection(self::$wsdlPartner);
            $sforceLogin = $sforceClient->login(self::$username, self::$password.self::$securityToken);
            self::$_sForceClient = $sforceClient;
        }
        return self::$_sForceClient; 
    }
    protected static $_sForceClient = NULL;
}

?>