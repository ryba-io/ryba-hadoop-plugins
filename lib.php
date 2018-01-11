<?php

  function query_socket($host, $port, $query, $bufsize=2048){
    if (!($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))){
      echo 'ERROR: Unknown error'.PHP_EOL;
      exit(2);
    }
    if(!socket_connect($sock, $host, $port)){
      echo 'ERROR: Connection refused'.PHP_EOL;
      exit(2);
    }
    if(!socket_write($sock, $query, strlen($query))){
      echo 'ERROR: Unable to write into socket'.PHP_EOL;
      exit(2);
    }
    $buf = '';
    if(!socket_shutdown($sock, 1)){ // 0 for read, 1 for write, 2 for r/w
      echo 'ERROR: Cannot close socket'.PHP_EOL;
      exit(2);
    }
    $read ='';
    while(($flag=socket_recv($sock, $buf, $bufsize,0))>0){
      $asc=ord(substr($buf, -1));
      if($asc==0) {
        $read.=substr($buf,0,-1);
        break;
      } else {
        $read.=$buf;
      }
    }
    if ($flag<0){
      echo 'ERROR: Socket read error'.PHP_EOL;
      exit(2);
    }
    socket_close($sock);
    return $read;
  }

  function query_livestatus($host, $port, $query){
    $buf=trim(query_socket($host, $port, $query));
    if($buf == ''){
      return array();
    }
    $lines=explode("\n", $buf);
    if(preg_match('/^Invalid GET/', $lines[0])){
      echo 'CRITICAL: Invalid request. Check filters'.PHP_EOL;
      exit(2);
    }
    return $lines;
  }

  function do_curl($protocol, $host, $port, $url, $json = false){
    //echo $protocol."://".$host.":".$port.$url;
    $ch = curl_init();
    curl_setopt_array($ch, array( CURLOPT_URL => $protocol."://".$host.":".$port.$url,
                                  CURLOPT_RETURNTRANSFER => true,
                                  CURLOPT_FAILONERROR => true,
                                  CURLOPT_HTTPAUTH => CURLAUTH_ANY,
                                  CURLOPT_USERPWD => ":",
                                  CURLOPT_SSL_VERIFYPEER => FALSE ));

    if ($json) {
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Accept: application/json",
        "Content-Type: application/json"
        ));
    }

    $ret = curl_exec($ch);
    if(curl_errno($ch)){
      echo 'Curl error: '.curl_error($ch);
      exit(3);
    }
    curl_close($ch);
    return $ret;
  }

  class NotFoundException extends Exception { }
  
  # Receive a, array of hosts as a parameter
  function do_curl_failover($protocol, $hosts, $port, $url, $json = false){
    foreach($hosts as $host) {
      $ch = curl_init();
      // echo $protocol."://".$host.":".$port.$url;
      curl_setopt_array($ch, array( CURLOPT_URL => $protocol."://".$host.":".$port.$url,
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_FAILONERROR => true,
                                    CURLOPT_HTTPAUTH => CURLAUTH_ANY,
                                    CURLOPT_USERPWD => ":",
                                    CURLOPT_SSL_VERIFYPEER => FALSE ));

      if ($json) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          "Accept: application/json",
          "Content-Type: application/json"
          ));
      }

      $ret = curl_exec($ch);
      if(!curl_errno($ch)){
        curl_close($ch);
        return $ret;
      }
      if(curl_errno($ch) == '22' && curl_getinfo($ch, CURLINFO_HTTP_CODE) == '404'){
        throw new NotFoundException($protocol."://".$host.":".$port.$url);
      }
    }
    $hostsList = join(',', $hosts);
    echo "Curl error : unable to connect to $hostsList".PHP_EOL;
    exit(3);
  }

  function get_from_jmx($protocol, $host, $port, $query){
    $json_string = do_curl($protocol, $host, $port, '/jmx?qry='.$query);
    if($json_string === false){
      return false;
    }
    $json_array = json_decode($json_string, true);
    if(empty($json_array['beans']) || empty($json_array['beans'][0])){
      return false;
    }
    return $json_array['beans'][0];
  }

  function create_service_request($service, $filters, $col=array('state')){
    $msg = "GET services\n";
    $msg.= "Filter: description = $service\n";
    foreach($filters as $filter){
      $msg.= "Filter: $filter\n";
    }
    $msg.= "Columns: ".join(' ', $col)."\n";
    return $msg;
  }

  function parseArrOpt($arr, $index, $default=array()){
    return (array_key_exists($index, $arr) ? (is_array($arr[$index]) ? $arr[$index] : array($arr[$index])) : $default);
  }
?>
