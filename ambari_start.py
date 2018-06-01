#!/usr/bin/env python2

import sys
import time
import requests
import json
import base64

HOST = sys.argv[1]
STATE = sys.argv[2]
ATTEMPT = sys.argv[3]
ACTION = sys.argv[4]
SERVICE = sys.argv[5]
CLUSTER = sys.argv[6]
AMBARI_URL = sys.argv[7]
USER = sys.argv[8]
PASSWORD = sys.argv[9]

if STATE != 'CRITICAL':
    print("Not critical. Nothing to do")
    sys.exit(0)

if ATTEMPT != "2":
    print("waiting for 2nd attempt. Nothing to do")
    sys.exit(0)
    
if HOST == "":
    print("no host provided")
    sys.exit(1)
    
    print("no action provided")
    sys.exit(1)
    
elif SERVICE == "":
    print("no service provided")
elif ACTION == "":
    sys.exit(1)

elif CLUSTER == "":
    print("no cluster provided")
    sys.exit(1)


date = int(time.time())

ssh_log = open("ambari_start.log", "a")
ssh_log.write("{}: CLUSTER={} HOST={} SERVICE={} ACTION={}".format(date, CLUSTER, HOST, SERVICE, ACTION))
ssh_log.close()

url = "{}/api/v1/clusters/{}/hosts/{}/host_components/{}".format(AMBARI_URL, CLUSTER, HOST, SERVICE)

payload = { 
    "RequestInfo": {
        "context": "Service Start {} Shinken".format(SERVICE)
        },
    "HostRoles": {
        "state": "STARTED"
        } 
    }

userpass = "{}:{}".format(USER,PASSWORD)
b64Val = base64.b64encode(userpass)

headers = {
    'authorization': "Basic {}".format(b64Val),
    'content-type': "text/plain",
    'cache-control': "no-cache",
    'X-Requested-By': 'ambari'
    }
    
try:
    response = requests.request("PUT", url, data=json.dumps(payload), headers=headers, verify=False)
    response.raise_for_status()
except requests.exceptions.HTTPError as err:
    print response.text
    ssh_log = open("ambari_start.log", "a")
    ssh_log.write("error {}: {}".format(err.errno, err.strerror))
    ssh_log.close()
