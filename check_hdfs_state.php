#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt ("hH:p:f:w:c:S");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) ||
     !array_key_exists('p', $options) || !array_key_exists('f', $options) ||
     !array_key_exists('w', $options) || !array_key_exists('c', $options)) {
    usage();
    exit(3);
  }

  $host=$options['H'];
  $port=$options['p'];
  $field=$options['f'];
  $warn=$options['w']; $warn = preg_replace('/%$/', '', $warn);
  $crit=$options['c']; $crit = preg_replace('/%$/', '', $crit);

  $protocol = (array_key_exists('S', $options) ? 'https' : 'http');

    /* Get the json document */
  $object = get_from_jmx($protocol, $host, $port, 'Hadoop:service=NameNode,name=FSNamesystemState');
  if (empty($object)) {
    echo 'CRITICAL: Data inaccessible'.PHP_EOL;
    exit(2);
  }
  $val = $object[$field];
  if(empty($val)){
    echo 'UNKNOWN: Field '.$field.' not found'.PHP_EOL;
    exit(3);
  }

  $out_msg = $field.' = '.$val;

  if ($val >= $crit) {
    echo 'CRITICAL: '.$out_msg.PHP_EOL;
    exit (2);
  }
  if ($val >= $warn) {
    echo 'WARNING: '.$out_msg.PHP_EOL;
    exit (1);
  }
  echo 'OK: '.$out_msg.PHP_EOL;
  exit(0);

  /* print usage */
  function usage () {
    echo 'Usage: ./'.basename(__FILE__).' -h help -H <host> -p <port> -f <field> -w <warn%> -c <crit%> [-S ssl_enabled]'.PHP_EOL;
  }
?>