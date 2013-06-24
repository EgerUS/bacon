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
