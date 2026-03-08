<?php

namespace App\Services;

/**
 * Service to handle Asterisk Manager Interface (AMI) communication
 * via native PHP TCP sockets.
 */
class AsteriskAmiService
{
  protected $host;
  protected $port;
  protected $username;
  protected $password;
  protected $socket;

  public function __construct() {
    $this->host = env('ASTERISK_AMI_HOST', '65.21.55.168');
    $this->port = env('ASTERISK_AMI_PORT', 5038);
    $this->username = env('ASTERISK_AMI_USER', 'babilonsipuserforwaterfacility');
    $this->password = env('ASTERISK_AMI_SECRET', 'raE3L1EHGyir81LibVf+eQ==');
  }

  /**
   * Connect to the Asterisk AMI port.
   */
  public function connect()
  {
    $context = stream_context_create(['socket' => ['bindto' => '0.0.0.0:0']]);
    $this->socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);

    if (!$this->socket) {
      throw new \Exception("Could not connect to Asterisk AMI at tcp://{$this->host}:{$this->port}. Error: $errstr ($errno)");
    }

    // Read the Asterisk Hello message
    fgets($this->socket);

    return true;
  }

  /**
   * Authenticate with the AMI.
   */
  public function login()
  {
    $response = $this->sendAction([
      'Action' => 'Login',
      'Username' => $this->username,
      'Secret' => $this->password,
    ]);

    if (strpos($response, 'Response: Success') === false) {
      throw new \Exception("AMI Login Failed: " . trim($response));
    }

    return true;
  }

  /**
   * Originate a call.
   */
  public function originate($channel, $extension, $context, $priority = 1, $callerid = null)
  {
    $action = [
      'Action' => 'Originate',
      'Channel' => $channel,
      'Exten' => $extension,
      'Context' => $context,
      'Priority' => $priority,
      'Async' => 'true',
    ];

    if ($callerid) {
      $action['CallerID'] = $callerid;
    }

    return $this->sendAction($action);
  }

  /**
   * Logoff and close the connection.
   */
  public function logoff()
  {
    if ($this->socket) {
      $this->sendAction(['Action' => 'Logoff']);
      fclose($this->socket);
      $this->socket = null;
    }
  }

  /**
   * Listen indefinitely for incoming AMI events and execute a callback.
   */
  public function listenForEvents(callable $callback)
  {
    if (!$this->socket) {
      $this->connect();
      $this->login();
    }

    $eventData = [];
    while (!feof($this->socket)) {
      $line = trim(fgets($this->socket));

      if ($line === "") {
        // Empty line means the end of an event block
        if (!empty($eventData)) {
          $callback($eventData);
          $eventData = [];
        }
      } else {
        $parts = explode(": ", $line, 2);
        if (count($parts) === 2) {
          $eventData[$parts[0]] = $parts[1];
        }
      }
    }
  }

  /**
   * Send an action to AMI and read the immediate response.
   */
  protected function sendAction(array $parameters)
  {
    if (!$this->socket) {
      $this->connect();
    }

    $message = "";
    foreach ($parameters as $key => $value) {
      $message .= "$key: $value\r\n";
    }
    $message .= "\r\n";

    fwrite($this->socket, $message);

    $response = "";
    while (!feof($this->socket)) {
      $line = fgets($this->socket);
      $response .= $line;
      // AMI responses end with a blank line
      if ($line == "\r\n") {
        break;
      }
    }

    return $response;
  }
}
