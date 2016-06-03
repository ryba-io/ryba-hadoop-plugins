#!/usr/bin/env bash

service=$1
hosts=$2
port=$3

checkurl () {
  url=$1
  host=$2
  export no_proxy=$host
  curl $url -o /dev/null
  echo $?
}

if [[ -z "$service" || -z "$hosts" ]]; then
  echo "UNKNOWN: Invalid arguments; Usage: check_webui_ha.sh service_name, host_name";
  exit 3;
fi

case "$service" in
resourcemanager)
    url_end_part="/cluster"
    ;;
*) echo "UNKNOWN: Invalid service name [$service], valid options [resourcemanager]"
   exit 3
   ;;
esac

OIFS="$IFS"
IFS=','
read -a hosts_array <<< "${hosts}"
IFS="$OIFS"

for host in "${hosts_array[@]}"
do
  weburl="http://${host}:${port}${url_end_part}"
  if [[ `checkurl "$weburl" "$host"` -eq 0 ]]; then
    echo "OK: Successfully accessed $service Web UI"
    exit 0;
  fi
done

echo "WARNING: $service Web UI not accessible : $weburl";
exit 1;
