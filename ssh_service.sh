#!/usr/bin/env bash

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
elif [ "$HOST" = "" ]; then
  echo "no host provided"
  exit 1;
elif [ "$ACTION" = "" ]; then
  echo "no action provided"
  exit 1;
elif [ "$SERVICE" = "" ]; then
  echo "no service provided"
  exit 1;
fi

ssh -o "StrictHostkeyChecking no" root@${HOST} service ${SERVICE} ${ACTION}
