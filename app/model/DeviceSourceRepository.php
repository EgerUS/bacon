<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        DeviceSourceRepository.php 
 * Created:     5.6.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Class for device source data manipulation
 * 
 * 
 */ 

namespace Device;
use Nette;

class DeviceSourceRepository extends Nette\Object {

    /** @var \DibiConnection */
    private $db;

    public function __construct(\DibiConnection $connection)
    {
        $this->db = $connection;
    }
	
	/** 
	 * Device source data getter
	 * @param string row Database row for select
	 * @param string value Value for select
	 * @return array Device source data
	 */
	
    public function getDeviceSourceData(array $query=array())
    {
		if(!isset($query['select']))
		{
			$query['select'] = 'devicesources.*, devicegroups.id AS gid, devicegroups.groupname AS deviceGroupName, authenticationgroups.id AS aid, authenticationgroups.groupname AS authenticationGroupName';
			$autoselect = TRUE;
		}
		$fluent = $this->db->select($query['select'])->from('devicesources');
		if(isset($autoselect))
		{
			$fluent = $fluent->leftJoin('devicegroups')->on('devicegroups.id = devicesources.deviceGroupId');
			$fluent = $fluent->leftJoin('authenticationgroups')->on('authenticationgroups.id = devicesources.authenticationGroupId');
		}
		if(isset($query['where']))
		{
			$fluent = $fluent->where($query['where']);
		}
		return $fluent;
	}


	/**
	 * Add new device source
	 * @return bool
	 */
	public function addDeviceSource($values) {
		try {
			if ($this->db->insert('devicesources', $values)->execute()) {
				return TRUE;
			}
		} catch (\DibiException $e) {
			return FALSE;
		} 
	}

	/**
	 * Update device source
	 * @return bool
	 */
	public function updateDeviceSource($id, $values) {
		try {
			$this->db->update('devicesources', $values)->where('id = %i', $id)->execute();
			return $this->db->affectedRows();
		} catch (\DibiException $e) {
			return FALSE;
		} 
	}
	
	/**
	 * Delete device source
	 * @return bool
	 */
	public function deleteDeviceSource($id) {
		try {
			$this->db->delete('devicesources')->where('id = %i', $id)->execute();
			if ($this->db->affectedRows()) {
				$this->db->update('devices', array('deviceSourceId' => '0'))->where('deviceSourceId = %i', $id)->execute();
				return TRUE;
			} else {
				return FALSE;
			}
		} catch (\DibiException $e) {
			return FALSE;
		} 
	}
	
}
