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

  $protocol = (array_key_exists('S', $options) ? 'https' : 'http');

    /* Get the json document */
  $object = get_info($protocol, $host, $port);
  if (empty($object)) {
    echo 'CRITICAL: Data inaccessible'.PHP_EOL;
    exit(2);
  }
  $val= array_key_exists($field, $object)? $object[$field] : null;
  if ($val === false){
    $out_msg = "Field $field = false";
  }
  elseif($val === true){
    $out_msg = "Field $field = true";
  }
  else{
    $out_msg = "Field $field = $val";
  }
  if($val === null){
    echo 'UNKNOWN: Field '.$field.' not found'.PHP_EOL;
    exit(3);
  }
  if (array_key_exists('r', $options)){
    $wantedResp=json_decode($options['r'], true);
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

  /* print usage */
  function usage () {
    echo 'Usage: ./'.basename(__FILE__).' -h help -H <host> -p <port> -f <field> -w <warn%> -c <crit%> [-r <ret_value> -S ssl_enabled]'.PHP_EOL;
  }
  function get_info($protocol, $host, $port){
    $json_string = do_curl($protocol, $host, $port, '/ws/v1/node/info');
    if($json_string === false){
      return false;
    }
    $json_array = json_decode($json_string, true);
    if(empty($json_array['nodeInfo'])){
      return false;
    }
    return $json_array['nodeInfo'];
  }
?>
