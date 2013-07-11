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
 * Description: RouterOS SSH2 library
 * 
 * 
 */ 

namespace Commands\RouterOS;

class SSH2 extends \Nette\Object {

	private $port = 22;
	private $fileStoragePath = '/var/backup/';
	
	private $connection = NULL;
	public $lastCommand = NULL;
	public $lastCommandResult = NULL;
	public $lastCommandError = NULL;
	
	private $methods = array("kex" => "diffie-hellman-group1-sha1", 
							 "client_to_server" => array("crypt" => "3des-cbc", 
                                                        "comp" => "none"), 
							 "server_to_client" => array("crypt" => "aes256-cbc,aes192-cbc,aes128-cbc", 
                                                        "comp" => "none")); 

	private $callbacks = array("disconnect" => "disconnect_cb");
	
	/** @var \Commands\ScriptCommandsRepository */
	private $script;

    public function __construct(\Commands\ScriptCommandsRepository $ScriptCommandsRepository)
    {
		$this->script = $ScriptCommandsRepository;
	}

	private function read($stream)
	{
		stream_set_blocking($stream,TRUE);
		$data = "";
		while($buf = fread($stream,4096)) $data .= $buf;
		fclose($stream);
		$this->lastCommandResult = $data;
	}

	public function connect($login) 
	{ 
		@$this->connection = ssh2_connect($this->script->deviceHost,$this->port,$this->methods,array($this,$this->callbacks)); 

		if($this->connection)
		{ 
			$this->script->logRecord['message'] = 'Connected';
			if ($login === '0' || strtolower($login) === 'false') $this->script->logRecord['message'] .= ' without login';
			$this->script->logRecord['severity'] = 'success';
			$this->script->log->addLog($this->script->logRecord);
			if (!$login || $login === '1' || strtolower($login) === 'true')
			{
				$this->login();
			}
		} else { 
			$this->script->logRecord['message'] = 'Connection failed';
			$this->script->logRecord['severity'] = 'error';
			$this->script->log->addLog($this->script->logRecord);
		} 

		return $this->connection; 
    } 

	public function login() 
	{ 
		if($this->connection) 
		{ 
			if(!(@$stream = ssh2_auth_password($this->connection,$this->script->deviceUsername,$this->script->devicePassword)))
			{
				$this->script->logRecord['message'] = 'User ['.$this->script->deviceUsername.'] login failed';
				$this->script->logRecord['severity'] = 'error';
				$this->script->log->addLog($this->script->logRecord);
				$this->disconnect();
			} else { 
				$this->script->logRecord['message'] = 'User ['.$this->script->deviceUsername.'] logged in';
				$this->script->logRecord['severity'] = 'success';
				$this->script->log->addLog($this->script->logRecord);
			}
		}
    } 

	private function disconnect_cb($reason,$message,$language) 
	{ 
		$this->connection = NULL;
		$this->script->logRecord['message'] = 'Disconnected with reason code ['.$reason.'] and message: '.$message;
		$this->script->logRecord['severity'] = 'warning';
		$this->script->log->addLog($this->script->logRecord);
    } 

	public function disconnect() 
	{
		if ($this->connection) {
			$this->connection = NULL;
			$this->script->logRecord['message'] = 'Disconnected';
			$this->script->logRecord['severity'] = 'success';
			$this->script->log->addLog($this->script->logRecord);
		}
    } 

	public function sleep($sec) {
		if(is_null($sec) || !is_numeric($sec)) $sec = 5;
		sleep($sec);
		$this->script->logRecord['message'] = 'Sleep for ['.$sec.'] seconds';
		$this->script->logRecord['severity'] = 'success';
		$this->script->log->addLog($this->script->logRecord);
	}
	
	public function command($command, $waitfor = NULL)
	{
		if ($this->connection) {
			if ($waitfor)
			{
				if (@!preg_match($waitfor, $this->lastCommandResult))
				{
					$this->script->logRecord['message'] = 'Failed to wait for the result ['.$waitfor.']';
					$this->script->logRecord['severity'] = 'error';
					$this->script->log->addLog($this->script->logRecord);
					$this->script->logRecord['message'] = 'Failed to send the command ['.$command.'] with wait for ['.$waitfor.']';
					$this->script->logRecord['severity'] = 'error';
					$this->script->log->addLog($this->script->logRecord);
					return FALSE;
				} else {
					$this->script->logRecord['message'] = 'Waiting for the result ['.$waitfor.'] was successful';
					$this->script->logRecord['severity'] = 'success';
					$this->script->log->addLog($this->script->logRecord);
				}
			}
			if(!(@$stream = ssh2_exec($this->connection,$command,null,null,80,25)))
			{
				$this->script->logRecord['message'] = 'Failed to send the command ['.$command.']';
				$this->script->logRecord['severity'] = 'error';
				$this->script->log->addLog($this->script->logRecord);
			} else {
				$this->lastCommand = $command;
				$this->read($stream);
				$this->script->logRecord['message'] = 'Command ['.$command.'] sended';
				$this->script->logRecord['severity'] = 'success';
				$this->script->log->addLog($this->script->logRecord);
				$result = explode("\n", $this->lastCommandResult);
				array_pop($result);
				if (count($result)) {
					if (preg_match('/error|failed|bad command/i', $result[count($result)-1])) {
						$this->lastCommandError = $result[count($result)-1];
						$this->script->logRecord['message'] = 'Command ['.$command.'] failed with error: '.$this->lastCommandError;
						$this->script->logRecord['severity'] = 'error';
						$this->script->log->addLog($this->script->logRecord);
					} else {
						$this->lastCommandError = NULL;
					}
				}
			}
		}
	}
	
	public function scp_recv($file) {
		if ($this->connection)
		{
			$target = rtrim($this->fileStoragePath, '/').'/'.$this->script->deviceHost.'/';
			if (!file_exists($target))
			{
				if (@mkdir($target, 0777, true))
				{
					$this->script->logRecord['message'] = 'Created target directory ['.$target.'].';
					$this->script->logRecord['severity'] = 'info';
					$this->script->log->addLog($this->script->logRecord);
				} else {
					$this->script->logRecord['message'] = 'Failed to create target directory ['.$target.'].';
					$this->script->logRecord['severity'] = 'error';
					$this->script->log->addLog($this->script->logRecord);
					return FALSE;
				}
			} else {
				$this->script->logRecord['message'] = 'Target directory ['.$target.'] exists';
				$this->script->logRecord['severity'] = 'info';
				$this->script->log->addLog($this->script->logRecord);
			}

			if (@ssh2_scp_recv($this->connection, $file, $target.$file))
			{
				$this->script->logRecord['message'] = 'File ['.$file.'] was successfully downloaded to ['.$target.'] by SCP';
				$this->script->logRecord['severity'] = 'success';
				$this->script->log->addLog($this->script->logRecord);
			} else {
				$this->script->logRecord['message'] = 'Failed to download file ['.$file.'] by SCP';
				$this->script->logRecord['severity'] = 'error';
				$this->script->log->addLog($this->script->logRecord);
			}
		}
	}
	
	public function sftp_recv($file) {
		if ($this->connection)
		{
			if (@!$sftp = ssh2_sftp($this->connection))
			{
				$this->script->logRecord['message'] = 'SFTP startup failed. File ['.$file.'] cannot be downloaded';
				$this->script->logRecord['severity'] = 'error';
				$this->script->log->addLog($this->script->logRecord);
			} else {
				$this->script->logRecord['message'] = 'SFTP startup ok.';
				$this->script->logRecord['severity'] = 'info';
				$this->script->log->addLog($this->script->logRecord);
				
				$target = rtrim($this->fileStoragePath, '/').'/'.$this->script->deviceHost.'/';
				if (!file_exists($target))
				{
					if (@mkdir($target, 0777, true))
					{
						$this->script->logRecord['message'] = 'Created target directory ['.$target.'].';
						$this->script->logRecord['severity'] = 'info';
						$this->script->log->addLog($this->script->logRecord);
					} else {
						$this->script->logRecord['message'] = 'Failed to create target directory ['.$target.'].';
						$this->script->logRecord['severity'] = 'error';
						$this->script->log->addLog($this->script->logRecord);
						return FALSE;
					}
				} else {
					$this->script->logRecord['message'] = 'Target directory ['.$target.'] exists';
					$this->script->logRecord['severity'] = 'info';
					$this->script->log->addLog($this->script->logRecord);
				}

				@$size = filesize('ssh2.sftp://'.$sftp.'/'.$file);
				@$stream = fopen('ssh2.sftp://'.$sftp.'/'.$file, 'r');
				if (! $stream)
				{
					$this->script->logRecord['message'] = 'Failed to download file ['.$file.'] by SFTP';
					$this->script->logRecord['severity'] = 'error';
					$this->script->log->addLog($this->script->logRecord);
				} else {
					$contents = '';
					$read = 0;
					$len = $size;
					while ($read < $len && ($buf = fread($stream, $len - $read)))
					{
						$read += strlen($buf);
						$contents .= $buf;
					}       
					file_put_contents ($target.$file,$contents);
					@fclose($stream);
					$this->script->logRecord['message'] = 'The file ['.$file.'] of size '.$size.' bytes was successfully downloaded to ['.$target.']';
					$this->script->logRecord['severity'] = 'success';
					$this->script->log->addLog($this->script->logRecord);
				}
			}
		}
	}

	public function logLastCommand() {
		if ($this->lastCommandResult)
		{
			$this->script->logRecord['message'] = 'Result of command ['.$this->lastCommand.']: '.$this->lastCommandResult;
			$this->script->logRecord['severity'] = 'info';
			$this->script->log->addLog($this->script->logRecord);
		}
	}

	public function logFingerprint()
	{
		if ($this->connection) {
			$this->script->logRecord['message'] = 'Host fingerprint: ['.ssh2_fingerprint($this->connection,SSH2_FINGERPRINT_MD5 | SSH2_FINGERPRINT_HEX).']';
			$this->script->logRecord['severity'] = 'info';
			$this->script->log->addLog($this->script->logRecord);
		}
	}
	
}
