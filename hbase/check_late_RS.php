#!/usr/bin/env php
<?php

  require 'lib.php';

  $options = getopt ("hH:p:P:M:S");
  if (!array_key_exists('H', $options) || !array_key_exists('p', $options) || 
      !array_key_exists('P', $options) || !array_key_exists('M', $options)) {
    usage();
    exit(3);
  }
  $hosts=$options['H'];
  $port=$options['p'];
  $path=$options['P'];
  $protocol = (array_key_exists('S', $options) ? 'https' : 'http');
  $delay=intval($options['M']) * 60;
  
  function get_wals($protocol, $host, $port, $path){
    try {
      $json_string = do_curl_failover($protocol, $host, $port, '/webhdfs/v1'.$path.'?op=LISTSTATUS');
    } catch (NotFoundException $e) {
      echo 'CRITICAL: No oldWALs found in '.$path.PHP_EOL;
      exit(2);
    }
    //echo $json_string;
    if($json_string === false){
      return false; 
    }
    // echo $json_string;
    $json_array = json_decode($json_string, true);
    // var_dump($json_array['FileStatuses']['FileStatus']);
    if(empty($json_array['FileStatuses']) || empty($json_array['FileStatuses']['FileStatus'])){
      return false;
    }
    return $json_array['FileStatuses']['FileStatus'];
  }
  
  /* Get the oldWALs metadata */
  $hostsList = explode(',',$hosts);
  $oldWALs = get_wals($protocol, $hostsList, $port, $path);
  if (empty($oldWALs)) {
    echo 'OK: No old WALs found in '.$path.PHP_EOL;
    exit(0);
  }
  
  $lateWALS = array_filter($oldWALs, function($oldWAL) use ($delay) {
    // echo('oldestTime '.strval(time() - $delay) * 1000).' ';
    // echo('fileTime '.$oldWAL['modificationTime'].PHP_EOL);
    return (((time() - $delay) * 1000) >= $oldWAL['modificationTime']);
  });
  
  if (empty($lateWALS)) {
    echo 'OK : All RS are on time'.PHP_EOL;
    exit(0);
  }
  
  $lateRS = array_unique(array_map("getRSName", $lateWALS));
  echo 'ERROR : RS '. join(', ', $lateRS) . ' are late'.PHP_EOL;
  exit(2);
  
  function getRSName($v) {
    $re = '/^(?<hostname>.*?)%/';
    preg_match_all($re, $v['pathSuffix'], $matches, PREG_SET_ORDER, 0);
    return ($matches[0]["hostname"]);
  }

  /* print usage */
  function usage () {
    echo 'Usage: hdfs/'.basename(__FILE__).' -h help -H <host> -p <port> -M <duration> -P <pathToOldWals> [-S ssl_enabled]'.PHP_EOL;
  }
?>
