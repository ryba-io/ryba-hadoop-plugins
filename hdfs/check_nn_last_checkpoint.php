#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt ("hH:p:w:c:d:x:S");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) ||
     !array_key_exists('p', $options) || !array_key_exists('w', $options) ||
     !array_key_exists('c', $options) || !array_key_exists('d', $options) ||
     !array_key_exists('x', $options)) {
    usage();
    exit(3);
  }

  $host=$options['H'];
  $port=$options['p'];
  # Default 200 - Percent for warning alert
  $warn=$options['w'];  $warn = preg_replace('/%$/', '', $warn);
  # Default 200 - Percent for critical alert
  $crit=$options['c']; $crit = preg_replace('/%$/', '', $crit);
  # Default 21600 - Period time
  $period=$options['d'];
  # Default 1000000 - CheckpointNode will create a checkpoint of the namespace every 'dfs.namenode.checkpoint.txns'
  $txns=$options['x'];

  $protocol = (array_key_exists('S', $options) ? 'https' : 'http');
  date_default_timezone_set('UTC');

  $response = get_from_jmx($protocol, $host, $port, 'Hadoop:service=NameNode,name=FSNamesystem');
  if ($response === false){
    echo 'CRITICAL: Data inaccessible'.PHP_EOL;
    exit(2);
  }
  $last_checkpoint_time = (int) $response['LastCheckpointTime'];

  $response = get_from_jmx($protocol, $host, $port, 'Hadoop:service=NameNode,name=NameNodeInfo');
  if ($response === false){
    echo 'CRITICAL: Data inaccessible'.PHP_EOL;
    exit(2);
  }

  $journal_transaction_info = json_decode($response['JournalTransactionInfo'], true);
  $last_txid = (int) $journal_transaction_info['LastAppliedOrWrittenTxId'];
  $most_txid = (int) $journal_transaction_info['MostRecentCheckpointTxId'];

  $delta = (time() * 1000 - $last_checkpoint_time)/1000;

  $out_msg='Last checkpoint was done ';
  if(date('H', $delta) > 0){
    $out_msg.=date('H', $delta).'h';
  }
  $out_msg.=date('i', $delta).'m ago|delta='.$delta.'s';
  if (($last_txid - $most_txid) > $txns && $delta / $period * 100 >= $crit){
    echo 'CRITICAL: '.$out_msg.PHP_EOL;
    exit(2);
  } else if(($last_txid - $most_txid) > $txns && $delta / $period * 100 >= $warn){
    echo 'WARNING: '.$out_msg.PHP_EOL;
    exit(1);
  } else {
    echo 'OK: '.$out_msg.PHP_EOL;
    exit(0);
  }

  /* print usage */
  function usage () {
    echo 'Usage: ./'.basename(__FILE__).' -h help -H <host> -p <port> -w <warn> -c <crit> -d <period> -x <txns> [-S ssl_enabled]'.PHP_EOL;
  }
?>
