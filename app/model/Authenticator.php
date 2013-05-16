<?php

use Nette\Security,
	Nette\Utils\Strings;
/**
 * Users authenticator.
 */
class Authenticator extends Nette\Object implements Security\IAuthenticator
{
	/**	@var string	*/
	public $salt;
	
	/** @var User\ProfileRepository */
	private $userRepository;

	public function __construct($salt, User\UserRepository $userRepository)
	{
		$this->salt = $salt;
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
		$userData = $this->userRepository->getUserData('username', $username);

		if (!$userData) {
			throw new Security\AuthenticationException('Wrong username', self::IDENTITY_NOT_FOUND);
		}

		if ($userData->value !== $password && $userData->value !== $this->calculateHash($password)) {
			throw new Security\AuthenticationException('Wrong password', self::INVALID_CREDENTIAL);
		}
		
		if ($userData->disabled) {
			throw new Security\AuthenticationException('Account disabled', self::INVALID_CREDENTIAL);
		}

		if (new DateTime($userData->datefrom) > new DateTime()) {
			throw new Security\AuthenticationException('Account inactive', self::INVALID_CREDENTIAL);
		}
		if (new DateTime($userData->dateto) < new DateTime()) {
			throw new Security\AuthenticationException('Account expired', self::INVALID_CREDENTIAL);
		}
			
		return new Security\Identity($userData->id, $userData->role, $userData->toArray());
	}

	/**
	 * Computes salted password hash.
	 * @param  string password
	 * @param  string salt
	 * @return string
	 */
	public function calculateHash($password)
	{
		return crypt($password, $this->salt);
	}

//	public function updateIdentity(Nette\Security\User $user)
//	{
//		if ($user->isLoggedIn())
//		{
//			try {
//				$this->authenticate(array($user->getIdentity()->username, $user->getIdentity()->value));
//			} catch (Nette\Security\AuthenticationException $e) {
//				throw new Security\AuthenticationException($e->getMessage());
//			}
//		}
//	}

}
