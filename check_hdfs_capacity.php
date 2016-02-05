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

/* This plugin makes call to master node, get the jmx-json document
 * check the % HDFS capacity used >= warn and critical limits.
 * check_jmx -H hostaddress -p port -w 1 -c 1
 */

  $options = getopt ("hH:p:w:c:s:");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) ||
     !array_key_exists('p', $options) || !array_key_exists('w', $options) ||
     !array_key_exists('c', $options)) {
    usage();
    exit(3);
  }

  $hosts=$options['H'];
  $port=$options['p'];
  $warn=$options['w']; $warn = preg_replace('/%$/', '', $warn);
  $crit=$options['c']; $crit = preg_replace('/%$/', '', $crit);
  $ssl_enabled=$options['e'];

  $protocol = ($ssl_enabled == "true" ? "https" : "http");


  foreach (preg_split('/,/', $hosts) as $host) {
    /* Get the json document */
    $ch = curl_init();
    $username = rtrim(`id -un`, "\n");
    curl_setopt_array($ch, array( CURLOPT_URL => $protocol."://".$host.":".$port."/jmx?qry=Hadoop:service=NameNode,name=FSNamesystemState",
                                  CURLOPT_RETURNTRANSFER => true,
                                  CURLOPT_HTTPAUTH => CURLAUTH_ANY,
                                  CURLOPT_USERPWD => "$username:",
                                  CURLOPT_SSL_VERIFYPEER => FALSE ));
    $json_string = curl_exec($ch);
    $info = curl_getinfo($ch);
    if (intval($info['http_code']) == 401){
      logout();
      $json_string = curl_exec($ch);
    }
    $info = curl_getinfo($ch);
    curl_close($ch);
    $json_array = json_decode($json_string, true);
    $percent = 0;
    $object = $json_array['beans'][0];
    $CapacityUsed = $object['CapacityUsed'];
    $CapacityRemaining = $object['CapacityRemaining'];
    if (count($object) == 0) {
      echo "CRITICAL: Data inaccessible, Status code = ". $info['http_code'] ."\n";
      exit(2);
    }
    $CapacityTotal = $CapacityUsed + $CapacityRemaining;
    if($CapacityTotal == 0) {
      $percent = 0;
    } else {
      $percent = ($CapacityUsed/$CapacityTotal)*100;
      break;
    }
  }
  $out_msg = "DFSUsedGB:<" . round ($CapacityUsed/(1024*1024*1024),1) .
             ">, DFSTotalGB:<" . round($CapacityTotal/(1024*1024*1024),1) . ">";

  if ($percent >= $crit) {
    echo "CRITICAL: " . $out_msg . "\n";
    exit (2);
  }
  if ($percent >= $warn) {
    echo "WARNING: " . $out_msg . "\n";
    exit (1);
  }
  echo "OK: " . $out_msg . "\n";
  exit(0);

  /* print usage */
  function usage () {
    echo "Usage: $0 -h help -H <host> -p <port> -w <warn%> -c <crit%> -s ssl_enabled\n";
  }
?>
