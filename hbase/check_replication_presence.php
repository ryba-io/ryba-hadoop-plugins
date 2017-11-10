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
 
/*
 * List the the CF where REPLICATION_SCOPE = 1  on host1 and check their 
 * presence in host 2 
 * Uses Hbase Rest
 */
require 'lib.php';

$longopts  = array(
    "H1:",
    "H2:",
    "p1:",
    "p2:",
    "S1",
    "S2" 
);
$options = getopt("", $longopts);

if (!array_key_exists('H1', $options) || !array_key_exists('p1', $options) 
      || !array_key_exists('H2', $options) || !array_key_exists('p2', $options)) {
    usage();
    exit(3);
}
$host1           = $options['H1'];
$port1           = $options['p1'];
$protocol1       = (array_key_exists('S1', $options) ? 'https' : 'http');

$host2     = $options['H2'];
$port2     = $options['p2'];
$protocol2 = (array_key_exists('S2', $options) ? 'https' : 'http');

$cf_not_replicated = array();

$output = do_curl($protocol1, $host1, $port1, "/", true);
$tables = json_decode($output, true);

// Get the list of columns families with replication scope != 0
// CF grouped by table
foreach ($tables['table'] as $table) {
    $output = do_curl($protocol1, $host1, $port1, "/" . $table['name'] . "/schema", true);
    $cfs = array();
    $decode   = json_decode($output, true);
    $cfs = array_map("getName", array_filter($decode['ColumnSchema'], function($cf) {
      return $cf['REPLICATION_SCOPE'] != 0;
    }));
    
    if (!empty($cfs)) {
        $cf_not_replicated[$table['name']] = $cfs;
    }
}

// For each table needed to be replicated, get on the second cluster whether it is actually replicated
// We remove from the array the CF/table that are replicated.
foreach ($cf_not_replicated as $table => $cfs) {
    $output = do_curl($protocol2, $host2, $port2, "/" . $table . "/schema", true);
    $decode                  = json_decode($output, true);
    $cf_not_replicated[$table] = array_diff($cf_not_replicated[$table], array_map("getName", $decode['ColumnSchema']));
    
    if (empty($cf_not_replicated[$table])) {
        unset($cf_not_replicated[$table]);
  }
}

if(empty($cf_not_replicated)) {
  echo "All replicated CF are present on $host2 \n";
  exit(0);
}

$result = array();
foreach($cf_not_replicated as $k => $v) {
  foreach($v as $cf) {
    array_push($result, "$k:$cf");
  }
}

echo "CRITICAL: replicated CFs '" . join(', ', $result) . "' are not present on $host2 \n";
exit(2);

function getName($v)
{
    return $v['name'];
}

function usage()
{
    echo "Usage: hbase/".basename(__FILE__)." --H1 <host1> --p1 <port1> --S1 --H2 <host2> --p2 <port2> --S2 \n";
}
?>
