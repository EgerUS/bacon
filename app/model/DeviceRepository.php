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
			$query['select'] = 'devices.*, devicegroups.groupname AS deviceGroupName, authenticationgroups.groupname AS authenticationGroupName';
			$autoselect = TRUE;
		}
		$fluent = $this->db->select($query['select'])->from('devices');
		if($autoselect)
		{
			$fluent = $fluent->leftJoin('devicegroups')->on('devicegroups.id = devices.deviceGroupId');
			$fluent = $fluent->leftJoin('authenticationgroups')->on('authenticationgroups.id = devices.authenticationGroupId');
		}
		if(isset($query['where']))
		{
			$fluent = $fluent->where($query['where']);
		}
		return $fluent;
	}
}
