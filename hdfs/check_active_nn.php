#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt ("hH:p:C:P:S");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) ||
     !array_key_exists('p', $options) || !array_key_exists('C', $options) ||
     !array_key_exists('P', $options)) {
    usage();
    exit(3);
  }
  $hosts=explode(',', $options['H']);
  $port=$options['p'];
  $nn_port=$options['P'];
  $cluster=$options['C'];

  $protocol = (array_key_exists('S', $options) ? 'https' : 'http');
  $object = false;
  $query = "GET hosts\n";
  $query.= "Filter: host_groups >= $cluster\n";
  $query.= "Filter: host_groups >= hdfs_nn\n";
  $query.= "Columns: host_name\n";
  foreach ($hosts as $host) {
    $namenodes=query_livestatus($host, $port, $query);
    if(!empty($namenodes)) break;
  }
  $active=array();
  foreach ($namenodes as $nn_host) {
    /* Get the json document */
    foreach ($hosts as $host) {
      $object = get_from_jmx($protocol, $nn_host, $nn_port, 'Hadoop:service=NameNode,name=FSNamesystem');
      if(!empty($object)) break;
    }
    if(empty($object)) {
      echo 'CRITICAL: Data inaccessible'.PHP_EOL;
      exit(2);
    }
    if($object['tag.HAState'] == 'active'){
      $active[] = $nn_host;
    }
  }
  if (sizeof($active) == 1) {
    echo $active[0].PHP_EOL;
    exit(0);
  }
  if (sizeof($active) > 1) {
    echo 'CRITICAL: More than 1 active NN detected'.PHP_EOL;
    exit(2);
  }
  else{
    echo 'CRITICAL: No active NN detected'.PHP_EOL;
    exit(2);
  }

  /* print usage */
  function usage () {
    echo 'Usage: hdfs/'.basename(__FILE__).' -h help -H <livestatus_host> -p <livestatus_port> -C <cluster_name> -P <namenode_port> [-S ssl_enabled]'.PHP_EOL;
  }
?>
