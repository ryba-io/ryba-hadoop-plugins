#!/usr/bin/env bash

# ./ $SERVICESTATE$ $SERVICEATTEMPTS$

HOST=$1
STATE=$2
ATTEMPT=$3
ACTION=$4
SERVICE=$5

if [ "$STATE" != "CRITICAL" ]; then
  echo "Not critical. Nothing to do";
  exit 0;
elif [ "$ATTEMPT" != "2" ]; then
  echo "waiting for 2nd attempt. Nothing to do";
  exit 0;
fi

ssh -o "StrictHostkeyChecking no" root@${HOST} service ${SERVICE} ${ACTION}
