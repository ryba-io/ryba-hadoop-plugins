#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt ("hH:p:d:f:u");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) ||
     !array_key_exists('p', $options) || !array_key_exists('d', $options)) {
    usage();
    exit(3);
  }

  $host=$options['H'];
  $port=$options['p'];
  $service=$options['d'];
  $filters=parseArrOpt($options, 'f');

  $res=query_livestatus($host,$port,create_service_request($service,$filters, array('plugin_output')));
  if(sizeof($res) == 0){
    echo "Error: No OUTPUT found";
    exit(2);
  }
  elseif(sizeof($res) > 1 && array_key_exists('u')){
    echo "ERROR: multiple OUTPUT found";
    exit(2);
  }
  else{
    foreach($res as $line){
      echo $line.PHP_EOL;
    }
  }
  function usage(){
    echo 'Usage: ./'.basename(__FILE__).' -h help -H <host> -p <port> -d <service_description> [-f [<filters>] -u raise_if_not_unique'.PHP_EOL;
  }
?>