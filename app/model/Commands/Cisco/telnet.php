<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        telnet.php 
 * Created:     15.7.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Cisco telnet library
 * 
 * 
 */ 

namespace Commands\Cisco;

class Telnet extends \Nette\Object {

	private $connection = NULL;
	private $lastCommand = NULL;
	private $lastCommandResult = NULL;
	private $lastCommandError = NULL;
	private $port = 23;
	private $timeOut = 5;
	private $usernamePrompt = '/Username:/i';
	private $passwordPrompt = '/Password:/i';
	private $loginFailedPrompt = '/Authentication failed/i';
	private $prompt = '/] >/i';
	private $errorPrompt = '/error|failed|invalid|unknown/i';

	/** @var \Commands\ScriptCommandsRepository */
	private $script;

    public function __construct(\Commands\ScriptCommandsRepository $ScriptCommandsRepository)
    {
		$this->script = $ScriptCommandsRepository;
	}

	public function setTimeout($sec) {
		if (is_numeric($sec))
		{
			$this->timeOut = $sec;
			$this->script->logRecord['message'] = 'The timeout set to ['.$this->timeOut.'] seconds';
			$this->script->logRecord['severity'] = 'info';
			$this->script->log->addLog($this->script->logRecord);
		}
	}

	public function setUsernamePrompt($prompt) {
		if ($prompt)
		{
			$this->usernamePrompt = $prompt;
			$this->script->logRecord['message'] = 'The username prompt set to ['.$this->usernamePrompt.']';
			$this->script->logRecord['severity'] = 'info';
			$this->script->log->addLog($this->script->logRecord);
		}
	}

	public function setPasswordPrompt($prompt) {
		if ($prompt)
		{
			$this->passwordPrompt = $prompt;
			$this->script->logRecord['message'] = 'The password prompt set to ['.$this->passwordPrompt.']';
			$this->script->logRecord['severity'] = 'info';
			$this->script->log->addLog($this->script->logRecord);
		}
	}

	public function setLoginFailedPrompt($prompt) {
		if ($prompt)
		{
			$this->loginPrompt = $prompt;
			$this->script->logRecord['message'] = 'The login failed prompt set to ['.$this->loginFailedPrompt.']';
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

	public function connect($login)	{
		if( ($this->connection = @fsockopen($this->script->deviceHost, $this->port, $errno, $errorstr, $this->timeOut)) !== FALSE )
		{
			stream_set_timeout($this->connection, 0, 380000);
			stream_set_blocking($this->connection, 1);

			$this->_read();

			$this->script->logRecord['message'] = 'Connected';
			if ($login === '0' || strtolower($login) === 'false') $this->script->logRecord['message'] .= ' without login';
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

	public function disconnect() {
		if ($this->connection) {
			fclose($this->connection);
			$this->connection=NULL;
			$this->script->logRecord['message'] = 'Disconnected';
			$this->script->logRecord['severity'] = 'success';
			$this->script->log->addLog($this->script->logRecord);
		} else {
			$this->script->logRecord['message'] = 'The connection was already disconnected';
			$this->script->logRecord['severity'] = 'warning';
			$this->script->log->addLog($this->script->logRecord);
		}
	}
  
	public function login()	{
		if ($this->connection)
		{
			if( count(preg_grep($this->usernamePrompt, $this->lastCommandResult)) )
			{
				$this->_SendString($this->script->deviceUsername);
			}
			if( count(preg_grep($this->passwordPrompt, $this->lastCommandResult)) )
			{
				$this->_SendString($this->script->devicePassword);
			}

			if( !count(preg_grep($this->loginFailedPrompt, $this->lastCommandResult)) )
			{
				$this->script->logRecord['message'] = 'User ['.$this->script->deviceUsername.'] logged in';
				$this->script->logRecord['severity'] = 'success';
				$this->script->log->addLog($this->script->logRecord);
			} else {
				$this->script->logRecord['message'] = 'User ['.$this->script->deviceUsername.'] login failed';
				$this->script->logRecord['severity'] = 'error';
				$this->script->log->addLog($this->script->logRecord);
			}
		}
	}

	public function command($command, $waitfor = NULL) {
		if ($this->connection)
		{
			if ($waitfor)
			{
				if (@!preg_grep($waitfor, $this->lastCommandResult))
				{
					$this->script->logRecord['message'] = 'Failed to wait for the result ['.$waitfor.']';
					$this->script->logRecord['severity'] = 'error';
					$this->script->log->addLog($this->script->logRecord);
					$this->script->logRecord['message'] = 'Failed to send the command ['.$command.'] with wait for ['.$waitfor.']';
					$this->script->logRecord['severity'] = 'error';
					$this->script->log->addLog($this->script->logRecord);
					return FALSE;
				} else {
					$this->script->logRecord['message'] = 'Waiting for the result ['.$waitfor.'] was successful';
					$this->script->logRecord['severity'] = 'success';
					$this->script->log->addLog($this->script->logRecord);
				}
			}

			
			if (!$this->_SendString($command))
			{
				$this->script->logRecord['message'] = 'Failed to send the command ['.$command.']';
				$this->script->logRecord['severity'] = 'error';
				$this->script->log->addLog($this->script->logRecord);
			} else {
				$this->lastCommand = $command;
				$this->script->logRecord['message'] = 'Command ['.$command.'] sended';
				$this->script->logRecord['severity'] = 'success';
				$this->script->log->addLog($this->script->logRecord);
				@$error = preg_grep($this->errorPrompt, $this->lastCommandResult);
				if (count($error))
				{
					$this->lastCommandError = end($error);
					$this->script->logRecord['message'] = 'Command ['.$command.'] failed with error: '.$this->lastCommandError;
					$this->script->logRecord['severity'] = 'error';
					$this->script->log->addLog($this->script->logRecord);
				} else {
					$this->lastCommandError = NULL;
				}
			}
		}
	}

	public function logLastCommand() {
		if (implode($this->lastCommandResult))
		{
			$this->script->logRecord['message'] = 'Result of command ['.$this->lastCommand.']: '.implode($this->lastCommandResult);
			$this->script->logRecord['severity'] = 'info';
			$this->script->log->addLog($this->script->logRecord);
		}
	}


	
	private function _SendString($string, $newLine=true)
	{
		$string = trim($string);

		if($newLine) $string .= "\n";
		if (fputs($this->connection, $string))
		{
			$this->_read();
			return TRUE;
		} else {
			return FALSE;
		}
	}

	private function _read()
	{
		$ret = array();
		$max_empty_lines = 10;
		$count_empty_lines = 0;

		while( !feof($this->connection) )
		{
			$read = fgets($this->connection);
			$ret[] = $read;

			if(trim($read) == "")
			{
				if($count_empty_lines++ > $max_empty_lines) break;
			} else $count_empty_lines = 0;
		}
		$this->lastCommandResult = array_filter($ret);
	}
    
}
