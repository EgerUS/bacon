<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        Mikrotik.php 
 * Created:     22.6.2013 
 * Encoding:    UTF-8 
 * 
 * Description: 
 * 
 * 
 */ 

namespace Commands\RouterOS;

class Telnet extends \Nette\Object {

	private $deviceHost;
	private $deviceGroupName;
	private $socket = NULL;
	private $port = 23;

	/** @var Log\LogRepository */
	private $script;

    public function __construct(\Commands\ScriptCommandsRepository $ScriptCommandsRepository)
    {
		$this->script = $ScriptCommandsRepository;
    }
	
	
	public function connect($deviceHost, $deviceGroupName)
	{
		$this->deviceHost = $deviceHost;
		$this->deviceGroupName = $deviceGroupName;
		

		$this->socket = fsockopen($this->deviceHost,$this->port);
		socket_set_timeout($this->socket,2,0);

        if ($this->socket)
		{
			$record = array('message' => 'Connected', 'messageType' => 'info', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
			$this->script->log->addLog($record);
			return TRUE;
		}
        else
		{
			$record = array('message' => 'Connection failed', 'messageType' => 'error', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
            $this->script->log->addLog($record);
			return FALSE;
		}
	}

	public function disconnect()
	{
		if ($this->socket)
		{
			fclose($this->socket);
			$record = array('message' => 'Disconnected', 'messageType' => 'info', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
			$this->script->log->addLog($record);
		} else {
			$record = array('message' => 'Disconnect failed .. not connected', 'messageType' => 'warning', 'deviceHost' => $this->deviceHost, 'deviceGroupName' => $this->deviceGroupName);
			$this->script->log->addLog($record);
		}
		$this->socket = NULL;
	}
	
}
