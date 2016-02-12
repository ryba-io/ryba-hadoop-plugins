#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt("hH:p:s");
  //Check only for mandatory options
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) || !array_key_exists('p', $options)) {
    usage();
    exit(3);
  }

  $host=$options['H'];
  $port=$options['p'];

  $protocol = (array_key_exists('s', $options) ? "https" : "http");

  $object = get_from_jmx($protocol, $host, $port, 'Hadoop:service=NameNode,name=NameNodeInfo');

  if (empty($object) || $object[0]['NameDirStatuses'] == "") {
    echo "CRITICAL: NameNode directory status not available\n";
    exit(2);
  }
  $NameDirStatuses = json_decode($object['NameDirStatuses'], true);
  $failed_dir_count = count($NameDirStatuses['failed']);
  $out_msg = "Offline NameNode directories: ";
  if ($failed_dir_count > 0) {
    foreach ($NameDirStatuses['failed'] as $key => $value) {
      $out_msg .= $key.":".$value.", ";
    }
    echo "CRITICAL: ".$out_msg."\n";
    exit (2);
  }
  echo "OK: All NameNode directories are active\n";
  exit(0);

  /* print usage */
  function usage () {
    echo "Usage: ./".basename(__FILE__)." -h help -H <host> -p <port> -s ssl_enabled\n";
    exit(3);
  }
?>