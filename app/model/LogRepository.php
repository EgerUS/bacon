<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        LogRepository.php 
 * Created:     22.6.2013 
 * Encoding:    UTF-8 
 * 
 * Description: 
 * 
 * 
 */ 

namespace Log;
use Nette;

class LogRepository extends Nette\Object {

    /** @var \DibiConnection */
    private $db;

    public function __construct(\DibiConnection $connection)
    {
        $this->db = $connection;
    }

	/** 
	 * Log data getter
	 * @param string row Database row for select
	 * @param string value Value for select
	 * @return array Log data
	 */
	
    public function getLogData(array $query=array())
    {
		if(!isset($query['select']))
		{
			$query['select'] = '*';
			$autoselect = TRUE;
		}
		$fluent = $this->db->select($query['select'])->from('logs');
		if(isset($query['where']))
		{
			$fluent = $fluent->where($query['where']);
		}
		return $fluent;
	}
	
	/**
	 * Add new log record
	 * @return bool
	 */
	public function addLog($values) {
		try {
			$values['dateTime'] = new \DateTime();
			if ($this->db->insert('logs', $values)->execute()) {
				return true;
			}
		} catch (\DibiException $e) {
			return false;
		} 
	}
}
