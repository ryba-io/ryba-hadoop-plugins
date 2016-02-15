#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt ("hH:p:f:w:c:");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) ||
     !array_key_exists('p', $options) || !array_key_exists('f', $options) ||
     !array_key_exists('w', $options) || !array_key_exists('c', $options)) {
    usage();
    exit(3);
  }
  
  $host=$options['H'];
  $port=$options['p'];
  $field=$options['f'];
  $warn=$options['w'];
  $crit=$options['c'];

  $resp=query_socket($host, $port, 'stat');
  $lines=explode("\n", $resp);
  $val=false;
  foreach($lines as $line){
    if(!(stripos($line, $field.': ') === false)){
      $val=intval(substr($line, strlen($field.': ')));
    }
  }
  if($val === false){
    echo "UNKNOWN: Field ".$field." doesnt exist".PHP_EOL;
    exit(3);
  }
  if($val >= $crit){
    echo "CRITICAL: ".$field." = ".$val.PHP_EOL;
    exit(2);
  }
  if($val >= $warn){
    echo "WARNING: ".$field." = ".$val.PHP_EOL;
    exit(2);
  }
  echo "OK: ".$field." = ".$val.PHP_EOL;
  exit(0);

  function usage() {
    echo "Usage: ./".basename(__FILE__)." -h help -H <hostname> -p <port> -f <field> -w <warning> -c <critical>".PHP_EOL;
  }
?>
