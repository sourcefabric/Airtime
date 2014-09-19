<?php

require_once('CORSHelper.php');

class ShowbuilderController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('schedule-move', 'json')
                    ->addActionContext('schedule-add', 'json')
                    ->addActionContext('schedule-remove', 'json')
                    ->addActionContext('builder-dialog', 'json')
                    ->addActionContext('check-builder-feed', 'json')
                    ->addActionContext('builder-feed', 'json')
                    ->addActionContext('context-menu', 'json')
                    ->initContext();
    }

    public function indexAction()
    {

        $CC_CONFIG = Config::getConfig();

        $request = $this->getRequest();
        $response = $this->getResponse();
        
        //Enable AJAX requests from www.airtime.pro because the autologin during the seamless sign-up follows
        //a redirect here.
        CORSHelper::enableATProCrossOriginRequests($request, $response);
        
        $baseUrl = Application_Common_OsPath::getBaseDir();

        $user = Application_Model_User::GetCurrentUser();
        $userType = $user->getType();
        $this->view->headScript()->appendScript("localStorage.setItem( 'user-type', '$userType' );");
        $this->view->headScript()->appendScript($this->generateGoogleTagManagerDataLayerJavaScript());

        $this->view->headScript()->appendFile($baseUrl.'js/contextmenu/jquery.contextMenu.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/datatables/js/jquery.dataTables.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/datatables/plugin/dataTables.pluginAPI.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/datatables/plugin/dataTables.fnSetFilteringDelay.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/datatables/plugin/dataTables.ColVis.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/datatables/plugin/dataTables.ColReorder.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/datatables/plugin/dataTables.FixedColumns.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/datatables/plugin/dataTables.columnFilter.js?'.$CC_CONFIG['airtime_version'], 'text/javascript');

        $this->view->headScript()->appendFile($baseUrl.'js/blockui/jquery.blockUI.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/airtime/buttons/buttons.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/airtime/utilities/utilities.js?'.$CC_CONFIG['airtime_version'],'text/javascript');

        $this->view->headLink()->appendStylesheet($baseUrl.'css/media_library.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'css/jquery.contextMenu.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'css/datatables/css/ColVis.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'css/datatables/css/ColReorder.css?'.$CC_CONFIG['airtime_version']);

        $refer_sses = new Zend_Session_Namespace('referrer');

        if ($request->isPost()) {
            $form = new Application_Form_RegisterAirtime();

            $values = $request->getPost();
            if ($values["Publicise"] != 1 && $form->isValid($values)) {
                Application_Model_Preference::SetSupportFeedback($values["SupportFeedback"]);

                if (isset($values["Privacy"])) {
                    Application_Model_Preference::SetPrivacyPolicyCheck($values["Privacy"]);
                }
                // unset session
                Zend_Session::namespaceUnset('referrer');
            } elseif ($values["Publicise"] == '1' && $form->isValid($values)) {
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

                if (isset($values["Privacy"])) {
                    Application_Model_Preference::SetPrivacyPolicyCheck($values["Privacy"]);
                }
                // unset session
                Zend_Session::namespaceUnset('referrer');
            } else {
                $logo = Application_Model_Preference::GetStationLogo();
                if ($logo) {
                    $this->view->logoImg = $logo;
                }
                $this->view->dialog = $form;
                $this->view->headScript()->appendFile($baseUrl.'js/airtime/nowplaying/register.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
            }
        }

        //popup if previous page was login
        if ($refer_sses->referrer == 'login' && Application_Model_Preference::ShouldShowPopUp()
                && !Application_Model_Preference::GetSupportFeedback() && $user->isAdmin()){

            $form = new Application_Form_RegisterAirtime();

            $logo = Application_Model_Preference::GetStationLogo();
            if ($logo) {
                $this->view->logoImg = $logo;
            }
            $this->view->dialog = $form;
            $this->view->headScript()->appendFile($baseUrl.'js/airtime/nowplaying/register.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        }

        //determine whether to remove/hide/display the library.
        $showLib = false;
        if (!$user->isGuest()) {
            $disableLib = false;

            $data = Application_Model_Preference::getNowPlayingScreenSettings();
            if (!is_null($data)) {
                if ($data["library"] == "true") {
                    $showLib = true;
                }
            }
        } else {
            $disableLib = true;
        }
        $this->view->disableLib = $disableLib;
        $this->view->showLib    = $showLib;

        //only include library things on the page if the user can see it.
        if (!$disableLib) {
            $this->view->headScript()->appendFile($baseUrl.'js/airtime/library/library.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
            $this->view->headScript()->appendFile($baseUrl.'js/airtime/library/events/library_showbuilder.js?'.$CC_CONFIG['airtime_version'],'text/javascript');

            $data = Application_Model_Preference::getCurrentLibraryTableSetting();
            if (!is_null($data)) {
                $libraryTable = json_encode($data);
                $this->view->headScript()->appendScript("localStorage.setItem( 'datatables-library', JSON.stringify($libraryTable) );");
            } else {
                $this->view->headScript()->appendScript("localStorage.setItem( 'datatables-library', '' );");
            }
        }

        $data = Application_Model_Preference::getTimelineDatatableSetting();
        if (!is_null($data)) {
            $timelineTable = json_encode($data);
            $this->view->headScript()->appendScript("localStorage.setItem( 'datatables-timeline', JSON.stringify($timelineTable) );");
        } else {
            $this->view->headScript()->appendScript("localStorage.setItem( 'datatables-timeline', '' );");
        }

        //populate date range form for show builder.
        $now  = time();
        $from = $request->getParam("from", $now);
        $to   = $request->getParam("to", $now + (24*60*60));

        $utcTimezone = new DateTimeZone("UTC");
        $displayTimeZone = new DateTimeZone(Application_Model_Preference::GetTimezone());

        $start = DateTime::createFromFormat("U", $from, $utcTimezone);
        $start->setTimezone($displayTimeZone);
        $end = DateTime::createFromFormat("U", $to, $utcTimezone);
        $end->setTimezone($displayTimeZone);

        $form = new Application_Form_ShowBuilder();
        $form->populate(array(
            'sb_date_start' => $start->format("Y-m-d"),
            'sb_time_start' => $start->format("H:i"),
            'sb_date_end'   => $end->format("Y-m-d"),
            'sb_time_end'   => $end->format("H:i")
        ));

        $this->view->sb_form = $form;

        $this->view->headScript()->appendFile($baseUrl.'js/timepicker/jquery.ui.timepicker.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/airtime/showbuilder/builder.js?'.$CC_CONFIG['airtime_version'],'text/javascript');
        $this->view->headScript()->appendFile($baseUrl.'js/airtime/showbuilder/main_builder.js?'.$CC_CONFIG['airtime_version'],'text/javascript');

        $this->view->headLink()->appendStylesheet($baseUrl.'css/jquery.ui.timepicker.css?'.$CC_CONFIG['airtime_version']);
        $this->view->headLink()->appendStylesheet($baseUrl.'css/showbuilder.css?'.$CC_CONFIG['airtime_version']);
    }

    public function contextMenuAction()
    {
        $baseUrl = Application_Common_OsPath::getBaseDir();

        $id = $this->_getParam('id');
        $now = floatval(microtime(true));

        $request = $this->getRequest();
        $menu = array();

        $user = Application_Model_User::getCurrentUser();

        $item = CcScheduleQuery::create()->findPK($id);
        $instance = $item->getCcShowInstances();

        $menu["preview"] = array("name"=> _("Preview"), "icon" => "play");
        //select the cursor
        $menu["selCurs"] = array("name"=> _("Select cursor"),"icon" => "select-cursor");
        $menu["delCurs"] = array("name"=> _("Remove cursor"),"icon" => "select-cursor");

        if ($now < floatval($item->getDbEnds("U.u")) && $user->canSchedule($instance->getDbShowId())) {

            //remove/truncate the item from the schedule
            $menu["del"] = array("name"=> _("Delete"), "icon" => "delete", "url" => $baseUrl."showbuilder/schedule-remove");
        }

        $this->view->items = $menu;
    }

    public function builderDialogAction()
    {
        $request = $this->getRequest();
        $id = $request->getParam("id");

        $instance = CcShowInstancesQuery::create()->findPK($id);

        if (is_null($instance)) {
            $this->view->error = _("show does not exist");

            return;
        }

        $displayTimeZone = new DateTimeZone(Application_Model_Preference::GetTimezone());
        
        $start = $instance->getDbStarts(null);
        $start->setTimezone($displayTimeZone);
        $end = $instance->getDbEnds(null);
        $end->setTimezone($displayTimeZone);

        $show_name = $instance->getCcShow()->getDbName();
        $start_time = $start->format("Y-m-d H:i:s");
        $end_time = $end->format("Y-m-d H:i:s");

        $this->view->title = "{$show_name}:    {$start_time} - {$end_time}";
        $this->view->start = $start_time;
        $this->view->end = $end_time;

        $this->view->dialog = $this->view->render('showbuilder/builderDialog.phtml');
    }
    
    private function getStartEnd()
    {
    	$request = $this->getRequest();
    
    	$userTimezone = new DateTimeZone(Application_Model_Preference::GetUserTimezone());
    	$utcTimezone = new DateTimeZone("UTC");
    	$utcNow = new DateTime("now", $utcTimezone);
    
    	$start = $request->getParam("start");
    	$end = $request->getParam("end");
    
    	if (empty($start) || empty($end)) {
    		$startsDT = clone $utcNow;
    		$startsDT->sub(new DateInterval("P1D"));
    		$endsDT = clone $utcNow;
    	}
    	else {
    		 
    		try {
    			$startsDT = new DateTime($start, $userTimezone);
    			$startsDT->setTimezone($utcTimezone);
    
    			$endsDT = new DateTime($end, $userTimezone);
    			$endsDT->setTimezone($utcTimezone);
    
    			if ($startsDT > $endsDT) {
    				throw new Exception("start greater than end");
    			}
    		}
    		catch (Exception $e) {
    			Logging::info($e);
    			Logging::info($e->getMessage());
    
    			$startsDT = clone $utcNow;
    			$startsDT->sub(new DateInterval("P1D"));
    			$endsDT = clone $utcNow;
    		}
    		 
    	}
    
    	return array($startsDT, $endsDT);
    }

    public function checkBuilderFeedAction()
    {
        $request = $this->getRequest();
        $show_filter = intval($request->getParam("showFilter", 0));
        $my_shows = intval($request->getParam("myShows", 0));
        $timestamp = intval($request->getParam("timestamp", -1));
        $instances = $request->getParam("instances", array());

        list($startsDT, $endsDT) = $this->getStartEnd();

        $opts = array("myShows" => $my_shows, "showFilter" => $show_filter);
        $showBuilder = new Application_Model_ShowBuilder($startsDT, $endsDT, $opts);

        //only send the schedule back if updates have been made.
        // -1 default will always call the schedule to be sent back if no timestamp is defined.
        $this->view->update = $showBuilder->hasBeenUpdatedSince(
            $timestamp, $instances);
    }

    public function builderFeedAction()
    {
    	$current_time = time();
    	
        $request = $this->getRequest();
        $show_filter = intval($request->getParam("showFilter", 0));
        $show_instance_filter = intval($request->getParam("showInstanceFilter", 0));
        $my_shows = intval($request->getParam("myShows", 0));

        list($startsDT, $endsDT) = $this->getStartEnd();

        $opts = array("myShows" => $my_shows,
                "showFilter" => $show_filter,
                "showInstanceFilter" => $show_instance_filter);
        $showBuilder = new Application_Model_ShowBuilder($startsDT, $endsDT, $opts);

        $data = $showBuilder->getItems();
        $this->view->schedule = $data["schedule"];
        $this->view->instances = $data["showInstances"];
        $this->view->timestamp = $current_time;
    }

    public function scheduleAddAction()
    {
        $request = $this->getRequest();
        
        $mediaItems = $request->getParam("mediaIds", array());
        $scheduledItems = $request->getParam("schedIds", array());
        
        $log_vars = array();
        $log_vars["url"] = $_SERVER['HTTP_HOST'];
        $log_vars["action"] = "showbuilder/schedule-add";
        $log_vars["params"] = array();
        $log_vars["params"]["media_items"] = $mediaItems;
        $log_vars["params"]["scheduled_items"] = $scheduledItems;
        Logging::info($log_vars);

        try {
            $scheduler = new Application_Model_Scheduler();
            $scheduler->scheduleAfter($scheduledItems, $mediaItems);
        } catch (OutDatedScheduleException $e) {
            $this->view->error = $e->getMessage();
            Logging::info($e->getMessage());
        } catch (Exception $e) {
            $this->view->error = $e->getMessage();
            Logging::info($e->getMessage());
        }
    }

    public function scheduleRemoveAction()
    {
        $request = $this->getRequest();
        $items = $request->getParam("items", array());
        
        $log_vars = array();
        $log_vars["url"] = $_SERVER['HTTP_HOST'];
        $log_vars["action"] = "showbuilder/schedule-remove";
        $log_vars["params"] = array();
        $log_vars["params"]["removed_items"] = $items;
        Logging::info($log_vars);

        try {
            $scheduler = new Application_Model_Scheduler();
            $scheduler->removeItems($items);
        } catch (OutDatedScheduleException $e) {
            $this->view->error = $e->getMessage();
            Logging::info($e->getMessage());
        } catch (Exception $e) {
            $this->view->error = $e->getMessage();
            Logging::info($e->getMessage());
        }
    }

    public function scheduleMoveAction()
    {
        $request = $this->getRequest();
        $selectedItems = $request->getParam("selectedItem");
        $afterItem = $request->getParam("afterItem");
        
        $log_vars = array();
        $log_vars["url"] = $_SERVER['HTTP_HOST'];
        $log_vars["action"] = "showbuilder/schedule-move";
        $log_vars["params"] = array();
        $log_vars["params"]["selected_items"] = $selectedItems;
        $log_vars["params"]["destination_after_item"] = $afterItem;
        Logging::info($log_vars);

        try {
            $scheduler = new Application_Model_Scheduler();
            $scheduler->moveItem($selectedItems, $afterItem);
        } catch (OutDatedScheduleException $e) {
            $this->view->error = $e->getMessage();
            Logging::info($e->getMessage());
        } catch (Exception $e) {
            $this->view->error = $e->getMessage();
            Logging::info($e->getMessage());
        }
    }

    public function scheduleReorderAction()
    {
        throw new Exception("this controller is/was a no-op please fix your
           code");
    }
    
    /** Returns a string containing the JavaScript code to pass some billing account info 
     *  into Google Tag Manager / Google Analytics, so we can track things like the plan type.
     */
    private static function generateGoogleTagManagerDataLayerJavaScript()
    {
        $code = "";
        
        try
        {
            $accessKey = $_SERVER["WHMCS_ACCESS_KEY"];
            $username = $_SERVER["WHMCS_USERNAME"];
            $password = $_SERVER["WHMCS_PASSWORD"];
            $url = "https://account.sourcefabric.com/includes/api.php?accesskey=" . $accessKey; # URL to WHMCS API file goes here
            
            $postfields = array();
            $postfields["username"] = $username;
            $postfields["password"] = md5($password);
            $postfields["action"] = "getclientsdetails";
            $postfields["stats"] = true;
            $postfields["clientid"] = Application_Model_Preference::GetClientId();
            $postfields["responsetype"] = "json";
            
            $query_string = "";
            foreach ($postfields AS $k=>$v) $query_string .= "$k=".urlencode($v)."&";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); //Aggressive 5 second timeout
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            $jsondata = curl_exec($ch);
            if (curl_error($ch)) {
                //die("Connection Error: ".curl_errno($ch).' - '.curl_error($ch));
                throw new Exception("WHMCS server down or invalid request.");
            }
            curl_close($ch);
                    
            $arr = json_decode($jsondata); # Decode JSON String
            
            if ($arr->result !== "success") {
                Logging::warn("WHMCS API call failed in " . __FUNCTION__); 
                return;
            }
            
            $client = $arr->client;
            $stats = $arr->stats;
            $currencyCode = $client->currency_code;
            //$incomeCents = NumberFormatter::parseCurrency($stats->income, $currencyCode);
            
            $isTrial = true;
            if (strpos($stats->income, "0.00") === FALSE) {
                $isTrial = false;
            }
            /*
            if ($incomeCents > 0) {
                $isTrial = false;
            }*/
            $plan = Application_Model_Preference::GetPlanLevel();
            $country = $client->country;
            $postcode = $client->postcode;
            
            //Figure out how long the customer has been around using a mega hack.
            //(I'm avoiding another round trip to WHMCS for now...)
            //We calculate it based on the trial end date...
            $trialEndDateStr = Application_Model_Preference::GetTrialEndingDate();
            if ($trialEndDateStr == '') {
                $accountDuration = 0;
            } else {
                $today = new DateTime();
                $trialEndDate = new DateTime($trialEndDateStr);
                $trialDuration = new DateInterval("P30D"); //30 day trial duration
                $accountCreationDate = $trialEndDate->sub($trialDuration);
                $interval = $today->diff($accountCreationDate);
                $accountDuration = $interval->days;
            }
            
            $code = "$( document ).ready(function() {
                    dataLayer.push({
                                    'ZipCode':  '" . $postcode . "',
                                    'UserID':  '" . $client->id . "',
                                    'Customer':  'Customer',
                                    'PlanType':  '" . $plan . "',
                                    'Trial':  '" . $isTrial . "',
                                    'Country':  '" . $country . "',
                                    'AccountDuration':  '" . strval($accountDuration) . "'
                                    });
                     });";
            
        } 
        catch (Exception $e)
        {
            return "";
        }
        return $code;
    }
}
