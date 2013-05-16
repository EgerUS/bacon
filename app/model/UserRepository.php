<?php
namespace User;
use Nette;

/**
 * Operuje s uzivatelskymi daty.
 * 
 * @author Jiri Eger <jiri@eger.us>
 */
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
	 * Vraci data uzivatele
	 */
    public function getUserData($row, $value)
    {
        if ($this->userData === NULL) {
            $this->setUserData($row, $value);
        }

        return $this->userData;
	}

	public function setUserData($row, $value)
	{
		$this->userData = $this->db->select('*')
									->from('radcheck')
									->where($row.'=%s', $value)
									->fetch();
		return $this;
	}
	
	public function saveProfile(\Nette\Security\IIdentity $identity, $update)
	{
		$this->identity = $identity;
		$this->update = $update;
		try {
			$this->db->update('radcheck', $update)
						->where('id=%i', $identity->getId())
						->execute();
			$this->updateIdentity();
			return TRUE;
		} catch (\DibiException $e) {
			return FALSE;
		}
	}

	private function updateIdentity() {
		foreach ($this->update as $key => $val) {
			$this->identity->{$key} = $val;
		}
		return TRUE;
	}

}