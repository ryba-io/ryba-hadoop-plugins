#!/usr/bin/env python2
# -*- coding: utf-8 -*-

import argparse
import sys
import json
import requests
import urllib3
import os
from requests import ConnectionError

class TableNotFoundException(Exception):
    pass

def get_nifi_token(nifi_host, username, password):

  urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

  data = {"username":username, "password":password}
  headers = {
    'content-type': "application/x-www-form-urlencoded; charset=UTF-8",
    'Accept': '*/*',
    'Accept-Encoding': 'gzip, deflate, br'
    }

  nifi_token_result = requests.post(url='{0}/nifi-api/access/token'.format(nifi_host),
                     verify=False, headers=headers, data=data )

  # print nifi_token_result.text
  # print nifi_token_result.status_code
  if nifi_token_result.status_code in ( 400,401,402,403 ):
      print "Authentification error, bad crédential? : http_code " + str(nifi_token_result.status_code)
      sys.exit(2)
  elif nifi_token_result.status_code != 201:
      print "Connexion error => http_code " + str(nifi_token_result.status_code)
      sys.exit(2)
  elif not nifi_token_result.text:
      print "No token define"
      sys.exit(2)
  else:
      return nifi_token_result.text

def get_status(nifi_host, token):
  srv_err=[]
  i=0
  urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

  headers = {
    'Authorization': 'Bearer ' + token,
    'content-type': "application/x-www-form-urlencoded; charset=UTF-8",
    'Accept': 'application/json, text/javascript, */*; q=0.01',
    'Accept-Encoding': 'gzip, deflate, br'
    }

  nifi_controler_result = requests.get(url='{0}/nifi-api/controller/cluster'.format(nifi_host),
                     verify=False, headers=headers )

  if nifi_controler_result.status_code != 200:
      print "Connexion error : http_code " + str(nifi_controler_result.status_code)
      sys.exit(2)

  dict=json.loads(nifi_controler_result.text)
  for node in dict["cluster"]["nodes"]:
      status=node["status"]
      server=node["address"]

      if status != "CONNECTED":
          srv_err.append(server)

  return (srv_err)



def main(arg):
  lst_err=[]

  nifi_host='https://' + arg.host + ':' + arg.port
  token=get_nifi_token(nifi_host,arg.username,arg.password)

  lst_err=get_status(nifi_host, token)
  if lst_err:
      print "Cluster in bad state, " + str(len(lst_err)) + " server nifi DISCONNECTED: " + str(lst_err)
      sys.exit(2)
  else:
      print "Cluster healthly"
      sys.exit(0)

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Feed arguments for replication presence check.')
    parser.add_argument('--H', metavar='host', type=str, help='host name', dest='host', required=True)
    parser.add_argument('--P', metavar='port', type=str, help='fport', dest='port', required=True)
    parser.add_argument('-u', metavar='username', type=str, help='Cluster Username authorized to check cluster health', dest='username', required=True)
    parser.add_argument('-p', metavar='password', type=str, help='Cluster User password', dest='password', required=True)
    args = parser.parse_args()


    main(args)

