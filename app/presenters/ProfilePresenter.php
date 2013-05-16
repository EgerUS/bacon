<?php

use Nette\Application\UI\Form,
	Nette\DateTime;

/**
 * Presenter pro spravu profilu uzivatele
 *
 * @author Jiri Eger <jiri@eger.us>
 */
class ProfilePresenter extends BasePresenter {

	/** @var array */
	private $userData;

	/** @var Nette\DateTime */
	private $dateNow;
	
	/** @var Nette\DateTime */
	private $userDataDateTo;

	/** @var Nette\DateTime */
	private $renewDate;
	
	/** @var User\ProfileRepository */
	private $userRepository;

	/** @var Authenticator */
	private $auth;
	
	public function __construct(User\UserRepository $userRepository, Authenticator $auth)
	{
		parent::__construct();
		$this->userRepository = $userRepository;
		$this->auth = $auth;
	}
	
	public function startup()
	{
		parent::startup();
		
		/** Nacteme si data uzivatele */
		$this->userData = $this->userRepository->getUserData('id', $this->getUser()->getId());

		/** Ulozime si aktualni datum */
		$this->dateNow = new DateTime();

		/** Ulozime si konecne datum platnosti uzivatelskeho uctu */
		$this->userDataDateTo = new DateTime($this->userData->dateto);

		/** Pripravime si datum prodlouzeni */
		$this->renewDate = DateTime::from($this->userDataDateTo->getTimestamp()+$this->context->params['user']['renew']);
	}
	

	/**
	 * Profile form factory.
	 * @return Nette\Application\UI\Form
	 */
	protected function createComponentProfileForm()
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		
		$form->addText('_username', 'Username:')
				->setDefaultValue($this->userData->username)
				->setRequired()
				->setDisabled();
		
		$form->addText('_role', 'Role:')
				->setDefaultValue($this->userData->role)
				->setDisabled();
		
		$form->addGroup('Change email');
		$form->addPassword('oldPassword', 'Current password:', 20, 100)
				->setAttribute('placeholder', $this->translator->translate('Fill in for password change...'));
		
		$form->addPassword('newPassword', 'New password:', 20, 100)
				->setAttribute('placeholder', $this->translator->translate('Fill in for password change...'));
		
		$form->addPassword('confirmPassword', 'Confirm password:', 20, 100)
				->setAttribute('placeholder', $this->translator->translate('Fill in for password change...'))
				->addRule(Form::EQUAL, 'Passwords must match', $form['newPassword']);
		
		$form->addText('email', 'Email:', 20, 255)
				->setDefaultValue($this->userData->email)
				->setRequired('Please, enter your email address')
				->addRule(Form::EMAIL, 'Please, enter your email address')
				->setAttribute('placeholder', $this->translator->translate('Enter your email...'));
		!$this->userData->email ? $form['email']->setAttribute('class', 'alert')->setAttribute('autofocus','TRUE') : NULL;
		
		$form->addText('_datefrom', 'Valid from:')
				->setDefaultValue($this->userData->datefrom)
				->setDisabled();

		$form->addText('_dateto', 'Valid to:')
				->setDefaultValue($this->userData->dateto)
				->setDisabled();
		
		($this->userDataDateTo->getTimestamp() - $this->dateNow->getTimestamp() <= $this->context->params['user']['renewExpire'])
			? $form->addCheckbox('renew', 'Renew to:') && $this->template->renew = $this->renewDate->format('j.n.Y')
			: FALSE;

		$form->addText('desc', 'Description:', 20, 255)
				->setDefaultValue($this->userData->desc)
				->setRequired('Please, describe you')
				->setAttribute('placeholder', $this->translator->translate('Enter info about yourself...'));
		$this->userData->desc ? $form['desc']->setDisabled() : FALSE;
		
		$form->addSubmit('save', 'Save')
				->setAttribute('class','btn btn-small btn-block btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onSuccess[] = $this->profileFormSubmitted;
		return $form;
	}
	
	public function profileFormSubmitted(Form $form)
	{
		if ($this->getUser()->isLoggedIn()) {
			$values = $form->getValues();
			$update = array();
			
			/** Renew account */
			$update['dateto'] = (isset($values->renew) && $values->renew) ? $this->renewDate->format('Y-m-d') : $this->userData->dateto ;
			
			/** Email */
			$update['email'] = $values->email ?: FALSE;
			
			/** Description */
			$update['desc'] = (isset($values->desc) && $values->desc && !$this->userData->desc) ? $values->desc : $this->userData->desc;
			
			/** Change password */
			if ($values->oldPassword)
			{
				try {
					/** Zkontrolujeme soucasne heslo */
					$this->auth->authenticate(array(
													$this->userData->username,
													$values->oldPassword
					));

					/** Zkontrolujeme zda se hesla shoduji a zda ma nove heslo spravnou delku */
					if ($values->newPassword == $values->confirmPassword &&
						strlen($values->newPassword) >= $this->context->params['user']['minPasswordLength']
					)
					{
						$update['value'] = ($this->userData->attribute == 'Crypt-Password') ?
											$this->auth->calculateHash($values->newPassword) :
											$values->newPassword;
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
					$this->flashMessage($this->translator->translate('User profile updated'),'success');
				}
			} catch (Exception $e) {
				$this->flashMessage($this->translator->translate('User profile update failed'),'error');
			}
		}
		
		$this->redirect('this');
	}
	
//	public function isDateValid($date) {
//		try {
//			DateTime::from($date)->getTimestamp();
//		} catch (Exception $e) {
//			throw new \InvalidArgumentException('Neplatné datum \'%c\'.',$date);
//		}
//		return true;
//	}
	
}