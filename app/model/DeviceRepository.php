<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        DeviceRepository.php 
 * Created:     31.5.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Class for device data manipulation
 * 
 * 
 */ 

namespace Device;
use Nette;

class DeviceRepository extends Nette\Object {

    /** @var \DibiConnection */
    private $db;

    public function __construct(\DibiConnection $connection)
    {
        $this->db = $connection;
    }
	
	/** 
	 * Device data getter
	 * @param string row Database row for select
	 * @param string value Value for select
	 * @return array Device data
	 */
	
    public function getDeviceData(array $query=array())
    {
		if(!isset($query['select']))
		{
			$query['select'] = 'devices.*, devicegroups.id AS gid, devicegroups.groupname AS deviceGroupName, authenticationgroups.id AS aid, authenticationgroups.groupname AS authenticationGroupName, authenticationgroups.username AS authenticationUsername, authenticationgroups.password AS authenticationPassword, devicesources.id AS sid, devicesources.sourcename AS deviceSourceName';
			$autoselect = TRUE;
		}
		$fluent = $this->db->select($query['select'])->from('devices');
		if(isset($autoselect))
		{
			$fluent = $fluent->leftJoin('devicegroups')->on('devicegroups.id = devices.deviceGroupId');
			$fluent = $fluent->leftJoin('authenticationgroups')->on('authenticationgroups.id = devices.authenticationGroupId');
			$fluent = $fluent->leftJoin('devicesources')->on('devicesources.id = devices.deviceSourceId');
		}
		if(isset($query['where']))
		{
			$fluent = $fluent->where($query['where']);
		}
		return $fluent;
	}

	/**
	 * Add new device
	 * @return bool
	 */
	public function addDevice($values) {
		try {
			if ($this->db->insert('devices', $values)->execute()) {
				return true;
			}
		} catch (\DibiException $e) {
			return false;
		} 
	}

	/**
	 * Update device
	 * @return bool
	 */
	public function updateDevice($id, $values) {
		try {
			$this->db->update('devices', $values)->where('id = %i', $id)->execute();
			return $this->db->affectedRows();
		} catch (\DibiException $e) {
			return false;
		} 
	}
	
	/**
	 * Delete device
	 * @return bool
	 */
	public function deleteDevice($id) {
		try {
			$this->db->delete("devices")->where('id = %i', $id)->execute();
			return $this->db->affectedRows();
		} catch (\DibiException $e) {
			return false;
		} 
	}
}
