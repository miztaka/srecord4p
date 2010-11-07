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
    public static function getClient() {
        
        if (self::$_sForceClient == NULL) {
            
            $sforceClient = new SforcePartnerClient(); // TODO タイプ指定できるように
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
}

?>