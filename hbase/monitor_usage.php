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

$overTreshold = function($k) use ($thresold)
{
   return $k['datapoints'][count($k['datapoints']) - 1][0] >= $thresold;
};

function getUserAndValues($k)
{
   return $k['target'] . ' (' . $k['datapoints'][count($k['datapoints']) - 1][0] . ')';
}


function usage()
{
   echo "Usage: hbase/" . basename(__FILE__) . " -H <host> -P <port1> -S -T <thresold> -D <duration> -C <cluster> -O <scanTime|get|mutate|delete> \n";
}

$shortopts = "H:";
$shortopts .= "P:";
$shortopts .= "T:";
$shortopts .= "D:";
$shortopts .= "C:";
$shortopts .= "O:";
$shortopts .= "S";

$options = getopt($shortopts);

$operations_supported = array(
   'scanTime',
   'get',
   'mutate',
   'delete'
);

if (!array_key_exists('H', $options) || !array_key_exists('P', $options) || !array_key_exists('T', $options) || !array_key_exists('D', $options) || !array_key_exists('C', $options) || !array_key_exists('O', $options)) {
   usage();
   exit(3);
}

$host      = $options['H'];
$port      = $options['P'];
$cluster   = $options['C'];
$protocol  = (array_key_exists('S', $options) ? 'https' : 'http');
$thresold  = $options['T'];
$duration  = $options['D'];
$operation = $options['O'];

if (!in_array($operation, $operations_supported)) {
   echo "ERROR: operation $operation not supported" . PHP_EOL;
   exit(3);
}

$data = array(
   'target' => "aliasByNode(integral(sumSeriesWithWildcards(perSecond($cluster.*.hbase.regionserver.user.*.$operation" . "_num_ops),1)),4)",
   'from' => '-' . $duration,
   'format' => 'json'
);

$output = do_curl($protocol, $host, $port, "/render?" . http_build_query($data));
$values = json_decode($output, true);



$users = array_map('getUserAndValues', array_filter($values, $overTreshold));
if (empty($users)) {
   print('OK: normal usage on method ' . $operation . PHP_EOL);
   return 0;
}

print('WARNING: ' . $operation . ' high usage by users ' . implode(', ', array_map('getUserAndValues', array_filter($values, $overTreshold))) . PHP_EOL);
return 1;

?>  
