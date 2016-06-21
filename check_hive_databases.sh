#!/usr/bin/env bash
#Author: Adrian PORTE
#Script to send SHOW DATABASES to HiveServer2 
#$1 = HOST
#$2 = PORT
#$3 = REALMNAME
#Example ./check_hive_databases.sh noeyy3fn.noe.edf.fr 10001 HADOOP_DEV.EDF.FR

beeline -u "jdbc:hive2://$1:$2/;principal=hive/_HOST@$3;transportMode=http;httpPath=cliservice;ssl=true" --silent=true --outputformat=csv -f command.txt --showWarnings=false 


if  [ $? -eq 0 ]; then
	echo "OK - Query executed"
	exit 0
else
	echo "CRITICAL - Query not executed"
	exit 2
fi
