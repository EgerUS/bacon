<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        FilesRepository.php 
 * Created:     29.7.2013 
 * Encoding:    UTF-8 
 * 
 * Description: 
 * 
 * 
 */ 

namespace Files;
use Nette,
	Nette\DateTime;

class FilesRepository extends Nette\Object {

    /** @var \DibiConnection */
    private $db;
	
    public function __construct(\DibiConnection $connection)
    {
        $this->db = $connection;
    }

	/** 
	 * Files list getter
	 * @param string row Database row for select
	 * @param string value Value for select
	 * @return array Backup files list
	 */
	
    public function getFilesData(array $query=array())
    {
		if(!isset($query['select']))
		{
			$query['select'] = '*';
			$autoselect = TRUE;
		}
		$fluent = $this->db->select($query['select'])->from('files');
		if(isset($query['where']))
		{
			$fluent = $fluent->where($query['where']);
		}
		return $fluent;
	}
	
	/**
	 * Add new file record
	 * @return bool
	 */
	public function addLog($values) {
		try {
			$values['dateTime'] = new \DateTime();
			if ($this->db->insert('files', $values)->execute()) {
				return true;
			}
		} catch (\DibiException $e) {
			return false;
		} 
	}

}
