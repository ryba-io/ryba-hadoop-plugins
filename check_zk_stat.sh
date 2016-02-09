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
  echo "Usage: check_zk_stat.sh -h help -H <hostname> -p <port> -f <field> -w <warning> -c <critical>";
  exit 3;
}

host=''
port=''
field=''
warn=''
crit=''

while getopts ":hH:p:f:w:c:" opt; do
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
    f)
      field=$OPTARG
      ;;
    w)
      warn=$OPTARG
      ;;
    c)
      crit=$OPTARG
      ;;
  esac
done


if [[ -z "$host" || -z "$port" || -z "$field" || -z "$warn" || -z "$crit" ]]; then
  usage
fi

response=`echo 'stat' | nc $host $port` #
ret=$?

if [[ $ret -ne 0 ]]; then
  echo "CRITICAL: Service not accessible";
  exit 2;
fi
# Filter line containing field
response=`echo "$response" | grep -i "$field"`
ret=$?

if [[ $ret -ne 0 ]]; then
  echo "UNKNOWN: Field $field doesnt exist";
  exit 3;
fi

# get the value
response=`echo "$response" | cut -d: -f2`
# Trim whitespace
response=$(echo $response)

if (( $response >= $crit )) ; then
  echo "CRITICAL: $field = $response"
  exit 2;
elif (( $response >= $warn )) ; then
  echo "WARNING: $field = $response"
  exit 1;
else
  echo "OK: $field = $response";
  exit 0;
fi
