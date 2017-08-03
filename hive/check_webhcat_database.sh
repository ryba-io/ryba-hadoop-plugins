#!/usr/bin/env bash

function usage {
  echo "Usage: hive/check_webhcat_database.sh HOST PORT [DATABASE|default] [TIMEOUT|50]"
  exit 3;
}

HOST=$1
PORT=$2
DATABASE=${3:-default}
TIMEOUT=${4:-50}

if [ -z "$HOST" -o -z "$PORT" ]; then
  usage
fi

regex="^.*\"tables\".*<status_code:200>$"
out=`curl --negotiate -u : -s -w '<status_code:%{http_code}>' -m $TIMEOUT http://$HOST:$PORT/templeton/v1/ddl/database/$DATABASE/table/ 2>&1`

if [[ $out =~ $regex ]]; then
  echo "OK: Database '$DATABASE' [${out:0:30}...]";
  exit 0;
fi
echo "CRITICAL: Error accessing WebHCat Server";
exit 2;
