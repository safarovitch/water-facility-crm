<?php
$host = '65.21.55.168';
$port = 5038;
echo "Connecting to $host:$port...\n";

$socket = @fsockopen($host, $port, $errno, $errstr, 10);
if (!$socket) {
  die("Error: $errstr ($errno)\n");
}

echo "Connected successfully to socket!\n";
$firstLine = fgets($socket);
echo "Asterisk says: $firstLine\n";

$login = "Action: Login\r\nUsername: babilonsipuserforwaterfacility\r\nSecret: raE3L1EHGyir81LibVf+eQ==\r\n\r\n";
fwrite($socket, $login);

$response = "";
while (!feof($socket)) {
  $line = fgets($socket);
  $response .= $line;
  if ($line == "\r\n") break;
}
echo "Login response: $response\n";

if ($socket) fclose($socket);
