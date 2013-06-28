<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        CronRepository.php 
 * Created:     28.6.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Class for cron data manipulation
 * 
 * 
 */ 

namespace Cron;
use Nette;

class CronRepository extends Nette\Object {

    /** @var \DibiConnection */
    private $db;

	private $uri;
	
    public function __construct(\DibiConnection $connection)
    {
        $this->db = $connection;
		if (isset($_SERVER['HTTP_REFERER'])) {
			$this->uri = new \Nette\Http\Url($_SERVER['HTTP_REFERER']);
			$this->uri = $this->uri->getBaseUrl();
		}
	}
	
	/** 
	 * Cron data getter
	 * @param string row Database row for select
	 * @param string value Value for select
	 * @return array Cron data
	 */
	
    public function getCronData(array $query=array())
    {
		if(!isset($query['select']))
		{
			$query['select'] = 'cron.*, devices.id AS did, devices.host AS deviceHost, devicegroups.id AS gid, devicegroups.groupname AS deviceGroupName, scripts.id AS sid, scripts.scriptName AS scriptName';
			$autoselect = TRUE;
		}
		$fluent = $this->db->select($query['select'])->from('cron');
		if(isset($autoselect))
		{
			$fluent = $fluent->leftJoin('devices')->on('devices.id = cron.deviceId');
			$fluent = $fluent->leftJoin('devicegroups')->on('devicegroups.id = cron.deviceGroupId');
			$fluent = $fluent->leftJoin('scripts')->on('scripts.id = cron.scriptId');
		}
		if(isset($query['where']))
		{
			$fluent = $fluent->where($query['where']);
		}
		return $fluent;
	}

	/**
	 * Add new cron task
	 * @return bool
	 */
	public function addCron($values) {
		try {
			if ($this->db->insert('cron', $values)->execute()) {
				$cronCmd = "wget -O - -q ".$this->uri."exec/".$this->db->getInsertId();
				$cron = new \Crontab\Crontab();
				$cron->setMinute($values->minute);
				$cron->setHour($values->hour);
				$cron->setDayOfMonth($values->dayOfMonth);
				$cron->setMonth($values->month);
				$cron->setDayOfWeek($values->dayOfWeek);
				$cron->append($cronCmd);
				$cron->execute();
				return true;
			}
		} catch (\DibiException $e) {
			return false;
		} 
	}

//	/**
//	 * Update device
//	 * @return bool
//	 */
//	public function updateDevice($id, $values) {
//		try {
//			$this->db->update('devices', $values)->where('id = %i', $id)->execute();
//			return $this->db->affectedRows();
//		} catch (\DibiException $e) {
//			return false;
//		} 
//	}
//	
	/**
	 * Delete cron task
	 * @return bool
	 */
	public function deleteCron($id) {
		try {
			$cronData = $this->getCronData(array('where' => 'cron.id = '.$id))->fetch();
			$this->db->delete("cron")->where('id = %i', $id)->execute();
			if ($this->db->affectedRows()) {
				$cronCmd = "wget -O - -q ".$this->uri."exec/".$id;
				$cron = new \Crontab\Crontab();
				$cron->setMinute($cronData->minute);
				$cron->setHour($cronData->hour);
				$cron->setDayOfMonth($cronData->dayOfMonth);
				$cron->setMonth($cronData->month);
				$cron->setDayOfWeek($cronData->dayOfWeek);
				$cron->remove($cronCmd);
				$cron->execute();
			}
			return $this->db->affectedRows();
		} catch (\DibiException $e) {
			return false;
		} 
	}
}
