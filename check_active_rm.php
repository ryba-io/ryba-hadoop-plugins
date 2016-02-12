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
    $json_string = do_curl($protocol, $host, $port, '/jmx?qry=Hadoop:service=ResourceManager,name=ClusterMetrics');
    if($json_string === false){
      echo "CRITICAL: Data inaccessible\n";
      exit(2);
    }
    if(preg_match('/^This is standby RM/', $json_string)){
      continue;
    }
    $json_array = json_decode($json_string, true);
    $object = $json_array['beans'][0];
    if (count($object) != 0) {
      $active[] = $host;
    }
  }
  if (sizeof($active) == 1) {
    echo "$active[0]\n";
    exit(0);
  }
  if (sizeof($active) > 1) {
    echo "CRITICAL: More than 1 active RM detected";
    exit(2);
  }
  else{
    echo "CRITICAL: No active RM detected";
    exit(2);
  }
  /* print usage */
  function usage () {
    echo "Usage: ./".basename(__FILE__)." -h help -H <hosts> -p <port> -s ssl_enabled\n";
  }
?>
