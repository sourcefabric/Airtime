<?php

class Application_Model_Nowplaying
{

	private static function CreateHeaderRow($p_showName, $p_showStart, $p_showEnd){
		return array("h", "", $p_showStart, $p_showEnd, $p_showName, "", "", "", "", "", "");
	}

	private static function CreateDatatableRows($p_dbRows){
        $dataTablesRows = array();
        
        $epochNow = time();
        
        foreach ($p_dbRows as $dbRow){
                    
            $showStartDateTime = Application_Model_DateHelper::ConvertToLocalDateTime($dbRow['show_starts']);
            $showEndDateTime = Application_Model_DateHelper::ConvertToLocalDateTime($dbRow['show_ends']);
            $itemStartDateTime = Application_Model_DateHelper::ConvertToLocalDateTime($dbRow['item_starts']);
            $itemEndDateTime = Application_Model_DateHelper::ConvertToLocalDateTime($dbRow['item_ends']);
        
            $showStarts = $showStartDateTime->format("Y-m-d H:i:s");
            $showEnds = $showEndDateTime->format("Y-m-d H:i:s");
            $itemStarts = $itemStartDateTime->format("Y-m-d H:i:s");
            $itemEnds = $itemEndDateTime->format("Y-m-d H:i:s");
            
            // Allow show to exceed 1 second per CC-3183
            $status = ($showEnds < $itemEnds) ? "x" : "";
            
            $type = "a";
            $type .= ($itemStartDateTime->getTimestamp() <= $epochNow 
                    && $epochNow < $itemEndDateTime->getTimestamp()
                    && $epochNow < $showEndDateTime->getTimestamp()) ? "c" : "";
            
            // remove millisecond from the time format
            $itemStart = explode('.', $dbRow['item_starts']);
            $itemEnd = explode('.', $dbRow['item_ends']);
            
            //format duration
            $duration = explode('.', $dbRow['clip_length']);
            $formatted = self::FormatDuration($duration[0]);
            $dataTablesRows[] = array($type, $showStarts, $itemStarts, $itemEnds,
                $formatted, $dbRow['track_title'], $dbRow['artist_name'], $dbRow['album_title'],
                $dbRow['playlist_name'], $dbRow['show_name'], $status);
        }

		return $dataTablesRows;
	}
	
	private static function CreateGapRow($p_gapTime){
		return array("g", "", "", "", $p_gapTime, "", "", "", "", "", "");
	}
	
	private static function CreateRecordingRow($p_showInstance){
		return array("r", "", "", "", $p_showInstance->getName(), "", "", "", "", "", "");
	}

	public static function GetDataGridData($viewType, $dateString){

        if ($viewType == "now"){
            $dateTime = new DateTime("now", new DateTimeZone("UTC"));
            $timeNow = $dateTime->format("Y-m-d H:i:s");
            
            $startCutoff = 60;
            $endCutoff = 86400; //60*60*24 - seconds in a day
        } else {
            $date = new Application_Model_DateHelper;
            $time = $date->getTime();
            $date->setDate($dateString." ".$time);
            $timeNow = $date->getUtcTimestamp();

            $startCutoff = $date->getNowDayStartDiff();
            $endCutoff = $date->getNowDayEndDiff();
        }
        
        $data = array();

        $showIds = Application_Model_ShowInstance::GetShowsInstancesIdsInRange($timeNow, $startCutoff, $endCutoff);
        foreach ($showIds as $showId){
            $instanceId = $showId['id'];
            
            $si = new Application_Model_ShowInstance($instanceId);
            
            $showId = $si->getShowId();
            $show = new Application_Model_Show($showId);
            
            $showStartDateTime = Application_Model_DateHelper::ConvertToLocalDateTime($si->getShowInstanceStart());
            $showEndDateTime = Application_Model_DateHelper::ConvertToLocalDateTime($si->getShowInstanceEnd());
            
            //append show header row
            $data[] = self::CreateHeaderRow($show->getName(), $showStartDateTime->format("Y-m-d H:i:s"), $showEndDateTime->format("Y-m-d H:i:s"));
            
            $scheduledItems = $si->getScheduleItemsInRange($timeNow, $startCutoff, $endCutoff);
            $dataTablesRows = self::CreateDatatableRows($scheduledItems);
            
            //append show audio item rows
            $data = array_merge($data, $dataTablesRows);
            
            //append show gap time row
            $gapTime = self::FormatDuration($si->getShowEndGapTime(), true);
            if ($si->isRecorded())
            	$data[] = self::CreateRecordingRow($si);
            else if ($gapTime > 0)
            	$data[] = self::CreateGapRow($gapTime);
        }

        $rows = Application_Model_Show::GetCurrentShow($timeNow);
        Application_Model_Show::ConvertToLocalTimeZone($rows, array("starts", "ends", "start_timestamp", "end_timestamp"));
        return array("currentShow"=>$rows, "rows"=>$data);
    }

    public static function ShouldShowPopUp(){
        $today = mktime(0, 0, 0, gmdate("m"), gmdate("d"), gmdate("Y"));
        $remindDate = Application_Model_Preference::GetRemindMeDate();
        if($remindDate == NULL || $today >= $remindDate){
            return true;
        }
    }
    /*
     * default $time format should be in format of 00:00:00
     * if $inSecond = true, then $time should be in seconds  
     */
    private static function FormatDuration($time, $inSecond=false){
        if($inSecond == false){
            $duration = explode(':', $time);
        }else{
            $duration = array();
            $duration[0] = intval(($time/3600)%24);
            $duration[1] = intval(($time/60)%60);
            $duration[2] = $time%60;
        }
        
        if($duration[2] == 0){
            $duration[2] = '';
        }else{
            $duration[2] = intval($duration[2],10).'s';
        }
        
        if($duration[1] == 0){
            if($duration[2] == ''){
                $duration[1] = '';
            }else{
                $duration[1] = intval($duration[1],10).'m ';
            }
        }else{
            $duration[1] = intval($duration[1],10).'m ';
        }
        
        if($duration[0] == 0){
            $duration[0] = '';
        }else{
            $duration[0] = intval($duration[0],10).'h ';
        }
        
        $out = $duration[0].$duration[1].$duration[2];
        return $out;
    }
}
