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

    public function __construct(\Log\LogRepository $LogRepository)
    {
		$this->log = $LogRepository;
    }
	
	public function init($class, $deviceHost, $deviceGroupName)
	{
		$className = strtr("/Commands/".$class, "/", "\\");
		if(class_exists($className)) {
			return new $className($this, $deviceHost, $deviceGroupName);
		} else {
			$record = array('message' => 'Class \''.$className.'\' does not exist', 'severity' => 'error', 'deviceHost' => $deviceHost, 'deviceGroupName' => $deviceGroupName);
			$this->log->addLog($record);
			return FALSE;
		}
	}
	
}
