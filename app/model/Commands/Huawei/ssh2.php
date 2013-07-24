<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        ssh2.php 
 * Created:     16.7.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Huawei SSH2 library
 * 
 * 
 */ 

namespace Commands\Huawei;

class SSH2 extends \Nette\Object {

	private $connection = NULL;
	private $port = 22;
	private $timeout = 10;
	private $prompt = '/<.+>/i';
	private $errorPrompt = '/error|failed|bad command|unable/i';

	private $lastCommand = NULL;
	private $lastCommandResult = NULL;
	private $lastCommandError = NULL;
	
	const LOG_COMMAND_ERROR_SEVERITY = 'error';
	private $logCommandErrorSeverity = self::LOG_COMMAND_ERROR_SEVERITY;
	
	/** @var \Commands\ScriptCommandsRepository */
	private $script;

    public function __construct(\Commands\ScriptCommandsRepository $ScriptCommandsRepository)
    {
		$this->script = $ScriptCommandsRepository;
	}

	private function read()
	{
		@$this->lastCommandResult = $this->connection->read($this->prompt, NET_SSH2_READ_REGEX);
	}

	public function setTimeout($sec) {
		if (is_numeric($sec))
		{
			$this->timeout = $sec;
			$this->script->logRecord['message'] = 'The timeout set to ['.$this->timeout.'] seconds';
			$this->script->logRecord['severity'] = 'info';
			$this->script->log->addLog($this->script->logRecord);
		}
	}

	public function setPrompt($prompt) {
		if ($prompt)
		{
			$this->prompt = $prompt;
			$this->script->logRecord['message'] = 'The device prompt set to ['.$this->prompt.']';
			$this->script->logRecord['severity'] = 'info';
			$this->script->log->addLog($this->script->logRecord);
		}
	}

	public function setErrorPrompt($prompt) {
		if ($prompt)
		{
			$this->errorPrompt = $prompt;
			$this->script->logRecord['message'] = 'The command error prompt set to ['.$this->errorPrompt.']';
			$this->script->logRecord['severity'] = 'info';
			$this->script->log->addLog($this->script->logRecord);
		}
	}

	public function setLogError($log)
	{
		if ($log === '1' || strtolower($log) === 'info') {
			$this->logCommandErrorSeverity = 'info';
		} elseif ($log === '2' || strtolower($log) === 'warning') {
			$this->logCommandErrorSeverity = 'warning';
		} else {
			$this->logCommandErrorSeverity = 'error';
		}
		
		if ($log === '0' || strtolower($log) === 'false')
		{
			$this->logCommandErrorSeverity = FALSE;
			$this->script->logRecord['message'] = 'Logging errors of the next command has been turned off';
		} else {
			$this->script->logRecord['message'] = 'Logging severity of the next command error has been set to ['.$this->logCommandErrorSeverity.']';
		}
		$this->script->logRecord['severity'] = 'info';
		$this->script->log->addLog($this->script->logRecord);
	}

	public function connect($login) 
	{ 
		@$this->connection = new \Net_SSH2($this->script->deviceHost, $this->port, $this->timeout);

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
			if(@!$this->connection->login($this->script->deviceUsername,$this->script->devicePassword))
			{
				$this->script->logRecord['message'] = 'User ['.$this->script->deviceUsername.'] login failed';
				$this->script->logRecord['severity'] = 'error';
				$this->script->log->addLog($this->script->logRecord);
				$this->disconnect();
			} else {
				$this->read();
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

	public function command($command, $waitfor = NULL)
	{
		if (!$this->connection)
			$this->connect('true');

		if ($this->connection) {
			if ($waitfor)
			{
				if (@!preg_match($waitfor, $this->lastCommandResult))
				{
					if ($this->logCommandErrorSeverity)
					{
						$this->script->logRecord['message'] = 'Failed to wait for the result ['.$waitfor.']. Command ['.$command.'] not sended.';
						$this->script->logRecord['severity'] = $this->logCommandErrorSeverity;
						$this->script->log->addLog($this->script->logRecord);
					}
					$this->logCommandErrorSeverity = self::LOG_COMMAND_ERROR_SEVERITY;
					return FALSE;
				} else {
					$this->script->logRecord['message'] = 'Waiting for the result ['.$waitfor.'] was successful';
					$this->script->logRecord['severity'] = 'success';
					$this->script->log->addLog($this->script->logRecord);
				}
			}
			if (!@$this->connection->write($command."\n"))
			{
				if ($this->logCommandErrorSeverity)
				{
					$this->script->logRecord['message'] = 'Failed to send the command ['.$command.']';
					$this->script->logRecord['severity'] = $this->logCommandErrorSeverity;
					$this->script->log->addLog($this->script->logRecord);
				}
			} else {
				$this->lastCommand = $command;
				$this->read();
				$this->script->logRecord['message'] = 'Command ['.$command.'] sended';
				$this->script->logRecord['severity'] = 'success';
				$this->script->log->addLog($this->script->logRecord);
				@$error = preg_grep($this->errorPrompt, explode("\n", $this->lastCommandResult));
				if (count($error))
				{
					$this->lastCommandError = end($error);
					if ($this->logCommandErrorSeverity)
					{
						$this->script->logRecord['message'] = 'Command ['.$command.'] failed with error: '.$this->lastCommandError;
						$this->script->logRecord['severity'] = $this->logCommandErrorSeverity;
						$this->script->log->addLog($this->script->logRecord);
					}
				} else {
					$this->lastCommandError = NULL;
				}
			}
		}
		$this->logCommandErrorSeverity = self::LOG_COMMAND_ERROR_SEVERITY;
	}
	
	public function logLastCommand() {
		if ($this->lastCommandResult)
		{
			$this->script->logRecord['message'] = 'Result of command ['.$this->lastCommand.']: '.$this->lastCommandResult;
			$this->script->logRecord['severity'] = 'info';
			$this->script->log->addLog($this->script->logRecord);
		}
	}

	public function sleep($sec) {
		if(is_null($sec) || !is_numeric($sec)) $sec = 5;
		sleep($sec);
		$this->script->logRecord['message'] = 'Sleep for ['.$sec.'] seconds';
		$this->script->logRecord['severity'] = 'success';
		$this->script->log->addLog($this->script->logRecord);
	}
	

}
