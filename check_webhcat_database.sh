#!/usr/bin/env bash
#Author: Adrian PORTE
#Usage ./check_webhcat_database.sh HOST PORT

regex="^.*\"tables\".*<status_code:200>$"
out=`curl --negotiate -u : -s -w '<status_code:%{http_code}>' -m 20 http://$1:$2/templeton/v1/ddl/database/default/table/?user.name=hive 2>&1`

if [[ $out =~ $regex ]]; then
  echo "OK: WebHCat Server database [${out:0:30}...]";
  exit 0;
fi
echo "CRITICAL: Error accessing WebHCat Server [${out:0:30}...]";
exit 2;
