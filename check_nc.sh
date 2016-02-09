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
  echo "Usage: check_nc.sh -h help -H <hostname> -p <port> [-m <msg_to_send> -r <response_regex>]";
  exit 3;
}

host=''
port=''
msg=''
wantedResponse=''

while getopts ":hH:p:m:r:" opt; do
  case $opt in
    h)
      usage
      ;;
    H)
      host=$OPTARG
      ;;
    p)
      port=$OPTARG
      ;;
    m)
      msg=$OPTARG
      ;;
    r)
      wantedResponse=$OPTARG
      ;;
  esac
done


if [[ -z "$host" || -z "$port" ]]; then
  usage
fi

response=`echo $msg | nc $host $port`
ret=$?

if [[ $ret -ne 0 ]]; then
  echo "CRITICAL: Service not accessible: $response";
  exit 2;
fi

if [[ -n "$wantedResponse" ]]; then
  if [[ $response =~ $wantedResponse ]]; then
    echo "OK: Service replies correctly: $response"
    exit 0;
  else
    echo "CRITICAL: Service replies $response"
    exit 2;
  fi
fi
echo "OK: Service accessible";
exit 0;
