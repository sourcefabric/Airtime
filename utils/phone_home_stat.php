<?php

exitIfNotRoot();

$values = parse_ini_file('/etc/airtime/airtime.conf', true);

// Name of the web server user
$CC_CONFIG['webServerUser'] = $values['general']['web_server_user'];
$CC_CONFIG['phpDir'] = $values['general']['airtime_dir'];
$CC_CONFIG['rabbitmq'] = $values['rabbitmq'];

//$CC_CONFIG['baseUrl'] = $values['general']['base_url'];
//$CC_CONFIG['basePort'] = $values['general']['base_port'];

// Database config
$CC_CONFIG['dsn']['username'] = $values['database']['dbuser'];
$CC_CONFIG['dsn']['password'] = $values['database']['dbpass'];
$CC_CONFIG['dsn']['hostspec'] = $values['database']['host'];
$CC_CONFIG['dsn']['phptype'] = 'pgsql';
$CC_CONFIG['dsn']['database'] = $values['database']['dbname'];

$CC_CONFIG['apiKey'] = array($values['general']['api_key']);

$CC_CONFIG['soundcloud-connection-retries'] = $values['soundcloud']['connection_retries'];
$CC_CONFIG['soundcloud-connection-wait'] = $values['soundcloud']['time_between_retries'];

require_once($CC_CONFIG['phpDir'].'/application/configs/constants.php');
require_once($CC_CONFIG['phpDir'].'/application/configs/conf.php');

$CC_CONFIG['phpDir'] = $values['general']['airtime_dir'];

require_once($CC_CONFIG['phpDir'].'/application/models/User.php');
require_once($CC_CONFIG['phpDir'].'/application/models/StoredFile.php');
require_once($CC_CONFIG['phpDir'].'/application/models/Playlist.php');
require_once($CC_CONFIG['phpDir'].'/application/models/Schedule.php');
require_once($CC_CONFIG['phpDir'].'/application/models/Show.php');
require_once($CC_CONFIG['phpDir'].'/application/models/ShowInstance.php');
require_once($CC_CONFIG['phpDir'].'/application/models/Preference.php');
require_once($CC_CONFIG['phpDir'].'/application/models/StreamSetting.php');

//Pear classes.
set_include_path($CC_CONFIG['phpDir'].'/library/pear' . PATH_SEPARATOR . get_include_path());
require_once('DB.php');
$CC_DBC = DB::connect($CC_CONFIG['dsn'], FALSE);
if (PEAR::isError($CC_DBC)) {
    /*echo $CC_DBC->getMessage().PHP_EOL;
    echo $CC_DBC->getUserInfo().PHP_EOL;
    echo "Database connection problem.".PHP_EOL;
    echo "Check if database '{$CC_CONFIG['dsn']['database']}' exists".
        " with corresponding permissions.".PHP_EOL;*/
    if ($p_exitOnError) {
        exit(1);
    }
} else {
    //echo "* Connected to database".PHP_EOL;
    $CC_DBC->setFetchMode(DB_FETCHMODE_ASSOC);
}

if(Application_Model_Preference::GetSupportFeedback() == '1'){
    $infoArray = Application_Model_Preference::GetSystemInfo(true);
    
    $url = 'http://stat.sourcefabric.org/index.php?p=airtime';
    //$url = 'http://stat-dev.sourcefabric.org/index.php?p=airtime';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    
    $data = json_encode($infoArray);
    
    $dataArray = array("data" => $data );
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $dataArray);
    $result = curl_exec($ch);
    
    curl_close($ch);
}

// Get latest version from stat server and store to db
if(Application_Model_Preference::GetPlanLevel() == 'disabled'){
    $url = 'http://stat.sourcefabric.org/airtime_latest_version';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    $result = curl_exec($ch);
    
    if(curl_errno($ch)) {
        echo "curl error: " . curl_error($ch) . "\n";
    } else {
        $resultArray = explode("\n", $result);
        Application_Model_Preference::SetLatestVersion($resultArray[0]);
        Application_Model_Preference::SetLatestLink($resultArray[1]);
    }
    
    curl_close($ch);
}

/**
 * Ensures that the user is running this PHP script with root
 * permissions. If not running with root permissions, causes the
 * script to exit.
 */
function exitIfNotRoot()
{
    // Need to check that we are superuser before running this.
    if(exec("whoami") != "root"){
        echo "Must be root user.\n";
        exit(1);
    }
}
