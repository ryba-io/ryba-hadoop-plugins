#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt ("hH:p:w:c:n:S");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) ||
     !array_key_exists('p', $options) || !array_key_exists('w', $options) ||
     !array_key_exists('c', $options) || !array_key_exists('n', $options)) {
    usage();
    exit(3);
  }

  $host=$options['H'];
  $port=$options['p'];
  $service=$options['n'];
  $warn=$options['w'];
  $crit=$options['c'];

  $protocol = (array_key_exists('S', $options) ? 'https' : 'http');

  $jmx_response = get_from_jmx($protocol, $host, $port, 'Hadoop:service='.$service.',name=RpcActivityForPort*');

  if (empty($jmx_response)) {
    echo 'CRITICAL: Data inaccessible'.PHP_EOL;
    exit(2);
  }

  $queueTime = round($jmx_response[0]['RpcQueueTimeAvgTime'], 2);
  $processingTime = round($jmx_response[0]['RpcProcessingTimeAvgTime'], 2);

  $out_msg = 'RPC: Queue: '.$queueTime.' s, Processing: '.$processingTime.' s';

  if ($queueTime >= $crit) {
    echo 'CRITICAL: '.$out_msg.PHP_EOL;
    exit (2);
  }
  if ($queueTime >= $warn) {
    echo 'WARNING: '.$out_msg.PHP_EOL;
    exit (1);
  }
  echo 'OK: '.$out_msg.PHP_EOL;
  exit(0);

  /* print usage */
  function usage () {
    echo 'Usage: ./'.basename(__FILE__).' -h help -H <host> -p <port> -n <JobTracker/NameNode/JobHistoryServer> -w <warn_in_sec> -c <crit_in_sec> [-S ssl_enabled]'.PHP_EOL;
  }
?>
