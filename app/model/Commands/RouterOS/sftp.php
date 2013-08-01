<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        sftp.php 
 * Created:     23.7.2013 
 * Encoding:    UTF-8 
 * 
 * Description: RouterOS SFTP library
 * 
 * 
 */ 

namespace Commands\RouterOS;

class SFTP extends \Nette\Object {

	private $connection = NULL;
	private $port = 22;
	private $timeout = 2;

	/** @var \Commands\ScriptCommandsRepository */
	private $script;

    public function __construct(\Commands\ScriptCommandsRepository $ScriptCommandsRepository)
    {
		$this->script = $ScriptCommandsRepository;
	}

	public function connect($login) 
	{ 
		@$this->connection = new \Net_SFTP($this->script->deviceHost);

		if ($this->connection)
		{ 
			$this->script->logRecord['message'] = 'Connected';
			if ($login === '0' || strtolower($login) === 'false')
				$this->script->logRecord['message'] .= ' without login';
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
    } 

	public function login() 
	{
		if (!$this->connection)
			$this->connect('true');

		if ($this->connection) 
		{ 
			if (@!$this->connection->login($this->script->deviceUsername,$this->script->devicePassword))
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
		} else {
			$this->script->logRecord['message'] = 'Unable to login due to the connection is not established';
			$this->script->logRecord['severity'] = 'error';
			$this->script->log->addLog($this->script->logRecord);
		}
    } 

	public function disconnect() 
	{
		if ($this->connection) {
			$this->connection->disconnect();
			$this->connection = NULL;
			$this->script->logRecord['message'] = 'Disconnected';
			$this->script->logRecord['severity'] = 'success';
			$this->script->log->addLog($this->script->logRecord);
		}
    } 

	public function get($file) {
		if (!$this->connection)
			$this->connect('true');
		
		if ($this->connection)
		{
			if (!file_exists(STORAGE_PATH))
			{
				if (@mkdir(STORAGE_PATH, 0777, true))
				{
					$this->script->logRecord['message'] = 'Created target directory ['.STORAGE_PATH.'].';
					$this->script->logRecord['severity'] = 'info';
					$this->script->log->addLog($this->script->logRecord);
				} else {
					$this->script->logRecord['message'] = 'Failed to create target directory ['.STORAGE_PATH.'].';
					$this->script->logRecord['severity'] = 'error';
					$this->script->log->addLog($this->script->logRecord);
					return FALSE;
				}
			} else {
				$this->script->logRecord['message'] = 'Target directory ['.STORAGE_PATH.'] exists';
				$this->script->logRecord['severity'] = 'info';
				$this->script->log->addLog($this->script->logRecord);
			}
			$size=$this->connection->size($file);
			if (@$this->connection->get($file, STORAGE_PATH.$file))
			{
				$this->script->logRecord['message'] = 'The file ['.$file.'] of size '.$size.' bytes was successfully downloaded to ['.STORAGE_PATH.']';
				$this->script->logRecord['severity'] = 'success';
				$this->script->log->addLog($this->script->logRecord);
				$this->script->fileRecord['filename'] = $file;
				$this->script->files->addLog($this->script->fileRecord);
			} else {
				$this->script->logRecord['message'] = 'Failed to download file ['.$file.']';
				$this->script->logRecord['severity'] = 'error';
				$this->script->log->addLog($this->script->logRecord);
			}
		} else { 
			$this->script->logRecord['message'] = 'SFTP connection failed. File ['.$file.'] cannot be downloaded';
			$this->script->logRecord['severity'] = 'error';
			$this->script->log->addLog($this->script->logRecord);
		} 
	}
	
	public function put($file, $data, $mode = NULL) {
		if (!$this->connection)
			$this->connect('true');
		
		if ($this->connection)
		{
			if (strtolower($mode) === 'string')
			{
				if (@$this->connection->put($file, $data))
				{
					$this->script->logRecord['message'] = 'The string was successfully written to the remote file ['.$file.']';
					$this->script->logRecord['severity'] = 'success';
					$this->script->log->addLog($this->script->logRecord);
				} else {
					$this->script->logRecord['message'] = 'Failed to write the string to the remote file ['.$file.']';
					$this->script->logRecord['severity'] = 'error';
					$this->script->log->addLog($this->script->logRecord);
				}
			} else {
				if (@$this->connection->put($file, $data, NET_SFTP_LOCAL_FILE))
				{
					$this->script->logRecord['message'] = 'The local file ['.$data.'] was successfully uploaded to the remote file ['.$file.']';
					$this->script->logRecord['severity'] = 'success';
					$this->script->log->addLog($this->script->logRecord);
				} else {
					$this->script->logRecord['message'] = 'Failed to upload the local file ['.$data.'] to the remote file ['.$file.']';
					$this->script->logRecord['severity'] = 'error';
					$this->script->log->addLog($this->script->logRecord);
				}
			}
		} else { 
			$this->script->logRecord['message'] = 'SFTP connection failed. Remote file ['.$file.'] cannot be created';
			$this->script->logRecord['severity'] = 'error';
			$this->script->log->addLog($this->script->logRecord);
		} 
	}
}

