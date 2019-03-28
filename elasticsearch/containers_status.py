#!/usr/bin/env python2
# -*- coding: utf-8 -*-

from requests import ConnectionError
import json
import re
import requests
import sys
import argparse
import warnings

warnings.filterwarnings("ignore")

_es_http_port = 9200
_es_env_monit_port = 'shinken_monit_port'
_es_env_monit_protocol = 'shinken_monit_protocol'
_es_health_url = '/_cluster/health'

def get_containers_list(swarm_manager_url, containers_name, client_cert_path, client_cert_key_path):
    """Connect to Docker Swarm API to provide a containers list matching container's name filter
    :param swarm_manager_url: Swarm Manager URL + Port (https://FQDN:Port)
    :type swarm_manager_url: str
    :param containers_name: Containers name to find ('dco' will find all containers starting with 'dco*')
    :type containers_name: str
    :type server_cert_path: str
    :param client_cert_path: Path to Client Certificate
    :type client_cert_path: str
    :param client_cert_key_path: Path to Client Certificate Key
    :type client_cert_key_path: str
    :return: An HTTP Response object containing JSON formatted result or error
    :rtype: json
    """

    docker_api_result = requests.get(url='{0}/containers/json?all=1&filters=%7B%22name%22%3A%7B%22{1}%22%3Atrue%7D%7D'.format(swarm_manager_url, containers_name),
                     verify=False, cert=(client_cert_path, client_cert_key_path))
    return docker_api_result

def get_container_host_port_from_env(swarm_manager_url, container_id, env_port_name, env_protocol_type, client_cert_path, client_cert_key_path):
    """Connect to Docker Swarm API to provide a containers list matching container's name filter
    :param swarm_manager_url: Swarm Manager URL + Port (https://FQDN:Port)
    :type swarm_manager_url: str
    :param containers_name: Containers name to find ('dco' will find all containers starting with 'dco*')
    :type containers_name: str
    :type server_cert_path: str
    :param client_cert_path: Path to Client Certificate
    :type client_cert_path: str
    :param client_cert_key_path: Path to Client Certificate Key
    :type client_cert_key_path: str
    :return: An HTTP Response object containing JSON formatted result or error
    :rtype: json
    """
   
      
    docker_api_result = requests.get(url='{0}/containers/{1}/json'.format(swarm_manager_url, container_id),
                     verify=False, cert=(client_cert_path, client_cert_key_path),timeout=5)
    container_info = json.loads(docker_api_result.content)

    env_port_shinken = filter(lambda env: env.startswith(env_port_name), container_info['Config']['Env'])
    env_protocol_shinken = filter(lambda env: env.startswith(env_protocol_type), container_info['Config']['Env'])

    if not env_port_shinken:
        return '', '','http'
    else:
      env_port_shinken = env_port_shinken[0].replace(":","=").split('=')[1]
      if env_protocol_shinken:
        env_protocol_shinken = env_protocol_shinken[0].replace(":","=").split('=')[1]
        return container_info['Node']['Name'],env_port_shinken, env_protocol_shinken
      else:
        return container_info['Node']['Name'],env_port_shinken, "http"

def get_es_http_url_list(containers_list, ssl_enabled, swarm_manager_url, env_port_name, env_protocol_type, client_cert_path, client_cert_key_path):
    """Find Containers with PrivatePort matching _es_http_port and build URL list to check ES health
    :param containers_list: Docker Swarm API JSON formatted result
    :type containers_list: json
    :param ssl_enabled: enable or not https
    :type ssl_enabled: bool
    :param _es_http_port: ES port used in PrivatePort for containers [default 9200]
    :type _es_http_port: int
    :return: Dict of URLs built with strings
    :rtype: dict
    """
    
    es_http_urls = {}
    for container in containers_list:
        container_name = container['Names'][0]
        container_id = container['Id']
        container_port = ''
        container_hostname = ''
        # print container['NetworkSettings']['Networks'][0].contains('host')
        if 'host' in container['NetworkSettings']['Networks']:
            # We need to get the env variable of this container in order to get the port
            container_hostname, container_port, container_protocol = get_container_host_port_from_env(swarm_manager_url, container_id, env_port_name, env_protocol_type, client_cert_path, client_cert_key_path)
        else:
            for port in container['Ports']:
                if port['PrivatePort'] == _es_http_port:
                    container_port = str(port['PublicPort'])
                    container_hostname = port['IP']
        if container_hostname != '':
            url = 'http'
            if ssl_enabled:
                url += 's'
            if container_protocol == "https" :
                url = container_protocol
            url += '://' + container_hostname + ':' + container_port
            es_http_urls[container_name]=url

    return es_http_urls

def get_es_cluster_health(es_http_urls, username=None, userpassword=None):
    """Get ES cluster health from ES API
    :param es_http_urls: URLs dict of ES nodes to check
    :type es_http_urls: dict
    :param username: Username authorized to check ES Health (Only needed if xpack security enabled)
    :type username: str
    :param userpassword: User password (needed only with username parameter)
    :type userpassword: str
    :return: An HTTP Response object containing JSON formatted result or error
    :rtype: json
    """
    es_health_results = {}
    for container in es_http_urls:
        main.container = container.split('/')[2]
        request_es_health = requests.get(url=es_http_urls[container] + _es_health_url, verify=False,
                                       auth=(args.username, args.userpassword), timeout=5)
        es_health_results[container] = request_es_health

    return es_health_results

def main(arg):
    """Check ES cluster status in Docker containers, via ES API
    Steps :
        - Get Container IP, Port List with _es_http_port from Docker Swarm API
        - Request ES API via es_health_url
        - Check ES cluster status from this request
    Exit code 0 if All OK
    Exit code 1 if Warning (ES Cluster Yellow status)
    Exit code 2 if Critical (ES Cluster Red status or unreachable)
    """

    url = "http"
    if args.S:
      url+='s'
    url+= '://' + args.swarm_manager_host + ':' +args.swarm_manager_port
    docker_api_result = get_containers_list(url, args.clustername,
                                args.client_cert_path, args.client_cert_key_path)

    if docker_api_result.status_code != 200:
      raise ConnectionError

    containers_list = json.loads(docker_api_result.content)

    es_http_urls = get_es_http_url_list(containers_list,args.Z, url, _es_env_monit_port, _es_env_monit_protocol, args.client_cert_path, args.client_cert_key_path)

    es_health_results = get_es_cluster_health(es_http_urls, args.username, args.userpassword)

    for container in es_health_results:
        main.container = container.split('/')[2]
        if es_health_results[container].status_code != 200:
            raise ConnectionError(es_health_results[container])

        es_health = json.loads(es_health_results[container].content)

        if es_health['status'] != 'green':
            if es_health['status'] == 'yellow':
                raise UserWarning('CLUSTER STATUS YELLOW: container_name: {0}; cluster_name: {1}'.format(main.container, es_health['cluster_name']))
            elif es_health['status'] == 'red':
                raise Exception('CLUSTER STATUS RED: container_name: {0}; cluster_name: {1}'.format(main.container, es_health['cluster_name']))
            else:
                raise Exception('UNKNOWN STATUS: container_name: {0}; cluster_name: {1}'.format(main.container, es_health['cluster_name']))

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Feed arguments for ES clusters check.')
    parser.add_argument('-H', metavar='swarm_manager_host', type=str, help='Swarm Manager Host', dest='swarm_manager_host', required=True)
    parser.add_argument('-p', metavar='swarm_manager_port', type=str, help='Swarm Manager Port', dest='swarm_manager_port', required=True)
    parser.add_argument('-u', metavar='username', type=str, help='Cluster Username authorized to check cluster health', dest='username')
    parser.add_argument('-P', metavar='userpassword', type=str, help='Cluster User password', dest='userpassword')
    parser.add_argument('-C', metavar='clustername', default='', type=str, help='Cluster Name to check', dest='clustername')
    parser.add_argument('-c', metavar='client_cert_path', type=str, help='Client Certificate path', dest='client_cert_path', required=True)
    parser.add_argument('-k', metavar='client_cert_key_path', type=str, help='Client Certificate key path', dest='client_cert_key_path', required=True)
    parser.add_argument('-S', help='Flag to enable SSL for swarm', action='store_true')
    parser.add_argument('-Z', help='Flag to enable SSL for containers', action='store_true')
    args = parser.parse_args()
    if (args.username and not args.userpassword) or (args.userpassword and not args.username):
        parser.error("Username and user password must be provided together")

    try:
        main(args)

    except UserWarning as e:
        print("WARNING: {}".format(e))
        sys.exit(1)
    except ConnectionError as e:
        if main.container:
            print("ERROR: Could not check es_health: container_name : {}".format(main.container))
        else:
            print("ERROR: Could not connect to Docker Swarm API: host:{}".format(args.swarm_manager_host))
        sys.exit(2)
    except Exception as e:
        print("ERROR: {}".format(e))
        sys.exit(2)
    print("OK: Status OK")
