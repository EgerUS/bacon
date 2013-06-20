<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        ScriptPresenter.php 
 * Created:     20.6.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Presenter for scripts
 * 
 * 
 */ 

use Grido\Grid,
    Grido\Components\Filters\Filter,
    Nette\Utils\Html,
	Nette\Application\UI\Form;

class ScriptPresenter extends BasePresenter {

	/** @var Script\ScriptRepository */
	private $Srepo;

	/** @var Authenticator */
	private $auth;

	/**
	 * @param Script\ScriptRepository ScriptRepository
	 * @param Authenticator auth
	 */
	public function __construct(Script\ScriptRepository $ScriptRepository, Authenticator $auth)
	{
		parent::__construct();
		$this->Srepo = $ScriptRepository;
		$this->auth = $auth;
	}

	public function startup()
	{
		parent::startup();
		
		if (!$this->isInRole('admin'))
		{
			$this->redirect('Profile:');
		}
	}

	/**
	 * Create datagrid
	 */
	protected function createComponentGrid($name)
    {
		$translator = $this->translator;
        $grid = new Grid($this, $name);
		$grid->setTranslator($this->translator);

		/** Get users data */
		$fluent = $this->Srepo->getScriptData();
		
        $grid->setModel($fluent);

        $grid->addColumnText('scriptName', 'Script name')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
        $grid->addColumnText('description', 'Description')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
		$grid->addActionHref('edit', 'Edit')
				->setIcon('pencil');

		$grid->addActionHref('delete', 'Delete')
				->setIcon('trash')
				->setConfirm(function($item) use ($translator) {
					return $translator->translate('Are you sure you want to delete \'%s\' ?',$item->scriptName);
				});

		$operations = array('delete' => 'Delete');
		$grid->setOperations($operations, callback($this, 'gridOperationsHandler'))
				->setConfirm('delete', $this->translator->translate('Are you sure you want to delete %i items ?'))
				->setPrimaryKey('id');
		
		$grid->setDefaultSort(array('scriptName' => 'asc'));
        $grid->setFilterRenderType(Filter::RENDER_INNER);
        $grid->setExporting();
    }

    /**
     * Handler for operations.
     * @param string $operation
     * @param array $id
     */
    public function gridOperationsHandler($operation, $id)
    {
        if ($id) {
            $ids = implode(',', $id);
        } else {
            $this->flashMessage($this->translator->translate('No rows selected.'), 'error');
        }
		$this->redirect($operation, array('id' => $ids));
    }

    public function actionEdit($id)
    {
		$query = array('select' => 'id', 'where' => 'id=\''.$id.'\'');
		!count($this->Srepo->getScriptData($query))
			? $this->flashMessage($this->translator->translate('Script does not exist'), 'error') && $this->redirect('default')
			: $this->setView('edit');
    }
	
    public function actionDelete()
    {
        $id = $this->getParam('id');
        $id = explode(',', $id);
		foreach ($id as $key => $script_id) {
			$script = $this->Srepo->getScriptData(array('select' => 'id, scriptName', 'where' => 'id=\''.$script_id.'\''))->fetch();
			if (isset($script->id)) {
				if ($this->Srepo->deleteScript($script->id))	{
					$this->flashMessage($this->translator->translate('Script \'%s\' successfully deleted', $script->scriptName), 'success');
				} else {
					$this->flashMessage($this->translator->translate('Script \'%s\' failed to delete', $script->scriptName), 'error');
				}
			} else {
				$this->flashMessage($this->translator->translate('Script does not exist'), 'error');
			}
		}
        $this->redirect('default');
	}

	protected function createComponentScriptAddForm()
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addText('scriptName', 'Script name', 30, 100)
				->setRequired('Please, enter script name')
				->setAttribute('placeholder', $this->translator->translate('Enter script name...'))
				->addRule(Form::MAX_LENGTH, 'Script name must be at max %d characters long', 100)
				->setAttribute('autofocus','TRUE')
				->setOption('input-prepend', Html::el('i')->class('icon-file-text'));
		$form->addTextArea('description', 'Description', 30, 3)
				->setAttribute('placeholder', $this->translator->translate('Enter description...'))
				->addRule(Form::MAX_LENGTH, 'Description must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-pencil'));
		$form->addTextArea('commands', 'Commands', 30, 10)
				->setRequired('Please, enter commands')
				->setAttribute('placeholder', $this->translator->translate('Enter commands...'))
				->setOption('input-prepend', Html::el('i')->class('icon-bolt'));
		$form->addSubmit('save', 'Create')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onValidate[] = callback($this, 'validateScriptAddForm');
		$form->onSuccess[] = $this->ScriptAddFormSubmitted;
		return $form;
	}
	
	public function validateScriptAddForm($form)
	{
		$values = $form->getValues();

		if (!$values->scriptName) {
			$form->addError($this->translator->translate('Please, enter script name'));
		} elseif (strlen($values->scriptName) > 100) {
			$form->addError($this->translator->translate('Script name must be at max %d characters long', 100));
		} elseif ($this->Srepo->getScriptData(array('select' => 'scriptName', 'where' => 'scriptName=\''.$values->scriptName.'\''))->fetch()) {
			$form->addError($this->translator->translate('Script name \'%s\' already exists', $values->scriptName));
		}
		if (strlen($values->description) > 255) {
			$form->addError($this->translator->translate('Description must be at max %d characters long', 255));
		}
		if (!$values->commands) {
			$form->addError($this->translator->translate('Please, enter commands'));
		} elseif ($this->Srepo->getScriptData(array('select' => 'commands', 'where' => 'commands=\''.$values->commands.'\''))->fetch()) {
			$form->addError($this->translator->translate('Script with entered commands already exists'));
		}
	}

	public function ScriptAddFormSubmitted(Form $form)
	{
		$values = $form->getValues();
		
		if ($this->Srepo->addScript($values))
		{
			$this->flashMessage($this->translator->translate('Script \'%s\' successfully created',$values->scriptName), 'success');
		} else {
			$this->flashMessage($this->translator->translate('Script \'%s\' cannot be created',$values->scriptName), 'error');
		}
		$this->redirect('default');
	}

	protected function createComponentScriptEditForm()
	{
		$id = $this->getParam('id');
		$query = array('where' => 'id=\''.$id.'\'');
		$scriptData = $this->Srepo->getScriptData($query)->fetch();
		$scriptData->hash = md5(serialize($scriptData));
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addHidden('id', $scriptData->id);
		$form->addHidden('_scriptName', $scriptData->scriptName);
		$form->addHidden('hash', $scriptData->hash);
		$form->addText('scriptName', 'Script name', 30, 100)
				->setValue($scriptData->scriptName)
				->setRequired('Please, enter script name')
				->setAttribute('placeholder', $this->translator->translate('Enter script name...'))
				->addRule(Form::MAX_LENGTH, 'Script name must be at max %d characters long', 100)
				->setAttribute('autofocus','TRUE')
				->setOption('input-prepend', Html::el('i')->class('icon-file-text'));
		$form->addTextArea('description', 'Description', 30, 3)
				->setValue($scriptData->description)
				->setAttribute('placeholder', $this->translator->translate('Enter description...'))
				->addRule(Form::MAX_LENGTH, 'Description must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-pencil'));
		$form->addTextArea('commands', 'Commands', 30, 10)
				->setValue($scriptData->commands)
				->setRequired('Please, enter commands')
				->setAttribute('placeholder', $this->translator->translate('Enter commands...'))
				->setOption('input-prepend', Html::el('i')->class('icon-bolt'));
		$form->addSubmit('save', 'Save')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onValidate[] = callback($this, 'validateScriptEditForm');
		$form->onSuccess[] = $this->ScriptEditFormSubmitted;
		return $form;
	}
	
	public function validateScriptEditForm($form)
	{
		$values = $form->getValues();

		if (!$values->scriptName) {
			$form->addError($this->translator->translate('Please, enter script name'));
		} elseif (strlen($values->scriptName) > 100) {
			$form->addError($this->translator->translate('Script name must be at max %d characters long', 100));
		} elseif (($values->scriptName != $values->_scriptName) && ($this->Srepo->getScriptData(array('select' => 'scriptName', 'where' => 'scriptName=\''.$values->scriptName.'\''))->fetch())) {
			$form->addError($this->translator->translate('Script name \'%s\' already exists', $values->scriptName));
		}
		if (strlen($values->description) > 255) {
			$form->addError($this->translator->translate('Description must be at max %d characters long', 255));
		}
		if (!$values->commands) {
			$form->addError($this->translator->translate('Please, enter commands'));
		}
	}

	public function ScriptEditFormSubmitted(Form $form)
	{
		$values = $form->getValues();
		$query = array('where' => 'id=\''.$values->id.'\'');
		$scriptData = $this->Srepo->getScriptData($query)->fetch();
		$scriptData->hash = md5(serialize($scriptData));
		
		if ($scriptData->hash === $values->hash)
		{
			$scriptValues = array('scriptName'				=> $values->scriptName,
								  'commands'				=> $values->commands,
								  'description'				=> $values->description);

			if ($this->Srepo->updateScript($values->id, $scriptValues))
			{
				$this->flashMessage($this->translator->translate('Script \'%s\' succesfully updated', $scriptData->scriptName), 'success');
				$this->redirect('default');
			} else {
				$this->flashMessage($this->translator->translate('Update of script \'%s\' failed', $scriptData->scriptName), 'error');
			}
		} else {
			$this->flashMessage($this->translator->translate('Database data changes during modification. Please modify data again.'),'error');
			$this->redirect('this');
		}
	}
	
}
