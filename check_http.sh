#!/usr/bin/env bash
#
#
# Licensed to the Apache Software Foundation (ASF) under one
# or more contributor license agreements.  See the NOTICE file
# distributed with this work for additional information
# regarding copyright ownership.  The ASF licenses this file
# to you under the Apache License, Version 2.0 (the
# "License"); you may not use this file except in compliance
# with the License.  You may obtain a copy of the License at
#
#   http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing,
# software distributed under the License is distributed on an
# "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
# KIND, either express or implied.  See the License for the
# specific language governing permissions and limitations
# under the License.
#
#

usage() {
  echo "Usage: check_http.sh -h help -H <hostname> [-p <port> -u <url_path> -r <response_regex> -s ssl_enabled]";
  exit 3;
}

cmd='curl --negotiate -fku:'
host=''
port=''
path='/'
protocol='http'
sec=false
wantedResponse=''

while getopts ":hH:p:u:r:sS" opt; do
  case $opt in
    h)
      usage
      ;;
    H)
      host=$OPTARG
      ;;
    p)
      port=":$OPTARG"
      ;;
    u)
      path=$OPTARG
      ;;
    r)
      wantedResponse=$OPTARG
      ;;
    s)
      protocol='https'
      ;;
  esac
done


if [[ -z "$host" ]]; then
  usage
fi

response=`$cmd $protocol://$host$port$path 2>/dev/null`
ret=$?

if [[ $ret -ne 0 ]]; then
  echo "CRITICAL: Service not accessible"
  exit 2;
fi

if [[ -n "$wantedResponse" ]]; then
  if [[ $response =~ $wantedResponse ]]; then
    echo "OK: Service replies correctly"
    exit 0;
  else
    echo "CRITICAL: Service replies incorrectly"
    exit 2;
  fi
fi
echo "OK: Service accessible";
exit 0;
