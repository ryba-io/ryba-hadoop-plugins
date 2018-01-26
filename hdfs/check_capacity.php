#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt ("hH:p:w:c:S");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) ||
     !array_key_exists('p', $options) || !array_key_exists('w', $options) ||
     !array_key_exists('c', $options)) {
    usage();
    exit(3);
  }

  $host=$options['H'];
  $port=$options['p'];
  $warn=$options['w']; $warn = preg_replace('/%$/', '', $warn);
  $crit=$options['c']; $crit = preg_replace('/%$/', '', $crit);

  $protocol = (array_key_exists('S', $options) ? 'https' : 'http');

    /* Get the json document */
  $object = get_from_jmx($protocol, $host, $port, 'Hadoop:service=NameNode,name=FSNamesystemState');
  if (empty($object)) {
    echo 'CRITICAL: Data inaccessible'.PHP_EOL;
    exit(2);
  }
  $cap_used = $object['CapacityUsed'];
  $cap_remain = $object['CapacityRemaining'];
  $cap_total = $cap_used + $cap_remain;
  if($cap_total == 0) {
    $percent_used = 0;
  } else {
    $percent_used = round(($cap_used/$cap_total)*100, 2);
  }

  $cap_total_h = round($cap_total/(1024*1024*1024),1);
  $cap_used_h = round($cap_used/(1024*1024*1024),1);

  $out_msg = 'Capacity: '.$cap_total_h.' GB, Used: '.$cap_used_h.' GB ('.$percent_used.'%)|capUsed='.$cap_used.';capTotal='.$cap_total.';percent='.$percent_used.'%';

  if ($percent_used >= $crit) {
    echo 'CRITICAL: '.$out_msg.PHP_EOL;
    exit (2);
  }
  if ($percent_used >= $warn) {
    echo 'WARNING: '.$out_msg.PHP_EOL;
    exit (1);
  }
  echo 'OK: '.$out_msg.PHP_EOL;
  exit(0);

  /* print usage */
  function usage () {
    echo 'Usage: hdfs/'.basename(__FILE__).' -h help -H <host> -p <port> -w <warn%> -c <crit%> [-S ssl_enabled]'.PHP_EOL;
  }
?>
