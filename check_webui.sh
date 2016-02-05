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
  echo "Usage: check_webui.sh -h help -H <hostname> [-p <port> -u <url_path> -s security_enabled -S SSl_enabled]";
  exit 3;
}

cmd='curl -f -k -o /dev/null'
host=''
port=''
path='/'
protocol='http'
sec=false

while getopts ":hH:p:u:sS" opt; do
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
    s)
      sec=true
      ;;
    S)
      protocol='https'
      ;;
  esac
done


if [[ -z "$host" ]]; then
  usage
fi

if [ "$sec" = true ]; then
  cmd+=' --negotiate -u:'
fi

`$cmd $protocol://$host$port$path 2>/dev/null`
ret=$?

if [[ $ret -ne 0 ]]; then
  echo "CRITICAL: Web UI not accessible";
  exit 1;
fi

echo "OK: Web UI accessible";
exit 0;
