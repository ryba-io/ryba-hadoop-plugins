#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt ("hH:p:f:r:w:c:S");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) ||
     !array_key_exists('p', $options) || !array_key_exists('f', $options)) {
    usage();
    exit(3);
  }
  if (!(array_key_exists('w', $options) && array_key_exists('c', $options)) &&
      !(array_key_exists('r', $options))){
    usage();
    exit(3);
  }

  $host=$options['H'];
  $port=$options['p'];
  $field=$options['f'];

  $resp=query_socket($host, $port, 'stat');
  $lines=explode("\n", $resp);
  $val=false;
  foreach($lines as $line){
    if(!(stripos($line, $field.': ') === false)){
      $val=intval(substr($line, strlen($field.': ')));
    }
  }

  $out_msg = $field.' = '.$val;
  if (array_key_exists('r', $options)){
    $wantedResp=$options['r'];
    if($val != $wantedResp){
      echo 'CRITICAL: '.$out_msg.PHP_EOL;
      exit(2);
    }
  }
  else{
    $warn=$options['w'];
    $crit=$options['c'];
    if ($val >= $crit) {
      echo 'CRITICAL: '.$out_msg.PHP_EOL;
      exit (2);
    }
    if ($val >= $warn) {
      echo 'WARNING: '.$out_msg.PHP_EOL;
      exit (1);
    }
  }
  echo 'OK: '.$out_msg.PHP_EOL;
  exit(0);

  function usage() {
    echo "Usage: ./".basename(__FILE__)." -h help -H <hostname> -p <port> -f <field> -w <warning> -c <critical> [-r <ret_value>]".PHP_EOL;
  }
?>
