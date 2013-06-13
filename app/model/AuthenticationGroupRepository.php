<?php
/**
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        AuthenticationGroupRepository.php 
 * Created:     28.5.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Class for authentication group data manipulation
 * 
 * 
 */

namespace Group;
use Nette;

class AuthenticationGroupRepository extends Nette\Object
{
    /** @var \DibiConnection */
    private $db;

    public function __construct(\DibiConnection $connection)
    {
        $this->db = $connection;
    }
	
	/** 
	 * Authentication group data getter
	 * @param string row Database row for select
	 * @param string value Value for select
	 * @return array Authentication group data
	 */
	
    public function getAuthenticationGroupData(array $query=array())
    {
		if(!isset($query['select']))
		{
			$query['select'] = '*';
		}
		$fluent = $this->db->select($query['select'])->from('authenticationgroups');
		if(isset($query['where']))
		{
			$fluent = $fluent->where($query['where']);
		}
		return $fluent;
	}
	
	/**
	 * Add new authentication group
	 * @return bool
	 */
	public function addAuthenticationGroup($values) {
		try {
			if ($this->db->insert('authenticationgroups', $values)->execute()) {
				return true;
			}
		} catch (\DibiException $e) {
			return false;
		} 
	}
	
	/**
	 * Update authentication group
	 * @return bool
	 */
	public function updateAuthenticationGroup($id, $values) {
		try {
			$this->db->update('authenticationgroups', $values)->where('id = %i', $id)->execute();
			return $this->db->affectedRows();
		} catch (\DibiException $e) {
			return false;
		} 
	}
	
	/**
	 * Delete authentication group
	 * @return bool
	 */
	public function deleteAuthenticationGroup($id) {
		try {
			$this->db->delete("authenticationgroups")->where('id = %i', $id)->execute();
			return $this->db->affectedRows();
		} catch (\DibiException $e) {
			return false;
		} 
	}
	
	/**
	 * Check if authentication group is used
	 * @return bool
	 */
	public function isAuthenticationGroupUsed($id) {
		$fluent1 = $this->db->select('COUNT(authenticationGroupId)')->from('devicegroups')->where('authenticationGroupId = %i', $id);
		$fluent2 = $this->db->select('COUNT(authenticationGroupId)')->from('devices')->where('authenticationGroupId = %i', $id);
		$fluent3 = $this->db->select('COUNT(authenticationGroupId)')->from('devicesources')->where('authenticationGroupId = %i', $id);
		$result = $fluent1->union($fluent2)->union($fluent3)->fetchPairs();
		return array_sum($result) ? TRUE : FALSE;
	}

}