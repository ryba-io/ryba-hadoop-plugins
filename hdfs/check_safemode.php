#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt ("hH:p:S");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) ||
     !array_key_exists('p', $options)) {
    usage();
    exit(3);
  }
  $host=$options['H'];
  $port=$options['p'];

  $protocol = (array_key_exists('S', $options) ? 'https' : 'http');
  $jmx_response = get_from_jmx($protocol, $host, $port, 'Hadoop:service=NameNode,name=NameNodeInfo');
  if(empty($jmx_response)) {
    echo 'CRITICAL: Data inaccessible'.PHP_EOL;
    exit(2);
  }
  $safemode = $jmx_response["Safemode"];
  if($safemode === ""){
    echo 'OK: Safe mode disabled'.PHP_EOL;
    exit(0);
  }
  else if (strncmp($safemode,"Safe mode is ON",15) == 0) {
    echo 'CRITICAL: Safe mode is ON.'.PHP_EOL;
    exit(2);
  }
  else {
    echo 'UNKNOWN: Unknown value "'.$safemode.'"'.PHP_EOL;
    exit(3);
  }
  /* print usage */
  function usage () {
    echo 'Usage: hdfs/'.basename(__FILE__).' -h help -H <host> -p <port> [-S ssl_enabled]'.PHP_EOL;
  }
?>
