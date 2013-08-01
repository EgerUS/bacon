<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        scp.php 
 * Created:     24.7.2013 
 * Encoding:    UTF-8 
 * 
 * Description: RouterOS SCP library
 *				Require RouterOS SSH2 or SSH1 library !
 * 
 * 
 */ 

namespace Commands\RouterOS;

define('CLASS_BASE', __NAMESPACE__.'\\');
define('SSH1', CLASS_BASE.'SSH1');
define('SSH2', CLASS_BASE.'SSH2');

class SCP extends \Nette\Object {

	private $connection = NULL;
	private $ssh = NULL;
	
	private $ssh1 = FALSE;
	
	/** @var \Commands\ScriptCommandsRepository */
	private $script;

    public function __construct(\Commands\ScriptCommandsRepository $ScriptCommandsRepository)
    {
		$this->script = $ScriptCommandsRepository;		
	}
	
	private function init()
	{
		if (!isset($this->ssh->connection))
		{
			$this->ssh1
				? $class = SSH1
				: $class = SSH2;
			if (class_exists($class))
			{
				@$this->ssh = new $class($this->script);
				$this->ssh->connect('true');
				if ($this->connection = new \Net_SCP($this->ssh->connection))
				{
					$this->script->logRecord['message'] = 'SCP startup ok';
					$this->script->logRecord['severity'] = 'success';
					$this->script->log->addLog($this->script->logRecord);
				}
			} else {
				$this->script->logRecord['message'] = 'Failed to init SSH class ['.$class.']';
				$this->script->logRecord['severity'] = 'error';
				$this->script->log->addLog($this->script->logRecord);
				exit;
			}
		}
	}

	public function ssh1()
	{
		if (class_exists(SSH1))
		{
			$this->ssh1 = TRUE;
			$this->script->logRecord['message'] = 'SSH is set to protocol version 1';
			$this->script->logRecord['severity'] = 'info';
			$this->script->log->addLog($this->script->logRecord);
		} else {
			$this->ssh1 = FALSE;
			$this->script->logRecord['message'] = 'Class for SSH1 not found. SSH is set to protocol version 2';
			$this->script->logRecord['severity'] = 'warning';
			$this->script->log->addLog($this->script->logRecord);
		}
	}

	public function ssh2()
	{
		$this->ssh1 = FALSE;
		$this->script->logRecord['message'] = 'SSH is set to protocol version 2';
		$this->script->logRecord['severity'] = 'info';
		$this->script->log->addLog($this->script->logRecord);
	}

	public function get($file)
	{
		$this->init();
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

			if (@$this->connection->get($file, STORAGE_PATH.$file))
			{
				$this->script->logRecord['message'] = 'The file ['.$file.'] was successfully downloaded to ['.STORAGE_PATH.']';
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
			$this->script->logRecord['message'] = 'SCP connection failed. File ['.$file.'] cannot be downloaded';
			$this->script->logRecord['severity'] = 'error';
			$this->script->log->addLog($this->script->logRecord);
		}
	}
	
	public function put($file, $data, $mode = NULL) {
		$this->init();
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
				if (@$this->connection->put($file, $data, NET_SCP_LOCAL_FILE))
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
			$this->script->logRecord['message'] = 'SCP connection failed. Remote file ['.$file.'] cannot be created';
			$this->script->logRecord['severity'] = 'error';
			$this->script->log->addLog($this->script->logRecord);
		} 
	}
}
