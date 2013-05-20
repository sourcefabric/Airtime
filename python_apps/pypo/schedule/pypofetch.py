# -*- coding: utf-8 -*-
"""
    schedule.pypofetch
    ~~~~~~~~~

    The purpose of this module is to parse schedule data received from the
    server and perform various tasks based on this schedule such as pre-cache
    tracks that are scheduled, do sanity checks on the schedule sent, perform
    cache clean-up etc.

    The schedule for the immediate future is pushed via RabbitMQ when a user is 
    editing the schedule, however a pull for this schedule is also initiated at 
    a specified interval in order to keep current when there is no user 
    activity.

    :author: (c) 2012 by Martin Konecny.
    :license: GPLv3, see LICENSE for more details.
"""

import os
import sys
import time
import logging.config
import json
import copy
import subprocess
import signal
import traceback

from Queue import Empty
from datetime import datetime
from threading import Thread
from subprocess import Popen, PIPE

from api_clients import api_client
from std_err_override import LogWriter

import pure

# configure logging
logging_cfg = os.path.join(os.path.dirname(__file__), "../configs/logging.cfg")
logging.config.fileConfig(logging_cfg)
logger = logging.getLogger()
LogWriter.override_std_err(logger)

def keyboardInterruptHandler(signum, frame):
    logger = logging.getLogger()
    logger.info('\nKeyboard Interrupt\n')
    sys.exit(0)
signal.signal(signal.SIGINT, keyboardInterruptHandler)

POLL_INTERVAL = 1800

class PypoFetch(Thread):
    def __init__(self, pypoFetch_q, pypoPush_q, media_q, pypo_liquidsoap, 
            config):
        Thread.__init__(self)

        self.api_client = api_client.AirtimeApiClient()
        self.fetch_queue = pypoFetch_q
        self.push_queue = pypoPush_q
        self.media_prepare_queue = media_q
        self.last_update_schedule_timestamp = time.time()
        self.config = config
        self.listener_timeout = POLL_INTERVAL

        self.logger = logging.getLogger()
        self.pypo_liquidsoap = pypo_liquidsoap

        self.cache_dir = os.path.join(config["cache_dir"], "scheduler")
        self.logger.debug("Cache dir %s", self.cache_dir)

        try:
            if not os.path.isdir(dir):
                """
                We get here if path does not exist, or path does exist but
                is a file. We are not handling the second case, but don't
                think we actually care about handling it.
                """
                self.logger.debug("Cache dir does not exist. Creating...")
                os.makedirs(dir)
        except Exception, e:
            pass

        self.schedule_data = []
        self.logger.info("PypoFetch: init complete")

    """
    Handle a message from RabbitMQ, put it into our yucky global var.
    Hopefully there is a better way to do this.
    """
    def handle_message(self, message):
        try:
            self.logger.info("Received event from Pypo Message Handler: %s", 
                    message)

            m = json.loads(message)
            command = m['event_type']
            self.logger.info("Handling command: " + command)

            if command == 'update_schedule':
                self.schedule_data = m['schedule']
                self.process_schedule(self.schedule_data)
            elif command == 'reset_liquidsoap_bootstrap':
                self.set_bootstrap_variables()
            elif command == 'update_stream_setting':
                self.logger.info("Updating stream setting...")
                self.regenerate_liquidsoap_conf(m['setting'])
            elif command == 'update_stream_format':
                self.logger.info("Updating stream format...")
                self.pypo_liquidsoap.\
                        get_telnet_dispatcher().\
                        update_liquidsoap_stream_format(m['stream_format'])
            elif command == 'update_station_name':
                self.logger.info("Updating station name...")
                self.pypo_liquidsoap.\
                        get_telnet_dispatcher().\
                        update_liquidsoap_station_name(m['station_name'])
            elif command == 'update_transition_fade':
                self.logger.info("Updating transition_fade...")
                self.pypo_liquidsoap.\
                        get_telnet_dispatcher().\
                        update_liquidsoap_transition_fade(m['transition_fade'])
            elif command == 'switch_source':
                self.logger.info("switch_on_source show command received...")
                self.pypo_liquidsoap.\
                        get_telnet_dispatcher().\
                        switch_source(m['sourcename'], m['status'])
            elif command == 'disconnect_source':
                self.logger.info("disconnect_on_source show command received...")
                self.pypo_liquidsoap.get_telnet_dispatcher().\
                        disconnect_source(m['sourcename'])
            else:
                self.logger.info("Unknown command: %s" % command)

            # update timeout value
            if command == 'update_schedule':
                self.listener_timeout = POLL_INTERVAL
            else:
                self.listener_timeout = \
                        (self.last_update_schedule_timestamp - 
                                time.time() + 
                                POLL_INTERVAL)
                if self.listener_timeout < 0:
                    self.listener_timeout = 0
            self.logger.info("New timeout: %s" % self.listener_timeout)
        except Exception, e:
            top = traceback.format_exc()
            self.logger.error('Exception: %s', e)
            self.logger.error("traceback: %s", top)


    def switch_source_temp(self, sourcename, status):
        self.logger.debug('Setting source %s to "%s" status', sourcename, 
                status)

        command = "streams."
        if sourcename == "master_dj":
            command += "master_dj_"
        elif sourcename == "live_dj":
            command += "live_dj_"
        elif sourcename == "scheduled_play":
            command += "scheduled_play_"

        if status == "on":
            command += "start\n"
        else:
            command += "stop\n"

        return command

    """
    Initialize Liquidsoap environment
    """
    def set_bootstrap_variables(self):
        self.logger.debug('Getting information needed on bootstrap from DB')
        try:
            info = self.api_client.get_bootstrap_info()
        except Exception, e:
            self.logger.error('Unable to get bootstrap info.. Exiting pypo...')
            self.logger.error(str(e))

        self.logger.debug('info:%s', info)
        commands = []
        for k, v in info['switch_status'].iteritems():
            commands.append(self.switch_source_temp(k, v))

        stream_format = info['stream_label']
        station_name = info['station_name']
        fade = info['transition_fade']

        commands.append(('vars.stream_metadata_type %s\n' % stream_format).\
                encode('utf-8'))

        commands.append(('vars.station_name %s\n' % station_name).\
                encode('utf-8'))

        commands.append(('vars.default_dj_fade %s\n' % fade).encode('utf-8'))
        self.pypo_liquidsoap.get_telnet_dispatcher().telnet_send(commands)

        self.pypo_liquidsoap.clear_queue_tracker()

    def restart_liquidsoap(self):
        self.logger.info("Restarting Liquidsoap")
        subprocess.call(['/etc/init.d/airtime-liquidsoap', 'restart'])

        #Wait here and poll Liquidsoap until it has started up
        self.logger.info("Waiting for Liquidsoap to start")
        self.telnetliquidsoap.liquidsoap_startup_test()

        try:
            self.set_bootstrap_variables()
            #get the most up to date schedule, which will #initiate the process
            #of making sure Liquidsoap is playing the schedule
            self.persistent_manual_schedule_fetch(max_attempts=5)
        except Exception, e:
            self.logger.error(str(e))

    """
    TODO: This function needs to be way shorter, and refactored :/ - MK
    """
    def regenerate_liquidsoap_conf(self, setting):
        existing = {}

        setting = sorted(setting.items())
        try:
            fh = open('/etc/airtime/liquidsoap.cfg', 'r')
        except IOError, e:
            #file does not exist
            self.restart_liquidsoap()
            return

        self.logger.info("Reading existing config...")
        # read existing conf file and build dict
        while True:
            line = fh.readline()

            # empty line means EOF
            if not line:
                break

            line = line.strip()

            if not len(line) or line[0] == "#":
                continue

            try:
                key, value = line.split('=', 1)
            except ValueError:
                continue
            key = key.strip()
            value = value.strip()
            value = value.replace('"', '')
            if value == '' or value == "0":
                value = ''
            existing[key] = value
        fh.close()

        # dict flag for any change in config
        change = {}
        # this flag is to detect disable -> disable change
        # in that case, we don't want to restart even if there are changes.
        state_change_restart = {}
        #restart flag
        restart = False

        self.logger.info("Looking for changes...")
        # look for changes
        for k, s in setting:
            if "output_sound_device" in s[u'keyname'] or "icecast_vorbis_metadata" in s[u'keyname']:
                dump, stream = s[u'keyname'].split('_', 1)
                state_change_restart[stream] = False
                # This is the case where restart is required no matter what
                if (existing[s[u'keyname']] != str(s[u'value'])):
                    self.logger.info("'Need-to-restart' state detected for %s...", s[u'keyname'])
                    restart = True;
            elif "master_live_stream_port" in s[u'keyname'] or "master_live_stream_mp" in s[u'keyname'] or "dj_live_stream_port" in s[u'keyname'] or "dj_live_stream_mp" in s[u'keyname'] or "off_air_meta" in s[u'keyname']:
                if (existing[s[u'keyname']] != s[u'value']):
                    self.logger.info("'Need-to-restart' state detected for %s...", s[u'keyname'])
                    restart = True;
            else:
                stream, dump = s[u'keyname'].split('_', 1)
                if "_output" in s[u'keyname']:
                    if (existing[s[u'keyname']] != s[u'value']):
                        self.logger.info("'Need-to-restart' state detected for %s...", s[u'keyname'])
                        restart = True;
                        state_change_restart[stream] = True
                    elif (s[u'value'] != 'disabled'):
                        state_change_restart[stream] = True
                    else:
                        state_change_restart[stream] = False
                else:
                    # setting inital value
                    if stream not in change:
                        change[stream] = False
                    if not (s[u'value'] == existing[s[u'keyname']]):
                        self.logger.info("Keyname: %s, Current value: %s, New Value: %s", s[u'keyname'], existing[s[u'keyname']], s[u'value'])
                        change[stream] = True

        # set flag change for sound_device alway True
        self.logger.info("Change:%s, State_Change:%s...", change, 
                state_change_restart)

        for k, v in state_change_restart.items():
            if k == "sound_device" and v:
                restart = True
            elif v and change[k]:
                self.logger.info("'Need-to-restart' state detected for %s...", k)
                restart = True
        # rewrite
        if restart:
            self.restart_liquidsoap()
        else:
            self.logger.info("No change detected in setting...")
            self.update_liquidsoap_connection_status()

    def update_liquidsoap_connection_status(self):
        """
        updates the status of Liquidsoap connection to the streaming server
        This function updates the bootup time variable in Liquidsoap script
        """

        output = self.telnetliquidsoap.\
                get_telnet_dispatcher().\
                get_liquidsoap_connection_status(time.time())

        output_list = output.split("\r\n")
        stream_info = output_list[2]

        # streamin info is in the form of:
        # eg. s1:true,2:true,3:false
        streams = stream_info.split(",")
        self.logger.info(streams)

        fake_time = current_time + 1
        for s in streams:
            info = s.split(':')
            stream_id = info[0]
            status = info[1]
            if(status == "true"):
                self.api_client.notify_liquidsoap_status("OK", stream_id, 
                        str(fake_time))








    """
    Process the schedule
     - Reads the scheduled entries of a given range (actual time +/- 
        "prepare_ahead" / "cache_for")
     - playlists are prepared. (brought to liquidsoap format) and, if not 
        mounted via nsf, files are copied to the cache dir (Folder-structure: 
        cache/YYYY-MM-DD-hh-mm-ss)
     - runs the cleanup routine, to get rid of unused cached files
    """
    def process_schedule(self, schedule_data):
        self.last_update_schedule_timestamp = time.time()
        self.logger.debug(schedule_data)
        media = schedule_data["media"]
        media_filtered = {}

        try:
            """
            Make sure cache_dir exists
            """
            download_dir = self.cache_dir
            try:
                os.makedirs(download_dir)
            except Exception, e:
                pass

            media_copy = {}
            for key in media:
                media_item = media[key]
                if (media_item['type'] == 'file'):
                    self.sanity_check_media_item(media_item)
                    fileExt = os.path.splitext(media_item['uri'])[1]
                    dst = os.path.join(download_dir, 
                            unicode(media_item['id']) + fileExt)
                    media_item['dst'] = dst
                    media_item['file_ready'] = False
                    media_filtered[key] = media_item

                media_item['start'] = datetime.strptime(media_item['start'],
                        "%Y-%m-%d-%H-%M-%S")
                media_item['end'] = datetime.strptime(media_item['end'], 
                        "%Y-%m-%d-%H-%M-%S")
                media_copy[media_item['start']] = media_item

            self.media_prepare_queue.put(copy.copy(media_filtered))
        except Exception, e: self.logger.error("%s", e)

        # Send the data to pypo-push
        self.logger.debug("Pushing to pypo-push")
        self.push_queue.put(media_copy)


        # cleanup
        try: self.cache_cleanup(media)
        except Exception, e: self.logger.error("%s", e)

    #do basic validation of file parameters. Useful for debugging
    #purposes
    def sanity_check_media_item(self, media_item):
        start = datetime.strptime(media_item['start'], "%Y-%m-%d-%H-%M-%S")
        end = datetime.strptime(media_item['end'], "%Y-%m-%d-%H-%M-%S")

        length1 = pure.date_interval_to_seconds(end - start)
        length2 = media_item['cue_out'] - media_item['cue_in']

        if abs(length2 - length1) > 1:
            self.logger.error("end - start length: %s", length1)
            self.logger.error("cue_out - cue_in length: %s", length2)
            self.logger.error("Two lengths are not equal!!!")

    def is_file_opened(self, path):
        #Capture stderr to avoid polluting py-interpreter.log
        proc = Popen(["lsof", path], stdout=PIPE, stderr=PIPE)
        out = proc.communicate()[0].strip()
        return bool(out)

    def cache_cleanup(self, media):
        """Get list of all files in the cache dir and remove them if they aren't 
        being used anymore. Input dict() media, lists all files that are 
        scheduled or currently playing. Not being in this dict() means the 
        file is safe to remove."""
        cached_file_set = set(os.listdir(self.cache_dir))
        scheduled_file_set = set()

        for mkey in media:
            media_item = media[mkey]
            if media_item['type'] == 'file':
                fileExt = os.path.splitext(media_item['uri'])[1]
                scheduled_file_set.add(unicode(media_item["id"]) + fileExt)

        expired_files = cached_file_set - scheduled_file_set

        self.logger.debug("Files to remove " + str(expired_files))
        for f in expired_files:
            try:
                path = os.path.join(self.cache_dir, f)
                self.logger.debug("Removing %s" % path)

                #check if this file is opened (sometimes Liquidsoap is still
                #playing the file due to our knowledge of the track length
                #being incorrect!)
                if not self.is_file_opened(path):
                    os.remove(path)
                    self.logger.info("File '%s' removed" % path)
                else:
                    self.logger.info("File '%s' not removed. Still busy!", 
                            path)
            except Exception, e:
                self.logger.error("Problem removing file '%s'" % f)
                self.logger.error(traceback.format_exc())

    def manual_schedule_fetch(self):
        success, self.schedule_data = self.api_client.get_schedule()
        if success:
            self.process_schedule(self.schedule_data)
        return success

    def persistent_manual_schedule_fetch(self, max_attempts=1):
        success = False
        num_attempts = 0
        while not success and num_attempts < max_attempts:
            success = self.manual_schedule_fetch()
            num_attempts += 1

        return success


    def main(self):
        """Make sure all Liquidsoap queues are empty. This is important in the
        case where we've just restarted the pypo scheduler, but Liquidsoap 
        still is playing tracks. In this case let's just restart everything 
        from scratch so that we can repopulate our dictionary that keeps track 
        of what Liquidsoap is playing much more easily."""
        self.pypo_liquidsoap.clear_all_queues()

        self.set_bootstrap_variables()

        # Bootstrap: since we are just starting up, we need to grab the
        # most recent schedule.  After that we can just wait for updates.
        success = self.persistent_manual_schedule_fetch(max_attempts=5)

        if success:
            self.logger.info("Bootstrap schedule received: %s", 
                    self.schedule_data)

        loops = 1
        while True:
            self.logger.info("Loop #%s", loops)
            try:
                """
                our simple_queue.get() requires a timeout, in which case we
                fetch the Airtime schedule manually. It is important to fetch
                the schedule periodically because if we didn't, we would only
                get schedule updates via RabbitMq if the user was constantly
                using the Airtime interface.

                If the user is not using the interface, RabbitMq messages are 
                not sent, and we will have very stale (or non-existent!) data 
                about the schedule.

                Currently we are checking every POLL_INTERVAL seconds.
                """

                message = self.fetch_queue.get(block=True, 
                        timeout=self.listener_timeout)
                self.handle_message(message)
            except Empty, e:
                self.logger.info("Queue timeout. Fetching schedule manually")
                self.persistent_manual_schedule_fetch(max_attempts=5)
            except Exception, e:
                top = traceback.format_exc()
                self.logger.error('Exception: %s', e)
                self.logger.error("traceback: %s", top)

            loops += 1

    def run(self):
        self.main()
