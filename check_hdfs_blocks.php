#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt ("hH:p:j:s");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) || 
     !array_key_exists('p', $options) || !array_key_exists('j', $options)) {
    usage();
    exit(3);
  }
  $host=$options['H'];
  $port=$options['p'];
  $nn_jmx_property=$options['j'];
  
  $protocol = (array_key_exists('s', $options) ? "https" : "http");

  $object = get_from_jmx($protocol, $host, $port, 'Hadoop:service=NameNode,name='.$nn_jmx_property)

  if(empty($object)) {
    echo "CRITICAL: Data inaccessible\n";
    exit(2);
  } 
  $missing_blocks = $object['MissingBlocks'];
  $total_blocks = $object['BlocksTotal'];
  if($total_blocks == 0) {
    $m_percent = 0;
  } else {
    $m_percent = ($missing_blocks/$total_blocks)*100;
  }
  $out_msg = "missing_blocks: <".$missing_blocks .">, total_blocks: <".$total_blocks.">";

  if($m_percent > 0) {
    echo "CRITICAL: ".$out_msg.PHP_EOL;
    exit(2);
  }
  echo "OK: ".$out_msg.PHP_EOL;
  exit(0);

  /* print usage */
  function usage () {
    echo "Usage: ./".basename(__FILE__)." -h help -H <host> -p <port> -j <namenode bean name> -s ssl_enabled\n";
  }
?>
