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

  $options = getopt ("H:p:t:P:u:c:S");
  if (!array_key_exists('H', $options) || !array_key_exists('S', $options) || 
     !array_key_exists('p', $options) || !array_key_exists('t', $options) ||
     !array_key_exists('P', $options) || !array_key_exists('u', $options)||
    !array_key_exists('c', $options)){
    print_r($options);
    usage();
    exit(3);
  }

  $host=$options['H'];
  $port=$options['p'];
  $ssl_enabled=$options['S'];
  $username = $options['u'];
  $password = $options['P'];
  $table = $options['t'];
  $cf = $options['c'];


  $protocol = (array_key_exists('S', $options) ? 'https' : 'http');

  $ch = curl_init();
  $xmlheader[] = "Content-Type: text/xml";
  $postfield = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><CellSet><Row key="dGVzdA=="><Cell column="Y2YxOg==">dmFsdWUy</Cell></Row></CellSet>';

  curl_setopt_array($ch, array( CURLOPT_URL => $protocol."://".$host.":".$port.'/gateway/clients/hbase/'.$table.'/'.$cf,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPAUTH => CURLAUTH_ANY,
                                CURLOPT_FOLLOWLOCATION => false,
                                CURLOPT_USERPWD => $username.":".$password,
                                CURLOPT_SSL_VERIFYPEER => FALSE,
                                CURLOPT_HTTPHEADER => $xmlheader,
                                CURLOPT_POSTFIELDS => $postfield,
                                CURLOPT_CUSTOMREQUEST => "POST",
                                CURLOPT_HEADER => true));
  $output = curl_exec($ch);
  $content_length = curl_getinfo($ch)['download_content_length'];
  $http_code = curl_getinfo($ch)['http_code'];
  if ($content_length == 0 && $http_code == 200) {
    echo "OK: Write Sucessful\n";
    exit(0);
  }else {
    echo "Can't write to table ".$table." : CRITICAL\n";
    exit(2);
  }


  /* print usage */
  function usage () {
    echo "Usage: hbase/".basename(__FILE__)." -H <host> -p <port> -u <knoxUsername> -P <knoxPassword> -t <hbaseTable> -c <columnfamily> -S ssl_enabled\n";
  }
?>
