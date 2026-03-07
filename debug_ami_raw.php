<?php
require 'vendor/autoload.php';

use App\Services\AsteriskAmiService;

$ami = new AsteriskAmiService();
$ami->connect();
$ami->login();

echo "Waiting for events...\n";
$ami->listenForEvents(function ($event) {
  echo "EVENT: " . json_encode($event) . "\n";
});
