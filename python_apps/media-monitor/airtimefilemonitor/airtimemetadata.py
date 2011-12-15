import os
import hashlib
import mutagen
import logging
import math
import re
from api_clients import api_client

"""
list of supported easy tags in mutagen version 1.20
['albumartistsort', 'musicbrainz_albumstatus', 'lyricist', 'releasecountry', 'date', 'performer', 'musicbrainz_albumartistid', 'composer', 'encodedby', 'tracknumber', 'musicbrainz_albumid', 'album', 'asin', 'musicbrainz_artistid', 'mood', 'copyright', 'author', 'media', 'length', 'version', 'artistsort', 'titlesort', 'discsubtitle', 'website', 'musicip_fingerprint', 'conductor', 'compilation', 'barcode', 'performer:*', 'composersort', 'musicbrainz_discid', 'musicbrainz_albumtype', 'genre', 'isrc', 'discnumber', 'musicbrainz_trmid', 'replaygain_*_gain', 'musicip_puid', 'artist', 'title', 'bpm', 'musicbrainz_trackid', 'arranger', 'albumsort', 'replaygain_*_peak', 'organization']
"""
class AirtimeMetadata:

    def __init__(self):

        self.airtime2mutagen = {\
        "MDATA_KEY_TITLE": "title",\
        "MDATA_KEY_CREATOR": "artist",\
        "MDATA_KEY_SOURCE": "album",\
        "MDATA_KEY_GENRE": "genre",\
        "MDATA_KEY_MOOD": "mood",\
        "MDATA_KEY_TRACKNUMBER": "tracknumber",\
        "MDATA_KEY_BPM": "bpm",\
        "MDATA_KEY_LABEL": "organization",\
        "MDATA_KEY_COMPOSER": "composer",\
        "MDATA_KEY_ENCODER": "encodedby",\
        "MDATA_KEY_CONDUCTOR": "conductor",\
        "MDATA_KEY_YEAR": "date",\
        "MDATA_KEY_URL": "website",\
        "MDATA_KEY_ISRC": "isrc",\
        "MDATA_KEY_COPYRIGHT": "copyright",\
        }

        self.mutagen2airtime = {\
        "title": "MDATA_KEY_TITLE",\
        "artist": "MDATA_KEY_CREATOR",\
        "album": "MDATA_KEY_SOURCE",\
        "genre": "MDATA_KEY_GENRE",\
        "mood": "MDATA_KEY_MOOD",\
        "tracknumber": "MDATA_KEY_TRACKNUMBER",\
        "bpm": "MDATA_KEY_BPM",\
        "organization": "MDATA_KEY_LABEL",\
        "composer": "MDATA_KEY_COMPOSER",\
        "encodedby": "MDATA_KEY_ENCODER",\
        "conductor": "MDATA_KEY_CONDUCTOR",\
        "date": "MDATA_KEY_YEAR",\
        "website": "MDATA_KEY_URL",\
        "isrc": "MDATA_KEY_ISRC",\
        "copyright": "MDATA_KEY_COPYRIGHT",\
        }
        
        self.cp1252toUnicode = {
        u"\x80": u"\u20AC", # EURO SIGN
        u"\x82": u"\u201A", # SINGLE LOW-9 QUOTATION MARK
        u"\x83": u"\u0192", # LATIN SMALL LETTER F WITH HOOK
        u"\x84": u"\u201E", # DOUBLE LOW-9 QUOTATION MARK
        u"\x85": u"\u2026", # HORIZONTAL ELLIPSIS
        u"\x86": u"\u2020", # DAGGER
        u"\x87": u"\u2021", # DOUBLE DAGGER
        u"\x88": u"\u02C6", # MODIFIER LETTER CIRCUMFLEX ACCENT
        u"\x89": u"\u2030", # PER MILLE SIGN
        u"\x8A": u"\u0160", # LATIN CAPITAL LETTER S WITH CARON
        u"\x8B": u"\u2039", # SINGLE LEFT-POINTING ANGLE QUOTATION MARK
        u"\x8C": u"\u0152", # LATIN CAPITAL LIGATURE OE
        u"\x8E": u"\u017D", # LATIN CAPITAL LETTER Z WITH CARON
        u"\x91": u"\u2018", # LEFT SINGLE QUOTATION MARK
        u"\x92": u"\u2019", # RIGHT SINGLE QUOTATION MARK
        u"\x93": u"\u201C", # LEFT DOUBLE QUOTATION MARK
        u"\x94": u"\u201D", # RIGHT DOUBLE QUOTATION MARK
        u"\x95": u"\u2022", # BULLET
        u"\x96": u"\u2013", # EN DASH
        u"\x97": u"\u2014", # EM DASH
        u"\x98": u"\u02DC", # SMALL TILDE
        u"\x99": u"\u2122", # TRADE MARK SIGN
        u"\x9A": u"\u0161", # LATIN SMALL LETTER S WITH CARON
        u"\x9B": u"\u203A", # SINGLE RIGHT-POINTING ANGLE QUOTATION MARK
        u"\x9C": u"\u0153", # LATIN SMALL LIGATURE OE
        u"\x9E": u"\u017E", # LATIN SMALL LETTER Z WITH CARON
        u"\x9F": u"\u0178", # LATIN CAPITAL LETTER Y WITH DIAERESIS
        }

        self.logger = logging.getLogger()

    def get_md5(self, filepath):
        f = open(filepath, 'rb')
        m = hashlib.md5()
        m.update(f.read())
        md5 = m.hexdigest()

        return md5

    ## mutagen_length is in seconds with the format (d+).dd
    ## return format hh:mm:ss.uuu
    def format_length(self, mutagen_length):
        t = float(mutagen_length)
        h = int(math.floor(t/3600))
        t = t % 3600
        m = int(math.floor(t/60))

        s = t % 60
        # will be ss.uuu
        s = str(s)
        seconds = s.split(".")
        s = seconds[0]

        # have a maximum of 6 subseconds.
        if len(seconds[1]) >= 6:
            ss = seconds[1][0:6]
        else:
            ss = seconds[1][0:]

        length = "%s:%s:%s.%s" % (h, m, s, ss)

        return length

    def save_md_to_file(self, m):
        try:
            airtime_file = mutagen.File(m['MDATA_KEY_FILEPATH'], easy=True)

            for key in m.keys() :
                if key in self.airtime2mutagen:
                    value = m[key]
                    if (value is not None):
                        self.logger.debug("Saving %s to file", key)
                        self.logger.debug(value)
                        if isinstance(value, basestring) and (len(value) > 0):
                            airtime_file[self.airtime2mutagen[key]] = api_client.encode_to(value, 'utf-8')
                        elif isinstance(value, int):
                            airtime_file[self.airtime2mutagen[key]] = str(value)


            airtime_file.save()
        except Exception, e:
            self.logger.error('Trying to save md')
            self.logger.error('Exception: %s', e)
            self.logger.error('Filepath %s', m['MDATA_KEY_FILEPATH'])

    def truncate_to_length(self, item, length):
        if isinstance(item, int):
            item = str(item)
        if isinstance(item, basestring):
            if len(item) > length:
                return item[0:length]
            else:
                return item

    def get_md_from_file(self, filepath):

        self.logger.info("getting info from filepath %s", filepath)

        try:
            md = {}
            md5 = self.get_md5(filepath)
            md['MDATA_KEY_MD5'] = md5

            file_info = mutagen.File(filepath, easy=True)

        except Exception, e:
            self.logger.error("failed getting metadata from %s", filepath)
            self.logger.error("Exception %s", e)
            return None

        if file_info is None:
            return None
        #check if file has any metadata
        if file_info is not None:
            for key in file_info.keys() :
                if key in self.mutagen2airtime and len(file_info[key]) > 0:
                    info = file_info[key][0]
                    while True:
                        temp = re.search(u"[\x80-\x9f]", info)
                        if temp is not None:
                            s = temp.group(0)
                            replace = self.cp1252toUnicode.get(s)
                            info = re.sub(s, replace, info)
                        else:
                            break
                    md[self.mutagen2airtime[key]] = info
        if 'MDATA_KEY_TITLE' not in md:
            #get rid of file extention from original name, name might have more than 1 '.' in it.
            #filepath = to_unicode(filepath)
            #filepath = filepath.encode('utf-8')
            original_name = os.path.basename(filepath)
            original_name = original_name.split(".")[0:-1]
            original_name = ''.join(original_name)
            md['MDATA_KEY_TITLE'] = original_name

        #incase track number is in format u'4/11'
        #need to also check that the tracknumber is even a tracknumber (cc-2582)
        if 'MDATA_KEY_TRACKNUMBER' in md:
            try:
                md['MDATA_KEY_TRACKNUMBER'] = int(md['MDATA_KEY_TRACKNUMBER'])
            except Exception, e:
                pass

            if isinstance(md['MDATA_KEY_TRACKNUMBER'], basestring):
                match = re.search('^(\d*/\d*)?', md['MDATA_KEY_TRACKNUMBER'])

                try:
                    md['MDATA_KEY_TRACKNUMBER'] = int(md['MDATA_KEY_TRACKNUMBER'].split("/")[0], 10)
                except Exception, e:
                    del md['MDATA_KEY_TRACKNUMBER']

        #make sure bpm is valid, need to check more types of formats for this tag to assure correct parsing.
        if 'MDATA_KEY_BPM' in md:
            if isinstance(md['MDATA_KEY_BPM'], basestring):
                try:
                    md['MDATA_KEY_BPM'] = int(md['MDATA_KEY_BPM'])
                except Exception, e:
                    del md['MDATA_KEY_BPM']

        #following metadata is truncated if needed to fit db requirements.
        if 'MDATA_KEY_GENRE' in md:
            md['MDATA_KEY_GENRE'] = self.truncate_to_length(md['MDATA_KEY_GENRE'], 64)

        if 'MDATA_KEY_TITLE' in md:
            md['MDATA_KEY_TITLE'] = self.truncate_to_length(md['MDATA_KEY_TITLE'], 512)

        if 'MDATA_KEY_CREATOR' in md:
            md['MDATA_KEY_CREATOR'] = self.truncate_to_length(md['MDATA_KEY_CREATOR'], 512)

        if 'MDATA_KEY_SOURCE' in md:
            md['MDATA_KEY_SOURCE'] = self.truncate_to_length(md['MDATA_KEY_SOURCE'], 512)

        if 'MDATA_KEY_MOOD' in md:
            md['MDATA_KEY_MOOD'] = self.truncate_to_length(md['MDATA_KEY_MOOD'], 64)

        if 'MDATA_KEY_LABEL' in md:
            md['MDATA_KEY_LABEL'] = self.truncate_to_length(md['MDATA_KEY_LABEL'], 512)

        if 'MDATA_KEY_COMPOSER' in md:
            md['MDATA_KEY_COMPOSER'] = self.truncate_to_length(md['MDATA_KEY_COMPOSER'], 512)

        if 'MDATA_KEY_ENCODER' in md:
            md['MDATA_KEY_ENCODER'] = self.truncate_to_length(md['MDATA_KEY_ENCODER'], 255)

        if 'MDATA_KEY_CONDUCTOR' in md:
            md['MDATA_KEY_CONDUCTOR'] = self.truncate_to_length(md['MDATA_KEY_CONDUCTOR'], 512)

        if 'MDATA_KEY_YEAR' in md:
            md['MDATA_KEY_YEAR'] = self.truncate_to_length(md['MDATA_KEY_YEAR'], 16)

        if 'MDATA_KEY_URL' in md:
            md['MDATA_KEY_URL'] = self.truncate_to_length(md['MDATA_KEY_URL'], 512)

        if 'MDATA_KEY_ISRC' in md:
            md['MDATA_KEY_ISRC'] = self.truncate_to_length(md['MDATA_KEY_ISRC'], 512)

        if 'MDATA_KEY_COPYRIGHT' in md:
            md['MDATA_KEY_COPYRIGHT'] = self.truncate_to_length(md['MDATA_KEY_COPYRIGHT'], 512)
        #end of db truncation checks.


        md['MDATA_KEY_BITRATE'] = file_info.info.bitrate
        md['MDATA_KEY_SAMPLERATE'] = file_info.info.sample_rate
        md['MDATA_KEY_DURATION'] = self.format_length(file_info.info.length)
        md['MDATA_KEY_MIME'] = file_info.mime[0]

        if "mp3" in md['MDATA_KEY_MIME']:
            md['MDATA_KEY_FTYPE'] = "audioclip"
        elif "vorbis" in md['MDATA_KEY_MIME']:
            md['MDATA_KEY_FTYPE'] = "audioclip"

        #do this so object can be urlencoded properly.
        for key in md.keys():

            if (isinstance(md[key], basestring)):
                #self.logger.info("Converting md[%s] = '%s' ", key, md[key])
                md[key] = api_client.encode_to(md[key], 'utf-8')
                #self.logger.info("Converting complete: md[%s] = '%s' ", key, md[key])

        return md
