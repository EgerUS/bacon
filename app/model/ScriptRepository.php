<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        ScriptRepository.php 
 * Created:     20.6.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Class for script data manipulation
 * 
 * 
 */ 

namespace Script;
use Nette;

class ScriptRepository extends Nette\Object {

    /** @var \DibiConnection */
    private $db;

    public function __construct(\DibiConnection $connection)
    {
        $this->db = $connection;
    }
	
	/** 
	 * Script data getter
	 * @param string row Database row for select
	 * @param string value Value for select
	 * @return array Script data
	 */
	
    public function getScriptData(array $query=array())
    {
		if(!isset($query['select']))
		{
			$query['select'] = '*';
			$autoselect = TRUE;
		}
		$fluent = $this->db->select($query['select'])->from('scripts');
		if(isset($query['where']))
		{
			$fluent = $fluent->where($query['where']);
		}
		return $fluent;
	}

	/**
	 * Add new script
	 * @return bool
	 */
	public function addScript($values) {
		try {
			if ($this->db->insert('scripts', $values)->execute()) {
				return true;
			}
		} catch (\DibiException $e) {
			return false;
		} 
	}

	/**
	 * Update script
	 * @return bool
	 */
	public function updateScript($id, $values) {
		try {
			$this->db->update('scripts', $values)->where('id = %i', $id)->execute();
			return $this->db->affectedRows();
		} catch (\DibiException $e) {
			return false;
		} 
	}
	
	/**
	 * Delete script
	 * @return bool
	 */
	public function deleteScript($id) {
		try {
			$this->db->delete("scripts")->where('id = %i', $id)->execute();
			return $this->db->affectedRows();
		} catch (\DibiException $e) {
			return false;
		} 
	}
	
}
