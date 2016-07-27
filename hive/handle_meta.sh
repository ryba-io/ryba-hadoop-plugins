#!/usr/bin/bash

# ./ $SERVICESTATE$ $SERVICEATTEMPTS$

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

sudo ./ssh_exec.sh root@${SSH_HOST} hive/metaflush.sh ${MYSQL_HOST};
sudo ssh -o "StrictHostkeyChecking no" root@${SSH2_HOST} service hive-hcatalog-server restart;

