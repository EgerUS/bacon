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
 *				Require RouterOS SSH2 library !
 * 
 * 
 */ 

namespace Commands\RouterOS;

class SCP extends \Nette\Object {

	private $connection = NULL;
	private $ssh = NULL;
	
	const SSH_VERSION = 'SSH2';
	
	/** @var \Commands\ScriptCommandsRepository */
	private $script;

    public function __construct(\Commands\ScriptCommandsRepository $ScriptCommandsRepository)
    {
		$this->script = $ScriptCommandsRepository;		
	}
	
	private function init($sshver = NULL)
	{
		if (!isset($this->ssh->connection))
		{
			$defaultClass = __NAMESPACE__.'\\'.self::SSH_VERSION;
			$class = __NAMESPACE__.'\\'.strtoupper($sshver);
			class_exists($class)
				? $this->ssh = new $class($this->script)
				: $this->ssh = new $defaultClass($this->script);

			$this->ssh->connect('true');
			if ($this->connection = new \Net_SCP($this->ssh->connection))
			{
				$this->script->logRecord['message'] = 'SCP startup ok';
				$this->script->logRecord['severity'] = 'success';
				$this->script->log->addLog($this->script->logRecord);
			}
		}
	}

	public function get($file, $sshver = NULL)
	{
		$this->init($sshver);
		if ($this->connection)
		{
			$target = rtrim($this->script->fileStoragePath, '/').'/'.$this->script->deviceHost.'/';
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

			if (@$this->connection->get($file, $target.$file))
			{
				$this->script->logRecord['message'] = 'The file ['.$file.'] was successfully downloaded to ['.$target.']';
				$this->script->logRecord['severity'] = 'success';
				$this->script->log->addLog($this->script->logRecord);
			} else {
				$this->script->logRecord['message'] = 'Failed to download file ['.$file.']';
				$this->script->logRecord['severity'] = 'error';
				$this->script->log->addLog($this->script->logRecord);
			}
			
		} else { 
			$this->script->logRecord['message'] = 'SCP startup failed. File ['.$file.'] cannot be downloaded';
			$this->script->logRecord['severity'] = 'error';
			$this->script->log->addLog($this->script->logRecord);
		} 
	}
}
