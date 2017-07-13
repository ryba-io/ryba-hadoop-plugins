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
  require 'lib.php';

  $options = getopt ("H:p:S");
  if (!array_key_exists('H', $options) || 
     !array_key_exists('p', $options)){
    usage();
    exit(3);
  }
  $host=$options['H'];
  $port=$options['p'];
  $protocol = (array_key_exists('S', $options) ? 'https' : 'http');
  $offline=array();
  $output = do_curl($protocol,$host,$port,"/master-status");
  #echo $output;
  $re = '/<td>(?<namespace>.*)<\/td>\s+<td>\<a\shref=(?<url>[^\<]+)>(?<tablename>[^<]+).*<\/td>\s+<td>(?<online>[0-9]+)<\/td>\s+<td>(?<offline>[0-9]+)/';
  preg_match_all($re, $output, $matches, PREG_SET_ORDER, 0);
  foreach ($matches as $key => $value) {
    if ($value['offline'] > 0) {
      #If offline, check if disabled
      $table_output = do_curl($protocol,$host,$port,'/'.$value["url"]);
      #If enabled: fail
      if(preg_match('/<td>Enabled<\/td>\s+<td>true<\/td>/', $table_output)) {
        if(!in_array($offline, $value['tablename'])){
          array_push($offline, $value['tablename']);
        }
      }
    }
  }
  if(!empty($offline)) {
    echo "CRITICAL: tables ".join(',', $offline)." have offline regions";
    exit(2);
  }
  echo "OK: No region offline";
  exit(0);

  /* print usage */
  function usage () {
    echo "Usage: hbase/".basename(__FILE__)." -H <host> -p <port> -S \n";
  }
?>
