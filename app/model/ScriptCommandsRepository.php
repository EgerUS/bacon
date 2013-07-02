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
	private $deviceUsername;
	private $devicePassword;

	/** @var \Device\DeviceRepository */
	private $Drepo;

	/** @var \Group\DeviceGroupRepository */
	private $DGrepo;
	
	/** @var \Script\ScriptRepository */
	private $Srepo;
	
	private $lib;
	
    public function __construct(\Log\LogRepository $LogRepository, \Device\DeviceRepository $DeviceRepository, \Group\DeviceGroupRepository $DeviceGroupRepository, \Script\ScriptRepository $ScriptRepository)
    {
		$this->log = $LogRepository;
		$this->Drepo = $DeviceRepository;
		$this->DGrepo = $DeviceGroupRepository;
		$this->Srepo = $ScriptRepository;
    }
	
	public function execScript($devices, $scriptId) {
		$scriptData = $this->Srepo->getScriptData(array('where' => 'id='.$scriptId))->fetch();
		foreach ($devices as $key => $value) {
			$deviceData = $this->Drepo->getDeviceData(array('where' => 'devices.id='.$value))->fetch();
			$this->deviceHost = $deviceData->host;
			!$deviceData->username
					? $this->deviceUsername = $deviceData->authenticationUsername
					: $this->deviceUsername = $deviceData->username;
			!$deviceData->password
					? $this->devicePassword = $deviceData->authenticationPassword
					: $this->devicePassword = $deviceData->password;
			
			$this->log->setLogId();
			$this->logRecord = array("logId" => $this->log->getLogId(),
									 "deviceHost" => $this->deviceHost,
									 "deviceGroupId" => $deviceData->deviceGroupId,
									 "deviceGroupName" => $deviceData->deviceGroupName,
									 "scriptId" => $scriptData->id,
									 "scriptName" => $scriptData->scriptName);

			foreach (preg_split('/\r\n|\n|\r/', $scriptData->commands) as $value) {
				$cmd = explode('->', rtrim($value, ';'));
				if (strtolower($cmd[0]) == 'file')
				{
					array_shift($cmd);
					$class = $deviceData->remoteFileClass;
				} else {
					$class = $deviceData->remoteShellClass;
				}
				$class = strtr("Commands/".$class, "/", "\\");
				$this->logRecord['class'] = $class;
				if (!isset($this->lib) || strtolower(get_class($this->lib)) != strtolower($class))
				{
					if (class_exists($class))
					{
						$this->lib = new $class($this);
						if ($this->lib)
						{
							$class = str_replace("\\Commands\\", "", get_class($this->lib));
							$this->logRecord['class'] = $class;
							$this->logRecord['message'] = 'Loaded class ['.$class.']';
							$this->logRecord['severity'] = 'info';
							$this->log->addLog($this->logRecord);
						} else {
							$this->logRecord['message'] = 'Fail to load class ['.$class.']';
							$this->logRecord['severity'] = 'error';
							$this->log->addLog($this->logRecord);
						}
					} else {
						$this->logRecord['message'] = 'Class ['.$class.'] does not exist';
						$this->logRecord['severity'] = 'error';
						$this->log->addLog($this->logRecord);
					}
				}
				if (class_exists(get_class($this->lib)))
				{
					$this->parseCmd($cmd);
				}
			}
			$this->lib = NULL;
		}
	}
	
	public function parseCmd($cmd) {
		$command = $this->getCmdWithParams($cmd[0]);
		switch ($command[0]) {
			case 'connect':
				$this->lib->connect($this->deviceUsername, $this->devicePassword);
				break;
			case 'disconnect':
				$this->lib->disconnect();
				break;
			case 'logLastCommand':
				$this->lib->logLastCommand();
				break;
			case 'command':
				(isset($cmd[1]) && preg_match('/waitfor/i', $cmd[1]))
					? $waitfor = $this->getCmdWithParams($cmd[1])
					: $waitfor = array('','');
				$this->lib->command($command[1], $waitfor[1]);
				break;
			default:
				$this->logRecord['message'] = 'Unrecognized command ['.$command[0].']';
				$this->logRecord['severity'] = 'warning';
				$this->log->addLog($this->logRecord);
				break;
		}
	}
	
	private function getCmdWithParams($value)
	{
		($cmd[0] = strstr($value, '(', true))
				? $cmd[1] = trim(strstr($value, '('), '(,)')
				: $cmd[0] = $value;
		return $cmd;
	}
}
