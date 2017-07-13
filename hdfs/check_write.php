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
  if (!array_key_exists('H', $options) || !array_key_exists('p', $options) || 
     !array_key_exists('t', $options) || !array_key_exists('P', $options) ||
     !array_key_exists('u', $options)){
    usage();
    exit(3);
  }

  $host=$options['H'];
  $port=$options['p'];
  $username = $options['u'];
  $password = $options['P'];
  $path = $options['t'];

  $protocol = (array_key_exists('S', $options) ? 'https' : 'http');
  $octetheader[] = "Content-Type: application/octet-stream";
  $ch = curl_init();
  curl_setopt_array($ch, array( 
    CURLOPT_URL => $protocol."://".$host.":".$port.'/gateway/clients/webhdfs/v1'.$path.'?op=CREATE&overwrite=true',
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPAUTH => CURLAUTH_ANY,
                                CURLOPT_FOLLOWLOCATION => false,
                                CURLOPT_USERPWD => $username.":".$password,
                                CURLOPT_SSL_VERIFYPEER => FALSE,
                                CURLOPT_CUSTOMREQUEST => "PUT",
                                CURLOPT_HEADER => true));
  $output = curl_exec($ch);
  $re = '/Location: (.+)/';
  preg_match($re, $output, $matches, PREG_OFFSET_CAPTURE, 0);
  if (count($matches) != 2) {
    echo "ERROR: Unable to get datanode URL\n";
    exit(2);
  }
  $location = trim($matches[1][0]);
  $ch = curl_init();
  curl_setopt_array($ch, array( CURLOPT_URL => $location,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPAUTH => CURLAUTH_ANY,
                                CURLOPT_FOLLOWLOCATION => false,
                                CURLOPT_USERPWD => $username.":".$password,
                                CURLOPT_SSL_VERIFYPEER => FALSE,
                                CURLOPT_CUSTOMREQUEST => "PUT",
                                CURLOPT_HTTPHEADER => $octetheader,
                                CURLOPT_POSTFIELDS => 'test'));
  $output = curl_exec($ch);
  echo $output;
  $content_length = curl_getinfo($ch)['download_content_length'];
  $http_code = curl_getinfo($ch)['http_code'];
  if ($http_code == 201) {
    echo "OK: HDFS Write Successful\n";
    exit(0);
  }else {
    echo "CRITICAL: Can't write to HDFS\n";
    exit(2);
  }

  /* print usage */
  function usage () {
    echo "Usage: hdfs/".basename(__FILE__)." H <knoxHost> -p <knoxPort> -u <knoxUsername> -P <knoxPassword> -t <hdfsPath> -S ssl_enabled\n";
  }
?>
