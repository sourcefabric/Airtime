from subprocess import Popen
import os

if os.geteuid() != 0:
    print "Please run this as root."
    sys.exit(1)

try:
    #update-rc.d init script
    p = Popen("update-rc.d airtime-media-monitor defaults >/dev/null 2>&1", shell=True)
    sts = os.waitpid(p.pid, 0)[1]

    #Start media-monitor daemon
    print "* Waiting for media-monitor processes to start..."
    p = Popen("/etc/init.d/airtime-media-monitor stop", shell=True)
    sts = os.waitpid(p.pid, 0)[1]
    p = Popen("/etc/init.d/airtime-media-monitor start-no-monit", shell=True)
    sts = os.waitpid(p.pid, 0)[1]
except Exception, e:
    print e
