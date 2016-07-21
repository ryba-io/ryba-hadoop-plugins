#!/bin/bash
#Author: Adrian PORTE
#Script to send SHOW DATABASES to HiveServer2
#$1 = HOST
#$2 = PORT
#$3 = REALMNAME
#$4 = USER
#$5 = PASSWD
#Example ./check_hive_databases.sh HOST PORT REALNAME USER PASSWD

#echo "HOST: $1"
#echo "PORT: $2"
#echo "REALM: $3"
#echo "USER: $4"

echo $5 | kinit $4@$3 >/dev/null 2>&1;

#klist;

beeline -u "jdbc:hive2://$1:$2/;principal=hive/_HOST@$3;transportMode=http;httpPath=cliservice;ssl=true" --silent=true --outputformat=csv -e "show databases;" --showWarnings=false >/dev/null 2>&1;

if  [ $? -eq 0 ]; then
  echo "OK - Query executed"
  exit 0
else
  echo "CRITICAL - Query not executed"
  exit 2
fi
