<?php

  function query_socket($host, $port, $query){
    if (!($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))){
      echo "Unknown error";
      exit(2);
    }
    if(!socket_connect($sock, $host, $port)){
      echo "Connection refused";
      exit(2);
    }
    if(!socket_write($sock, $query, strlen($query))){
      echo "Unable to write into socket";
      exit(2);
    }
    $buf = '';
    if(!socket_shutdown($sock, 1)){ // 0 for read, 1 for write, 2 for r/w
      echo "Cannot close socket";
      exit(2);
    }
    if (!($bytes = socket_recv($sock, $buf, 2048, MSG_WAITALL))){
      echo "Service not responding";
      exit(2);
    }
    socket_close($sock);
    return trim($buf);
  }

  function query_livestatus($host, $port, $query){
    $buf=query_socket($host, $port, $query);
    if(empty($buf)){
      return array();
    }
    $lines=explode("\n", $buf);
    if(preg_match('/^Invalid GET/', $lines[0])){
      echo "CRITICAL: Invalid request. Check filters.";
      exit(2);
    }
    return $lines;
  }

  function do_curl($protocol, $host, $port, $url){
    $ch = curl_init();
    curl_setopt_array($ch, array( CURLOPT_URL => $protocol."://".$host.":".$port.$url,
                                  CURLOPT_RETURNTRANSFER => true,
                                  CURLOPT_HTTPAUTH => CURLAUTH_ANY,
                                  CURLOPT_USERPWD => ":",
                                  CURLOPT_SSL_VERIFYPEER => FALSE ));
    $ret = curl_exec($ch);
    curl_close($ch);
    return $ret;
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
?>