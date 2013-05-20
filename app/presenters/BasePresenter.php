<?php

use Nette\Security\User;

/**
 * Base presenter for all application presenters.
 */
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

		// Nastavime jazyk
        if (!isset($this->lang)) {
            $this->lang = $this->translator->getLang();
        } else {
            $this->translator->setLang($this->lang);
        }
		
		$this->auth = $this->context->authenticator;
		
		/**
		 * Otestujeme prihlaseni uzivatele a jeho identitu
		 */
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
	 * Overeni stavu uzivatele a identity
	 */
	public function checkUser()
    { 
		/**
		 * Zkontrolujeme zda je uzivatel prihlasen
		 * Pokud ne, tak jej presmerujeme na prihlasovaci formular
		 * Pokud byl odhlasen z duvodu neaktivity, tak mu to oznamime
		 */
		if ($this->name != 'Sign') {
			if (!$this->getUser()->isLoggedIn() && $this->getUser()->getLogoutReason() === User::INACTIVITY) {
				$this->getUser()->logout(TRUE);
				$this->flashMessage($this->translator->translate('Signed out due to inactivity'));
			}

			if (!$this->getUser()->isLoggedIn()) {
				$this->redirect('Sign:in', array('backlink' => $this->storeRequest()));
			}
		}

		/**
		 * Pokud je uzivatel prihlasen, tak overime zda souhlasi identita s udaji v db
		 */
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
	 * Overi opravneni
	 */
	public function isInRole($role = 'admin', $msgType = 'error')
    {
		if (!$this->getUser()->isInRole($role))
		{
			$this->flashMessage($this->translator->translate('You do not have sufficient rights'), $msgType);
			return FALSE;
		}
		return TRUE;
    }

	/**
	 * Zpracovava odhlaseni uzivatele s pripadnym hlasenim
	 */
	public function handleSignOut($msg = 'You have successfully signed out', $type = 'success')
    {
		$this->getUser()->logout(TRUE);
		if ($msg) { $this->flashMessage($this->translator->translate($msg),$type); }
		$this->redirect('Sign:in');
    }

}
