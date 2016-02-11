#!/usr/bin/env php
<?php
/*
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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

  $counts=query_livestatus($host, $port, $service, $filters, $status_codes);

  $percent = ($counts['affected']/$counts['total'])*100;
  $exit_msg = "total: <".$counts['total'].">, affected: <".$counts['affected'].">\n";
  if ($percent >= $crit) {
    echo "CRITICAL: ".$exit_msg;
    exit (2);
  }
  if ($percent >= $warn) {
    echo "WARNING: ".$exit_msg;
    exit (1);
  }
  echo "OK: ".$exit_msg;
  exit(0);

  function usage () {
    echo "Usage: ./check_aggregate.php  -h help -H <host> -p <port> -d <service description> -w <warn%> -c <crit%> [-f [<filters>] -s <status_codes>]\n";
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

  function query_livestatus($host, $port, $service, $filters, $status_codes){
    $msg=create_request($service, $filters);
    if (!($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))){
      echo "Unknown error";
      exit(2);
    }
    if(!socket_connect($sock, $host, $port)){
      echo "Connection refused";
      exit(2);
    }
    if(!socket_write($sock, $msg, strlen($msg))){
      echo "Unable to write into socket";
      exit(2);
    }
    $buf = '';
    if(!socket_shutdown($sock, 1)){ // 0 for read, 1 for write, 2 for r/w
      echo "Cannot close socket";
      exit(2);
    }
    if (!($bytes = socket_recv($sock, $buf, 2048, MSG_WAITALL))){
      echo "livestatus not responding";
      exit(2);
    }
    socket_close($sock);
    $buf=trim($buf);
    if(empty($buf)){
      echo "CRITICAL: total: 0";
      exit(2);
    }
    $lines=explode("\n", $buf);
    if(preg_match('/^Invalid GET/', $lines[0])){
      echo "CRITICAL: Invalid request. Check filters.";
      exit(2);
    }
    $invalid=0;
    foreach ($lines as $state) {
      if(!in_array($state, $status_codes)){
        $invalid++;
      }
    }
    return array('total' => sizeof($lines), 'affected' => $invalid);
  }
?>
