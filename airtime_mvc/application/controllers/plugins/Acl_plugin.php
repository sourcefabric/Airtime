<?php

class Zend_Controller_Plugin_Acl extends Zend_Controller_Plugin_Abstract
{
    /**
     * @var Zend_Acl
     **/
    protected $_acl;

    /**
     * @var string
     **/
    protected $_roleName;

    /**
     * @var array
     **/
    protected $_errorPage;

    /**
     * Constructor
     *
     * @param mixed $aclData
     * @param $roleName
     * @return void
     **/
    public function __construct(Zend_Acl $aclData, $roleName = 'G')
    {
        $this->_errorPage = array('module' => 'default',
                                  'controller' => 'error',
                                  'action' => 'denied');

        $this->_roleName = $roleName;

        if (null !== $aclData) {
            $this->setAcl($aclData);
        }
    }

    /**
     * Sets the ACL object
     *
     * @param  mixed $aclData
     * @return void
     **/
    public function setAcl(Zend_Acl $aclData)
    {
        $this->_acl = $aclData;
    }

    /**
     * Returns the ACL object
     *
     * @return Zend_Acl
     **/
    public function getAcl()
    {
        return $this->_acl;
    }

    /**
     * Returns the ACL role used
     *
     * @return string
     * @author
     **/
    public function getRoleName()
    {
        return $this->_roleName;
    }

    public function setRoleName($type)
    {
        $this->_roleName = $type;
    }

    /**
     * Sets the error page
     *
     * @param  string $action
     * @param  string $controller
     * @param  string $module
     * @return void
     **/
    public function setErrorPage($action, $controller = 'error', $module = null)
    {
        $this->_errorPage = array('module' => $module,
                                  'controller' => $controller,
                                  'action' => $action);
    }

    /**
     * Returns the error page
     *
     * @return array
     **/
    public function getErrorPage()
    {
        return $this->_errorPage;
    }

    /**
     * Predispatch
     * Checks if the current user identified by roleName has rights to the requested url (module/controller/action)
     * If not, it will call denyAccess to be redirected to errorPage
     *
     * @return void
     **/
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        $controller = strtolower($request->getControllerName());
        Application_Model_Auth::pinSessionToClient(Zend_Auth::getInstance());
        
        if (in_array($controller, array("api", "auth", "locale", "upgrade"))) {
            $this->setRoleName("G");
        } elseif (!Zend_Auth::getInstance()->hasIdentity()) {
            
            // If we don't have an identity and we're making a RESTful request,
            // we need to do API key verification
            if ($request->getModuleName() == "rest") {
                $this->verifyAuth();
                return;
            }

             if ($controller !== 'login') {

                if ($request->isXmlHttpRequest()) {

                    $url = 'http://'.$request->getHttpHost().'/login';
                    $json = Zend_Json::encode(array('auth' => false, 'url' => $url));

                    // Prepare response
                    $this->getResponse()
                         ->setHttpResponseCode(401)
                         ->setBody($json)
                         ->sendResponse();

                    //redirectAndExit() cleans up, sends the headers and stops the script
                    Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')->redirectAndExit();
                } else {
                    $r = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
                    $r->gotoSimpleAndExit('index', 'login', $request->getModuleName());
               }
            }
        } else {
            // If we have an identity and we're making a RESTful request,
            // we need to check the CSRF token
            if ($request->_action != "get" && $request->getModuleName() == "rest") {
                $tokenValid = $this->verifyCSRFToken($request->getParam("csrf_token"));

                if (!$tokenValid) {
                    $this->getResponse()
                         ->setHttpResponseCode(401)
                         ->appendBody("ERROR: CSRF token mismatch.");
                    return;
                }
            }
            
            $userInfo = Zend_Auth::getInstance()->getStorage()->read();
            $this->setRoleName($userInfo->type);

            Zend_View_Helper_Navigation_HelperAbstract::setDefaultAcl($this->_acl);
            Zend_View_Helper_Navigation_HelperAbstract::setDefaultRole($this->_roleName);

            $resourceName = '';

            if ($request->getModuleName() != 'default') {
                $resourceName .= strtolower($request->getModuleName()) . ':';
            }

            $resourceName .= $controller;

            /** Check if the controller/action can be accessed by the current user */
            if (!$this->getAcl()->has($resourceName) 
                || !$this->getAcl()->isAllowed($this->_roleName, 
                        $resourceName, 
                        $request->getActionName())) {
                /** Redirect to access denied page */
                $this->denyAccess();
            }
        }
    }

    private function verifyAuth() {
        if ($this->verifyAPIKey()) {
            return true;
        }
    
        $this->getResponse()
             ->setHttpResponseCode(401)
             ->appendBody("ERROR: Incorrect API key.");
        return false;
    }
    
    private function verifyCSRFToken($token) {
        $current_namespace = new Zend_Session_Namespace('csrf_namespace');
        $observed_csrf_token = $token;
        $expected_csrf_token = $current_namespace->authtoken;
        Logging::error("Observed: " . $observed_csrf_token);
        Logging::error("Expected: " . $expected_csrf_token);
        
        return ($observed_csrf_token == $expected_csrf_token);
    }
    
    private function verifyAPIKey() {
        // The API key is passed in via HTTP "basic authentication":
        // http://en.wikipedia.org/wiki/Basic_access_authentication
        $CC_CONFIG = Config::getConfig();
    
        // Decode the API key that was passed to us in the HTTP request.
        $authHeader = $this->getRequest()->getHeader("Authorization");
        $encodedRequestApiKey = substr($authHeader, strlen("Basic "));
        $encodedStoredApiKey = base64_encode($CC_CONFIG["apiKey"][0] . ":");
    
        return ($encodedRequestApiKey === $encodedStoredApiKey);
    }

    /**
     * Deny Access Function
     * Redirects to errorPage, this can be called from an action using the action helper
     *
     * @return void
     **/
    public function denyAccess()
    {
        $this->_request->setModuleName($this->_errorPage['module']);
        $this->_request->setControllerName($this->_errorPage['controller']);
        $this->_request->setActionName($this->_errorPage['action']);
    }
}
