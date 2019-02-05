#!/usr/bin/env python2
# -*- coding: utf-8 -*-

import argparse
import sys
import json
import requests
import base64
import urllib3
from requests import ConnectionError

class TableNotFoundException(Exception):
    pass

def get_tables_list(knox_url, username, password):
    """Return list of tables present in hbase
    :param knox_url: knox URL + Port (https://FQDN:Port)
    :type knox_url: str
    :param username: username
    :type username: str
    :param password: password
    :type password: str
    :return: An HTTP Response object containing JSON formatted result or error
    :rtype: json
    """

    userpass = "{}:{}".format(username, password)
    b64Val = base64.b64encode(userpass)

    headers = {
        'authorization': "Basic {}".format(b64Val),
        'content-type': "application/json",
        'cache-control': "no-cache",
        'Accept': 'application/json'
        }
    knox_hbase_result = requests.get(url='{0}/gateway/clients/hbase'.format(knox_url),
                     verify=False, headers=headers)

    if knox_hbase_result.status_code != 200:
        raise ConnectionError
 
    return json.loads(knox_hbase_result.content)

def get_cf(knox_url, table_name, username, password):
    """return list of column families
    :param knox_url: knox URL + Port (https://FQDN:Port)
    :type knox_url: str
    :param table_name: table name
    :type table_name: str
    :param username: username
    :type username: str
    :param password: password
    :type password: str
    :return: an object containing the table and cf
    :rtype: object
    """

    userpass = "{}:{}".format(username, password)
    b64Val = base64.b64encode(userpass)

    headers = {
        'authorization': "Basic {}".format(b64Val),
        'content-type': "application/json",
        'cache-control': "no-cache",
        'Accept': 'application/json'
        }
    knox_hbase_result = requests.get(url='{0}/gateway/clients/hbase/{1}/schema'.format(knox_url,table_name),
                     verify=False, headers=headers)
                     
    if knox_hbase_result.status_code == 404:
        raise TableNotFoundException
    if knox_hbase_result.status_code != 200:
        raise ConnectionError
    
    columnfamilies = json.loads(knox_hbase_result.content)
    
    names = map(lambda cf: cf['name'], columnfamilies['ColumnSchema'])
    
    return {
        'name': table_name,
        'cf': names
    }

def get_replicated_cf(knox_url, table_name, username, password):
    """return list of column families with a replication scope
    :param knox_url: knox URL + Port (https://FQDN:Port)
    :type knox_url: str
    :param table_name: table name
    :type table_name: str
    :param username: username
    :type username: str
    :param password: password
    :type password: str
    :return: an object containing the table and cf needed to be replicated
    :rtype: object
    """

    userpass = "{}:{}".format(username, password)
    b64Val = base64.b64encode(userpass)

    headers = {
        'authorization': "Basic {}".format(b64Val),
        'content-type': "application/json",
        'cache-control': "no-cache",
        'Accept': 'application/json'
        }
    knox_hbase_result = requests.get(url='{0}/gateway/clients/hbase/{1}/schema'.format(knox_url,table_name),
                     verify=False, headers=headers)
                     
    
    if knox_hbase_result.status_code != 200:
        raise ConnectionError
    
    columnfamilies = json.loads(knox_hbase_result.content)
    
    filtered = filter(lambda cf : cf['REPLICATION_SCOPE'] != '0', columnfamilies['ColumnSchema'])
    names = map(lambda cf: cf['name'], filtered)
    
    if filtered:
        return {
            'name': table_name,
            'cf': names
        }
    else:
        return None
    
def main(arg):
    """Compare list of replicated tables on HBASE, via KNOX
    Steps : 
        - Get all the tables on first environment
        - Check if table should be replicated
        - Check the presence of the tables on second host
    Exit code 0 if All OK
    Exit code 1 if not with list of not present tables
    """
    urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
    
    url = "http"
    if args.S1: 
      url+='s'
    url+= '://' + args.first_host + ':' + args.first_port
    host1_table_list = get_tables_list(url, args.username, args.password)
        
    replicated = {}
    
    for table in host1_table_list['table']:
        table_replication = get_replicated_cf(url, table['name'], args.username, args.password)
        if table_replication is not None:
            replicated[table_replication['name']] = table_replication['cf']
    
    url = "http"
    if args.S2: 
      url+='s'
    url+= '://' + args.second_host + ':' + args.second_port
    
    not_replicated = {}
    
    for tablename, cfs in replicated.iteritems():
        try:
            host2_cfs = get_cf(url, tablename, args.username, args.password)
            not_replicated_cfs = filter(lambda cf: cf not in host2_cfs['cf'], cfs)
            if not_replicated_cfs:
                not_replicated[tablename] = not_replicated_cfs
        except TableNotFoundException as e:
            not_replicated[tablename] = cfs

    if not not_replicated:
        print "OK : all replicated tables are present"
        sys.exit(0)
    
    returnString = ''
    for tablename, cfs in not_replicated.iteritems():
        for cf in cfs:
            returnString += "{};{}, ".format(tablename,cf)
    print "CRITICAL : {} are not present on replication cluster".format(returnString)
    sys.exit(2)
        
if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Feed arguments for replication presence check.')
    parser.add_argument('--H1', metavar='first_host', type=str, help='first host name', dest='first_host', required=True)
    parser.add_argument('--H2', metavar='second_host', type=str, help='second host name', dest='second_host', required=True)
    parser.add_argument('--P1', metavar='first_port', type=str, help='first host port', dest='first_port', required=True)
    parser.add_argument('--P2', metavar='second_port', type=str, help='second host port', dest='second_port', required=True)
    parser.add_argument('--S1', help='Flag to enable SSL for host 1', action='store_true')
    parser.add_argument('--S2', help='Flag to enable SSL for host 2', action='store_true')
    parser.add_argument('-u', metavar='username', type=str, help='Cluster Username authorized to check cluster health', dest='username', required=True)
    parser.add_argument('-p', metavar='password', type=str, help='Cluster User password', dest='password', required=True)
    args = parser.parse_args()
    
    
    main(args)
