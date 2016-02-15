#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt ("hH:p:m:r:");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) ||
     !array_key_exists('p', $options) || !array_key_exists('m', $options) ||
     !array_key_exists('r', $options)) {
    usage();
    exit(3);
  }
  $host=$options['H'];
  $port=$options['p'];
  $msg=$options['m'];
  $wantedResp=$options['r'];

  $resp=query_socket($host, $port, $msg);
  if(($wantedResp{0} == '/' && preg_match($wantedResp, $resp)) || ($wantedResp == $resp)){
    echo 'OK: Service replies correctly'.PHP_EOL;
    exit(0);
  }
  else{
    echo 'CRITICAL: Service replies incorrectly'.PHP_EOL;
    exit(2);
  }

  function usage(){
    echo 'Usage: ./'.basename(__FILE__).' -h help -H <host> -p <port> -m <msg> -r <response>'.PHP_EOL;
  }
?>
