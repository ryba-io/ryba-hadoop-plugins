# -*- coding: utf-8 -*-

import json
import requests
import sys
import argparse

_es_http_port = 9200
_es_health_url = '/_cluster/health'

def get_containers_list(swarm_manager_url, containers_name, server_cert_path, client_cert_path, client_cert_key_path):
    """Connect to Docker Swarm API to provide a containers list matching container's name filter
    :param swarm_manager_url: Swarm Manager URL + Port (https://FQDN:Port)
    :type swarm_manager_url: str
    :param containers_name: Containers name to find ('dco' will find all containers starting with 'dco*')
    :type containers_name: str
    :param server_cert_path: Path to Server Certificate
    :type server_cert_path: str
    :param client_cert_path: Path to Client Certificate
    :type client_cert_path: str
    :param client_cert_key_path: Path to Client Certificate Key
    :type client_cert_key_path: str
    :return: An HTTP Response object containing JSON formatted result or error
    :rtype: json
    """

    docker_api_result = requests.get(url='{0}/containers/json?all=1&filters=%7B%22name%22%3A%7B%22{1}%22%3Atrue%7D%7D'.format(swarm_manager_url, containers_name),
                     verify=server_cert_path, cert=(client_cert_path, client_cert_key_path))

    return docker_api_result

def get_es_http_url_list(containers_list):
    """Find Containers with PrivatePort matching _es_http_port and build URL list to check ES health
    :param containers_list: Docker Swarm API JSON formatted result
    :type containers_list: json
    :param _es_http_port: ES port used in PrivatePort for containers [default 9200]
    :type _es_http_port: int
    :return: List of URLs built with strings
    :rtype: list
    """

    es_http_urls = []

    for container in containers_list:
        for port in container['Ports']:
            if port['PrivatePort'] == _es_http_port:
                es_http_urls.append('http://' + port['IP'] + ':' + str(port['PublicPort']))

    return es_http_urls

def get_es_cluster_health(es_http_urls, username=None, userpassword=None):
    """Get ES cluster health from ES API
    :param es_http_urls: URLs list of ES nodes to check
    :type es_http_urls: list
    :param username: Username authorized to check ES Health (Only needed if xpack security enabled)
    :type username: str
    :param userpassword: User password (needed only with username parameter)
    :type userpassword: str
    :return: An HTTP Response object containing JSON formatted result or error
    :rtype: json
    """
    es_health_results = []

    for es_http_url in es_http_urls:
        request_es_health = requests.get(url=es_http_url + _es_health_url,
                                       auth=(args.username, args.userpassword))
        es_health_results.append(request_es_health)

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

    docker_api_result = get_containers_list(args.swarm_manager_url, args.clustername,
                                          args.server_cert_path, args.client_cert_path, args.client_cert_key_path)

    if docker_api_result.status_code != 200:
      raise Exception('Could not connect to Docker Swarm API: ' + request_es_health.content)

    containers_list = json.loads(docker_api_result.content)

    es_http_urls = get_es_http_url_list(containers_list)

    es_health_results = get_es_cluster_health(es_http_urls, args.username, args.userpassword)

    for es_health_result in es_health_results:
        if es_health_result.status_code != 200:
            raise Exception('Could not check es_health: ' + es_health_result.content)

        es_health = json.loads(es_health_result.content)

        if es_health['status'] != 'green':
            if es_health['status'] == 'yellow':
                raise UserWarning('WARNING: CLUSTER STATUS YELLOW: check full health status {}'.format(es_health))

            elif es_health['status'] == 'red':
                raise Exception('ERROR: CLUSTER STATUS RED: check full health status {}'.format(es_health))
            else:
                raise Exception('ERROR: UNKNOWN STATUS {}'.format(es_health))

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Feed arguments for ES clusters check.')
    parser.add_argument('-C', metavar='clustername', type=str, help='Cluster Name to check', dest='clustername', required=True)
    parser.add_argument('-u', metavar='username', type=str, help='Cluster Username authorized to check cluster health', dest='username')
    parser.add_argument('-P', metavar='userpassword', type=str, help='Cluster User password', dest='userpassword')
    parser.add_argument('-s', metavar='server_certificate_path', type=str, help='Server Certificate path', dest='server_cert_path', required=True)
    parser.add_argument('-c', metavar='client_cert_path', type=str, help='Client Certificate path', dest='client_cert_path', required=True)
    parser.add_argument('-k', metavar='client_cert_key_path', type=str, help='Client Certificate key path', dest='client_cert_key_path', required=True)
    parser.add_argument('-H', metavar='swarm_manager_url', type=str, help='Swarm Manager URL', dest='swarm_manager_url', required=True)
    args = parser.parse_args()

    if (args.username and not args.userpassword) or (args.userpassword and not args.username):
        parser.error("Username and user password must be provided together")

    try:
        main(args)

    except UserWarning as e:
        raise UserWarning(e)

    except Exception as e:
        print e
        sys.exit(2)
