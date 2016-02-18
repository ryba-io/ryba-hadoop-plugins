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
  $CapacityUsed = $object['CapacityUsed'];
  $CapacityRemaining = $object['CapacityRemaining'];
  $CapacityTotal = $CapacityUsed + $CapacityRemaining;
  if($CapacityTotal == 0) {
    $percent = 0;
  } else {
    $percent = round(($CapacityUsed/$CapacityTotal)*100, 2);
  }

  $out_msg = "$percent% -- DFS Used: ".round($CapacityUsed/(1024*1024*1024),1)
            .' GB, Total: '.round($CapacityTotal/(1024*1024*1024),1).' GB';

  if ($percent >= $crit) {
    echo 'CRITICAL: '.$out_msg.PHP_EOL;
    exit (2);
  }
  if ($percent >= $warn) {
    echo 'WARNING: '.$out_msg.PHP_EOL;
    exit (1);
  }
  echo 'OK: '.$out_msg.PHP_EOL;
  exit(0);

  /* print usage */
  function usage () {
    echo 'Usage: ./'.basename(__FILE__).' -h help -H <host> -p <port> -w <warn%> -c <crit%> [-S ssl_enabled]'.PHP_EOL;
  }
?>
