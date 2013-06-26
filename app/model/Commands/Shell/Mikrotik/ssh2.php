<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        ssh2.php 
 * Created:     22.6.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Mikrotik SSH2 remote shell class
 * 
 * 
 */ 

namespace Commands\Mikrotik;

class SSH2 extends \Nette\Object {

	private $deviceHost;
	private $deviceGroupName;
	private $username;
	private $password;
	private $connection = NULL;
	private $port = 22;
	public $lastCommand = NULL;
	public $lastCommandResult = NULL;
	public $lastCommandError = NULL;
	
	private $methods = array("kex" => "diffie-hellman-group1-sha1", 
							 "client_to_server" => array("crypt" => "3des-cbc", 
                                                        "comp" => "none"), 
							 "server_to_client" => array("crypt" => "aes256-cbc,aes192-cbc,aes128-cbc", 
                                                        "comp" => "none")); 

	private $callbacks = array("disconnect" => "disconnect_cb"); 
	
	/** @var Log\LogRepository */
	private $script;

    public function __construct(\Commands\ScriptCommandsRepository $ScriptCommandsRepository, $deviceHost, $deviceGroupName)
    {
		$this->script = $ScriptCommandsRepository;
		$this->deviceHost = $deviceHost;
		$this->deviceGroupName = $deviceGroupName;
		$record = array('message' => 'Loaded class \''.get_class($this).'\'', 'severity' => 'info', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
		$this->script->log->addLog($record);
	}

	private function read($stream)
	{
		stream_set_blocking($stream,TRUE);
		$data = "";
		while($buf = fread($stream,4096)) $data .= $buf;
		fclose($stream);
		$this->lastCommandResult = $data;
	}
	

	public function connect($username,$password) 
	{ 
		$this->username = $username;
		$this->password = $password;

		$this->connection = ssh2_connect($this->deviceHost,$this->port,$this->methods,array($this,$this->callbacks)); 

		if($this->connection) 
		{ 
			$record = array('message' => 'Connected', 'severity' => 'success', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
			$this->script->log->addLog($record);
			if(!($stream = ssh2_auth_password($this->connection,$this->username,$this->password)))
			{
				$record = array('message' => 'User \''.$this->username.'\' login failed', 'severity' => 'error', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
				$this->script->log->addLog($record);
				$this->disconnect();
			} else { 
				$record = array('message' => 'User \''.$this->username.'\' logged in', 'severity' => 'success', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
				$this->script->log->addLog($record);
			} 
		} else { 
			$record = array('message' => 'Connection failed', 'severity' => 'error', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
			$this->script->log->addLog($record);
		} 

		return $this->connection; 
    } 

	public function disconnect_cb($reason,$message,$language) 
	{ 
		$this->connection = NULL;
		$record = array('message' => 'Disconnected with reason code ['.$reason.'] and message: '.$message, 'severity' => 'warning', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
		$this->script->log->addLog($record);
    } 

	public function disconnect() 
	{
		if ($this->connection) {
			$this->connection = NULL;
			$record = array('message' => 'Disconnected', 'severity' => 'success', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
			$this->script->log->addLog($record);
		}
    } 

	public function getFingerprint()
	{
		if ($this->connection) {
			return ssh2_fingerprint($this->connection,SSH2_FINGERPRINT_MD5 | SSH2_FINGERPRINT_HEX);
		}
	}
	
	public function command($command, $waitfor = NULL)
	{
		if ($this->connection) {
			if ($waitfor)
			{
				if (!preg_match($waitfor, $this->lastCommandResult))
				{
					$record = array('message' => 'Failed to wait for the result \''.$waitfor.'\'', 'severity' => 'error', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
					$this->script->log->addLog($record);
					$record = array('message' => 'Failed to send the command \''.$command.'\'', 'severity' => 'error', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
					$this->script->log->addLog($record);
					return FALSE;
				} else {
					$record = array('message' => 'Waiting for the result \''.$waitfor.'\' was successful', 'severity' => 'success', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
					$this->script->log->addLog($record);
				}
			}
			if(!($stream = ssh2_exec($this->connection,$command,null,null,80,25)))
			{
				$record = array('message' => 'Failed to send command \''.$command.'\'', 'severity' => 'error', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
				$this->script->log->addLog($record);
			} else {
				$this->lastCommand = $command;
				$this->read($stream);
				$record = array('message' => 'Command \''.$command.'\' sended', 'severity' => 'success', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
				$this->script->log->addLog($record);
				$result = explode("\n", $this->lastCommandResult);
				array_pop($result);
				if (count($result)) {
					echo $result[count($result)-1];
					if (preg_match('/error|failed|bad command/i', $result[count($result)-1])) {
						$this->lastCommandError = $result[count($result)-1];
						$record = array('message' => 'Command \''.$command.'\' failed with error: '.$this->lastCommandError, 'severity' => 'error', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
						$this->script->log->addLog($record);
					} else {
						$this->lastCommandError = NULL;
					}
				}
			}
		}
	}
	
	public function logLastCommand() {
		if ($this->lastCommandResult)
		{
			$record = array('message' => 'Result of command \''.$this->lastCommand.'\': '.$this->lastCommandResult, 'severity' => 'info', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
			$this->script->log->addLog($record);
		}
	}
}
