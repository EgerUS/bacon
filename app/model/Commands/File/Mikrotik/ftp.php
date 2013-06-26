<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        ftp.php 
 * Created:     26.6.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Mikrotik FTP remote file class
 * 
 * 
 */ 

namespace Commands\Mikrotik;

class ftp extends \Nette\Object {

	private $deviceHost;
	private $deviceGroupName;
	private $username;
	private $password;
	private $connection = NULL;
	private $port = 21;
	public $lastCommand = NULL;
	public $lastCommandResult = NULL;
	public $lastCommandError = NULL;
	
	/** @var Log\LogRepository */
	private $script;

    public function __construct(\Commands\ScriptCommandsRepository $ScriptCommandsRepository, $deviceHost, $deviceGroupName)
    {
		$this->script = $ScriptCommandsRepository;
		$this->deviceHost = $deviceHost;
		$this->deviceGroupName = $deviceGroupName;
		$record = array('message' => 'Loaded class \''.get_class($this).'\'', 'messageType' => 'info', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
		$this->script->log->addLog($record);
	}

	public function connect($username,$password) 
	{ 
	}
}
