<?php
/**
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        SignPresenter.php 
 * Created:     21.5.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Sign-in presenter
 * 
 * 
 */

use Nette\Application\UI\Form,
	Nette\Utils\Html;

class SignPresenter extends BasePresenter
{

	/** @persistent */
    public $backlink;
	
	protected function startup()
	{
		parent::startup();
		if ($this->getUser()->isLoggedIn()) {
			$this->redirect('Profile:');
		}
	}
	
	/**
	 * Sign-in form factory.
	 * @return Nette\Application\UI\Form
	 */
	protected function createComponentSignInForm()
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addText('username', 'Username', 30, 64)
				->setRequired('Please, enter your username')
				->setAttribute('placeholder', $this->translator->translate('Enter your username...'))
				->setAttribute('autofocus','TRUE')
				->setOption('input-prepend', Html::el('i')->class('icon-user'));
		$form->addPassword('password', 'Password', 30)
				->setRequired('Please, enter your password')
				->setAttribute('placeholder', $this->translator->translate('Enter your password...'))
				->setOption('input-prepend', Html::el('i')->class('icon-key'));
		$form->addCheckbox('persistent', 'Keep me signed in')
				->setAttribute('class','checkbox');
		$form->addSubmit('signin', 'Sign in')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onSuccess[] = $this->signInFormSubmitted;
		return $form;
	}

	public function signInFormSubmitted(Form $form)
	{
		try {
			$user = $this->getUser();
			$values = $form->getValues();
			if ($values->persistent) {
				$user->setExpiration($this->context->params['security']['sessionLongExpire'], FALSE);
			}
			else {
				$user->setExpiration($this->context->params['security']['sessionExpire'], TRUE);
			}
			$user->login($values->username, $values->password);
			$this->flashMessage($this->translator->translate('You have successfully signed in'), 'success');
			$this->restoreRequest($this->backlink);
			$this->redirect('Homepage:');
		} catch (Nette\Security\AuthenticationException $e) {
			$this->flashMessage($this->translator->translate($e->getMessage()), 'error');
		}
	}

}