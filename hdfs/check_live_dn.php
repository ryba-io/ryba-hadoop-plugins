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
  $jmx_response = get_from_jmx($protocol, $host, $port, 'Hadoop:service=NameNode,name=FSNamesystemState');
  if(empty($jmx_response)) {
    echo 'CRITICAL: Data inaccessible'.PHP_EOL;
    exit(2);
  }
  $live = $jmx_response["NumLiveDataNodes"];
  $dead = $jmx_response["NumDeadDataNodes"];
  $decomlive = $jmx_response["NumDecomLiveDataNodes"];
  $decomdead = $jmx_response["NumDecomDeadDataNodes"];
  $stale = $jmx_response["NumStaleDataNodes"];
  $decom = $decomlive + $decomdead;
  $err = $dead + $decom + $stale;
  if($err == 0){
    echo 'OK: '.$live.' datanodes are alive'.PHP_EOL;
    exit(0);
  }
  if($err <= 3){
    echo 'WARNING: Live:'.$live.', Dead:'.$dead.', Decommissionned:'.$decom.', Stale: '.$stale.PHP_EOL;
    exit(1);
  }
  echo 'CRITICAL: Live:'.$live.', Dead:'.$dead.', Decommissionned:'.$decom.', Stale: '.$stale.PHP_EOL;
  exit(2);

  function usage () {
    echo 'Usage: hdfs/'.basename(__FILE__).' -h help -H <host> -p <port> [-S ssl_enabled]'.PHP_EOL;
  }
?>
