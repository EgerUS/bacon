<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        ScriptCommandsRepository.php 
 * Created:     21.6.2013 
 * Encoding:    UTF-8 
 * 
 * Description: 
 * 
 * 
 */ 

namespace Commands;
use Nette;

class ScriptCommandsRepository extends Nette\Object {

	/** @var Log\LogRepository */
	public $log;
		
	/**
	 * ===================================
	 */
	
	
	private $debug = true;
	private $attempts = 5;
	private $port = 23;
	private $timeout = 3;
	private $delay = 3;
	private $connected = false;
	private $error_no;
	private $error_str;
	
    public function __construct(\Log\LogRepository $LogRepository)
    {
		$this->log = $LogRepository;
    }
	
	public function init($class, $deviceHost, $deviceGroupName)
	{
		$className = "\\Commands\\".$class;
		if(class_exists($className)) {
			return new $className($this, $deviceHost, $deviceGroupName);
		} else {
			$record = array('message' => 'Class \''.$className.'\' does not exist', 'messageType' => 'error', 'deviceHost' => $deviceHost, 'deviceGroupName' => $deviceGroupName);
			$this->log->addLog($record);
			return FALSE;
		}
	}
	
}
