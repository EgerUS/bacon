<?php
/**
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        UserRepository.php 
 * Created:     21.5.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Class for user data manipulation
 * 
 * 
 */

namespace User;
use Nette;

class UserRepository extends Nette\Object
{
	/**	@var \Nette\Security\IIdentity */
	private $identity;
	
	/** @var array */
	private $update;
	
	/** @var array */
	private $userData;

    /** @var \DibiConnection */
    private $db;

    public function __construct(\DibiConnection $connection)
    {
        $this->db = $connection;
    }
	
	/** 
	 * User data getter
	 * @param string row Database row for select
	 * @param string value Value for select
	 * @return array User data
	 */
	
    public function getUserData(array $query=array())
    {
		if(!isset($query['select']))
		{
			$query['select'] = '*';
		}
		$fluent = $this->db->select($query['select'])->from('users');
		if(isset($query['where']))
		{
			$fluent = $fluent->where($query['where']);
		}
		return $fluent;
	}
	
	/**
	 * Update user data in database
	 * @param \Nette\Security\IIdentity identity User identity data
	 * @param array update Updated data
	 * @return bool
	 */
	public function saveProfile(\Nette\Security\IIdentity $identity, $update)
	{
		$this->identity = $identity;
		$this->update = $update;
		try {
			$this->db->update('users', $update)
						->where('id=%i', $identity->getId())
						->execute();
			$this->updateIdentity();
			return TRUE;
		} catch (\DibiException $e) {
			return FALSE;
		}
	}

	/**
	 * Update user identity
	 * @return bool
	 */
	private function updateIdentity() {
		foreach ($this->update as $key => $val) {
			$this->identity->{$key} = $val;
		}
		return TRUE;
	}

	/**
	 * Add new user
	 * @return bool
	 */
	public function addUser($values) {
		try {
			if ($this->db->insert('users', $values)->execute()) {
				return true;
			}
		} catch (\DibiException $e) {
			return false;
		} 
	}
	
	/**
	 * Update user
	 * @return bool
	 */
	public function updateUser($id, $values) {
		try {
			$this->db->update('users', $values)->where('id = %i', $id)->execute();
			return $this->db->affectedRows();
		} catch (\DibiException $e) {
			return false;
		} 
	}
	
	/**
	 * Delete user
	 * @return bool
	 */
	public function deleteUser($id) {
		try {
			$this->db->delete("users")->where("id = %i", $id)->execute();
			return $this->db->affectedRows();
		} catch (\DibiException $e) {
			return false;
		} 
	}
}