#!/usr/bin/env python

################################################################################
#                                                                              #
#   Licensed under the Apache License, Version 2.0 (the "License");            #
#   you may not use this file except in compliance with the License.           #
#   You may obtain a copy of the License at                                    #
#                                                                              #
#       http://www.apache.org/licenses/LICENSE-2.0                             #
#                                                                              #
#   Unless required by applicable law or agreed to in writing, software        #
#   distributed under the License is distributed on an "AS IS" BASIS,          #
#   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.   #
#   See the License for the specific language governing permissions and        #
#   limitations under the License.                                             #
################################################################################

"""
This requires:
 python-kerberos (CentOS Base)
 python-urllib2_kerberos (EPEL)
 pytz (CentOS Base)
"""

import sys
import urllib
import json
import datetime
import urllib2_kerberos
import urllib2
import pytz


class OozieConnect():
    def __init__(self, host="localhost", port="11000", security_flag="false"):
        self.uris = {}
        self.security_flag = security_flag
        self.host = host
        self.port = port
#       Setter functions below here
        self.set_uris(self.host, self.port)
#       Get current timezone - This assumes Oozie has been setup for the system timezone

    def set_uris(self, host, port, history_offset=1, history_length=100, status="SUCCEEDED"):
        if self.security_flag == "true":
            self.uris["base"] = "https://" + host + ":" + port + "/oozie"
        else:
           self.uris["base"] = "http://" + host + ":" + port + "/oozie"
        self.set_job_uris(history_offset, history_length, status)
        self.uris["status"] = self.uris["base"] + "/v1/admin/status"

    def set_job_uris(self, history_offset=1, history_length=100, status="SUCCEEDED"):
        self.uris["jobs"] = self.uris["base"] + \
                            "/v1/jobs?jobType=wf&localtime&offset=" + str(history_offset) + \
                            "&len=" + str(history_length) + \
                            "&filter=status%3" + status

    def set_security_flag(self, security_flag):
        self.security_flag = security_flag

    def connect(self, uri):
        if self.security_flag == "true":
            return self.secure_connect(self.uris[uri])
        else:
            return self.insecure_connect(self.uris[uri])

    def secure_connect(self, uri):
        opener = urllib2.build_opener()
        opener.add_handler(urllib2_kerberos.HTTPKerberosAuthHandler())
        resp = opener.open(uri)
        a = resp.read()
        return json.loads(a)

    def insecure_connect(self, uri):
        raw_json = urllib.urlopen(uri)
        return json.load(raw_json)

    def test_connection(self):
        json_object = self.connect("status")
        if json_object[u'systemMode'] == "NORMAL":
            return True
        return False


class OozieJobs():
    def __init__(self, oozie, time_range_minutes=60, history_length=1000):
        self.failed_count = 0
        self.suspended_count = 0
        self.killed_count = 0
        self.succeeded_count = 0
        self.prep_count = 0
        self.running_count = 0
        self.workflows = []
        self.oozie_connection = oozie
        self.statuses = ["FAILED", "KILLED", "SUCCEEDED", "RUNNING", "SUSPENDED"]
        self.time_range_minutes = int(time_range_minutes)
        self.history_length = history_length

    def set_time_range_minutes(self, minutes):
        self.time_range_minutes = int(minutes)

    def get_jobs(self):
        # iterate through the json and pull out the workflows if the time range is not met, continue getting historical
        # jobs using 'len' until you reach the end or time_range width
        # We'll use the job ending time as a measure

        offset = 1
        wf_ids = []
        wf_count = 0
        self.oozie_connection.set_job_uris(offset, self.history_length)
        json_object = self.oozie_connection.connect("jobs")
        for job in json_object[u'workflows']:
            wf_count += 1
            if self.is_within_time_range(self.time_range_minutes, job[u'createdTime']) and (job[u'id'] not in wf_ids):

                wf_ids.append(job[u'id'])

                wf_job = {
                            "id"          : job[u'id'],
                            "appName"     : job[u'appName'],
                            "status"      : job[u'status'],
                            "createdTime" : job[u'createdTime'],
                            "startTime"   : job[u'startTime'],
                            "endTime"     : job[u'endTime']}

                self.workflows.append(wf_job)

    def is_within_time_range(self, range_in_minutes, input_time):
        time_now = datetime.datetime.now(pytz.timezone("GMT"))
        wf_time = datetime.datetime.strptime(input_time, "%a, %d %b %Y %X %Z")
        tz = pytz.timezone("GMT")
        dt = tz.localize(datetime.datetime(wf_time.year,
                                           wf_time.month,
                                           wf_time.day,
                                           wf_time.hour,
                                           wf_time.minute,
                                           wf_time.second))
        range_time_minutes = datetime.timedelta(minutes=range_in_minutes)
        if (time_now - dt) <= range_time_minutes:
            return True
        return False

    def analyze_results(self):
        self.suspended_count = 0
        self.failed_count = 0
        self.killed_count = 0
        self.succeeded_count = 0
        self.running_count = 0
        self.prep_count = 0
        # iterate through the workflows and get status
        # For RUNNING and PREP jobs, increment their counts but don't look at their endTime since they dont have one
        for workflow in self.workflows:
            if workflow["status"] == "PREP":
                self.prep_count += 1
                continue
            elif workflow["status"] == "RUNNING":
                self.running_count += 1
                continue
            if self.is_within_time_range(self.time_range_minutes, workflow["endTime"]):
                    if workflow["status"] == "FAILED":
                        self.failed_count += 1

                    elif workflow["status"] == "SUSPENDED":
                        self.suspended_count += 1

                    elif workflow["status"] == "KILLED":
                        self.killed_count += 1

                    elif workflow["status"] == "SUCCEEDED":
                        self.succeeded_count += 1

        return self.failed_count + \
               self.suspended_count + \
               self.killed_count + \
               self.succeeded_count + \
               self.running_count + \
               self.prep_count

    def get_return_code(self):
        total = self.failed_count +\
                self.suspended_count +\
                self.killed_count +\
                self.succeeded_count +\
                self.running_count +\
                self.prep_count

        ret_code = 0
        result = "OK:"
        if (total > 0):
            percent = (100*(self.failed_count+self.killed_count+self.suspended_count))/total
            if (((100*(self.failed_count+self.killed_count+self.suspended_count))/total) >= int(critical)):
                result  = "CRITICAL (%d%%):" % (percent)
                ret_code = 2
            elif (((100*(self.failed_count+self.killed_count+self.suspended_count))/total) >= int(warning)):
                result = "WARNING (%d%%):" % (percent)
                ret_code = 1
            if (self.failed_count > 0):
                result += " FAILED: %s" % (self.failed_count)
            if (self.killed_count > 0):
                result += " KILLED: %s" % (self.killed_count)
            if (self.suspended_count > 0):
                result += " SUSPENDED: %s" % (self.suspended_count)
        print "%s TOTAL: %s on %d min|failed=%d;suspended=%d;killed=%d;succeeded=%d;running=%d;prep=%d" % (result, total, self.time_range_minutes, self.failed_count, self.suspended_count, self.killed_count, self.succeeded_count, self.running_count, self.prep_count)
        return ret_code

    def run(self):
        self.get_jobs()
        self.analyze_results()
        #self.print_results()
        return self.get_return_code()

if __name__ == "__main__":
    try:
        host = sys.argv[1]
        port = sys.argv[2]
        sec_flag = sys.argv[3]
        time_range = sys.argv[4]
        history_length = sys.argv[5]
        warning = sys.argv[6]
        critical = sys.argv[7]
    except:
        print "Expecting [1] host [2] port [3] security (true|false) [4] range in minutes [5] jobs limit [6] Warning (%) [7] Critical (%) "
        sys.exit(0)

    oozie_connection = OozieConnect(host, port, sec_flag)
    # if oozie_connection.test_connection():
    #     print "Good Connection"
    jobs = OozieJobs(oozie_connection, time_range, history_length)
    try:
        rc = jobs.run()
    except IOError:
        print "Couldn't connect to Oozie on %s:%s" % (host, port)
        sys.exit(1)
    sys.exit(rc)
