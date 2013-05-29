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
				->setDisabled()
				->setOption('input-prepend', Html::el('i')->class('icon-user'));
		
		$form->addText('_role', 'Role')
				->setDefaultValue($this->userData->role)
				->setDisabled()
				->setOption('input-prepend', Html::el('i')->class('icon-briefcase'));
		
		$form->addPassword('oldPassword', 'Current password', 30, 255)
				->setAttribute('placeholder', $this->translator->translate('Fill in for password change...'))
				->addRule(Form::MAX_LENGTH, 'Password must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-key'));
		
		$form->addPassword('newPassword', 'New password', 30, 255)
				->setAttribute('placeholder', $this->translator->translate('Fill in for password change...'))
				->addRule(Form::MAX_LENGTH, 'Password must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-key'));
		
		$form->addPassword('confirmPassword', 'Confirm password', 30, 255)
				->addRule(Form::EQUAL, 'Passwords must match', $form['newPassword'])
				->setAttribute('placeholder', $this->translator->translate('Fill in for password change...'))
				->addRule(Form::MAX_LENGTH, 'Password must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-key'));
		
		$form->addText('email', 'Email', 30, 255)
				->setDefaultValue($this->userData->email)
				->setRequired('Please, enter your email address')
				->addRule(Form::EMAIL, 'Please, enter your email address')
				->addRule(Form::MAX_LENGTH, 'Email must be at max %d characters long', 255)
				->setAttribute('placeholder', $this->translator->translate('Enter your email...'))
				->setOption('input-prepend', Html::el('i')->class('icon-envelope-alt'));
		!$this->userData->email ? $form['email']->setAttribute('class', 'alert')->setAttribute('autofocus','TRUE') : NULL;
		
		$form->addTextArea('description', 'Description', 30, 3)
				->setDefaultValue($this->userData->description)
				->setRequired('Please enter your informations')
				->addRule(Form::MAX_LENGTH, 'Description must be at max %d characters long', 255)
				->setAttribute('placeholder', $this->translator->translate('Enter info about yourself...'))
				->setOption('input-prepend', Html::el('i')->class('icon-pencil'));
		
		$form->addSubmit('save', 'Save')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onValidate[] = callback($this, 'validateProfileForm');
		$form->onSuccess[] = $this->profileFormSubmitted;
		return $form;
	}

	public function validateProfileForm($form)
	{
		$values = $form->getValues();

		if ($values->oldPassword || $values->newPassword || $values->confirmPassword) {
			try {
				$this->auth->authenticate(array($this->userData->username, $values->oldPassword));
			} catch (Exception $e) {
				$form->addError($this->translator->translate('Wrong current password'));
			}
			if ($values->newPassword != $values->confirmPassword) {
				$form->addError($this->translator->translate('Passwords must match'));
			}
			if (strlen($values->newPassword) < $this->context->params['user']['minPasswordLength']) {
				$form->addError($this->translator->translate('New password must be at least %d characters long', $this->context->params['user']['minPasswordLength']));
			} elseif (strlen($values->newPassword) > 255) {
				$form->addError($this->translator->translate('New password must be at max %d characters long', 255));
			}
		}
		
		if (!$values->email) {
			$form->addError($this->translator->translate('Please, enter your email address'));
		} elseif (strlen($values->email) > 255) {
			$form->addError($this->translator->translate('Email must be at max %d characters long', 255));
		} elseif (!filter_var($values->email, FILTER_VALIDATE_EMAIL)) {
			$form->addError($this->translator->translate('Email \'%s\' is not valid', $values->email));
		}

		if (!$values->description) {
			$form->addError($this->translator->translate('Please enter your informations'));
		} elseif (strlen($values->description) > 255) {
			$form->addError($this->translator->translate('Description must be at max %d characters long', 255));
		}
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
				$update['password'] = $this->auth->calculateHash($values->newPassword);
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