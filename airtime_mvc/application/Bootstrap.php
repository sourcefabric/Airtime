<?php
require_once __DIR__."/configs/conf.php";
require_once __DIR__."/configs/ACL.php";
require_once 'propel/runtime/lib/Propel.php';

Propel::init(__DIR__."/configs/airtime-conf-production.php");

require_once __DIR__."/configs/constants.php";
require_once 'Preference.php';
require_once "DateHelper.php";
require_once "OsPath.php";
require_once "Database.php";
require_once __DIR__.'/forms/helpers/ValidationTypes.php';
require_once __DIR__.'/controllers/plugins/RabbitMqPlugin.php';



date_default_timezone_set('UTC');
require_once (APPLICATION_PATH."/logging/Logging.php");
Logging::setLogPath('/var/log/airtime/zendphp.log');

date_default_timezone_set(Application_Model_Preference::GetTimezone());

global $CC_CONFIG;
$airtime_version = Application_Model_Preference::GetAirtimeVersion();
$uniqueid = Application_Model_Preference::GetUniqueId();
$CC_CONFIG['airtime_version'] = md5($airtime_version.$uniqueid);
require_once __DIR__."/configs/navigation.php";

Zend_Validate::setDefaultNamespaces("Zend");

$front = Zend_Controller_Front::getInstance();
$front->registerPlugin(new RabbitMqPlugin());

//localization configuration
$codeset = 'UTF-8';
$lang = Application_Model_Preference::GetLocale().'.'.$codeset;

putenv("LC_ALL=$lang");
putenv("LANG=$lang");
$res = setlocale(LC_MESSAGES, $lang);

$domain = 'airtime';
bindtextdomain($domain, '/usr/share/airtime/locale');
textdomain($domain);
bind_textdomain_codeset($domain, $codeset);


/* The bootstrap class should only be used to initialize actions that return a view.
   Actions that return JSON will not use the bootstrap class! */
class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initDoctype()
    {
        $this->bootstrap('view');
        $view = $this->getResource('view');
        $view->doctype('XHTML1_STRICT');
    }
    
    protected function _initGlobals()
    {
        $view = $this->getResource('view');
        $baseUrl = Application_Common_OsPath::getBaseDir();
        
        $view->headScript()->appendScript("var baseUrl = '$baseUrl'");
                                               
        $user = Application_Model_User::GetCurrentUser();
        if (!is_null($user)){
            $userType = $user->getType();
        } else {
            $userType = "";
        }
        $view->headScript()->appendScript("var userType = '$userType';");
            
    }

    protected function _initHeadLink()
    {
        global $CC_CONFIG;

        $view = $this->getResource('view');

        $baseUrl = Application_Common_OsPath::getBaseDir();
        
        $view->headLink()->appendStylesheet($baseUrl.'/css/redmond/jquery-ui-1.8.8.custom.css?'.$CC_CONFIG['airtime_version']);
        $view->headLink()->appendStylesheet($baseUrl.'/css/pro_dropdown_3.css?'.$CC_CONFIG['airtime_version']);
        $view->headLink()->appendStylesheet($baseUrl.'/css/qtip/jquery.qtip.min.css?'.$CC_CONFIG['airtime_version']);
        $view->headLink()->appendStylesheet($baseUrl.'/css/styles.css?'.$CC_CONFIG['airtime_version']);
        $view->headLink()->appendStylesheet($baseUrl.'/css/masterpanel.css?'.$CC_CONFIG['airtime_version']);
        $view->headLink()->appendStylesheet($baseUrl.'/css/bootstrap.css?'.$CC_CONFIG['airtime_version']);
        $view->headLink()->appendStylesheet($baseUrl.'/css/tipsy/jquery.tipsy.css?'.$CC_CONFIG['airtime_version']);
    }

    protected function _initHeadScript()
    {
        global $CC_CONFIG;

        $view = $this->getResource('view');
        
        $baseUrl = Application_Common_OsPath::getBaseDir();
                
        $view->headScript()->appendFile($baseUrl.'/js/libs/jquery-1.7.2.min.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/libs/jquery-ui-1.8.18.custom.min.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/libs/jquery.stickyPanel.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/qtip/jquery.qtip.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/jplayer/jquery.jplayer.min.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/sprintf/sprintf-0.7-beta1.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/bootstrap/bootstrap.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/i18n/jquery.i18n.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/locale/general-translation-table?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/locale/datatables-translation-table?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendScript("$.i18n.setDictionary(general_dict)");
        $view->headScript()->appendScript("var baseUrl='$baseUrl'");
        
        //scripts for now playing bar
        $view->headScript()->appendFile($baseUrl.'/js/airtime/airtime_bootstrap.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/airtime/dashboard/helperfunctions.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/airtime/dashboard/dashboard.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/airtime/dashboard/versiontooltip.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/tipsy/jquery.tipsy.js?'.$CC_CONFIG['airtime_version'],'text/javascript');

        $view->headScript()->appendFile($baseUrl.'/js/airtime/common/common.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $view->headScript()->appendFile($baseUrl.'/js/airtime/common/audioplaytest.js?'.$CC_CONFIG['airtime_version'],'text/javascript');

        $user = Application_Model_User::getCurrentUser();
        if (!is_null($user)){
            $userType = $user->getType();
        } else {
            $userType = "";
        }
        $view->headScript()->appendScript("var userType = '$userType';");

        if (isset($CC_CONFIG['demo']) && $CC_CONFIG['demo'] == 1) {
            $view->headScript()->appendFile($baseUrl.'/js/libs/google-analytics.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        }

        if (Application_Model_Preference::GetPlanLevel() != "disabled"
                && !($_SERVER['REQUEST_URI'] == $baseUrl.'/Dashboard/stream-player' || 
                     strncmp($_SERVER['REQUEST_URI'], $baseUrl.'/audiopreview/audio-preview', strlen($baseUrl.'/audiopreview/audio-preview'))==0)) {
            $client_id = Application_Model_Preference::GetClientId();
            $view->headScript()->appendScript("var livechat_client_id = '$client_id';");
            $view->headScript()->appendFile($baseUrl . '/js/airtime/common/livechat.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');
        }

    }

    protected function _initViewHelpers()
    {
        $view = $this->getResource('view');
        $view->addHelperPath('../application/views/helpers', 'Airtime_View_Helper');
    }

    protected function _initTitle()
    {
        $view = $this->getResource('view');
        $view->headTitle(Application_Model_Preference::GetHeadTitle());
    }

    protected function _initZFDebug()
    {

        Zend_Controller_Front::getInstance()->throwExceptions(true); 

        /*
        if (APPLICATION_ENV == "development") {
            $autoloader = Zend_Loader_Autoloader::getInstance();
            $autoloader->registerNamespace('ZFDebug');

            $options = array(
                'plugins' => array('Variables',
                                   'Exception',
                                   'Memory',
                                   'Time')
            );
            $debug = new ZFDebug_Controller_Plugin_Debug($options);

            $this->bootstrap('frontController');
            $frontController = $this->getResource('frontController');
            $frontController->registerPlugin($debug);
        }
        */
    }

    protected function _initRouter()
    {
        $front = Zend_Controller_Front::getInstance();
        $router = $front->getRouter();

        $router->addRoute(
            'password-change',
            new Zend_Controller_Router_Route('password-change/:user_id/:token', array(
                'module' => 'default',
                'controller' => 'login',
                'action' => 'password-change',
            )));
    }
}

