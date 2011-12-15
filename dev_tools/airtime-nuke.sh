#!/bin/bash

echo "Are you sure? Press Enter to continue..."
read

service airtime-playout stop >/dev/null 2>&1
service airtime-media-monitor stop >/dev/null 2>&1
service airtime-show-recorder stop >/dev/null 2>&1

airtime-pypo-stop >/dev/null 2>&1
airtime-show-recorder-stop >/dev/null 2>&1

killall liquidsoap

rm -rf "/etc/airtime"
rm -rf "/var/log/airtime"
rm -rf "/etc/service/pypo"
rm -rf "/etc/service/pypo-liquidsoap"
rm -rf "/etc/service/recorder"
rm -rf "/usr/share/airtime"
rm -rf "/var/tmp/airtime"
rm -rf "/var/www/airtime"
rm -rf "/usr/bin/airtime-*"
rm -rf "/usr/lib/airtime"
rm -rf "/var/lib/airtime"
rm -rf "/var/tmp/airtime"
rm -rf "/opt/pypo"
rm -rf "/opt/recorder"
rm -rf "/srv/airtime"
rm -rf "/etc/monit/conf.d/airtime-monit.cfg"
rm -rf /etc/monit/conf.d/monit-airtime-*

echo "DROP DATABASE AIRTIME;" | su postgres -c psql
echo "DROP LANGUAGE plpgsql;" | su postgres -c psql
echo "DROP USER AIRTIME;" | su postgres -c psql
