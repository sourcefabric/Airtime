<?php

class NowplayingController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('get-data-grid-data', 'json')
                    ->addActionContext('register', 'json')
                    ->addActionContext('remindme', 'json')
                    ->initContext();
    }

    public function indexAction()
    {
        $request = $this->getRequest();
        $baseUrl = $request->getBaseUrl();

        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/js/jquery.dataTables.min.js','text/javascript');
        
        //nowplayingdatagrid.js requires this variable, so that datePicker widget can be offset to server time instead of client time
        $this->view->headScript()->appendScript("var timezoneOffset = ".date("Z")."; //in seconds");
        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/nowplaying/nowplayingdatagrid.js','text/javascript');
        
        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/nowplaying/nowview.js','text/javascript');
        
        $refer_sses = new Zend_Session_Namespace('referrer');
        $userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $user = new Application_Model_User($userInfo->id);
        
        if ($request->isPost()) {
            $form = new Application_Form_RegisterAirtime();
        
            $values = $request->getPost();
            if ($values["Publicise"] != 1 && $form->isValid($values)){
                Application_Model_Preference::SetSupportFeedback($values["SupportFeedback"]);
                if(isset($values["Privacy"])){
                    Application_Model_Preference::SetPrivacyPolicyCheck($values["Privacy"]);
                }
                // unset session
                Zend_Session::namespaceUnset('referrer');
            }
            else if ($values["Publicise"] == '1' && $form->isValid($values)) {
                Application_Model_Preference::SetHeadTitle($values["stnName"], $this->view);
                Application_Model_Preference::SetPhone($values["Phone"]);
                Application_Model_Preference::SetEmail($values["Email"]);
                Application_Model_Preference::SetStationWebSite($values["StationWebSite"]);
                Application_Model_Preference::SetPublicise($values["Publicise"]);
                
                $form->Logo->receive();
                $imagePath = $form->Logo->getFileName();
    
                Application_Model_Preference::SetStationCountry($values["Country"]);
                Application_Model_Preference::SetStationCity($values["City"]);
                Application_Model_Preference::SetStationDescription($values["Description"]);
                Application_Model_Preference::SetStationLogo($imagePath);
                Application_Model_Preference::SetSupportFeedback($values["SupportFeedback"]);
                if(isset($values["Privacy"])){
                    Application_Model_Preference::SetPrivacyPolicyCheck($values["Privacy"]);
                }
                // unset session
                Zend_Session::namespaceUnset('referrer');
            }else{
                $logo = Application_Model_Preference::GetStationLogo();
                if($logo){
                    $this->view->logoImg = $logo;
                }
                $this->view->dialog = $form;
                $this->view->headScript()->appendFile($baseUrl.'/js/airtime/nowplaying/register.js','text/javascript');
            }
        }else{
            //popup if previous page was login
            if($refer_sses->referrer == 'login' && Application_Model_Nowplaying::ShouldShowPopUp()
                && !Application_Model_Preference::GetSupportFeedback() && $user->isAdmin()){
                
                $form = new Application_Form_RegisterAirtime();
                
                
                $logo = Application_Model_Preference::GetStationLogo();
                if($logo){
                    $this->view->logoImg = $logo;
                }
                $this->view->dialog = $form;
            	$this->view->headScript()->appendFile($baseUrl.'/js/airtime/nowplaying/register.js','text/javascript');
            }
        }
    }

    public function getDataGridDataAction()
    {
        $viewType = $this->_request->getParam('view');
        $dateString = $this->_request->getParam('date');
        $this->view->entries = Application_Model_Nowplaying::GetDataGridData($viewType, $dateString);
        
    }
/*
    public function livestreamAction()
    {
        //use bare bones layout (no header bar or menu)
        $this->_helper->layout->setLayout('bare');
    }
*/

    public function dayViewAction()
    {
        $request = $this->getRequest();
        $baseUrl = $request->getBaseUrl();

        $this->view->headScript()->appendFile($baseUrl.'/js/datatables/js/jquery.dataTables.min.js','text/javascript');
        
        //nowplayingdatagrid.js requires this variable, so that datePicker widget can be offset to server time instead of client time
        $this->view->headScript()->appendScript("var timezoneOffset = ".date("Z")."; //in seconds");
        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/nowplaying/nowplayingdatagrid.js','text/javascript');
        
        $this->view->headScript()->appendFile($baseUrl.'/js/airtime/nowplaying/dayview.js','text/javascript');
    }

    public function remindmeAction()
    {
        // unset session
        Zend_Session::namespaceUnset('referrer');
    	Application_Model_Preference::SetRemindMeDate();
    	die();
    }
    
    public function donotshowregistrationpopupAction()
    {
    	// unset session
    	Zend_Session::namespaceUnset('referrer');
    	die();
    }
}









