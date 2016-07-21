#!/usr/bin/env php
<?php

  $options = getopt ("hH:p:C:c:k:S");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) ||
     !array_key_exists('p', $options) || !array_key_exists('c', $options) ||
     !array_key_exists('k', $options)) {
    usage();
    exit(3);
  }

  $host=$options['H'];
  $port=$options['p'];
  $cert=$options['c'];
  $key=$options['k'];
  $protocol = (array_key_exists('S', $options) ? 'https' : 'http');
  $cluster = (array_key_exists('C', $options) ? $options['C'] : false);

  function check_tcp($ip, $port){
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
      return false;
    }
    $res = socket_connect($socket, $ip, $port);
    if ($res != 1){
      return false;
    }
    return true;
  }
  function get_info($protocol, $host, $port, $cert, $key, $cluster){
    $ch = curl_init();
    curl_setopt_array($ch, array( CURLOPT_URL => $protocol."://".$host.":".$port.'/containers/json',
                                  CURLOPT_RETURNTRANSFER => true,
                                  CURLOPT_HTTPAUTH => CURLAUTH_ANY,
                                  CURLOPT_SSLCERT => $cert,
                                  CURLOPT_SSLKEY => $key,
                                  CURLOPT_USERPWD => ":",
                                  CURLOPT_SSL_VERIFYPEER => FALSE ));
    $json = curl_exec($ch);
    curl_close($ch);
    if($json === false){
      return false;
    }
    $json = json_decode($json, true);
    $ret = array();
    foreach ($json as $value){
      $i = $value["Names"][0];
      if($cluster !== false){
        if(strstr($i,$cluster)){
          $ret[$i] = $value["Ports"];
        }
      }
      else{
        $ret[$i] = $value["Ports"];
      }
    }
    return $ret;
  }
  /* Get the json document */
  $object = get_info($protocol, $host, $port, $cert, $key, $cluster);
  if (empty($object)) {
    echo 'CRITICAL: Data inaccessible'.PHP_EOL;
    exit(2);
  }
  //print_r($object);
  //echo PHP_EOL."---------------------".PHP_EOL;
  $ret_code = 0;
  $strings = array();
  foreach ($object as $k => $container){
    foreach ($container as $cnx){
      if(!empty($cnx["IP"]) && !empty($cnx["PublicPort"])) {
        if(!check_tcp($cnx["IP"], $cnx["PublicPort"])){
          $ret_code = 2;
          array_push($strings, $k.':'.$cnx["PublicPort"]);
        }
      }
    }
  }
  if($ret_code == 0){ 
    echo "OK: All tests successful".PHP_EOL;
  }
  else {
    echo "CRITICAL: ".join(',',$strings).PHP_EOL;
  }
  exit($ret_code);
  /* print usage */
  function usage () {
    echo 'Usage: ./'.basename(__FILE__).' -h help -H <host> -p <port> -P <path> -c <cert> -k <key> [-S ssl_enabled]'.PHP_EOL;
  }
?>