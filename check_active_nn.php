#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt ("hH:p:C:s");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) ||
     !array_key_exists('p', $options) || !array_key_exists('C', $options) {
    usage();
    exit(3);
  }
  $host=$options['H'];
  $port=$options['p'];
  $cluster=$options['C'];

  $protocol = (array_key_exists('s', $options) ? "https" : "http");

  $query = "GET hosts\n";
  $query.= "Filter: host_name != $cluster\n";
  $query.= "Filter: host_groups >= $cluster\n";
  $query.= "Filter: host_groups >= hdfs_nn\n";
  $query.= "Columns: host_name\n";
  $namenodes=query_livestatus($host, $port, $query);

  $active=array();
  foreach ($namenodes as $host) {
    /* Get the json document */
    $object = get_from_jmx($protocol, $host, $port, 'Hadoop:service=NameNode,name=FSNamesystem');
    if(empty($object)) {
      echo "CRITICAL: Data inaccessible\n";
      exit(2);
    }
    if($object['tag.HAState'] == 'active'){
      $active[] = $host;
    }
  }
  if (sizeof($active) == 1) {
    echo "$active[0]\n";
    exit(0);
  }
  if (sizeof($active) > 1) {
    echo "CRITICAL: More than 1 active NN detected";
    exit(2);
  }
  else{
    echo "CRITICAL: No active NN detected";
    exit(2);
  }

  /* print usage */
  function usage () {
    echo "Usage: ./".basename(__FILE__)." -h help -H <host> -p <port> -s ssl_enabled\n";
  }

  function create_request($cluster){
    return $msg;
  }
?>
