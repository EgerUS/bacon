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
	
	/** @var \Files\FilesRepository */
	public $files;

	public $logRecord;
	public $fileRecord;
	public $deviceHost;
	public $deviceUsername;
	public $devicePassword;

	/** @var \Device\DeviceRepository */
	private $Drepo;

	/** @var \Group\DeviceGroupRepository */
	private $DGrepo;
	
	/** @var \Script\ScriptRepository */
	private $Srepo;
	
	private $lib;
	
	private $dateTime;
	private $dateTimeFull;
	
    public function __construct(\Log\LogRepository $LogRepository, \Files\FilesRepository $FilesRepository, \Device\DeviceRepository $DeviceRepository, \Group\DeviceGroupRepository $DeviceGroupRepository, \Script\ScriptRepository $ScriptRepository)
    {
		$this->log = $LogRepository;
		$this->files = $FilesRepository;
		$this->Drepo = $DeviceRepository;
		$this->DGrepo = $DeviceGroupRepository;
		$this->Srepo = $ScriptRepository;
	}
	
	public function loadClass($class) {
		if (class_exists($class))
		{
			$this->lib = new $class($this);
			if ($this->lib)
			{
				$class = str_replace("Commands\\", "", get_class($this->lib));
				$this->logRecord['class'] = $class;
				$this->logRecord['message'] = 'Loaded class ['.$class.']';
				$this->logRecord['severity'] = 'info';
				$this->log->addLog($this->logRecord);
				$this->fileRecord['class'] = $class;
			} else {
				$this->logRecord['message'] = 'Fail to load class ['.$class.']';
				$this->logRecord['severity'] = 'error';
				$this->log->addLog($this->logRecord);
			}
		} else {
			$this->logRecord['class'] = $class;
			$this->logRecord['message'] = 'Class ['.$class.'] does not exist';
			$this->logRecord['severity'] = 'error';
			$this->log->addLog($this->logRecord);
			exit;
		}
	}
	
	public function execScript($devices, $scriptId) {
		$date = new \DateTime();
		$this->dateTime = $date->format('Y-m-d');
		$this->dateTimeFull = $date->format('Y-m-d-H-i-s');
		
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
			
			define('STORAGE_PATH', '/var/backup/'.$deviceData->deviceClass.'/'.$this->deviceHost.'/');

			$this->log->setLogId();
			$this->logRecord = array("logId" => $this->log->getLogId(),
									 "deviceHost" => $this->deviceHost,
									 "deviceGroupId" => $deviceData->deviceGroupId,
									 "deviceGroupName" => $deviceData->deviceGroupName,
									 "scriptId" => $scriptData->id,
									 "scriptName" => $scriptData->scriptName);

			$this->fileRecord = array("deviceHost" => $this->deviceHost,
									  "deviceGroupId" => $deviceData->deviceGroupId,
									  "deviceGroupName" => $deviceData->deviceGroupName,
									  "scriptId" => $scriptData->id,
									  "scriptName" => $scriptData->scriptName,
									  "filePath" => STORAGE_PATH);

			foreach (preg_split('/\r\n|\n|\r/', $scriptData->commands) as $value) {
				$cmd = explode('->', rtrim($value, ';'));
				$class = strtr("Commands/".$deviceData->deviceClass."/".$cmd[0], "/", "\\");
				if (!isset($this->lib) || strtolower(get_class($this->lib)) != strtolower($class))
				{
					$this->loadClass($class);
				}
				if (class_exists(get_class($this->lib)))
				{
					array_shift($cmd);
					if (!($command = strstr($cmd[0], '(', true)))
					{
						$command = $cmd[0];
					}
					$params = str_getcsv(trim(strstr($cmd[0], '('), '()'));
					foreach ($params as $key => $value) {
						$replacements = array(
												'{host}' => $this->deviceHost,
												'{user}' => $this->deviceUsername,
												'{datetime}' => $this->dateTime,
												'{datetimefull}' => $this->dateTimeFull
											 );
						$value = str_replace(array_keys($replacements), $replacements, $value);
						$params[$key] = trim(trim($value), '\'');
					}
					if (method_exists($this->lib, $command)) {
						call_user_func_array(array($this->lib, $command), $params);
					} else {
						$this->logRecord['message'] = 'Unrecognized command ['.$command.']';
						$this->logRecord['severity'] = 'warning';
						$this->log->addLog($this->logRecord);
					}
				}
			}
			$this->lib = NULL;
		}
	}

}
