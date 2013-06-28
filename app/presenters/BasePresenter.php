<?php
/**
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        BasePresenter.php 
 * Created:     21.5.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Base presenter for all application presenters
 * 
 * 
 */

use Nette\Security\User;

abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    /** @var GettextTranslator\Gettext */
    protected $translator;

    /** @persistent */
    public $lang;

	/** @var Authenticator */
	private $auth;
	
    /**
     * @param GettextTranslator\Gettext
     */
    public function injectTranslator(GettextTranslator\Gettext $translator)
    {
        $this->translator = $translator;
	}

	
	protected function startup()
    {
        parent::startup();

		/** Set language */
		if (!isset($this->lang)) {
            $this->lang = $this->translator->getLang();
        } else {
            $this->translator->setLang($this->lang);
        }
		
		$this->auth = $this->context->authenticator;
		
		/** Check user login status */
		$this->checkUser();
    }

    public function createTemplate($class = NULL)
    {
        $template = parent::createTemplate($class);

        $latte = new Nette\Latte\Engine;
        $macros = Nette\Latte\Macros\MacroSet::install($latte->compiler);
        $macros->addMacro('scache', '?>?<?php echo strtotime(date(\'Y-m-d hh \')); ?>"<?php');

        $template->registerFilter($latte);
        $template->registerHelper('strtoupper', 'strtoupper');

        $template->setTranslator($this->translator);
		
        return $template;
    }

	/**
	 * Check user status and identity
	 */
	public function checkUser()
    { 
		/**
		 * If user is not logged in ...
		 * ... show logout reason if exists
		 * ... and redirect to sign form
		 */
		if ($this->name != 'Sign' && ($this->name != 'Cron' && $this->view != 'exec')) {
			if (!$this->getUser()->isLoggedIn() && $this->getUser()->getLogoutReason() === User::INACTIVITY) {
				$this->getUser()->logout(TRUE);
				$this->flashMessage($this->translator->translate('Signed out due to inactivity'));
			}

			if (!$this->getUser()->isLoggedIn()) {
				$this->redirect('Sign:in', array('backlink' => $this->storeRequest()));
			}
		}

		/** Check if user identity is correct */
		if ($this->getUser()->isLoggedIn())
		{
			try {
				$this->auth->authenticate(array($this->getUser()->getIdentity()->username, $this->getUser()->getIdentity()->password));
			} catch (Nette\Security\AuthenticationException $e) {
				$this->handleSignOut($e->getMessage(),'error');
			}
		}
	}

	/**
	 * Check user role
	 * @param string role
	 * @param string msgType
	 * @return bool
	 */
	public function isInRole($role = 'admin', $msgType = 'error')
    {
		if (!$this->getUser()->isInRole($role))
		{
			if ($msgType) { $this->flashMessage($this->translator->translate('You do not have sufficient rights'), $msgType); }
			return FALSE;
		}
		return TRUE;
    }

	/**
	 * Handle user sign out with message
	 * @param string msg
	 * @param string type
	 */
	public function handleSignOut($msg = 'You have successfully signed out', $type = 'success')
    {
		$this->getUser()->logout(TRUE);
		if ($msg) { $this->flashMessage($this->translator->translate($msg),$type); }
		$this->redirect('Sign:in');
    }

}
