#!/usr/bin/bash

PASS=${2:-Ryba4MySQL}

service hive-hcatalog-server stop;
mysql -h $1 -uryba -p${PASS} <<< "DELETE FROM hive.MASTER_KEYS; DELETE FROM hive.DELEGATION_TOKENS; COMMIT;";
service hive-hcatalog-server start;
