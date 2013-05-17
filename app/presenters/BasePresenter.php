<?php

/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    /** @var GettextTranslator\Gettext */
    protected $translator;

    /** @persistent */
    public $lang;

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

}
