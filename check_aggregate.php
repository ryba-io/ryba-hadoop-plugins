#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt ("hH:p:d:f:s:w:c:");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) ||
     !array_key_exists('p', $options) || !array_key_exists('d', $options) ||
     !array_key_exists('w', $options) || !array_key_exists('c', $options)) {
    usage();
    exit(3);
  }

  $host=$options['H'];
  $port=$options['p'];
  $service=$options['d'];
  $warn=$options['w']; $warn = preg_replace('/%$/', '', $warn);
  $crit=$options['c']; $crit = preg_replace('/%$/', '', $crit);
  $status_codes=parseArrOpt($options, 's', array(0));
  $filters=parseArrOpt($options, 'f');

  $query=create_request($service,$filters);
  $lines=query_livestatus($host, $port, $query);
  $invalid=0;
  foreach ($lines as $status) {
    if(!in_array($status, $status_codes)){
      $invalid++;
    }
  }
  $total = sizeof($lines);
  $percent = ($total != 0)? ($invalid/$total)*100 : 0;
  $exit_msg = "total: <".$total.">, affected: <".$invalid.">";
  if ($percent >= $crit) {
    echo 'CRITICAL: '.$exit_msg.PHP_EOL;
    exit (2);
  }
  if ($percent >= $warn) {
    echo 'WARNING: '.$exit_msg.PHP_EOL;
    exit (1);
  }
  echo 'OK: '.$exit_msg.PHP_EOL;
  exit(0);

  function usage () {
    echo 'Usage: ./'.basename(__FILE__).' -h help -H <host> -p <port> -d <service description> -w <warn%> -c <crit%> [-f [<filters>] -s <status_codes>]'.PHP_EOL;
  }

  function parseArrOpt($arr, $index, $default=array()){
    return (array_key_exists($index, $arr) ? (is_array($arr[$index]) ? $arr[$index] : array($arr[$index])) : $default);
  }

  function create_request($service, $filters, $col=array('state')){
    $msg = "GET services\n";
    $msg.= "Filter: description = $service\n";
    foreach($filters as $filter){
      $msg.= "Filter: $filter\n";
    }
    $msg.= "Columns: ".join(' ', $col)."\n";
    return $msg;
  }
?>
