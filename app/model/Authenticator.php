<?php
/**
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        Authenticator.php 
 * Created:     21.5.2013 
 * Encoding:    UTF-8 
 * 
 * Description: User authentication class
 * 
 * 
 */

use Nette\Security,
	Nette\Utils\Strings;

class Authenticator extends Nette\Object implements Security\IAuthenticator
{
	/** @var User\ProfileRepository */
	private $userRepository;

	public function __construct(User\UserRepository $userRepository)
	{
		$this->userRepository = $userRepository;
	}

	/**
	 * Performs an authentication.
	 * @param array credentials
	 * @return Nette\Security\Identity
	 * @throws Nette\Security\AuthenticationException
	 */
	public function authenticate(array $credentials)
	{
		list($username, $password) = $credentials;
		
		$query = array('where'=>'username=\''.$username.'\'');
		$userData = $this->userRepository->getUserData($query)->fetch();

		if (!$userData) {
			throw new Security\AuthenticationException('Wrong username', self::IDENTITY_NOT_FOUND);
		}

		if ($userData->password !== $password && $userData->password !== $this->calculateHash($password)) {
			throw new Security\AuthenticationException('Wrong password', self::INVALID_CREDENTIAL);
		}
		
		if ($userData->disabled) {
			throw new Security\AuthenticationException('Account disabled', self::INVALID_CREDENTIAL);
		}

		return new Security\Identity($userData->id, $userData->role, $userData->toArray());
	}

	/**
	 * Computes md5 password hash.
	 * @param  string password
	 * @return string
	 */
	public function calculateHash($password)
	{
		return md5($password);
	}

}
