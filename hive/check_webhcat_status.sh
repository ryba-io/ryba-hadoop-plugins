#!/usr/bin/env bash

function usage {
  echo "Usage: hive/check_webhcat_status.sh HOST PORT [TIMEOUT|50]"
  exit 3;
}

HOST=$1
PORT=$2
TIMEOUT=${3:-50}

if [ -z "$HOST" -o -z "$PORT" ]; then
  usage
fi

regex="^.*\"status\":\"ok\".*<status_code:200>$"
out=`curl --negotiate -u : -s -w '<status_code:%{http_code}>' -m $TIMEOUT http://$HOST:$PORT/templeton/v1/status 2>&1`
if [[ $out =~ $regex ]]; then
  echo "OK: WebHCat Server status [$out]";
  exit 0;
fi
echo "CRITICAL: Error accessing WebHCat Server";
exit 2;
