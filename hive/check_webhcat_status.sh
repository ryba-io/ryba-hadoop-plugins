#!/usr/bin/env bash
#Author: Adrian PORTE
#Usage ./check_webhcat_status.sh HOST PORT

regex="^.*\"status\":\"ok\".*<status_code:200>$"
out=`curl --negotiate -u : -s -w '<status_code:%{http_code}>' http://$1:$2/templeton/v1/status 2>&1`
if [[ $out =~ $regex ]]; then
  echo "OK: WebHCat Server status [$out]";
  exit 0;
fi
echo "CRITICAL: Error accessing WebHCat Server";
exit 2;
