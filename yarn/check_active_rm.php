#!/usr/bin/env php
<?php

  require '../lib.php';

  $options = getopt ("hH:p:C:P:S");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) ||
     !array_key_exists('p', $options) || !array_key_exists('C', $options) ||
     !array_key_exists('P', $options)) {
    usage();
    exit(3);
  }

  $hosts=explode(',', $options['H']);
  $port=$options['p'];
  $rm_port=$options['P'];
  $cluster=$options['C'];

  $protocol = (array_key_exists('S', $options) ? 'https' : 'http');

  $query = "GET hosts\n";
  $query.= "Filter: host_groups >= $cluster\n";
  $query.= "Filter: host_groups >= yarn_rm\n";
  $query.= "Columns: host_name\n";
  $resourcemanagers=false;
  foreach ($hosts as $host) {
    $resourcemanagers = query_livestatus($host, $port, $query);
    if(!empty($resourcemanagers)) break;
  }

  $active=array();
  foreach ($resourcemanagers as $rm_host) {
    $json_string = do_curl($protocol, $rm_host, $rm_port, '/jmx?qry=Hadoop:service=ResourceManager,name=ClusterMetrics');
    if($json_string === false || preg_match('/^This is standby RM/', $json_string)){
      continue;
    }
    $json_array = json_decode($json_string, true);
    $object = $json_array['beans'][0];
    if (count($object) != 0) {
      $active[] = $rm_host;
    }
  }
  if (sizeof($active) == 1) {
    echo $active[0].PHP_EOL;
    exit(0);
  }
  if (sizeof($active) > 1) {
    echo 'CRITICAL: More than 1 active RM detected'.PHP_EOL;
    exit(2);
  }
  else{
    echo 'CRITICAL: No active RM detected'.PHP_EOL;
    exit(2);
  }
  /* print usage */
  function usage () {
    echo 'Usage: ./'.basename(__FILE__).' -h help -H <livestatus_host> -p <livestatus_port> -C <cluster_name> -P <namenode_port> [-S ssl_enabled]'.PHP_EOL;
  }
?>