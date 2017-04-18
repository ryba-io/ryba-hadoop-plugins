#!/usr/bin/bash

# ./ $SERVICESTATE$ $SERVICEATTEMPTS$
echo "----------------------" >> handle_meta.log;
echo "./ $1 $2 $3:" >> handle_meta.log;
SSH_HOST=$3;

SSH2_HOST=""

if [ "$1" != "CRITICAL" ]; then
  echo "Not critical. Nothing to do";
  exit 0;
elif [ "$2" != "2" ]; then
  echo "waiting for 2nd attempt. Nothing to do";
  exit 0;
fi

MYSQL_HOST=""
if [ "$SSH_HOST" = "noeyy5gt.noe.edf.fr" ]; then
  MYSQL_HOST="noeyy5gr.noe.edf.fr"
  SSH2_HOST="noeyy5gu.noe.edf.fr"
elif [ "$SSH_HOST" = "noeyy5jj.noe.edf.fr" ]; then
  MYSQL_HOST="noeyy5j6.noe.edf.fr"
  SSH2_HOST="noeyy5jg.noe.edf.fr"
else
  echo "Incorrect host";
  exit 1;
fi;

sudo ssh -o "StrictHostkeyChecking no" root@${SSH2_HOST} service hive-hcatalog-server stop >> handle_meta.log;
sudo ./ssh_exec.sh root@${SSH_HOST} hive/metaflush.sh ${MYSQL_HOST} >> handle_meta.log;
sudo ssh -o "StrictHostkeyChecking no" root@${SSH2_HOST} service hive-hcatalog-server start >> handle_meta.log;
sudo ssh -o "StrictHostkeyChecking no" root@${SSH2_HOST} service hive-server2 start >> handle_meta.log;

