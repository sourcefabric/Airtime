<?php

class Application_Model_Show
{
    private $_showId;

    public function __construct($showId=NULL)
    {
        $this->_showId = $showId;
    }

    public function getName()
    {
        $show = CcShowQuery::create()->findPK($this->_showId);

        return $show->getDbName();
    }

    public function setName($name)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbName($name);
        Application_Model_RabbitMq::PushSchedule();
    }

    public function setAirtimeAuthFlag($flag)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbLiveStreamUsingAirtimeAuth($flag);
        $show->save();
    }

    public function setCustomAuthFlag($flag)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbLiveStreamUsingCustomAuth($flag);
        $show->save();
    }

    public function setCustomUsername($username)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbLiveStreamUser($username);
        $show->save();
    }

    public function setCustomPassword($password)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbLiveStreamPass($password);
        $show->save();
    }

    public function getDescription()
    {
        $show = CcShowQuery::create()->findPK($this->_showId);

        return $show->getDbDescription();
    }

    public function setDescription($description)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbDescription($description);
    }

    public function getColor()
    {
        $show = CcShowQuery::create()->findPK($this->_showId);

        return $show->getDbColor();
    }

    public function setColor($color)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbColor($color);
    }

    public function getUrl()
    {
        $show = CcShowQuery::create()->findPK($this->_showId);

        return $show->getDbUrl();
    }

    /*TODO : This method is not actually used anywhere as far as I can tell. We
        can safely remove it and probably many other superfluous methods.
        --RG*/

    public function setUrl($p_url)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbUrl($p_url);
    }

    public function getGenre()
    {
        $show = CcShowQuery::create()->findPK($this->_showId);

        return $show->getDbGenre();
    }

    public function setGenre($p_genre)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbGenre($p_genre);
    }

    public function getBackgroundColor()
    {
        $show = CcShowQuery::create()->findPK($this->_showId);

        return $show->getDbBackgroundColor();
    }

    public function setBackgroundColor($backgroundColor)
    {
        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->setDbBackgroundColor($backgroundColor);
    }

    public function getId()
    {
        return $this->_showId;
    }

    public function getHosts()
    {
        $sql = <<<SQL
SELECT first_name,
       last_name
FROM cc_show_hosts
LEFT JOIN cc_subjs ON cc_show_hosts.subjs_id = cc_subjs.id
WHERE show_id = :show_id
SQL;

        $hosts = Application_Common_Database::prepareAndExecute( $sql,
            array( ':show_id' => $this->getId() ), 'all');

        $res = array();
        foreach ($hosts as $host) {
            $res[] = $host['first_name']." ".$host['last_name'];
        }
        return $res;
    }

    public function getHostsIds()
    {
        $sql = <<<SQL
SELECT subjs_id
FROM cc_show_hosts
WHERE show_id = :show_id
SQL;

        $hosts = Application_Common_Database::prepareAndExecute(
            $sql, array( ':show_id' => $this->getId() ), 'all');

        return $hosts;
    }

    /**
     * remove everything about this show.
     */
    public function delete()
    {
        //usually we hide the show-instance, but in this case we are deleting the show template
        //so delete all show-instances as well.
        CcShowInstancesQuery::create()->filterByDbOriginalShow($this->_showId)->delete();

        $show = CcShowQuery::create()->findPK($this->_showId);
        $show->delete();
    }

    public function resizeShow($deltaDay, $deltaMin)
    {
        $con = Propel::getConnection();

        if ($deltaDay > 0) {
            return _("Shows can have a max length of 24 hours.");
        }
        
        $utc = new DateTimeZone("UTC");
        
        $nowDateTime = new DateTime("now", $utc);

        $showInstances = CcShowInstancesQuery::create()
            ->filterByDbShowId($this->_showId)
            ->find($con);

        /* Check two things:
           1. If the show being resized and any of its repeats end in the past 
           2. If the show being resized and any of its repeats overlap
              with other scheduled shows */

        foreach ($showInstances as $si) {
            $startsDateTime = new DateTime($si->getDbStarts(), new DateTimeZone("UTC"));
            $endsDateTime   = new DateTime($si->getDbEnds(), new DateTimeZone("UTC"));

            /* The user is moving the show on the calendar from the perspective
                of local time.  * incase a show is moved across a time change
                border offsets should be added to the local * timestamp and
                then converted back to UTC to avoid show time changes */
            $startsDateTime->setTimezone(new DateTimeZone(date_default_timezone_get()));
            $endsDateTime->setTimezone(new DateTimeZone(date_default_timezone_get()));

            $newStartsDateTime = Application_Model_ShowInstance::addDeltas($startsDateTime, $deltaDay, $deltaMin);
            $newEndsDateTime   = Application_Model_ShowInstance::addDeltas($endsDateTime, $deltaDay, $deltaMin);
            
            if ($newEndsDateTime->getTimestamp() < $nowDateTime->getTimestamp()) {
                return _("End date/time cannot be in the past");
            }

            //convert our new starts/ends to UTC.
            $newStartsDateTime->setTimezone($utc);
            $newEndsDateTime->setTimezone($utc);

            $overlapping = Application_Model_Schedule::checkOverlappingShows(
                $newStartsDateTime, $newEndsDateTime, true, $si->getDbId());

            if ($overlapping) {
                return _("Cannot schedule overlapping shows.\nNote: Resizing a repeating show ".
                       "affects all of its repeats.");
            }
        }

        $hours = $deltaMin/60;
        $hours = ($hours > 0) ? floor($hours) : ceil($hours);
        $mins  = abs($deltaMin % 60);

        //current timesamp in UTC.
        $current_timestamp = gmdate("Y-m-d H:i:s");

        $sql_gen = <<<SQL
UPDATE cc_show_instances
SET ends = (ends + :deltaDay1::INTERVAL + :interval1::INTERVAL)
WHERE (show_id = :show_id1
       AND ends > :current_timestamp1)
  AND ((ends + :deltaDay2::INTERVAL + :interval2::INTERVAL - starts) <= interval '24:00')
SQL;

        Application_Common_Database::prepareAndExecute($sql_gen,
            array(
                ':deltaDay1'          => "$deltaDay days",
                ':interval1'          => "$hours:$mins",
                ':show_id1'           =>  $this->_showId,
                ':current_timestamp1' =>  $current_timestamp,
                ':deltaDay2'          => "$deltaDay days",
                ':interval2'          => "$hours:$mins"
            ), "execute");

        $sql_gen = <<<SQL
UPDATE cc_show_days
SET duration = (CAST(duration AS interval) + :deltaDay3::INTERVAL + :interval3::INTERVAL)
WHERE show_id = :show_id2
  AND ((CAST(duration AS interval) + :deltaDay4::INTERVAL + :interval4::INTERVAL) <= interval '24:00')
SQL;

        Application_Common_Database::prepareAndExecute($sql_gen,
            array(
                ':deltaDay3'          => "$deltaDay days",
                ':interval3'          => "$hours:$mins",
                ':show_id2'           =>  $this->_showId,
                ':deltaDay4'          => "$deltaDay days",
                ':interval4'          => "$hours:$mins"
            ), "execute");

        $con = Propel::getConnection(CcSchedulePeer::DATABASE_NAME);
        $con->beginTransaction();

        try {
            //update the status flag in cc_schedule.

            /* Since we didn't use a propel object when updating
             * cc_show_instances table we need to clear the instances
             * so the correct information is retrieved from the db
             */
            CcShowInstancesPeer::clearInstancePool();

            $instances = CcShowInstancesQuery::create()
                ->filterByDbEnds($current_timestamp, Criteria::GREATER_THAN)
                ->filterByDbShowId($this->_showId)
                ->find($con);

            foreach ($instances as $instance) {
                $instance->updateScheduleStatus($con);
            }

            $con->commit();
        } catch (Exception $e) {
            $con->rollback();
            Logging::info("Couldn't update schedule status.");
            Logging::info($e->getMessage());
        }

        Application_Model_RabbitMq::PushSchedule();
    }

    public function cancelShow($day_timestamp)
    {
        $timeinfo = explode(" ", $day_timestamp);

        CcShowDaysQuery::create()
            ->filterByDbShowId($this->_showId)
            ->update(array('DbLastShow' => $timeinfo[0]));

        $sql = <<<SQL
SELECT id from cc_show_instances
WHERE starts >= :dayTimestamp::TIMESTAMP
  AND show_id = :showId
SQL;
    
        $rows = Application_Common_Database::prepareAndExecute( $sql, array(
            ':dayTimestamp' => $day_timestamp,
            ':showId'       => $this->getId()), 'all');

        foreach ($rows as $row) {
            try {
                $showInstance = new Application_Model_ShowInstance($row["id"]);
                $showInstance->delete($rabbitmqPush = false);
            } catch (Exception $e) {
                Logging::info($e->getMessage());
            }
        } 

        Application_Model_RabbitMq::PushSchedule();
    }

    /**
     * This function is called when a repeating show is edited and the
     * days that is repeats on have changed. More specifically, a day
     * that the show originally repeated on has been "unchecked".
     *
     * Removes Show Instances that occur on days of the week specified
     * by input array. For example, if array contains one value of "0",
     * (0 = Sunday, 1=Monday) then all show instances that occur on
     * Sunday are removed.
     *
     * @param array p_uncheckedDays
     *      An array specifying which days should be removed. (in the local timezone)
     */
    public function removeUncheckedDaysInstances($p_uncheckedDays)
    {
        //need to convert local doftw to UTC doftw (change made for 2.0 since shows are stored in UTC)
        $daysRemovedUTC = array();

        $showDays = CcShowDaysQuery::create()
                        ->filterByDbShowId($this->getId())
                        ->find();

        Logging::info("Unchecked days:");
        foreach ($p_uncheckedDays as $day) {
            Logging::info($day);
        }

        foreach ($showDays as $showDay) {
            //Logging::info("Local show day is: {$showDay->getDbDay()}");
            //Logging::info("First show day is: {$showDay->getDbFirstShow()}");
            //Logging::info("Id show days is: {$showDay->getDbId()}");

            if (in_array($showDay->getDbDay(), $p_uncheckedDays)) {
               $showDay->reload();
               //Logging::info("Local show day is: {$showDay->getDbDay()}");
               //Logging::info("First show day is: {$showDay->getDbFirstShow()}");
               //Logging::info("Id show days is: {$showDay->getDbId()}");
               $startDay = new DateTime("{$showDay->getDbFirstShow()} {$showDay->getDbStartTime()}", new DateTimeZone($showDay->getDbTimezone()));
               //Logging::info("Show start day: {$startDay->format('Y-m-d H:i:s')}");
               $startDay->setTimezone(new DateTimeZone("UTC"));
               //Logging::info("Show start day UTC: {$startDay->format('Y-m-d H:i:s')}");
               $daysRemovedUTC[] = $startDay->format('w');
               //Logging::info("UTC show day is: {$startDay->format('w')}");
            }
        }

        $uncheckedDaysImploded = implode(",", $daysRemovedUTC);
        $showId = $this->getId();

        $esc_uncheckedDays = pg_escape_string($uncheckedDaysImploded);
        $timestamp = gmdate("Y-m-d H:i:s");

        $sql = <<<SQL
DELETE
FROM cc_show_instances
WHERE EXTRACT(DOW FROM starts) IN ($esc_uncheckedDays)
  AND starts > :timestamp::TIMESTAMP
  AND show_id = :showId
SQL;

        Application_Common_Database::prepareAndExecute( $sql,
            array(
                ":timestamp" => $timestamp,
                ":showId"    => $showId,
            ), "execute");
    }

    /**
     * Check whether the current show originated
     * from a recording.
     *
     * @return boolean
     *      true if originated from recording, otherwise false.
     */
    public function isRecorded()
    {
        $showInstancesRow = CcShowInstancesQuery::create()
            ->filterByDbShowId($this->getId())
            ->filterByDbRecord(1)
            ->filterByDbModifiedInstance(false)
            ->findOne();

        return !is_null($showInstancesRow);
    }

    /**
     * Check whether the current show has rebroadcasts of a recorded
     * show. Should be used in conjunction with isRecorded().
     *
     * @return boolean
     *      true if show has rebroadcasts, otherwise false.
     */
    public function isRebroadcast()
    {
         $showInstancesRow = CcShowInstancesQuery::create()
        ->filterByDbShowId($this->_showId)
        ->filterByDbRebroadcast(1)
        ->filterByDbModifiedInstance(false)
        ->findOne();

        return !is_null($showInstancesRow);
    }

    /**
     * Get start time and absolute start date for a recorded
     * shows rebroadcasts. For example start date format would be
     * YYYY-MM-DD and time would HH:MM
     *
     * @return array
     *      array of associate arrays containing "start_date" and "start_time"
     */
    public function getRebroadcastsAbsolute()
    {
        $sql = <<<SQL
SELECT starts
FROM cc_show_instances
WHERE instance_id =
    (SELECT id
     FROM cc_show_instances
     WHERE show_id = :showId
     ORDER BY starts LIMIT 1)
  AND rebroadcast = 1
ORDER BY starts
SQL;

        $rebroadcasts = Application_Common_Database::prepareAndExecute( $sql,
            array( 'showId' => $this->getId() ), 'all' );

        $rebroadcastsLocal = array();
        //get each rebroadcast show in cc_show_instances, convert to current timezone to get start date/time.
        /*TODO: refactor the following code to get rid of the $i temporary
            variable. -- RG*/
        $i = 0;

        $utc = new DateTimeZone("UTC");
        $dtz = new DateTimeZone( date_default_timezone_get() );

        foreach ($rebroadcasts as $show) {
            $startDateTime = new DateTime($show["starts"], $utc);
            $startDateTime->setTimezone($dtz);

            $rebroadcastsLocal[$i]["start_date"] = 
                $startDateTime->format("Y-m-d");
            $rebroadcastsLocal[$i]["start_time"] =
                $startDateTime->format("H:i");

            $i = $i + 1;
        }

        return $rebroadcastsLocal;
    }

    /**
     * Get start time and relative start date for a recorded
     * shows rebroadcasts. For example start date format would be
     * "x days" and time would HH:MM:SS
     *
     * @return array
     *      array of associate arrays containing "day_offset" and "start_time"
     */
    public function getRebroadcastsRelative()
    {
        $sql = <<<SQL
SELECT day_offset,
       start_time
FROM cc_show_rebroadcast
WHERE show_id = :showId
ORDER BY day_offset
SQL;
        return Application_Common_Database::prepareAndExecute( $sql,
            array( ':showId' => $this->getId() ), 'all' );
    }

    /**
     * Check whether the current show is set to repeat
     * repeating shows.
     *
     * @return boolean
     *      true if repeating shows, otherwise false.
     */
    public function isRepeating()
    {
        $showDaysRow = CcShowDaysQuery::create()
            ->filterByDbShowId($this->_showId)
            ->findOne();

        if (!is_null($showDaysRow)) {
            return ($showDaysRow->getDbRepeatType() != -1);
        } else {
            return false;
        }
    }

    /**
     * Get the repeat type of the show. Show can have repeat
     * type of "weekly", "bi-weekly" and "monthly". These values
     * are represented by 0, 1, and 2 respectively.
     *
     * @return int
     *      Return the integer corresponding to the repeat type.
     */
    public function getRepeatType()
    {
        $showDaysRow = CcShowDaysQuery::create()
            ->filterByDbShowId($this->_showId)
            ->findOne();

        if (!is_null($showDaysRow))  
            return $showDaysRow->getDbRepeatType();
        else
            return -1;
    }

    /**
     * Get the end date for a repeating show in the format yyyy-mm-dd
     *
     * @return string
     *      Return the end date for the repeating show or the empty
     *      string if there is no end.
     */
    public function getRepeatingEndDate()
    {
        $sql = <<<SQL
SELECT last_show
FROM cc_show_days
WHERE show_id = :showId
ORDER BY last_show DESC
SQL;

        $query = Application_Common_Database::prepareAndExecute( $sql,
            array( 'showId' => $this->getId() ), 'column' );

        /* TODO: Why return empty string instead of false? very confusing --RG
         */
        return ($query !== false) ? $query : "";
    }

    /**
     * Deletes all future instances of the current show object
     * from the show_instances table. This function is used when
     * a show is being edited - in some cases, when a show is edited
     * we just destroy all future show instances, and let another function
     * regenerate them later on. Note that this isn't always the most
     * desirable thing to do. Deleting a show instance and regenerating
     * it cause any scheduled playlists within those show instances to
     * be gone for good.
     */
    public function deleteAllInstances()
    {
        $sql = <<<SQL
DELETE
FROM cc_show_instances
WHERE starts > :timestamp::TIMESTAMP
  AND show_id = :showId
SQL;
        Application_Common_Database::prepareAndExecute( $sql,
            array( ':timestamp' => gmdate("Y-m-d H:i:s"), 
                   ':showId'    => $this->getId()), 'execute');
    }

    /**
     * Deletes all future rebroadcast instances of the current
     * show object from the show_instances table.
     */
    public function deleteAllRebroadcasts()
    {
        $sql = <<<SQL
DELETE
FROM cc_show_instances
WHERE starts > :timestamp::TIMESTAMP
  AND show_id :showId
  AND rebroadcast 1
SQL;
        Application_Common_Database::prepareAndExecute( $sql,
            array( ':showId' => $this->getId(),
                   ':timestamp' => gmdate("Y-m-d H:i:s")), 'execute');

        $con->exec($sql);
    }

    /**
     * Deletes all show instances of current show after a
     * certain date. Note that although not enforced, $p_date
     * should never be in the past, as we never want to allow
     * deletion of shows that have already occured.
     *
     * @param string $p_date
     *      The date which to delete after, if null deletes from the current timestamp.
     */
    public function removeAllInstancesFromDate($p_date=null)
    {
        $con = Propel::getConnection();

        $timestamp = gmdate("Y-m-d H:i:s");

        if (is_null($p_date)) {
            $date = new Application_Common_DateHelper;
            $p_date = $date->getDate();
        }

        $showId = $this->getId();
        $sql = "DELETE FROM cc_show_instances "
                ." WHERE date(starts) >= DATE '$p_date'"
                ." AND starts > TIMESTAMP '$timestamp'"
                ." AND show_id = $showId";

        $con->exec($sql);

    }

    /**
     * Deletes all show instances of current show before a
     * certain date.
     *
     * This function is used in the case where a repeating show is being
     * edited and the start date of the first show has been changed more
     * into the future. In this case, delete any show instances that
     * exist before the new start date.
     *
     * @param string $p_date
     *      The date which to delete before
     */
    public function removeAllInstancesBeforeDate($p_date)
    {
        $con = Propel::getConnection();

        $timestamp = gmdate("Y-m-d H:i:s");

        $showId = $this->getId();
        $sql = "DELETE FROM cc_show_instances "
                ." WHERE date(starts) < DATE '$p_date'"
                ." AND starts > TIMESTAMP '$timestamp'"
                ." AND show_id = $showId";

        $con->exec($sql);
    }

    /**
     * Get the start date of the current show in UTC timezone.
     *
     * @return string
     *      The start date in the format YYYY-MM-DD or empty string in case
     *      start date could not be found
     */
    public function getStartDateAndTime()
    {
        $con = Propel::getConnection();

        $showId = $this->getId();
        $stmt = $con->prepare(
            "SELECT first_show, start_time, timezone FROM cc_show_days"
            ." WHERE show_id = :showId"
            ." ORDER BY first_show"
            ." LIMIT 1");

        $stmt->bindParam(':showId', $showId);
        $stmt->execute();

        if (!$stmt) {
            return "";
        }

        $rows = $stmt->fetchAll();
        $row = $rows[0];

        $dt = new DateTime($row["first_show"]." ".$row["start_time"], new DateTimeZone($row["timezone"]));
        $dt->setTimezone(new DateTimeZone("UTC"));

        return $dt->format("Y-m-d H:i");
    }

    /**
     * Get the start date of the current show in UTC timezone.
     *
     * @return string
     *      The start date in the format YYYY-MM-DD
     */
    public function getStartDate()
    {
        list($date,) = explode(" ", $this->getStartDateAndTime());

        return $date;
    }

    /**
     * Get the start time of the current show in UTC timezone.
     *
     * @return string
     *      The start time in the format HH:MM
     */

    public function getStartTime()
    {
        list(,$time) = explode(" ", $this->getStartDateAndTime());

        return $time;
    }

    /**
     * Get the end date of the current show.
     * Note that this is not the end date of repeated show
     *
     * @return string
     *      The end date in the format YYYY-MM-DD
     */
    public function getEndDate()
    {
        $startDate     = $this->getStartDate();
        $startTime     = $this->getStartTime();
        $duration      = $this->getDuration();

        $startDateTime = new DateTime($startDate.' '.$startTime);
        $duration      = explode(":", $duration);

        $endDate = $startDateTime->add(new DateInterval('PT'.$duration[0].'H'.$duration[1].'M'));

        return $endDate->format('Y-m-d');
    }

    /**
     * Get the end time of the current show.
     *
     * @return string
     *      The start time in the format HH:MM:SS
     */
    public function getEndTime()
    {
        $startDate = $this->getStartDate();
        $startTime = $this->getStartTime();
        $duration = $this->getDuration();

        $startDateTime = new DateTime($startDate.' '.$startTime);
        $duration = explode(":", $duration);

        $endDate = $startDateTime->add(new DateInterval('PT'.$duration[0].'H'.$duration[1].'M'));

        return $endDate->format('H:i:s');
    }

    /**
     * Indicate whether the starting point of the show is in the
     * past.
     *
     * @return boolean
     *      true if the StartDate is in the past, false otherwise
     */
    public function isStartDateTimeInPast()
    {
        $date = new Application_Common_DateHelper;
        $current_timestamp = $date->getUtcTimestamp();

        return ($current_timestamp > ($this->getStartDate()." ".$this->getStartTime()));
    }

    /**
     * Get the ID's of future instance of the current show.
     *
     * @return array
     *      A simple array containing all ID's of show instance
     *  scheduled in the future.
     */
    public function getAllFutureInstanceIds()
    {
        $sql = <<<SQL
SELECT id
FROM cc_show_instances
WHERE show_id = :showId
  AND starts > :timestamp::TIMESTAMP
  AND modified_instance != TRUE
SQL;
        $rows = Application_Common_Database::prepareAndExecute($sql,
            array( ':showId'    => $this->getId(),
                   ':timestamp' => gmdate("Y-m-d H:i:s")), "all");

        $res = array();
        foreach ($rows as $r) {
            $res[] = $r['id'];
        }
        return $res;
    }

    /* Called when a show's duration is changed (edited).
     *
     * @param array $p_data
     *      array containing the POST data about the show from the
     *      browser.
     *
     */
    private function updateDurationTime($p_data)
    {
        //need to update cc_show_instances, cc_show_days
        $con = Propel::getConnection();

        $date = new Application_Common_DateHelper;
        $timestamp = $date->getUtcTimestamp();

        $stmt =  $con->prepare("UPDATE cc_show_days "
                 ."SET duration = :add_show_duration "
                 ."WHERE show_id = :add_show_id" );
        $stmt->execute( array(
            ':add_show_duration' => $p_data['add_show_duration'],
            ':add_show_id' => $p_data['add_show_id']
        ));


        $sql = <<<SQL
UPDATE cc_show_instances
SET ends = starts + :add_show_duration::INTERVAL
WHERE show_id = :show_id
  AND ends > :timestamp::TIMESTAMP
SQL;
        
        Application_Common_Database::prepareAndExecute( $sql, array( 
            ':add_show_duration' => $p_data['add_show_duration'],
            ':show_id' => $p_data['add_show_id'],
            ':timestamp' => $timestamp), "execute");
    }

    private function updateStartDateTime($p_data, $p_endDate)
    {
        //need to update cc_schedule, cc_show_instances, cc_show_days
        $con = Propel::getConnection();

        $date = new Application_Common_DateHelper;
        $timestamp = $date->getTimestamp();

        //TODO fix this from overwriting info.
        $sql = "UPDATE cc_show_days "
                ."SET start_time = TIME '$p_data[add_show_start_time]', "
                ."first_show = DATE '$p_data[add_show_start_date]', ";
        if (strlen ($p_endDate) == 0) {
            $sql .= "last_show = NULL ";
        } else {
            $sql .= "last_show = DATE '$p_endDate' ";
        }
        $sql .= "WHERE show_id = $p_data[add_show_id]";
        $con->exec($sql);

        $dtOld = new DateTime($this->getStartDate()." ".$this->getStartTime(), new DateTimeZone("UTC"));
        $dtNew = new DateTime($p_data['add_show_start_date']." ".$p_data['add_show_start_time'], new DateTimeZone(date_default_timezone_get()));
        $diff = $dtOld->getTimestamp() - $dtNew->getTimestamp();

        $sql = "UPDATE cc_show_instances "
                ."SET starts = starts + INTERVAL '$diff sec', "
                ."ends = ends + INTERVAL '$diff sec' "
                ."WHERE show_id = $p_data[add_show_id] "
                ."AND starts > TIMESTAMP '$timestamp'";
        $con->exec($sql);

        $showInstanceIds = $this->getAllFutureInstanceIds();
        if (count($showInstanceIds) > 0 && $diff != 0) {
            $showIdsImploded = implode(",", $showInstanceIds);
            $sql = "UPDATE cc_schedule "
                    ."SET starts = starts + INTERVAL '$diff sec', "
                    ."ends = ends + INTERVAL '$diff sec' "
                    ."WHERE instance_id IN ($showIdsImploded)";
            $con->exec($sql);
        }
    }

    public function getDuration($format=false)
    {
        $showDay = CcShowDaysQuery::create()->filterByDbShowId($this->getId())->findOne();
        if (!$format) {
            return $showDay->getDbDuration();
        } else {
            $info = explode(':',$showDay->getDbDuration());

            return str_pad(intval($info[0]),2,'0',STR_PAD_LEFT).'h '.str_pad(intval($info[1]),2,'0',STR_PAD_LEFT).'m';
        }
    }

    public function getShowDays()
    {
        $showDays = CcShowDaysQuery::create()->filterByDbShowId(
            $this->getId())->find();
        $res = array();
        foreach ($showDays as $showDay) {
            $res[] = $showDay->getDbDay();
        }
        return $res;
    }

    /* Only used for shows that aren't repeating.
     *
     * @return Boolean: true if show has an instance, otherwise false. */
    public function hasInstance()
    {
        return (!is_null($this->getInstance()));
    }

    /* Only used for shows that aren't repeating.
     *
     * @return CcShowInstancesQuery: An propel object representing a
     *      row in the cc_show_instances table. */
    public function getInstance()
    {
        $showInstance = CcShowInstancesQuery::create()
            ->filterByDbShowId($this->getId())
            ->findOne();

        return $showInstance;
    }

    /**
     *  returns info about live stream override info
     */
    public function getLiveStreamInfo()
    {
        $info = array();
        if ($this->getId() == null) {
            return $info;
        } else {
            $ccShow = CcShowQuery::create()->findPK($this->_showId);
            $info['custom_username'] = $ccShow->getDbLiveStreamUser();
            $info['cb_airtime_auth'] = $ccShow->getDbLiveStreamUsingAirtimeAuth();
            $info['cb_custom_auth']  = $ccShow->getDbLiveStreamUsingCustomAuth();
            $info['custom_username'] = $ccShow->getDbLiveStreamUser();
            $info['custom_password'] = $ccShow->getDbLiveStreamPass();
            return $info;
        }
    }

    /* Only used for shows that are repeating. Note that this will return
     * true even for dates that only have a "modified" show instance (does not
     * check if the "modified_instance" column is set to true). This is intended
     * behaviour.
     *
     * @param $p_dateTime: Date for which we are checking if instance
     * exists.
     *
     * @return Boolean: true if show has an instance on $p_dateTime,
     *      otherwise false. */
    public function hasInstanceOnDate($p_dateTime)
    {
        return (!is_null($this->getInstanceOnDate($p_dateTime)));
    }

    /* Only used for shows that are repeating. Note that this will return
     * shows that have been "modified" (does not check if the "modified_instance"
     * column is set to true). This is intended behaviour.
     *
     * @param $p_dateTime: Date for which we are getting an instance.
     *
     * @return CcShowInstancesQuery: An propel object representing a
     *      row in the cc_show_instances table. */
    public function getInstanceOnDate($p_dateTime)
    {
        $timestamp = $p_dateTime->format("Y-m-d H:i:s");
        $sql = <<<SQL
SELECT id
FROM cc_show_instances
WHERE date(starts) = date(:timestamp::TIMESTAMP)
  AND show_id = :showId
  AND rebroadcast = 0;
SQL;
        try {
            $row = Application_Common_Database::prepareAndExecute( $sql,
                array( ':showId' => $this->getId(),
                       ':timestamp' => $timestamp ), 'column');
            return CcShowInstancesQuery::create()
                ->findPk($row);
        } catch (Exception $e) {
            return null;
        }
        
    }

    public function deletePossiblyInvalidInstances($p_data, $p_endDate, $isRecorded, $repeatType)
    {
        if ($p_data['add_show_repeats'] != $this->isRepeating()) {
            //repeat option was toggled
            $this->deleteAllInstances();
        }

        if ($p_data['add_show_duration'] != $this->getDuration()) {
            //duration has changed
            $this->updateDurationTime($p_data);
        }

        if ($p_data['add_show_repeats']) {
            if (($repeatType == 1 || $repeatType == 2) &&
                $p_data['add_show_start_date'] != $this->getStartDate()){

                //start date has changed when repeat type is bi-weekly or monthly.
                //This screws up the repeating positions of show instances, so lets
                //just delete them for now. (CC-2351)

                $this->deleteAllInstances();
            }

            if ($repeatType != $this->getRepeatType()) {
                //repeat type changed.
                $this->deleteAllInstances();
            } else {
                //repeat type is the same, check if the days of the week are the same
                $repeatingDaysChanged = false;
                $showDaysArray = $this->getShowDays();
                if (count($p_data['add_show_day_check']) == count($showDaysArray)) {
                    //same number of days checked, lets see if they are the same numbers
                    $intersect = array_intersect($p_data['add_show_day_check'], $showDaysArray);
                    if (count($intersect) != count($p_data['add_show_day_check'])) {
                        $repeatingDaysChanged = true;
                    }
                } else {
                    $repeatingDaysChanged = true;
                }

                if ($repeatingDaysChanged) {
                    $daysRemoved = array_diff($showDaysArray, $p_data['add_show_day_check']);

                    if (count($daysRemoved) > 0) {

                        $this->removeUncheckedDaysInstances($daysRemoved);
                    }
                }

                if ($p_data['add_show_start_date'] != $this->getStartDate()
                    || $p_data['add_show_start_time'] != $this->getStartTime()){
                    //start date/time has changed

                    $newDate = strtotime($p_data['add_show_start_date']);
                    $oldDate = strtotime($this->getStartDate());
                    if ($newDate > $oldDate) {
                        $this->removeAllInstancesBeforeDate($p_data['add_show_start_date']);
                    }

                    $this->updateStartDateTime($p_data, $p_endDate);
                }
            }

            //Check if end date for the repeat option has changed. If so, need to take care
            //of deleting possible invalid Show Instances.
            if ((strlen($this->getRepeatingEndDate()) == 0) == $p_data['add_show_no_end']) {
                //show "Never Ends" option was toggled.
                if ($p_data['add_show_no_end']) {
                } else {
                    $this->removeAllInstancesFromDate($p_endDate);
                }
            }
            if ($this->getRepeatingEndDate() != $p_data['add_show_end_date']) {
                //end date was changed.

                $newDate = strtotime($p_data['add_show_end_date']);
                $oldDate = strtotime($this->getRepeatingEndDate());
                if ($newDate < $oldDate) {
                    $this->removeAllInstancesFromDate($p_endDate);
                }
            }
        }
    }

    /**
     * Create a show.
     *
     * Note: end dates are non inclusive.
     *
     * @param  array $data
     * @return int
     *     Show ID
     */
    public static function create($data)
    {
        $startDateTime = new DateTime($data['add_show_start_date']." ".$data['add_show_start_time']);
        $utcStartDateTime = clone $startDateTime;
        $utcStartDateTime->setTimezone(new DateTimeZone('UTC'));

        if ($data['add_show_no_end']) {
            $endDate = NULL;
        } elseif ($data['add_show_repeats']) {
            $endDateTime = new DateTime($data['add_show_end_date']);
            //$endDateTime->setTimezone(new DateTimeZone('UTC'));
            $endDateTime->add(new DateInterval("P1D"));
            $endDate = $endDateTime->format("Y-m-d");
        } else {
            $endDateTime = new DateTime($data['add_show_start_date']);
            //$endDateTime->setTimezone(new DateTimeZone('UTC'));
            $endDateTime->add(new DateInterval("P1D"));
            $endDate = $endDateTime->format("Y-m-d");
        }

        //What we are doing here is checking if the show repeats or if
        //any repeating days have been checked. If not, then by default
        //the "selected" DOW is the initial day.
        //DOW in local time.
        $startDow = date("w", $startDateTime->getTimestamp());
        if (!$data['add_show_repeats']) {
            $data['add_show_day_check'] = array($startDow);
        } elseif ($data['add_show_repeats'] && $data['add_show_day_check'] == "") {
            $data['add_show_day_check'] = array($startDow);
        }

        //find repeat type or set to a non repeating show.
        $repeatType = ($data['add_show_repeats']) ? $data['add_show_repeat_type'] : -1;

        if ($data['add_show_id'] == -1) {
            $ccShow = new CcShow();
        } else {
            $ccShow = CcShowQuery::create()->findPK($data['add_show_id']);
        }
        $ccShow->setDbName($data['add_show_name']);
        $ccShow->setDbDescription($data['add_show_description']);
        $ccShow->setDbUrl($data['add_show_url']);
        $ccShow->setDbGenre($data['add_show_genre']);
        $ccShow->setDbColor($data['add_show_color']);
        $ccShow->setDbBackgroundColor($data['add_show_background_color']);
        $ccShow->setDbLiveStreamUsingAirtimeAuth($data['cb_airtime_auth'] == 1);
        $ccShow->setDbLiveStreamUsingCustomAuth($data['cb_custom_auth'] == 1);
        $ccShow->setDbLiveStreamUser($data['custom_username']);
        $ccShow->setDbLiveStreamPass($data['custom_password']);
        $ccShow->save();

        $showId = $ccShow->getDbId();

        $isRecorded = (isset($data['add_show_record']) && $data['add_show_record']) ? 1 : 0;

        if ($data['add_show_id'] != -1) {
            $show = new Application_Model_Show($showId);
            $show->deletePossiblyInvalidInstances($data, $endDate, $isRecorded, $repeatType);
        }

        //check if we are adding or updating a show, and if updating
        //erase all the show's show_days information first.
        if ($data['add_show_id'] != -1) {
            CcShowDaysQuery::create()->filterByDbShowId($data['add_show_id'])->delete();
        }

        //don't set day for monthly repeat type, it's invalid.
        if ($data['add_show_repeats'] && $data['add_show_repeat_type'] == 2) {
            $showDay = new CcShowDays();
            $showDay->setDbFirstShow($startDateTime->format("Y-m-d"));
            $showDay->setDbLastShow($endDate);
            $showDay->setDbStartTime($startDateTime->format("H:i:s"));
            $showDay->setDbTimezone(date_default_timezone_get());
            $showDay->setDbDuration($data['add_show_duration']);
            $showDay->setDbRepeatType($repeatType);
            $showDay->setDbShowId($showId);
            $showDay->setDbRecord($isRecorded);
            $showDay->save();
        } else {
            foreach ($data['add_show_day_check'] as $day) {
                $daysAdd=0;
                $startDateTimeClone = clone $startDateTime;
                if ($startDow !== $day) {
                    if ($startDow > $day)
                        $daysAdd = 6 - $startDow + 1 + $day;
                    else
                        $daysAdd = $day - $startDow;

                    $startDateTimeClone->add(new DateInterval("P".$daysAdd."D"));
                }
                if (is_null($endDate) || $startDateTimeClone->getTimestamp() <= $endDateTime->getTimestamp()) {
                    $showDay = new CcShowDays();
                    $showDay->setDbFirstShow($startDateTimeClone->format("Y-m-d"));
                    $showDay->setDbLastShow($endDate);
                    $showDay->setDbStartTime($startDateTimeClone->format("H:i"));
                    $showDay->setDbTimezone(date_default_timezone_get());
                    $showDay->setDbDuration($data['add_show_duration']);
                    $showDay->setDbDay($day);
                    $showDay->setDbRepeatType($repeatType);
                    $showDay->setDbShowId($showId);
                    $showDay->setDbRecord($isRecorded);
                    $showDay->save();
                }
            }
        }

        //check if we are adding or updating a show, and if updating
        //erase all the show's future show_rebroadcast information first.
        if (($data['add_show_id'] != -1) && isset($data['add_show_rebroadcast']) && $data['add_show_rebroadcast']) {
            CcShowRebroadcastQuery::create()
                ->filterByDbShowId($data['add_show_id'])
                ->delete();
        }
        //adding rows to cc_show_rebroadcast
        /* TODO: Document magic constant 10 and define it properly somewhere
            --RG */
        if (($isRecorded && $data['add_show_rebroadcast']) && ($repeatType != -1)) {
            for ($i=1; $i<=10; $i++) {
                if ($data['add_show_rebroadcast_date_'.$i]) {
                    $showRebroad = new CcShowRebroadcast();
                    $showRebroad->setDbDayOffset($data['add_show_rebroadcast_date_'.$i]);
                    $showRebroad->setDbStartTime($data['add_show_rebroadcast_time_'.$i]);
                    $showRebroad->setDbShowId($showId);
                    $showRebroad->save();
                }
            }
        } elseif ($isRecorded && $data['add_show_rebroadcast'] && ($repeatType == -1)) {
            for ($i=1; $i<=10; $i++) {
                if ($data['add_show_rebroadcast_date_absolute_'.$i]) {
                    //$con = Propel::getConnection(CcShowPeer::DATABASE_NAME);
                    //$sql = "SELECT date '{$data['add_show_rebroadcast_date_absolute_'.$i]}' - date '{$data['add_show_start_date']}' ";
                    $sql = <<<SQL
SELECT :rebroadcast::date - :start::date
SQL;

                    $offset_days = 
                        Application_Common_Database::prepareAndExecute($sql,
                            array(
                                'rebroadcast' => 
                                $data["add_show_rebroadcast_date_absolute_$i"],
                                'start' => 
                                $data['add_show_start_date']), "column" );

                    //$r = $con->query($sql);
                    //$offset_days = $r->fetchColumn(0);

                    $showRebroad = new CcShowRebroadcast();
                    $showRebroad->setDbDayOffset($offset_days." days");
                    $showRebroad->setDbStartTime($data['add_show_rebroadcast_time_absolute_'.$i]);
                    $showRebroad->setDbShowId($showId);
                    $showRebroad->save();
                }
            }
        }

        //check if we are adding or updating a show, and if updating
        //erase all the show's show_rebroadcast information first.
        if ($data['add_show_id'] != -1) {
            CcShowHostsQuery::create()->filterByDbShow($data['add_show_id'])->delete();
        }
        if (is_array($data['add_show_hosts'])) {
            //add selected hosts to cc_show_hosts table.
            foreach ($data['add_show_hosts'] as $host) {
                $showHost = new CcShowHosts();
                $showHost->setDbShow($showId);
                $showHost->setDbHost($host);
                $showHost->save();
            }
        }

        if ($data['add_show_id'] != -1) {
            $con = Propel::getConnection(CcSchedulePeer::DATABASE_NAME);
            $con->beginTransaction();
            

            //current timesamp in UTC.
            $current_timestamp = gmdate("Y-m-d H:i:s");

            try {
                //update the status flag in cc_schedule.
                $instances = CcShowInstancesQuery::create()
                    ->filterByDbEnds($current_timestamp, Criteria::GREATER_THAN)
                    ->filterByDbShowId($data['add_show_id'])
                    ->find($con);

                foreach ($instances as $instance) {
                    $instance->updateScheduleStatus($con);
                }

                $con->commit();
            } catch (Exception $e) {
                $con->rollback();
                Logging::info("Couldn't update schedule status.");
                Logging::info($e->getMessage());
            }
        }

        Application_Model_Show::populateShowUntil($showId);
        Application_Model_RabbitMq::PushSchedule();

        return $showId;
    }

    /**
     * Generate repeating show instances for a single show up to the given date.
     * It will always try to use enddate from DB but if that's empty, it will use
     * time now.
     *
     * @param int $p_showId
     */
    public static function populateShowUntil($p_showId)
    {
        $con = Propel::getConnection();
        $date = Application_Model_Preference::GetShowsPopulatedUntil();

        if (is_null($date)) {
            $p_populateUntilDateTime = new DateTime("now", new DateTimeZone('UTC'));
            Application_Model_Preference::SetShowsPopulatedUntil($p_populateUntilDateTime);
        } else {
            $p_populateUntilDateTime = $date;
        }

        $stmt = $con->prepare("SELECT * FROM cc_show_days WHERE show_id = :show_id");
        $stmt->bindParam(':show_id', $p_showId);
        $stmt->execute();

        $res = $stmt->fetchAll();

        foreach ($res as $showDaysRow) {
            Application_Model_Show::populateShow($showDaysRow, $p_populateUntilDateTime);
        }
    }

    /**
     * We are going to use cc_show_days as a template, to generate Show Instances. This function
     * is basically a dispatcher that looks at the show template, and sends it to the correct function
     * so that Show Instance generation can begin. After the all show instances have been created, pushes
     * the schedule to Pypo.
     *
     * @param array $p_showRow
     *        A row from cc_show_days table
     * @param DateTime $p_populateUntilDateTime
     *        DateTime object in UTC time.
     */
    private static function populateShow($p_showDaysRow, $p_populateUntilDateTime)
    {
        // TODO : use constants instead of int values here? or maybe php will
        // get enum types by the time somebody gets around to fix this. -- RG
        if ($p_showDaysRow["repeat_type"] == -1) {
            Application_Model_Show::populateNonRepeatingShow($p_showDaysRow, $p_populateUntilDateTime);
        } elseif ($p_showDaysRow["repeat_type"] == 0) {
            Application_Model_Show::populateRepeatingShow($p_showDaysRow, $p_populateUntilDateTime, 'P7D');
        } elseif ($p_showDaysRow["repeat_type"] == 1) {
            Application_Model_Show::populateRepeatingShow($p_showDaysRow, $p_populateUntilDateTime, 'P14D');
        } elseif ($p_showDaysRow["repeat_type"] == 2) {
            Application_Model_Show::populateRepeatingShow($p_showDaysRow, $p_populateUntilDateTime, 'P1M');
        }
        Application_Model_RabbitMq::PushSchedule();
    }

    /**
     * Creates a single show instance. If the show is recorded, it may have multiple
     * rebroadcast dates, and so this function will create those as well.
     *
     * @param array $p_showRow
     *        A row from cc_show_days table
     * @param DateTime $p_populateUntilDateTime
     *        DateTime object in UTC time.
     */
    private static function populateNonRepeatingShow($p_showRow, $p_populateUntilDateTime)
    {
        $show_id    = $p_showRow["show_id"];
        $first_show = $p_showRow["first_show"]; //non-UTC
        $start_time = $p_showRow["start_time"]; //non-UTC
        $duration   = $p_showRow["duration"];
        $record     = $p_showRow["record"];
        $timezone   = $p_showRow["timezone"];
        $start      = $first_show." ".$start_time;

        //start & end UTC DateTimes for the show.
        list($utcStartDateTime, $utcEndDateTime) = Application_Model_Show::createUTCStartEndDateTime($start, $duration, $timezone);
        if ($utcStartDateTime->getTimestamp() < $p_populateUntilDateTime->getTimestamp()) {
            $currentUtcTimestamp = gmdate("Y-m-d H:i:s");

            $show = new Application_Model_Show($show_id);
            if ($show->hasInstance()) {
                $ccShowInstance = $show->getInstance();
                $newInstance = false;
            } else {
                $ccShowInstance = new CcShowInstances();
                $newInstance = true;
            }

            if ($newInstance || $ccShowInstance->getDbStarts() > $currentUtcTimestamp) {
                $ccShowInstance->setDbShowId($show_id);
                $ccShowInstance->setDbStarts($utcStartDateTime);
                $ccShowInstance->setDbEnds($utcEndDateTime);
                $ccShowInstance->setDbRecord($record);
                $ccShowInstance->save();
            }

            $show_instance_id = $ccShowInstance->getDbId();
            $showInstance = new Application_Model_ShowInstance($show_instance_id);

            if (!$newInstance) {
                $showInstance->correctScheduleStartTimes();
            }

            $sql = "SELECT * FROM cc_show_rebroadcast WHERE show_id=:show_id";
            $rebroadcasts = Application_Common_Database::prepareAndExecute($sql,
                array( ':show_id' => $show_id ), 'all');

            if ($showInstance->isRecorded()) {
                $showInstance->deleteRebroadcasts();
                self::createRebroadcastInstances($rebroadcasts, $currentUtcTimestamp, $show_id, $show_instance_id, $start, $duration, $timezone);
            }
        }
    }

    /**
     * Creates a 1 or more than 1 show instances (user has stated this show repeats). If the show
     * is recorded, it may have multiple rebroadcast dates, and so this function will create
     * those as well.
     *
     * @param array $p_showRow
     *        A row from cc_show_days table
     * @param DateTime $p_populateUntilDateTime
     *        DateTime object in UTC time. "shows_populated_until" date YY-mm-dd in cc_pref
     * @param string $p_interval
     *        Period of time between repeating shows (in php DateInterval notation 'P7D')
     */
    private static function populateRepeatingShow($p_showDaysRow, $p_populateUntilDateTime, $p_interval)
    {
        $show_id       = $p_showDaysRow["show_id"];
        $next_pop_date = $p_showDaysRow["next_pop_date"];
        $first_show    = $p_showDaysRow["first_show"]; //non-UTC
        $last_show     = $p_showDaysRow["last_show"]; //non-UTC
        $start_time    = $p_showDaysRow["start_time"]; //non-UTC
        $duration      = $p_showDaysRow["duration"];
        $day           = $p_showDaysRow["day"];
        $record        = $p_showDaysRow["record"];
        $timezone      = $p_showDaysRow["timezone"];

        $currentUtcTimestamp = gmdate("Y-m-d H:i:s");

        if (isset($next_pop_date)) {
            $start = $next_pop_date." ".$start_time;
        } else {
            $start = $first_show." ".$start_time;
        }

        $utcStartDateTime = Application_Common_DateHelper::ConvertToUtcDateTime($start, $timezone);
        //convert $last_show into a UTC DateTime object, or null if there is no last show.
        $utcLastShowDateTime = $last_show ? Application_Common_DateHelper::ConvertToUtcDateTime($last_show, $timezone) : null;

        $sql = "SELECT * FROM cc_show_rebroadcast WHERE show_id=:show_id";

        $rebroadcasts = Application_Common_Database::prepareAndExecute( $sql,
            array( ':show_id' => $show_id ), 'all');

        $show = new Application_Model_Show($show_id);

        while ($utcStartDateTime->getTimestamp() <= $p_populateUntilDateTime->getTimestamp()
                && (is_null($utcLastShowDateTime) || $utcStartDateTime->getTimestamp() < $utcLastShowDateTime->getTimestamp())){

            list($utcStartDateTime, $utcEndDateTime) = self::createUTCStartEndDateTime($start, $duration, $timezone);

            if ($show->hasInstanceOnDate($utcStartDateTime)) {
                $ccShowInstance = $show->getInstanceOnDate($utcStartDateTime);

                if ($ccShowInstance->getDbModifiedInstance()) {
                    //show instance on this date has been deleted.
                    list($start, $utcStartDateTime) = self::advanceRepeatingDate($p_interval, $start, $timezone);
                    continue;
                }

                $newInstance = false;
            } else {
                $ccShowInstance = new CcShowInstances();
                $newInstance = true;
            }

            /* When editing the start/end time of a repeating show, we don't want to
             * change shows that started in the past. So check the start time.
             */
            if ($newInstance || $ccShowInstance->getDbStarts() > $currentUtcTimestamp) {
                $ccShowInstance->setDbShowId($show_id);
                $ccShowInstance->setDbStarts($utcStartDateTime);
                $ccShowInstance->setDbEnds($utcEndDateTime);
                $ccShowInstance->setDbRecord($record);
                $ccShowInstance->save();
            }


            $show_instance_id = $ccShowInstance->getDbId();
            $showInstance = new Application_Model_ShowInstance($show_instance_id);

            /* If we are updating a show then make sure that the scheduled content within
             * the show is updated to the correct time. */
            if (!$newInstance) {
                $showInstance->correctScheduleStartTimes();
            }

            $showInstance->deleteRebroadcasts();
            self::createRebroadcastInstances($rebroadcasts, $currentUtcTimestamp, $show_id, $show_instance_id, $start, $duration, $timezone);
            list($start, $utcStartDateTime) = self::advanceRepeatingDate($p_interval, $start, $timezone);

        }

        Application_Model_Show::setNextPop($start, $show_id, $day);
    }

    private static function advanceRepeatingDate($p_interval, $start, $timezone)
    {
        $startDt = new DateTime($start, new DateTimeZone($timezone));
        if ($p_interval == 'P1M') {
            /* When adding months, there is a problem if we are on January 31st and add one month with PHP.
             * What ends up happening is that since February 31st doesn't exist, the date returned is
             * March 3rd. For now let's ignore the day and assume we are always working with the
             * first of each month, and use PHP to add 1 month to this (this will take care of rolling
             * over the years 2011->2012, etc.). Then let's append the actual day, and use the php
             * checkdate() function, to see if it is valid. If not, then we'll just skip this month. */

            /* pass in only the year and month (not the day) */
            $dt = new DateTime($startDt->format("Y-m"), new DateTimeZone($timezone));


            /* Keep adding 1 month, until we find the next month that contains the day
             * we are looking for (31st day for example) */
            do {
                $dt->add(new DateInterval($p_interval));
            } while (!checkdate($dt->format("m"), $startDt->format("d"), $dt->format("Y")));

            $dt->setDate($dt->format("Y"), $dt->format("m"), $startDt->format("d"));

        } else {
            $dt = new DateTime($start, new DateTimeZone($timezone));
            $dt->add(new DateInterval($p_interval));
        }

        $start = $dt->format("Y-m-d H:i:s");

        $dt->setTimezone(new DateTimeZone('UTC'));
        $utcStartDateTime = $dt;

        return array($start, $utcStartDateTime);
    }

    /*
     * @param   $p_start
     *              timestring format "Y-m-d H:i:s" (not UTC)
     * @param   $p_duration
     *              string time interval (h)h:(m)m(:ss)
     * @param   $p_timezone
     *              string "Europe/Prague"
     * @param   $p_offset
     *              array (days, hours, mins) used for rebroadcast shows.
     *
     * @return
     *      array of 2 DateTime objects, start/end time of the show in UTC.
     */
    private static function createUTCStartEndDateTime($p_start, $p_duration, $p_timezone=null, $p_offset=null)
    {
        $timezone = $p_timezone ? $p_timezone : date_default_timezone_get();

        $startDateTime = new DateTime($p_start, new DateTimeZone($timezone));
        if (isset($p_offset)) {
            $startDateTime->add(new DateInterval("P{$p_offset["days"]}DT{$p_offset["hours"]}H{$p_offset["mins"]}M"));
        }
        //convert time to UTC
        $startDateTime->setTimezone(new DateTimeZone('UTC'));

        $endDateTime = clone $startDateTime;
        $duration = explode(":", $p_duration);
        list($hours, $mins) = array_slice($duration, 0, 2);
        $endDateTime->add(new DateInterval("PT{$hours}H{$mins}M"));

        return array($startDateTime, $endDateTime);
    }

    /*  Create rebroadcast instances for a created show marked for recording
     *
     *  @param $p_rebroadcasts
     *      rows gotten from the db table cc_show_rebroadcasts, tells airtime when to schedule the rebroadcasts.
     *  @param $p_currentUtcTimestamp
     *      a timestring in format "Y-m-d H:i:s", current UTC time.
     *  @param $p_showId
     *      int of the show it belongs to (from cc_show)
     *  @param $p_showInstanceId
     *      the instance id of the created recorded show instance
     *      (from cc_show_instances), used to associate rebroadcasts to this show.
     *  @param $p_startTime
     *      a timestring in format "Y-m-d H:i:s" in the timezone, not UTC of the rebroadcasts' parent recorded show.
     *  @param $p_duration
     *      string time interval (h)h:(m)m:(ss) length of the show.
     *  @param $p_timezone
     *      string of user's timezone "Europe/Prague"
     *
     */
    private static function createRebroadcastInstances($p_rebroadcasts, $p_currentUtcTimestamp, $p_showId, $p_showInstanceId, $p_startTime, $p_duration, $p_timezone=null)
    {
        //Y-m-d
        //use only the date part of the show start time stamp for the offsets to work properly.
        $date = explode(" ", $p_startTime);
        $start_date = $date[0];

        foreach ($p_rebroadcasts as $rebroadcast) {

            $days = explode(" ", $rebroadcast["day_offset"]);
            $time = explode(":", $rebroadcast["start_time"]);
            $offset = array("days"=>$days[0], "hours"=>$time[0], "mins"=>$time[1]);

            list($utcStartDateTime, $utcEndDateTime) = Application_Model_Show::createUTCStartEndDateTime($start_date, $p_duration, $p_timezone, $offset);

            if ($utcStartDateTime->format("Y-m-d H:i:s") > $p_currentUtcTimestamp) {

                $newRebroadcastInstance = new CcShowInstances();
                $newRebroadcastInstance->setDbShowId($p_showId);
                $newRebroadcastInstance->setDbStarts($utcStartDateTime);
                $newRebroadcastInstance->setDbEnds($utcEndDateTime);
                $newRebroadcastInstance->setDbRecord(0);
                $newRebroadcastInstance->setDbRebroadcast(1);
                $newRebroadcastInstance->setDbOriginalShow($p_showInstanceId);
                $newRebroadcastInstance->save();
            }
        }
    }

    /**
     * Get all the show instances in the given time range (inclusive).
     *
     * @param DateTime $start_timestamp
     *      In UTC time.
     * @param DateTime $end_timestamp
     *      In UTC time.
     * @param  unknown_type $excludeInstance
     * @param  boolean      $onlyRecord
     * @return array
     */
    public static function getShows($start_timestamp, $end_timestamp, $onlyRecord=FALSE)
    {
        //UTC DateTime object
        $showsPopUntil = Application_Model_Preference::GetShowsPopulatedUntil();
        //if application is requesting shows past our previous populated until date, generate shows up until this point.
        if (is_null($showsPopUntil) || $showsPopUntil->getTimestamp() < $end_timestamp->getTimestamp()) {
            Application_Model_Show::populateAllShowsInRange($showsPopUntil, $end_timestamp);
            Application_Model_Preference::SetShowsPopulatedUntil($end_timestamp);
        }

        $sql = <<<SQL
SELECT si1.starts            AS starts,
       si1.ends              AS ends,
       si1.record            AS record,
       si1.rebroadcast       AS rebroadcast,
       si2.starts            AS parent_starts,
       si1.instance_id       AS record_id,
       si1.show_id           AS show_id,
       show.name             AS name,
       show.color            AS color,
       show.background_color AS background_color,
       si1.file_id           AS file_id,
       si1.id                AS instance_id,
       si1.created           AS created,
       si1.last_scheduled    AS last_scheduled,
       si1.time_filled       AS time_filled,
       f.soundcloud_id
FROM cc_show_instances      AS si1
LEFT JOIN cc_show_instances AS si2  ON si1.instance_id = si2.id
LEFT JOIN cc_show           AS show ON show.id         = si1.show_id
LEFT JOIN cc_files          AS f    ON f.id            = si1.file_id
WHERE si1.modified_instance = FALSE
SQL;
        //only want shows that are starting at the time or later.
        $start_string = $start_timestamp->format("Y-m-d H:i:s");
        $end_string = $end_timestamp->format("Y-m-d H:i:s");
        if ($onlyRecord) {
            $sql .= " AND (si1.starts >= :start::TIMESTAMP AND si1.starts < :end::TIMESTAMP)";
            $sql .= " AND (si1.record = 1)";

            return Application_Common_Database::prepareAndExecute( $sql,
                array( ':start' => $start_string,
                       ':end'   => $end_string ), 'all');

        } else {
            $sql .= " ". <<<SQL
AND ((si1.starts >= :start1::TIMESTAMP AND si1.starts < :end1::TIMESTAMP)
     OR (si1.ends > :start2::TIMESTAMP AND si1.ends <= :end2::TIMESTAMP)
     OR (si1.starts <= :start3::TIMESTAMP AND si1.ends >= :end3::TIMESTAMP))
SQL;
            return Application_Common_Database::prepareAndExecute( $sql,
                array(
                    'start1' => $start_string,
                    'start2' => $start_string,
                    'start3' => $start_string,
                    'end1'   => $end_string,
                    'end2'   => $end_string,
                    'end3'   => $end_string
                ), 'all');
        }
    }

    private static function setNextPop($next_date, $show_id, $day)
    {
        $nextInfo = explode(" ", $next_date);

        $repeatInfo = CcShowDaysQuery::create()
            ->filterByDbShowId($show_id)
            ->filterByDbDay($day)
            ->findOne();

        $repeatInfo->setDbNextPopDate($nextInfo[0])
            ->save();
    }

    /**
     * Generate all the repeating shows in the given range.
     *
     * @param DateTime $p_startTimestamp
     *         In UTC format.
     * @param DateTime $p_endTimestamp
     *         In UTC format.
     */
    public static function populateAllShowsInRange($p_startTimestamp, $p_endTimestamp)
    {
        $con = Propel::getConnection();

        $endTimeString = $p_endTimestamp->format("Y-m-d H:i:s");
        if (!is_null($p_startTimestamp)) {
            $startTimeString = $p_startTimestamp->format("Y-m-d H:i:s");
        } else {
            $today_timestamp = new DateTime("now", new DateTimeZone("UTC"));
            $startTimeString = $today_timestamp->format("Y-m-d H:i:s");
        }

        $stmt = $con->prepare("
            SELECT * FROM cc_show_days
            WHERE last_show IS NULL
            OR first_show < :endTimeString AND last_show > :startTimeString");

        $stmt->bindParam(':endTimeString', $endTimeString);
        $stmt->bindParam(':startTimeString', $startTimeString);
        $stmt->execute();

        $res = $stmt->fetchAll();
        foreach ($res as $row) {
            Application_Model_Show::populateShow($row, $p_endTimestamp);
        }
    }

    /**
     *
     * @param DateTime $start
     *          -in UTC time
     * @param DateTime $end
     *          -in UTC time
     * @param boolean $editable
     */
    public static function &getFullCalendarEvents($p_start, $p_end, $p_editable=false)
    {
        $events   = array();
        $interval = $p_start->diff($p_end);
        $days     = $interval->format('%a');
        $shows    = Application_Model_Show::getShows($p_start, $p_end);
        $nowEpoch = time();
        $content_count = Application_Model_ShowInstance::getContentCount(
            $p_start, $p_end);
        $timezone = date_default_timezone_get();

        foreach ($shows as $show) {
            $options = array();

            //only bother calculating percent for week or day view.
            if (intval($days) <= 7) {
                $options["percent"] = Application_Model_Show::getPercentScheduled($show["starts"], $show["ends"], $show["time_filled"]);
            }

            $utc = new DateTimeZone("UTC");

            if (isset($show["parent_starts"])) {
                $parentStartsDT = new DateTime($show["parent_starts"], $utc);
                $parentStartsEpoch = intval($parentStartsDT->format("U"));
            }

            $startsDT = DateTime::createFromFormat("Y-m-d G:i:s", 
                $show["starts"],$utc);
            $endsDT   = DateTime::createFromFormat("Y-m-d G:i:s",
                $show["ends"], $utc);

            $startsEpochStr = $startsDT->format("U");
            $endsEpochStr   = $endsDT->format("U");

            $startsEpoch    = intval($startsEpochStr);
            $endsEpoch      = intval($endsEpochStr);

            $startsDT->setTimezone(new DateTimeZone($timezone));
            $endsDT->setTimezone(new DateTimeZone($timezone));

            if( $p_editable ) {
                if ($show["record"] && $nowEpoch > $startsEpoch) {
                    $options["editable"] = false;
                } elseif ($show["rebroadcast"] &&
                    $nowEpoch > $parentStartsEpoch) {
                    $options["editable"] = false;
                } elseif ($nowEpoch < $endsEpoch) {
                    $options["editable"] = true;
                }
            }


            $showInstance = new Application_Model_ShowInstance(
                $show["instance_id"]);

            $options["show_empty"] = (array_key_exists($show['instance_id'],
                $content_count)) ? 0 : 1;

            $events[] = &self::makeFullCalendarEvent($show, $options,
                $startsDT, $endsDT, $startsEpochStr, $endsEpochStr);
        }

        return $events;
    }

    /**
     * Calculates the percentage of a show scheduled given the start and end times in date/time format
     * and the time_filled as the total time the schow is scheduled for in time format.
     **/
    private static function getPercentScheduled($p_starts, $p_ends, $p_time_filled)
    {
        $durationSeconds = (strtotime($p_ends) - strtotime($p_starts));
        $time_filled = Application_Model_Schedule::WallTimeToMillisecs($p_time_filled) / 1000;
        $percent = ceil(( $time_filled / $durationSeconds) * 100);

        return $percent;
    }

    private static function &makeFullCalendarEvent(&$show, $options=array(), $startDateTime, $endDateTime, $startsEpoch, $endsEpoch)
    {
        $event = array();

        $event["id"]            = intval($show["instance_id"]);
        $event["title"]         = $show["name"];
        $event["start"]         = $startDateTime->format("Y-m-d H:i:s");
        $event["startUnix"]     = $startsEpoch;
        $event["end"]           = $endDateTime->format("Y-m-d H:i:s");
        $event["endUnix"]       = $endsEpoch;
        $event["allDay"]        = false;
        $event["showId"]        = intval($show["show_id"]);
        $event["record"]        = intval($show["record"]);
        $event["rebroadcast"]   = intval($show["rebroadcast"]);
        $event["soundcloud_id"] = is_null($show["soundcloud_id"])
            ? -1 : $show["soundcloud_id"];

        //event colouring
        if ($show["color"] != "") {
            $event["textColor"] = "#".$show["color"];
        }

        if ($show["background_color"] != "") {
            $event["color"] = "#".$show["background_color"];
        }

        foreach ($options as $key => $value) {
            $event[$key] = $value;
        }

        return $event;
    }

    /* Takes in a UTC DateTime object.
     * Converts this to local time, since cc_show days
     * requires local time. */
    public function setShowFirstShow($p_dt)
    {
        //clone object since we are modifying it and it was passed by reference.
        $dt = clone $p_dt;

        $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));

        $showDay = CcShowDaysQuery::create()
        ->filterByDbShowId($this->_showId)
        ->findOne();

        $showDay->setDbFirstShow($dt)->setDbStartTime($dt)
        ->save();

        //Logging::info("setting show's first show.");
    }

    /* Takes in a UTC DateTime object
     * Converts this to local time, since cc_show days
     * requires local time. */
    public function setShowLastShow($p_dt)
    {
        //clone object since we are modifying it and it was passed by reference.
        $dt = clone $p_dt;

        $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));

        //add one day since the Last Show date in CcShowDays is non-inclusive.
        $dt->add(new DateInterval("P1D"));

        $showDay = CcShowDaysQuery::create()
        ->filterByDbShowId($this->_showId)
        ->findOne();

        $showDay->setDbLastShow($dt)
        ->save();
    }

    /**
     * Given time $timeNow, returns the show being played right now.
     * Times are all in UTC time.
     *
     * @param  String $timeNow - current time (in UTC)
     * @return array  - show being played right now
     */
    public static function getCurrentShow($timeNow=null)
    {
        global $CC_CONFIG;
        $con = Propel::getConnection();
        if ($timeNow == null) {
            $date = new Application_Common_DateHelper;
            $timeNow = $date->getUtcTimestamp();
        }
        //TODO, returning starts + ends twice (once with an alias). Unify this after the 2.0 release. --Martin
        $sql = <<<SQL
SELECT si.starts AS start_timestamp,
       si.ends AS end_timestamp,
       s.name,
       s.id,
       si.id AS instance_id,
       si.record,
       s.url,
       starts,
       ends
FROM cc_show_instances si
     LEFT JOIN cc_show s
     ON si.show_id = s.id
WHERE si.show_id = s.id
  AND si.starts <= :timeNow1::timestamp
  AND si.ends > :timeNow2::timestamp
  AND modified_instance != TRUE
SQL;

        $stmt = $con->prepare($sql);
        $stmt->bindParam(':timeNow1', $timeNow);
        $stmt->bindParam(':timeNow2', $timeNow);

        if ($stmt->execute()) {
            $rows = $stmt->fetchAll();
        } else {
            $msg = implode(',', $stmt->errorInfo());
            throw new Exception("Error: $msg");
        }

        return $rows;
    }

    /**
     * Gets the current show, previous and next with an 2day window from
     * the given timeNow, so timeNow-2days and timeNow+2days.
     */
    public static function getPrevCurrentNext($p_timeNow)
    {
        global $CC_CONFIG;
        $con = Propel::getConnection();
        //
        //TODO, returning starts + ends twice (once with an alias). Unify this after the 2.0 release. --Martin
        $sql = <<<SQL
SELECT si.starts AS start_timestamp,
       si.ends AS end_timestamp,
       s.name,
       s.id,
       si.id AS instance_id,
       si.record,
       s.url,
       starts,
       ends
FROM cc_show_instances si
     LEFT JOIN cc_show s
     ON si.show_id = s.id
WHERE si.show_id = s.id
  AND si.starts > :timeNow1::timestamp - INTERVAL '2 days'
  AND si.ends < :timeNow2::timestamp + INTERVAL '2 days'
  AND modified_instance != TRUE
ORDER BY si.starts
SQL;

        $stmt = $con->prepare($sql);

        $stmt->bindValue(':timeNow1', $p_timeNow);
        $stmt->bindValue(':timeNow2', $p_timeNow);

        if ($stmt->execute()) {
            $rows = $stmt->fetchAll();
        } else {
            $msg = implode(',', $stmt->errorInfo());
            throw new Exception("Error: $msg");
        }

        $numberOfRows = count($rows);

        $results['previousShow'] = array();
        $results['currentShow']  = array();
        $results['nextShow']     = array();

        $timeNowAsMillis = strtotime($p_timeNow);

        for ($i = 0; $i < $numberOfRows; ++$i) {
            //Find the show that is within the current time.
            if ((strtotime($rows[$i]['starts']) <= $timeNowAsMillis)
                && (strtotime($rows[$i]['ends']) > $timeNowAsMillis)) {
                if ($i-1 >= 0) {
                    $results['previousShow'][0] = array(
                                "id"              => $rows[$i-1]['id'],
                                "instance_id"     => $rows[$i-1]['instance_id'],
                                "name"            => $rows[$i-1]['name'],
                                "url"             => $rows[$i-1]['url'],
                                "start_timestamp" => $rows[$i-1]['start_timestamp'],
                                "end_timestamp"   => $rows[$i-1]['end_timestamp'],
                                "starts"          => $rows[$i-1]['starts'],
                                "ends"            => $rows[$i-1]['ends'],
                                "record"          => $rows[$i-1]['record'],
                                "type"            => "show");
                }

                $results['currentShow'][0] =  $rows[$i];

                if (isset($rows[$i+1])) {
                    $results['nextShow'][0] =  array(
                                "id"              => $rows[$i+1]['id'],
                                "instance_id"     => $rows[$i+1]['instance_id'],
                                "name"            => $rows[$i+1]['name'],
                                "url"             => $rows[$i+1]['url'],
                                "start_timestamp" => $rows[$i+1]['start_timestamp'],
                                "end_timestamp"   => $rows[$i+1]['end_timestamp'],
                                "starts"          => $rows[$i+1]['starts'],
                                "ends"            => $rows[$i+1]['ends'],
                                "record"          => $rows[$i+1]['record'],
                                "type"            => "show");
                }
                break;
            }
            //Previous is any row that ends after time now capture it in case we need it later.
            if (strtotime($rows[$i]['ends']) < $timeNowAsMillis ) {
                $previousShowIndex = $i;
            }
            //if we hit this we know we've gone to far and can stop looping.
            if (strtotime($rows[$i]['starts']) > $timeNowAsMillis) {
                $results['nextShow'][0] = array(
                                "id"              => $rows[$i]['id'],
                                "instance_id"     => $rows[$i]['instance_id'],
                                "name"            => $rows[$i]['name'],
                                "url"             => $rows[$i]['url'],
                                "start_timestamp" => $rows[$i]['start_timestamp'],
                                "end_timestamp"   => $rows[$i]['end_timestamp'],
                                "starts"          => $rows[$i]['starts'],
                                "ends"            => $rows[$i]['ends'],
                                "record"          => $rows[$i]['record'],
                                "type"            => "show");
                break;
            }
        }
        //If we didn't find a a current show because the time didn't fit we may still have
        //found a previous show so use it.
        if (count($results['previousShow']) == 0 && isset($previousShowIndex)) {
                $results['previousShow'][0] = array(
                    "id"              => $rows[$previousShowIndex]['id'],
                    "instance_id"     => $rows[$previousShowIndex]['instance_id'],
                    "name"            => $rows[$previousShowIndex]['name'],
                    "start_timestamp" => $rows[$previousShowIndex]['start_timestamp'],
                    "end_timestamp"   => $rows[$previousShowIndex]['end_timestamp'],
                    "starts"          => $rows[$previousShowIndex]['starts'],
                    "ends"            => $rows[$previousShowIndex]['ends'],
                    "record"          => $rows[$previousShowIndex]['record'],
                    "type"            => "show");
        }

        return $results;
    }

    /**
     * Given a start time $timeStart and end time $timeEnd, returns the next $limit
     * number of shows within the time interval
     * If $timeEnd not given, shows within next 48 hours from $timeStart are returned
     * If $limit not given, all shows within the intervals are returned
     * Times are all in UTC time.
     *
     * @param  String $timeStart - interval start time (in UTC)
     * @param  int    $limit     - number of shows to return
     * @param  String $timeEnd   - interval end time (in UTC)
     * @return array  - the next $limit number of shows within the time interval
     */
    public static function getNextShows($timeStart, $limit = "ALL", $timeEnd = "")
    {
        // defaults to retrieving shows from next 2 days if no end time has
        // been specified
        if ($timeEnd == "") {
            $timeEnd = "'$timeStart' + INTERVAL '2 days'";
        }

        //TODO, returning starts + ends twice (once with an alias). Unify this after the 2.0 release. --Martin
        $sql = <<<SQL
SELECT si.starts AS start_timestamp,
       si.ends AS end_timestamp,
       s.name,
       s.id,
       si.id AS instance_id,
       si.record,
       s.url,
       starts,
       ends
FROM cc_show_instances si
     LEFT JOIN cc_show s
     ON si.show_id = s.id
WHERE si.show_id = s.id
  AND si.starts >= :timeStart::timestamp
  AND si.starts < :timeEnd::timestamp
  AND modified_instance != TRUE
ORDER BY si.starts
SQL;

        //PDO won't accept "ALL" as a limit value (complains it is not an
        //integer, and so we must completely remove the limit clause if we
        //want to show all results - MK
        if ($limit != "ALL") {
            $sql .= PHP_EOL."LIMIT :lim";
            $params =  array(
            ':timeStart' => $timeStart,
            ':timeEnd'   => $timeEnd,
            ':lim'       => $limit);
        } else {
            $params = array(
            ':timeStart' => $timeStart,
            ':timeEnd'   => $timeEnd);
        }

        return Application_Common_Database::prepareAndExecute( $sql, $params, 'all');
    }

    /**
     * Convert the columns given in the array $columnsToConvert in the
     * database result $rows to local timezone.
     *
     * @param type $rows             arrays of arrays containing database query result
     * @param type $columnsToConvert array of column names to convert
     */
    public static function convertToLocalTimeZone(&$rows, $columnsToConvert)
    {
        if (!is_array($rows)) {
            return;
        }
        foreach ($rows as &$row) {
            foreach ($columnsToConvert as $column) {
                $row[$column] = Application_Common_DateHelper::ConvertToLocalDateTimeString($row[$column]);
            }
        }
    }

    public static function getMaxLengths()
    {
        $con = Propel::getConnection();
        $sql = <<<SQL
SELECT column_name, character_maximum_length FROM information_schema.columns
WHERE table_name = 'cc_show' AND character_maximum_length > 0
SQL;
        $result     = $con->query($sql)->fetchAll();
        $assocArray = array();
        foreach ($result as $row) {
            $assocArray[$row['column_name']] = $row['character_maximum_length'];
        }
        return $assocArray;
    }
}
