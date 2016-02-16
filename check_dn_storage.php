#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt ("hH:p:w:c:s");
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
  $protocol = (array_key_exists('s', $options) ? 'https' : 'http');

  /* Get the json document */
  $object = get_from_jmx($protocol, $host, $port, 'Hadoop:service=DataNode,name=FSDatasetState-*');
  if (empty($object)) {
    echo 'CRITICAL: Data inaccessible'.PHP_EOL;
    exit(2);
  }

  $cap_remain = $object['Remaining']; /* Total capacity - any extenal files created in data directories by non-hadoop app */
  $cap_total = $object['Capacity']; /* Capacity used by all data partitions minus space reserved for M/R */
  $cap_used = $cap_total - $cap_remain;
  $percent_used = round($cap_used/$cap_total * 100, 2);

  $cap_used = round($cap_used/(1024*1024*1024),2);
  $cap_total = round($cap_total/(1024*1024*1024),2);

  $out_msg = 'Capacity: '.$cap_total.' GB, Used: '.$cap_used.' GB ('.$percent_used.'%)';

  if ($percent_full > $crit) {
    echo 'CRITICAL: '.$out_msg.PHP_EOL;
    exit (2);
  }
  if ($percent_full > $warn) {
    echo 'WARNING: '.$out_msg.PHP_EOL;
    exit (1);
  }
  echo 'OK: '.$out_msg.PHP_EOL;
  exit(0);

  /* print usage */
  function usage () {
    echo 'Usage: ./'.basename(__FILE__).' -h help -H <host> -p <port> -w <warn%> -c <crit%> -s ssl_enabled'.PHP_EOL;
  }
?>