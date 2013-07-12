<?php
/**
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        DeviceGroupRepository.php 
 * Created:     28.5.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Class for device group data manipulation
 * 
 * 
 */

namespace Group;
use Nette;

class DeviceGroupRepository extends Nette\Object
{
    /** @var \DibiConnection */
    private $db;

    public function __construct(\DibiConnection $connection)
    {
        $this->db = $connection;
    }
	
	/** 
	 * Device group data getter
	 * @param string row Database row for select
	 * @param string value Value for select
	 * @return array Device group data
	 */
	
    public function getDeviceGroupData(array $query=array())
    {
		if(!isset($query['select']))
		{
			$query['select'] = '*';
		}
		$fluent = $this->db->select($query['select'])->from('view_devicegroups');
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
	public function addDeviceGroup($values) {
		try {
			if ($this->db->insert('devicegroups', $values)->execute()) {
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
	public function updateDeviceGroup($id, $values) {
		try {
			$this->db->update('devicegroups', $values)->where('id = %i', $id)->execute();
			return $this->db->affectedRows();
		} catch (\DibiException $e) {
			return false;
		} 
	}
	
	/**
	 * Delete authentication group
	 * @return bool
	 */
	public function deleteDeviceGroup($id) {
		try {
			$this->db->delete("devicegroups")->where('id = %i', $id)->execute();
			return $this->db->affectedRows();
		} catch (\DibiException $e) {
			return false;
		} 
	}
	
	/**
	 * Check if device group is used
	 * @return bool
	 */	
	public function isDeviceGroupUsed($id) {
		$fluent1 = $this->db->select('COUNT(parentId)')->from('devicegroups')->where('parentId = %i', $id);
		$fluent2 = $this->db->select('COUNT(deviceGroupId)')->from('devices')->where('deviceGroupId = %i', $id);
		$fluent3 = $this->db->select('COUNT(deviceGroupId)')->from('devicesources')->where('deviceGroupId = %i', $id);
		$result = $fluent1->union($fluent2)->union($fluent3)->fetchPairs();
		return array_sum($result) ? TRUE : FALSE;
	}
	
	public function getDeviceGroupTree($parent = 0, $level = 0) {
		$groupTree = array();
		$groups = $this->getDeviceGroupData(array('select' => 'id, groupname','where' => 'pid=\''.$parent.'\''))->fetchPairs();
		foreach ($groups as $id => $value) {
			$value = str_repeat('  ',$level).$value; /** Space is U+00A0 */
			$groupTree[$id] = $value;
			$groupTree = $groupTree + $this->getDeviceGroupTree($id, $level+1);
		}
		return $groupTree;
	} 
	
	//TODO: Rewrite groups to http://codeassembly.com/How-to-display-infinite-depth-expandable-categories-using-php-and-javascript/
}
