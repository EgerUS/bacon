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
	

	public function connect($username,$password) 
	{ 
		@$this->connection = ssh2_connect($this->script->deviceHost,$this->port,$this->methods,array($this,$this->callbacks)); 

		if($this->connection) 
		{ 
			$this->script->logRecord['message'] = 'Connected';
			$this->script->logRecord['severity'] = 'success';
			$this->script->log->addLog($this->script->logRecord);
			if(!(@$stream = ssh2_auth_password($this->connection,$username,$password)))
			{
				$this->script->logRecord['message'] = 'User ['.$username.'] login failed';
				$this->script->logRecord['severity'] = 'error';
				$this->script->log->addLog($this->script->logRecord);
				$this->disconnect();
			} else { 
				$this->script->logRecord['message'] = 'User ['.$username.'] logged in';
				$this->script->logRecord['severity'] = 'success';
				$this->script->log->addLog($this->script->logRecord);
			} 
		} else { 
			$this->script->logRecord['message'] = 'Connection failed';
			$this->script->logRecord['severity'] = 'error';
			$this->script->log->addLog($this->script->logRecord);
		} 

		return $this->connection; 
    } 

	public function disconnect_cb($reason,$message,$language) 
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
	
	public function scpRecv($file) {
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
		}
		if ($this->connection)
		{
			if (@ssh2_scp_recv($this->connection, $file, $target.$file))
			{
				$this->script->logRecord['message'] = 'File ['.$file.'] was successfully downloaded to ['.$target.']';
				$this->script->logRecord['severity'] = 'success';
				$this->script->log->addLog($this->script->logRecord);
			} else {
				$this->script->logRecord['message'] = 'Failed to download file ['.$file.'] by SCP';
				$this->script->logRecord['severity'] = 'error';
				$this->script->log->addLog($this->script->logRecord);
			}
		}
	}
	
	public function sftpRecv($file) {
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
		}
		if ($this->connection)
		{
			$sftp = ssh2_sftp($this->connection);
			$size = filesize('ssh2.sftp://'.$sftp.'/'.$file);
			$stream = fopen('ssh2.sftp://'.$sftp.'/'.$file, 'r');			
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
				$this->script->logRecord['message'] = 'File ['.$file.'] was successfully downloaded to ['.$target.']';
				$this->script->logRecord['severity'] = 'success';
				$this->script->log->addLog($this->script->logRecord);
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
}
