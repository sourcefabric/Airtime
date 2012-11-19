import os
from os.path import join

from media.monitor.exceptions import NoConfigFile
from media.monitor.pure import LazyProperty
from media.monitor.config import MMConfig
from api_clients.api_client import AirtimeApiClient

# poor man's phantom types...
class SignalString(str): pass

class AirtimeInstance(object):
    """ AirtimeInstance is a class that abstracts away every airtime
    instance by providing all the necessary objects required to interact
    with the instance. ApiClient, configs, root_directory """

    @classmethod
    def root_make(cls, name, root):
        cfg = {
                'api_client' : join(root, 'etc/airtime/api_client.cfg'),
                'media_monitor' : join(root, 'etc/airtime/media-monitor.cfg'),
        }
        return cls(name, root, cfg)

    def __init__(self,name, root_path, config_paths):
        """ name is an internal name only """
        for cfg in ['api_client','media_monitor']:
            if cfg not in config_paths: raise NoConfigFile(config_paths)
            elif not os.path.exists(config_paths[cfg]):
                raise NoConfigFile(config_paths[cfg])
        self.name         = name
        self.config_paths = config_paths
        self.root_path    = root_path

    def signal(self, sig):
        if isinstance(sig, SignalString): return sig
        else: return SignalString("%s_%s" % (self.name, sig))

    def __str__(self):
        return "%s,%s(%s)" % (self.name, self.root_path, self.config_paths)

    @LazyProperty
    def api_client(self):
        return AirtimeApiClient(config_path=self.config_paths['api_client'])

    @LazyProperty
    def mm_config(self):
        return MMConfig(self.config_paths['media_monitor'])
