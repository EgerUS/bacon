<?php

use Nette\Security,
	Nette\Utils\Strings;
/**
 * Users authenticator.
 */
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
		$userData = $this->userRepository->getUserData('username', $username);

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
	 * Computes salted password hash.
	 * @param  string password
	 * @param  string salt
	 * @return string
	 */
	public function calculateHash($password)
	{
		return md5($password);
	}

}
