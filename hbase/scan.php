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

  $options = getopt ("H:p:t:P:u:S");
  if (!array_key_exists('H', $options) || !array_key_exists('S', $options) || 
     !array_key_exists('p', $options) || !array_key_exists('t', $options) ||
     !array_key_exists('P', $options) || !array_key_exists('u', $options)){
    usage();
    exit(3);
  }

  $host=$options['H'];
  $port=$options['p'];
  $ssl_enabled=$options['S'];
  $username = $options['u'];
  $password = $options['P'];
  $table = $options['t'];

  $protocol = (array_key_exists('S', $options) ? 'https' : 'http');

  $ch = curl_init();
  $xmlheader[] = "Content-Type: text/xml";
  curl_setopt_array($ch, array( CURLOPT_URL => $protocol."://".$host.":".$port.'/gateway/clients/hbase/'.$table.'/scanner',
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPAUTH => CURLAUTH_ANY,
                                CURLOPT_FOLLOWLOCATION => false,
                                CURLOPT_USERPWD => $username.":".$password,
                                CURLOPT_SSL_VERIFYPEER => FALSE,
                                CURLOPT_HTTPHEADER => $xmlheader,
                                CURLOPT_POSTFIELDS => '<Scanner batch="1"/>',
                                CURLOPT_CUSTOMREQUEST => "PUT",
                                CURLOPT_HEADER => true));
  $output = curl_exec($ch);
  $re = '/Location: (.+)/';
  preg_match($re, $output, $matches, PREG_OFFSET_CAPTURE, 0);
  if (count($matches) != 2) {
    echo "Unable to get scanner\n";
    exit(3);
  }
  $location = trim($matches[1][0]);
  //echo $location;
  $ch = curl_init();
  $jsonheader[] = "Content-Type: application/json";
  curl_setopt_array($ch, array( CURLOPT_URL => $location,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPAUTH => CURLAUTH_ANY,
                                CURLOPT_FOLLOWLOCATION => false,
                                CURLOPT_USERPWD => $username.":".$password,
                                CURLOPT_SSL_VERIFYPEER => FALSE,
                                CURLOPT_HTTPHEADER => $jsonheader,
                                CURLOPT_HEADER => true));
  $output = curl_exec($ch);
  $content_length = curl_getinfo($ch)['download_content_length'];
  $http_code = curl_getinfo($ch)['http_code'];
  if ($content_length > 0 && $http_code == 200) {
    echo "OK: Scan HBase successful\n";
    exit(0);
  }else {
    echo "CRITICAL: Can't read table ".$table."\n";
    exit(2);
  }

  /* print usage */
  function usage () {
    echo "Usage: hbase/".basename(__FILE__)." -H <knoxHost> -p <knoxPort> -u <knoxUser> -P <knoxPasswd> -t <table> -S <ssl_enabled>\n";
  }
?>
