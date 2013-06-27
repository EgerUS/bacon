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

	/** @var \Log\LogRepository */
	public $log;

	public $logRecord;
	public $deviceHost;

	/** @var \Group\DeviceGroupRepository */
	private $DGrepo;
	
	/** @var \Script\ScriptRepository */
	private $Srepo;
	
    public function __construct(\Log\LogRepository $LogRepository, \Group\DeviceGroupRepository $DeviceGroupRepository, \Script\ScriptRepository $ScriptRepository)
    {
		$this->log = $LogRepository;
		$this->DGrepo = $DeviceGroupRepository;
		$this->Srepo = $ScriptRepository;
    }
	
	public function init($class, $deviceHost, $deviceGroupId, $scriptId)
	{
		$className = strtr("/Commands/".$class, "/", "\\");
		$this->deviceHost = $deviceHost;
		$deviceGroupName = $this->DGrepo->getDeviceGroupData(array('select' => 'groupname', 'where' => 'id='.$deviceGroupId))->fetchSingle();
		$scriptName = $this->Srepo->getScriptData(array('select' => 'scriptName', 'where' => 'id='.$scriptId))->fetchSingle();
		$this->logRecord = array("logId" => $this->log->getLogId(),
								 "deviceHost" => $this->deviceHost,
								 "deviceGroupId" => $deviceGroupId,
								 "deviceGroupName" => $deviceGroupName,
								 "scriptId" => $scriptId,
								 "scriptName" => $scriptName,
								 "class" => $className);

		if(class_exists($className)) {
			$_class = new $className($this);
			if ($_class)
			{
				$class = str_replace("Commands\\", "", get_class($_class));
				$this->logRecord['class'] = $class;
				$this->logRecord['message'] = 'Loaded class ['.$class.']';
				$this->logRecord['severity'] = 'info';
				$this->log->addLog($this->logRecord);
				return $_class;
			} else {
				$this->logRecord['message'] = 'Fail to load class ['.$class.']';
				$this->logRecord['severity'] = 'error';
				$this->log->addLog($this->logRecord);
				return FALSE;
			}
		} else {
			$this->logRecord['message'] = 'Class ['.$class.'] does not exist';
			$this->logRecord['severity'] = 'error';
			$this->log->addLog($this->logRecord);
			return FALSE;
		}
	}
	
}
