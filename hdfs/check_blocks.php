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

  $object = get_from_jmx($protocol, $host, $port, 'Hadoop:service=NameNode,name=FSNamesystem');

  if(empty($object)) {
    echo 'CRITICAL: Data inaccessible'.PHP_EOL;
    exit(2);
  }
  $missing_blocks = $object['MissingBlocks'];
  $total_blocks = $object['BlocksTotal'];
  if($total_blocks == 0) {
    $m_percent = 0;
  } else {
    $m_percent = ($missing_blocks/$total_blocks)*100;
  }

  $out_msg = 'Missing blocks: '.$missing_blocks .'/'.$total_blocks.' ('.$m_percent.'%)|missingBlocks='.$missing_blocks.';totalBlocks='.$total_blocks.';percent='.$m_percent.'%';

  if($m_percent > 0) {
    echo 'CRITICAL: '.$out_msg.PHP_EOL;
    exit(2);
  }
  echo 'OK: '.$out_msg.PHP_EOL;
  exit(0);

  /* print usage */
  function usage () {
    echo 'Usage: ./'.basename(__FILE__).' -h help -H <host> -p <port> [-S ssl_enabled]'.PHP_EOL;
  }
?>
