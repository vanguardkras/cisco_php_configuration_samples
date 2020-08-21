<!DOCTYPE html>
<html>
    <head>
        <script type="text/javascript" src="js/jquery-3.3.1.min.js"></script>
        <script>
            function ajax() {
                $.ajax({
                    url: 'get_log.php',
                    dataType: "text",
                    success: success
                });
            }
                       
            function success(result) {
                var result = $.parseJSON(result);
                var res_suc = result.suc - 1;
                $('#log').html('<pre>' + result.log + '</pre>');
                var percent = Math.round((res_suc / result.count) * 100);
                $('#progress').html('<h1>' + percent + ' %</h1>');
            }
            
            $(document).ready(function() {
                setInterval(ajax, 1000);
            });
        </script>
    </head>
    <body>
        <div id="progress"></div>
        <div id="log"></div>
    </body>
</html>

<?php

$login = 'optima';
$pass = 'optima';

include_once 'cisco.php';

echo date('H:i:s d-m-Y', time());

$file = fopen("Listcsv.csv", "r"); //Файл источников адресов для подключения

$time = stripcslashes(date('H:i:s d-m-Y', time()) . PHP_EOL);
file_put_contents('log.txt', $time);

$row = 0;
while (($datafile = fgetcsv($file, 100, ';')) !== FALSE) {
	//print_r("\r\n"); 
	//print_r($datafile);
	$ip = trim($datafile[0]);
	$host = trim($datafile[1]);
	//print_r("\r\n"); 
	//print_r($ip);
	//print_r("\r\n"); 
	//print_r($host);
	
	$con = new Cisco($ip, $login, $pass, 2);
	$con->debug = true;
	
	if ($con->connect()) {
		$con->enable('optima');
		
		$con->sendWhile("ping vrf TC $host", '#');
		$con->sendWhile('show ip arp vrf TC', '#');
		$res = $con->_data;
		$res = explode("\r\n", $res);
		array_pop($res);
		//print_r("\r\n");
		//print_r($res);
		$result = [];
		foreach ($res as $k => $r) {
			$string = $res[$k];
			$string = trim($string);
			$result[] = $string;
		}
		$result = array_unique($result);
	    //print_r("\r\n");
		//print_r($result);
		
		foreach ($result as $key => $r) {
			if (strstr($r, 'Protocol')) {
				$header = $key;
			}
		}
		$result = array_slice($result, $header);
		
		
		$titles = $result[0];
				
		$protocol = strpos($titles, 'Protocol');
		$titles = substr($titles, $protocol);
		//print_r("\r\n");
		//print_r($titles);
		$address = strpos($titles, 'Address');
		$titles = substr($titles, $address);
		//print_r("\r\n");
		//print_r($titles);
		$age = strpos($titles, 'Age (min)');
		$titles = substr($titles, $age);
		//print_r("\r\n");
		//print_r($titles);
		$hwaddress = strpos($titles, 'Hardware Addr');
		$titles = substr($titles, $hwaddress);
		//print_r("\r\n");
		//print_r($titles);
		$type = strpos($titles, 'Type');
		$titles = substr($titles, $type);
		//print_r("\r\n");
		//print_r($titles);
		$interface = strpos($titles, 'Interface');
		$titles = substr($titles, $interface);
		//print_r("\r\n");
		//print_r($titles);
		array_shift($result);
		//print_r("\r\n");
		//print_r($result);
		
		$new = [];
		foreach ($result as $k => $r) {
			$elem = [];
			$elem[] = trim(substr($r, 0, $address));
			$elem[] = trim(substr($r, $address, $age));
			$elem[] = trim(substr($r, $address + $age, $hwaddress));
			$elem[] = trim(substr($r, $address + $age + $hwaddress, $type));
			$elem[] = trim(substr($r, $address + $age + $hwaddress + $type, $interface));
			$elem[] = trim(substr($r, $address + $age + $hwaddress + $type + $interface));
			$new[] = $elem;
		}
		//print_r("\r\n");
		//print_r($new);
			
		foreach ($new as $arp) {
			if ($arp[1] == $host) {
				$mac = $arp[3];
				print_r("\r\n");
				print_r($host);
				print_r("\r\n");
				print_r($mac);
			}
		}
		
		$con->send('conf t');
		$con->send("arp vrf TC $host $mac arpa");
		$con->send('exit');
		$con->close();
	}

	$row++;
	
	file_put_contents('log.txt', $ip.' ... ' . $con->con_status . PHP_EOL, FILE_APPEND); //Статус выполнения

}
fclose($file);
?>