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

  public function __construct()
  {
    $this->host = config('services.asterisk.host');
    $this->port = config('services.asterisk.port');
    $this->username = config('services.asterisk.username');
    $this->password = config('services.asterisk.password');
  }

  /**
   * Connect to the Asterisk AMI port.
   */
  public function connect()
  {
    $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 10);
    if (!$this->socket) {
      throw new \Exception("Could not connect to Asterisk AMI at {$this->host}:{$this->port}. Error: $errstr ($errno)");
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
