<?php

class Application_Model_Schedule {

    /**
     * Return true if there is nothing in the schedule for the given start time
     * up to the length of time after that.
     *
     * @param string $p_datetime
     *    In the format YYYY-MM-DD HH:MM:SS.mmmmmm
     * @param string $p_length
     *    In the format HH:MM:SS.mmmmmm
     * @return boolean|PEAR_Error
     */
    public static function isScheduleEmptyInRange($p_datetime, $p_length) {
        global $CC_CONFIG, $CC_DBC;
        if (empty($p_length)) {
            return new PEAR_Error("Application_Model_Schedule::isSchedulerEmptyInRange: param p_length is empty.");
        }
        $sql = "SELECT COUNT(*) FROM ".$CC_CONFIG["scheduleTable"]
        ." WHERE (starts >= '$p_datetime') "
        ." AND (ends <= (TIMESTAMP '$p_datetime' + INTERVAL '$p_length'))";
        //$_SESSION["debug"] = $sql;
        //echo $sql;
        $count = $CC_DBC->GetOne($sql);
        //var_dump($count);
        return ($count == '0');
    }

    /**
     * Return TRUE if file is going to be played in the future.
     *
     * @param string $p_fileId
     */
    public function IsFileScheduledInTheFuture($p_fileId)
    {
        global $CC_CONFIG, $CC_DBC;
        $sql = "SELECT COUNT(*) FROM ".$CC_CONFIG["scheduleTable"]
        ." WHERE file_id = {$p_fileId} AND starts > NOW()";
        $count = $CC_DBC->GetOne($sql);
        if (is_numeric($count) && ($count != '0')) {
            return TRUE;
        } else {
            return FALSE;
        }
    }


    /**
     * Returns array indexed by:
     *    "playlistId"/"playlist_id" (aliases to the same thing)
     *    "start"/"starts" (aliases to the same thing) as YYYY-MM-DD HH:MM:SS.nnnnnn
     *    "end"/"ends" (aliases to the same thing) as YYYY-MM-DD HH:MM:SS.nnnnnn
     *    "group_id"/"id" (aliases to the same thing)
     *    "clip_length" (for audio clips this is the length of the audio clip,
     *                   for playlists this is the length of the entire playlist)
     *    "name" (playlist only)
     *    "creator" (playlist only)
     *    "file_id" (audioclip only)
     *    "count" (number of items in the playlist, always 1 for audioclips.
     *      Note that playlists with one item will also have count = 1.
     *
     * @param string $p_fromDateTime
     *    In the format YYYY-MM-DD HH:MM:SS.nnnnnn
     * @param string $p_toDateTime
     *    In the format YYYY-MM-DD HH:MM:SS.nnnnnn
     * @param boolean $p_playlistsOnly
     *    Retrieve playlists as a single item.
     * @return array
     *      Returns empty array if nothing found
     */

    public static function GetItems($p_currentDateTime, $p_toDateTime, $p_playlistsOnly = true)
    {
        global $CC_CONFIG, $CC_DBC;
        $rows = array();
        if (!$p_playlistsOnly) {
            $sql = "SELECT * FROM ".$CC_CONFIG["scheduleTable"]
            ." WHERE (starts >= TIMESTAMP '$p_currentDateTime') "
            ." AND (ends <= TIMESTAMP '$p_toDateTime')";
            $rows = $CC_DBC->GetAll($sql);
            foreach ($rows as &$row) {
                $row["count"] = "1";
                $row["playlistId"] = $row["playlist_id"];
                $row["start"] = $row["starts"];
                $row["end"] = $row["ends"];
                $row["id"] = $row["group_id"];
            }
        } else {
            $sql = "SELECT MIN(pt.creator) AS creator,"
            ." st.group_id,"
            ." SUM(st.clip_length) AS clip_length,"
            ." MIN(st.file_id) AS file_id,"
            ." COUNT(*) as count,"
            ." MIN(st.playlist_id) AS playlist_id,"
            ." MIN(st.starts) AS starts,"
            ." MAX(st.ends) AS ends,"
            ." MIN(sh.name) AS show_name,"
            ." MIN(si.starts) AS show_start,"
            ." MAX(si.ends) AS show_end"
            ." FROM $CC_CONFIG[scheduleTable] as st"
            ." LEFT JOIN $CC_CONFIG[playListTable] as pt"
            ." ON st.playlist_id = pt.id"
            ." LEFT JOIN $CC_CONFIG[showInstances] as si"
            ." ON st.instance_id = si.id"
            ." LEFT JOIN $CC_CONFIG[showTable] as sh"
            ." ON si.show_id = sh.id"
            //The next line ensures we only get songs that haven't ended yet
            ." WHERE (st.ends >= TIMESTAMP '$p_currentDateTime')"
            ." AND (st.ends <= TIMESTAMP '$p_toDateTime')"
            //next line makes sure that we aren't returning items that
            //are past the show's scheduled timeslot.
            ." AND (st.starts < si.ends)"
            ." GROUP BY st.group_id"
            ." ORDER BY starts";

            $rows = $CC_DBC->GetAll($sql);
            if (!PEAR::isError($rows)) {
                foreach ($rows as &$row) {
                    $row["playlistId"] = $row["playlist_id"];
                    $row["start"] = $row["starts"];
                    $row["end"] = $row["ends"];
                    $row["id"] = $row["group_id"];
                }
            }
        }
        return $rows;
    }

    /**
     * Returns data related to the scheduled items.
     *
     * @param int $prev
     * @param int $next
     * @return date
     */
    public static function GetPlayOrderRange($prev = 1, $next = 1)
    {
        if (!is_int($prev) || !is_int($next)){
            //must enter integers to specify ranges
            return array();
        }

        global $CC_CONFIG;

        $date = new Application_Model_DateHelper;
        $timeNow = $date->getTimestamp();
        $utcTimeNow = $date->getUtcTimestamp();
        $range = array("env"=>APPLICATION_ENV,
            "schedulerTime"=>$timeNow,
            "previous"=>Application_Model_Dashboard::GetPreviousItem($utcTimeNow),
            "current"=>Application_Model_Dashboard::GetCurrentItem($utcTimeNow),
            "next"=>Application_Model_Dashboard::GetNextItem($utcTimeNow),
            "currentShow"=>Application_Model_Show::GetCurrentShow($utcTimeNow),
            "nextShow"=>Application_Model_Show::GetNextShows($utcTimeNow, 1),
            "timezone"=> date("T"),
            "timezoneOffset"=> date("Z"));
                                        
        return $range;
    }

    public static function GetLastScheduleItem($p_timeNow){
        global $CC_CONFIG, $CC_DBC;

        $sql = "SELECT"
        ." ft.artist_name, ft.track_title,"
        ." st.starts as starts, st.ends as ends"
        ." FROM $CC_CONFIG[scheduleTable] st"
        ." LEFT JOIN $CC_CONFIG[filesTable] ft"
        ." ON st.file_id = ft.id"
        ." LEFT JOIN $CC_CONFIG[showInstances] sit"
        ." ON st.instance_id = sit.id"
        ." WHERE st.ends < TIMESTAMP '$p_timeNow'"
        ." AND st.starts >= sit.starts" //this and the next line are necessary since we can overbook shows.
        ." AND st.starts < sit.ends"
        ." ORDER BY st.ends DESC"
        ." LIMIT 1";

        $row = $CC_DBC->GetAll($sql);
        return $row;
    }


    public static function GetCurrentScheduleItem($p_timeNow, $p_instanceId){
        global $CC_CONFIG, $CC_DBC;

        /* Note that usually there will be one result returned. In some
         * rare cases two songs are returned. This happens when a track
         * that was overbooked from a previous show appears as if it
         * hasnt ended yet (track end time hasn't been reached yet). For
         * this reason,  we need to get the track that starts later, as
         * this is the *real* track that is currently playing. So this
         * is why we are ordering by track start time. */
        $sql = "SELECT *"
        ." FROM $CC_CONFIG[scheduleTable] st"
        ." LEFT JOIN $CC_CONFIG[filesTable] ft"
        ." ON st.file_id = ft.id"
        ." WHERE st.starts <= TIMESTAMP '$p_timeNow'"
        ." AND st.instance_id = $p_instanceId"
        ." AND st.ends > TIMESTAMP '$p_timeNow'"
        ." ORDER BY st.starts DESC"
        ." LIMIT 1";

        $row = $CC_DBC->GetAll($sql);
        return $row;
    }

    public static function GetNextScheduleItem($p_timeNow){
        global $CC_CONFIG, $CC_DBC;

        $sql = "SELECT"
        ." ft.artist_name, ft.track_title,"
        ." st.starts as starts, st.ends as ends"
        ." FROM $CC_CONFIG[scheduleTable] st"
        ." LEFT JOIN $CC_CONFIG[filesTable] ft"
        ." ON st.file_id = ft.id"
        ." LEFT JOIN $CC_CONFIG[showInstances] sit"
        ." ON st.instance_id = sit.id"
        ." WHERE st.starts > TIMESTAMP '$p_timeNow'"
        ." AND st.starts >= sit.starts" //this and the next line are necessary since we can overbook shows.
        ." AND st.starts < sit.ends"
        ." ORDER BY st.starts"
        ." LIMIT 1";

        $row = $CC_DBC->GetAll($sql);
        return $row;
    }

    /**
     * Builds an SQL Query for accessing scheduled item information from
     * the database.
     *
     * @param int $timeNow
     * @param int $timePeriod
     * @param int $count
     * @param String $interval
     * @return date
     *
     * $timeNow is the the currentTime in the format "Y-m-d H:i:s".
     * For example: 2011-02-02 22:00:54
     *
     * $timePeriod can be either negative, zero or positive. This is used
     * to indicate whether we want items from the past, present or future.
     *
     * $count indicates how many results we want to limit ourselves to.
     *
     * $interval is used to indicate how far into the past or future we
     * want to search the database. For example "5 days", "18 hours", "60 minutes",
     * "30 seconds" etc.
     */
    public static function GetScheduledItemData($timeStamp, $timePeriod=0, $count = 0, $interval="0 hours")
    {
        global $CC_CONFIG, $CC_DBC;

        $sql = "SELECT DISTINCT"
        ." pt.name,"
        ." ft.track_title,"
        ." ft.artist_name,"
        ." ft.album_title,"
        ." st.starts,"
        ." st.ends,"
        ." st.clip_length,"
        ." st.media_item_played,"
        ." st.group_id,"
        ." show.name as show_name,"
        ." st.instance_id"
        ." FROM $CC_CONFIG[scheduleTable] st"
        ." LEFT JOIN $CC_CONFIG[filesTable] ft"
        ." ON st.file_id = ft.id"
        ." LEFT JOIN $CC_CONFIG[playListTable] pt"
        ." ON st.playlist_id = pt.id"
        ." LEFT JOIN $CC_CONFIG[showInstances] si"
        ." ON st.instance_id = si.id"
        ." LEFT JOIN $CC_CONFIG[showTable] show"
        ." ON si.show_id = show.id"
        ." WHERE st.starts < si.ends";

        if ($timePeriod < 0){
        	$sql .= " AND st.ends < TIMESTAMP '$timeStamp'"
        	." AND st.ends > (TIMESTAMP '$timeStamp' - INTERVAL '$interval')"
  	        ." ORDER BY st.starts DESC"
        	." LIMIT $count";
		} else if ($timePeriod == 0){
	        $sql .= " AND st.starts <= TIMESTAMP '$timeStamp'"
    	    ." AND st.ends >= TIMESTAMP '$timeStamp'";
		} else if ($timePeriod > 0){
        	$sql .= " AND st.starts > TIMESTAMP '$timeStamp'"
        	." AND st.starts < (TIMESTAMP '$timeStamp' + INTERVAL '$interval')"
        	." ORDER BY st.starts"
        	." LIMIT $count";
		}

        $rows = $CC_DBC->GetAll($sql);
        return $rows;
	}

    public static function GetShowInstanceItems($instance_id)
    {
        global $CC_CONFIG, $CC_DBC;

        $sql = "SELECT DISTINCT pt.name, ft.track_title, ft.artist_name, ft.album_title, st.starts, st.ends, st.clip_length, st.media_item_played, st.group_id, show.name as show_name, st.instance_id"
        ." FROM $CC_CONFIG[scheduleTable] st, $CC_CONFIG[filesTable] ft, $CC_CONFIG[playListTable] pt, $CC_CONFIG[showInstances] si, $CC_CONFIG[showTable] show"
        ." WHERE st.playlist_id = pt.id"
        ." AND st.file_id = ft.id"
        ." AND st.instance_id = si.id"
        ." AND si.show_id = show.id"
        ." AND instance_id = $instance_id"
        ." ORDER BY st.starts";

        $rows = $CC_DBC->GetAll($sql);
        return $rows;
    }

    public static function UpdateMediaPlayedStatus($p_id)
    {
        global $CC_CONFIG, $CC_DBC;
        $sql = "UPDATE ".$CC_CONFIG['scheduleTable']
                ." SET media_item_played=TRUE"
                ." WHERE id=$p_id";
        $retVal = $CC_DBC->query($sql);
        return $retVal;
    }

    public static function getSchduledPlaylistCount(){
    	global $CC_CONFIG, $CC_DBC;
        $sql = "SELECT count(*) as cnt FROM ".$CC_CONFIG['scheduleTable'];
        return $CC_DBC->GetOne($sql);
    }


    /**
     * Convert a time string in the format "YYYY-MM-DD HH:mm:SS"
     * to "YYYY-MM-DD-HH-mm-SS".
     *
     * @param string $p_time
     * @return string
     */
    private static function AirtimeTimeToPypoTime($p_time)
    {
        $p_time = substr($p_time, 0, 19);
        $p_time = str_replace(" ", "-", $p_time);
        $p_time = str_replace(":", "-", $p_time);
        return $p_time;
    }

    /**
     * Convert a time string in the format "YYYY-MM-DD-HH-mm-SS" to
     * "YYYY-MM-DD HH:mm:SS".
     *
     * @param string $p_time
     * @return string
     */
    private static function PypoTimeToAirtimeTime($p_time)
    {
        $t = explode("-", $p_time);
        return $t[0]."-".$t[1]."-".$t[2]." ".$t[3].":".$t[4].":00";
    }

    /**
     * Return true if the input string is in the format YYYY-MM-DD-HH-mm
     *
     * @param string $p_time
     * @return boolean
     */
    public static function ValidPypoTimeFormat($p_time)
    {
        $t = explode("-", $p_time);
        if (count($t) != 5) {
            return false;
        }
        foreach ($t as $part) {
            if (!is_numeric($part)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Converts a time value as a string (with format HH:MM:SS.mmmmmm) to
     * millisecs.
     *
     * @param string $p_time
     * @return int
     */
    public static function WallTimeToMillisecs($p_time)
    {
        $t = explode(":", $p_time);
        $millisecs = 0;
        if (strpos($t[2], ".")) {
            $secParts = explode(".", $t[2]);
            $millisecs = $secParts[1];
            $millisecs = substr($millisecs, 0, 3);
            $millisecs = intval($millisecs);
            $seconds = intval($secParts[0]);
        } else {
            $seconds = intval($t[2]);
        }
        $ret = $millisecs + ($seconds * 1000) + ($t[1] * 60 * 1000) + ($t[0] * 60 * 60 * 1000);
        return $ret;
    }


    /**
     * Compute the difference between two times in the format "HH:MM:SS.mmmmmm".
     * Note: currently only supports calculating millisec differences.
     *
     * @param string $p_time1
     * @param string $p_time2
     * @return double
     */
    private static function TimeDiff($p_time1, $p_time2)
    {
        $parts1 = explode(".", $p_time1);
        $parts2 = explode(".", $p_time2);
        $diff = 0;
        if ( (count($parts1) > 1) && (count($parts2) > 1) ) {
            $millisec1 = substr($parts1[1], 0, 3);
            $millisec1 = str_pad($millisec1, 3, "0");
            $millisec1 = intval($millisec1);
            $millisec2 = substr($parts2[1], 0, 3);
            $millisec2 = str_pad($millisec2, 3, "0");
            $millisec2 = intval($millisec2);
            $diff = abs($millisec1 - $millisec2)/1000;
        }
        return $diff;
    }


    /**
     * Export the schedule in json formatted for pypo (the liquidsoap scheduler)
     *
     * @param string $p_fromDateTime
     *      In the format "YYYY-MM-DD-HH-mm-SS"
     * @param string $p_toDateTime
     *      In the format "YYYY-MM-DD-HH-mm-SS"
     */
    public static function GetScheduledPlaylists($p_fromDateTime = null, $p_toDateTime = null)
    {
        global $CC_CONFIG, $CC_DBC;

        if (is_null($p_fromDateTime)) {
            $t1 = new DateTime("@".time());
            $range_start = $t1->format("Y-m-d H:i:s");
        } else {
            $range_start = Application_Model_Schedule::PypoTimeToAirtimeTime($p_fromDateTime);
        }
        if (is_null($p_fromDateTime)) {
            $t2 = new DateTime("@".time());
            $t2->add(new DateInterval("PT24H"));
            $range_end = $t2->format("Y-m-d H:i:s");
        } else {
            $range_end = Application_Model_Schedule::PypoTimeToAirtimeTime($p_toDateTime);
        }

        // Scheduler wants everything in a playlist
        $data = Application_Model_Schedule::GetItems($range_start, $range_end, true);
        $playlists = array();

        if (is_array($data)){
            foreach ($data as $dx){
                $start = $dx['start'];

                //chop off subseconds
                $start = substr($start, 0, 19);

                //Start time is the array key, needs to be in the format "YYYY-MM-DD-HH-mm-ss"
                $pkey = Application_Model_Schedule::AirtimeTimeToPypoTime($start);
                $timestamp =  strtotime($start);
                $playlists[$pkey]['source'] = "PLAYLIST";
                $playlists[$pkey]['x_ident'] = $dx['group_id'];
                $playlists[$pkey]['timestamp'] = $timestamp;
                $playlists[$pkey]['duration'] = $dx['clip_length'];
                $playlists[$pkey]['played'] = '0';
                $playlists[$pkey]['schedule_id'] = $dx['group_id'];
                $playlists[$pkey]['show_name'] = $dx['show_name'];
                $playlists[$pkey]['show_start'] = Application_Model_Schedule::AirtimeTimeToPypoTime($dx['show_start']);
                $playlists[$pkey]['show_end'] = Application_Model_Schedule::AirtimeTimeToPypoTime($dx['show_end']);
                $playlists[$pkey]['user_id'] = 0;
                $playlists[$pkey]['id'] = $dx['group_id'];
                $playlists[$pkey]['start'] = Application_Model_Schedule::AirtimeTimeToPypoTime($dx["start"]);
                $playlists[$pkey]['end'] = Application_Model_Schedule::AirtimeTimeToPypoTime($dx["end"]);
            }
        }
        ksort($playlists);

        foreach ($playlists as &$playlist)
        {
            $scheduleGroup = new Application_Model_ScheduleGroup($playlist["schedule_id"]);
            $items = $scheduleGroup->getItems();
            $medias = array();
            foreach ($items as $item)
            {
                $storedFile = Application_Model_StoredFile::Recall($item["file_id"]);
                $uri = $storedFile->getFileUrlUsingConfigAddress();

                $starts = Application_Model_Schedule::AirtimeTimeToPypoTime($item["starts"]);
                $medias[$starts] = array(
                    'row_id' => $item["id"],
                    'id' => $storedFile->getGunid(),
                    'uri' => $uri,
                    'fade_in' => Application_Model_Schedule::WallTimeToMillisecs($item["fade_in"]),
                    'fade_out' => Application_Model_Schedule::WallTimeToMillisecs($item["fade_out"]),
                    'fade_cross' => 0,
                    'cue_in' => Application_Model_DateHelper::CalculateLengthInSeconds($item["cue_in"]),
                    'cue_out' => Application_Model_DateHelper::CalculateLengthInSeconds($item["cue_out"]),
                    'export_source' => 'scheduler',
                    'start' => $starts,
                    'end' => Application_Model_Schedule::AirtimeTimeToPypoTime($item["ends"])
                );
            }
            ksort($medias);
            $playlist['medias'] = $medias;
        }

        $result = array();
        $result['status'] = array('range' => array('start' => $range_start, 'end' => $range_end),
                                  'version' => AIRTIME_REST_VERSION);
        $result['playlists'] = $playlists;
        $result['check'] = 1;
        $result['stream_metadata'] = array();
        $result['stream_metadata']['format'] = Application_Model_Preference::GetStreamLabelFormat();
        $result['stream_metadata']['station_name'] = Application_Model_Preference::GetStationName();

        return $result;
    }

    public static function deleteAll()
    {
        global $CC_CONFIG, $CC_DBC;
        $CC_DBC->query("TRUNCATE TABLE ".$CC_CONFIG["scheduleTable"]);
    }

    public static function createNewFormSections($p_view){
        $isSaas = Application_Model_Preference::GetPlanLevel() == 'disabled'?false:true;
        
        $formWhat = new Application_Form_AddShowWhat();
		$formWho = new Application_Form_AddShowWho();
		$formWhen = new Application_Form_AddShowWhen();
		$formRepeats = new Application_Form_AddShowRepeats();
		$formStyle = new Application_Form_AddShowStyle();

		$formWhat->removeDecorator('DtDdWrapper');
		$formWho->removeDecorator('DtDdWrapper');
		$formWhen->removeDecorator('DtDdWrapper');
		$formRepeats->removeDecorator('DtDdWrapper');
		$formStyle->removeDecorator('DtDdWrapper');

        $p_view->what = $formWhat;
        $p_view->when = $formWhen;
        $p_view->repeats = $formRepeats;
        $p_view->who = $formWho;
        $p_view->style = $formStyle;

        $formWhat->populate(array('add_show_id' => '-1'));
        $formWhen->populate(array('add_show_start_date' => date("Y-m-d"),
                                      'add_show_start_time' => '00:00',
        							  'add_show_end_date_no_repeate' => date("Y-m-d"),
        							  'add_show_end_time' => '01:00',
                                      'add_show_duration' => '1h'));

		$formRepeats->populate(array('add_show_end_date' => date("Y-m-d")));
		
        if(!$isSaas){
            $formRecord = new Application_Form_AddShowRR();
            $formAbsoluteRebroadcast = new Application_Form_AddShowAbsoluteRebroadcastDates();
            $formRebroadcast = new Application_Form_AddShowRebroadcastDates();
            
            $formRecord->removeDecorator('DtDdWrapper');
            $formAbsoluteRebroadcast->removeDecorator('DtDdWrapper');
            $formRebroadcast->removeDecorator('DtDdWrapper');
            
            $p_view->rr = $formRecord;
            $p_view->absoluteRebroadcast = $formAbsoluteRebroadcast;
            $p_view->rebroadcast = $formRebroadcast;
        }
        $p_view->addNewShow = true;
    }
}

