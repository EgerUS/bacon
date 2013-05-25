<?php
/**
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        ProfilePresenter.php 
 * Created:     21.5.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Profile presenter
 * 
 * 
 */

use Nette\Application\UI\Form,
	Nette\DateTime,
	Nette\Utils\Html;

class ProfilePresenter extends BasePresenter {

	/** @var array */
	private $userData;

	/** @var User\ProfileRepository */
	private $userRepository;

	/** @var Authenticator */
	private $auth;
	

	/**
	 * @param User\UserRepository userRepository
	 * @param Authenticator auth
	 */
	public function __construct(User\UserRepository $userRepository, Authenticator $auth)
	{
		parent::__construct();
		$this->userRepository = $userRepository;
		$this->auth = $auth;
	}
	
	public function startup()
	{
		parent::startup();
		
		/** Get user data */
		$query = array('where'=>'id=\''.$this->getUser()->getId().'\'');
		$this->userData = $this->userRepository->getUserData($query)->fetch();
	}
	

	/**
	 * Profile form factory.
	 * @return Nette\Application\UI\Form
	 */
	protected function createComponentProfileForm()
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		
		$form->addText('_username', 'Username')
				->setDefaultValue($this->userData->username)
				->setRequired()
				->setDisabled()
				->setOption('input-prepend', Html::el('i')->class('icon-user'));
		
		$form->addText('_role', 'Role')
				->setDefaultValue($this->userData->role)
				->setDisabled()
				->setOption('input-prepend', Html::el('i')->class('icon-briefcase'));
		
		$form->addPassword('oldPassword', 'Current password', 20, 100)
				->setAttribute('placeholder', $this->translator->translate('Fill in for password change...'))
				->setOption('input-prepend', Html::el('i')->class('icon-key'));
		
		$form->addPassword('newPassword', 'New password', 20, 100)
				->setAttribute('placeholder', $this->translator->translate('Fill in for password change...'))
				->setOption('input-prepend', Html::el('i')->class('icon-key'));
		
		$form->addPassword('confirmPassword', 'Confirm password', 20, 100)
				->addRule(Form::EQUAL, 'Passwords must match', $form['newPassword'])
				->setAttribute('placeholder', $this->translator->translate('Fill in for password change...'))
				->setOption('input-prepend', Html::el('i')->class('icon-key'));
		
		$form->addText('email', 'Email', 20, 255)
				->setDefaultValue($this->userData->email)
				->setRequired('Please, enter your email address')
				->addRule(Form::EMAIL, 'Please, enter your email address')
				->setAttribute('placeholder', $this->translator->translate('Enter your email...'))
				->setOption('input-prepend', Html::el('i')->class('icon-envelope-alt'));
		!$this->userData->email ? $form['email']->setAttribute('class', 'alert')->setAttribute('autofocus','TRUE') : NULL;
		
		$form->addText('description', 'Description', 20, 255)
				->setDefaultValue($this->userData->description)
				->setRequired('Please, describe you')
				->setAttribute('placeholder', $this->translator->translate('Enter info about yourself...'))
				->setOption('input-prepend', Html::el('i')->class('icon-pencil'));
		$this->userData->description ? $form['description']->setDisabled() : FALSE;
		
		$form->addSubmit('save', 'Save')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onSuccess[] = $this->profileFormSubmitted;
		return $form;
	}
	
	/**
	 * Save user profile
	 * @param Form form
	 */
	public function profileFormSubmitted(Form $form)
	{
		if ($this->getUser()->isLoggedIn()) {
			$values = $form->getValues();
			$update = array();
			
			/** Email */
			$update['email'] = $values->email ?: FALSE;
			
			/** Description */
			$update['description'] = (isset($values->description) && !$this->userData->description) ? $values->description : $this->userData->description;
			
			/** Change password */
			if ($values->oldPassword)
			{
				try {
					/** Check old password */
					$this->auth->authenticate(array(
													$this->userData->username,
													$values->oldPassword
					));

					/** Checks whether the passwords match and have the correct length */
					if ($values->newPassword == $values->confirmPassword &&
						strlen($values->newPassword) >= $this->context->params['user']['minPasswordLength']
					) {
						$update['password'] = $this->auth->calculateHash($values->newPassword);
					} else {
						$this->flashMessage($this->translator->translate('Passwords must be at least %d characters long.',$this->context->params['user']['minPasswordLength']),'error');
					}
				} catch (Exception $e) {
					$this->flashMessage($this->translator->translate('Wrong current password. Password was not changed.'),'error');
				}
			}
						
			try {
				if ($this->userRepository->saveProfile($this->getUser()->getIdentity(), $update))
				{
					$this->flashMessage($this->translator->translate('User profile successfully updated'),'success');
				}
			} catch (Exception $e) {
				$this->flashMessage($this->translator->translate('User profile update failed'),'error');
			}
		}
		$this->redirect('this');
	}
	
}