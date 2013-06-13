<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        DeviceSourcePresenter.php 
 * Created:     5.6.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Presenter for device source administration
 * 
 * 
 */ 

use Grido\Grid,
    Grido\Components\Filters\Filter,
    Nette\Utils\Html,
	Nette\Application\UI\Form;

class DeviceSourcePresenter extends BasePresenter {

	/** @var Device\DeviceSourceRepository */
	private $DSrepo;

	/** @var Group\DeviceGroupRepository */
	private $DGrepo;
	
	/** @var Group\AuthenticationGroupRepository */
	private $AGrepo;

	/** @var Authenticator */
	private $auth;

	/**
	 * @param Device\DeviceSourceRepository $DeviceSourceRepository
	 * @param Authenticator auth
	 */
	public function __construct(Device\DeviceSourceRepository $DeviceSourceRepository, Group\DeviceGroupRepository $DeviceGroupRepository, Group\AuthenticationGroupRepository $AuthenticationGroupRepository, Authenticator $auth)
	{
		parent::__construct();
		$this->DSrepo = $DeviceSourceRepository;
		$this->DGrepo = $DeviceGroupRepository;
		$this->AGrepo = $AuthenticationGroupRepository;
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

		/** Get device source data */
		$fluent = $this->DSrepo->getDeviceSourceData();
		
        $grid->setModel($fluent);

        $grid->addColumnText('sourceName', 'Source name')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('devicesources.sourceName');
		
        $grid->addColumnText('sourceURL', 'Source URL')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('devicesources.sourceURL');

        $grid->addColumnText('deviceGroupName', 'Group name')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('devicegroups.groupname');

        $grid->addColumnText('authenticationGroupName', 'Authentication group')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('authenticationgroups.groupname');

        $grid->addColumnText('description', 'Description')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('devicesources.description');
		
		$grid->addActionHref('edit', 'Edit')
				->setIcon('pencil');

		$grid->addActionHref('delete', 'Delete')
				->setIcon('trash')
				->setConfirm(function($item) use ($translator) {
					return $translator->translate('Are you sure you want to delete \'%s\' ? All devices of this source will be converted to manual mode, but will not be deleted.',$item->sourceName);
				});

		$operations = array('delete' => 'Delete');
		$grid->setOperations($operations, callback($this, 'gridOperationsHandler'))
				->setConfirm('delete', $this->translator->translate('Are you sure you want to delete %i items ? All devices of deleted sources will be converted to manual mode, but will not be deleted.'))
				->setPrimaryKey('id');
		
		$grid->setDefaultSort(array('sourceName' => 'asc'));
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
		!count($this->DSrepo->getDeviceSourceData($query))
			? $this->flashMessage($this->translator->translate('Device source does not exist'), 'error') && $this->redirect('default')
			: $this->setView('edit');
    }
	
    public function actionDelete()
    {
        $id = $this->getParam('id');
        $id = explode(',', $id);
		foreach ($id as $key => $source_id) {
			$source = $this->DSrepo->getDeviceSourceData(array('select' => 'id, sourceName', 'where' => 'id=\''.$source_id.'\''))->fetch();
			if (isset($source->id)) {
				if ($this->DSrepo->deleteDeviceSource($source->id))	{
					$this->flashMessage($this->translator->translate('Device source \'%s\' successfully deleted', $source->sourceName), 'success');
				} else {
					$this->flashMessage($this->translator->translate('Device source \'%s\' failed to delete', $source->sourceName), 'error');
				}
			} else {
				$this->flashMessage($this->translator->translate('Device source does not exist'), 'error');
			}
		}
        $this->redirect('default');
	}

	protected function createComponentDeviceSourceAddForm()
	{
		$groups = $this->DGrepo->getDeviceGroupTree();
		$query = array('select' => 'id, groupname');
		$authGroups = $this->AGrepo->getAuthenticationGroupData($query)->fetchPairs();
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addText('sourceName', 'Source name', 30, 100)
				->setRequired('Please, enter source name')
				->setAttribute('placeholder', $this->translator->translate('Enter source name...'))
				->addRule(Form::MAX_LENGTH, 'Source name must be at max %d characters long', 100)
				->setAttribute('autofocus','TRUE')
				->setOption('input-prepend', Html::el('i')->class('icon-edit'));
		$form->addText('sourceURL', 'Source URL', 30, 255)
				->setRequired('Please, enter source URL')
				->setAttribute('placeholder', $this->translator->translate('Enter source URL...'))
				->addRule(Form::MAX_LENGTH, 'Source URL must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-link'));
		$prompt = Html::el('option')->setText($this->translator->translate('none'))->class('prompt');
		$form->addSelect('deviceGroupId', 'Device group', $groups)
				->setOption('input-prepend', Html::el('i')->class('icon-sitemap'))
				->setPrompt($prompt);
		$form->addSelect('authenticationGroupId', 'Authentication group', $authGroups)
				->setOption('input-prepend', Html::el('i')->class('icon-key'))
				->setPrompt($prompt);
		$form->addTextArea('description', 'Description', 30, 3)
				->setAttribute('placeholder', $this->translator->translate('Enter description...'))
				->addRule(Form::MAX_LENGTH, 'Description must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-comment'));
		$form->addSubmit('save', 'Create')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onValidate[] = callback($this, 'validateDeviceSourceAddForm');
		$form->onSuccess[] = $this->DeviceSourceAddFormSubmitted;
		return $form;
	}

	public function validateDeviceSourceAddForm($form)
	{
		$values = $form->getValues();

		if (!$values->sourceName) {
			$form->addError($this->translator->translate('Please, enter source name'));
		} elseif (strlen($values->sourceName) > 100) {
			$form->addError($this->translator->translate('Source name must be at max %d characters long', 100));
		} elseif ($this->DSrepo->getDeviceSourceData(array('select' => 'sourceName', 'where' => 'sourceName=\''.$values->sourceName.'\''))->fetch()) {
			$form->addError($this->translator->translate('Source \'%s\' already exists', $values->sourceName));
		}

		if (!$values->sourceURL) {
			$form->addError($this->translator->translate('Please, enter source URL'));
		} elseif (strlen($values->sourceURL) > 255) {
			$form->addError($this->translator->translate('Source URL must be at max %d characters long', 255));
		} elseif ($this->DSrepo->getDeviceSourceData(array('select' => 'sourceURL', 'where' => 'sourceURL=\''.$values->sourceURL.'\''))->fetch()) {
			$form->addError($this->translator->translate('Source with URL \'%s\' already exists', $values->sourceURL));
		}

		if ($values->deviceGroupId && !$this->DGrepo->getDeviceGroupData(array('select' => 'id', 'where' => 'id=\''.$values->deviceGroupId.'\''))->fetch()) {
			$form->addError($this->translator->translate('Group does not exist'));
		}

		if ($values->authenticationGroupId && !$this->AGrepo->getAuthenticationGroupData(array('select' => 'id', 'where' => 'id=\''.$values->authenticationGroupId.'\''))->fetch()) {
			$form->addError($this->translator->translate('Authentication group does not exist'));
		}

		if (strlen($values->description) > 255) {
			$form->addError($this->translator->translate('Description must be at max %d characters long', 255));
		}
	}

	public function DeviceSourceAddFormSubmitted(Form $form)
	{
		$values = $form->getValues();
		
		if ($this->DSrepo->addDeviceSource($values))
		{
			$this->flashMessage($this->translator->translate('Device source \'%s\' successfully created',$values->sourceName), 'success');
		} else {
			$this->flashMessage($this->translator->translate('Device source \'%s\' cannot be created',$values->sourceName), 'error');
		}
		$this->redirect('default');
	}

	protected function createComponentDeviceSourceEditForm()
	{
		$groups = $this->DGrepo->getDeviceGroupTree();
		$query = array('select' => 'id, groupname');
		$authGroups = $this->AGrepo->getAuthenticationGroupData($query)->fetchPairs();
		$id = $this->getParam('id');
		$query = array('where' => 'devicesources.id=\''.$id.'\'');
		$sourceData = $this->DSrepo->getDeviceSourceData($query)->fetch();
		$sourceData->hash = md5(serialize($sourceData));
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addHidden('id', $sourceData->id);
		$form->addHidden('_sourcename', $sourceData->sourceName);
		$form->addHidden('_sourceurl', $sourceData->sourceURL);
		$form->addHidden('hash', $sourceData->hash);
		$form->addText('sourceName', 'Source name', 30, 100)
				->setValue($sourceData->sourceName)
				->setRequired('Please, enter source name')
				->setAttribute('placeholder', $this->translator->translate('Enter source name...'))
				->addRule(Form::MAX_LENGTH, 'Source name must be at max %d characters long', 100)
				->setAttribute('autofocus','TRUE')
				->setOption('input-prepend', Html::el('i')->class('icon-edit'));
		$form->addText('sourceURL', 'Source URL', 30, 255)
				->setValue($sourceData->sourceURL)
				->setRequired('Please, enter source URL')
				->setAttribute('placeholder', $this->translator->translate('Enter source URL...'))
				->addRule(Form::MAX_LENGTH, 'Source URL must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-link'));
		$prompt = Html::el('option')->setText($this->translator->translate('none'))->class('prompt');
		$form->addSelect('deviceGroupId', 'Device group', $groups)
				->setValue($sourceData->gid)
				->setOption('input-prepend', Html::el('i')->class('icon-sitemap'))
				->setPrompt($prompt);
		$form->addSelect('authenticationGroupId', 'Authentication group', $authGroups)
				->setValue($sourceData->aid)
				->setOption('input-prepend', Html::el('i')->class('icon-key'))
				->setPrompt($prompt);
		$form->addTextArea('description', 'Description', 30, 3)
				->setValue($sourceData->description)
				->setAttribute('placeholder', $this->translator->translate('Enter description...'))
				->addRule(Form::MAX_LENGTH, 'Description must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-comment'));
		$form->addSubmit('save', 'Save')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onValidate[] = callback($this, 'validateDeviceSourceEditForm');
		$form->onSuccess[] = $this->DeviceSourceEditFormSubmitted;
		return $form;
	}

	public function validateDeviceSourceEditForm($form)
	{
		$values = $form->getValues();

		if (!$values->sourceName) {
			$form->addError($this->translator->translate('Please, enter source name'));
		} elseif (strlen($values->sourceName) > 100) {
			$form->addError($this->translator->translate('Source name must be at max %d characters long', 100));
		} elseif (($values->sourceName != $values->_sourcename) && ($this->DSrepo->getDeviceSourceData(array('select' => 'sourceName', 'where' => 'sourceName=\''.$values->sourceName.'\''))->fetch())) {
			$form->addError($this->translator->translate('Source \'%s\' already exists', $values->sourceName));
		}

		if (!$values->sourceURL) {
			$form->addError($this->translator->translate('Please, enter source URL'));
		} elseif (strlen($values->sourceURL) > 255) {
			$form->addError($this->translator->translate('Source URL must be at max %d characters long', 255));
		} elseif (($values->sourceURL != $values->_sourceurl) && ($this->DSrepo->getDeviceSourceData(array('select' => 'sourceURL', 'where' => 'sourceURL=\''.$values->sourceURL.'\''))->fetch())) {
			$form->addError($this->translator->translate('Source with URL \'%s\' already exists', $values->sourceURL));
		}
		
		if ($values->deviceGroupId && !$this->DGrepo->getDeviceGroupData(array('select' => 'id', 'where' => 'id=\''.$values->deviceGroupId.'\''))->fetch()) {
			$form->addError($this->translator->translate('Group does not exist'));
		}

		if ($values->authenticationGroupId && !$this->AGrepo->getAuthenticationGroupData(array('select' => 'id', 'where' => 'id=\''.$values->authenticationGroupId.'\''))->fetch()) {
			$form->addError($this->translator->translate('Authentication group does not exist'));
		}

		if (strlen($values->description) > 255) {
			$form->addError($this->translator->translate('Description must be at max %d characters long', 255));
		}
	}

	public function DeviceSourceEditFormSubmitted(Form $form)
	{
		$values = $form->getValues();
		$query = array('where' => 'devicesources.id=\''.$values->id.'\'');
		$sourceData = $this->DSrepo->getDeviceSourceData($query)->fetch();
		$sourceData->hash = md5(serialize($sourceData));

		if ($sourceData->hash === $values->hash)
		{
			$sourceValues = array('sourceName'			 => $values->sourceName,
								 'sourceURL'			 => $values->sourceURL,
								 'deviceGroupId'		 => $values->deviceGroupId,
								 'authenticationGroupId' => $values->authenticationGroupId,
								 'description'			 => $values->description);

			if ($this->DSrepo->updateDeviceSource($values->id, $sourceValues))
			{
				$this->flashMessage($this->translator->translate('Device source \'%s\' succesfully updated', $sourceData->sourceName), 'success');
				$this->redirect('default');
			} else {
				$this->flashMessage($this->translator->translate('Update of device source \'%s\' failed', $sourceData->sourceName), 'error');
			}
		} else {
			$this->flashMessage($this->translator->translate('Database data changes during modification. Please modify data again.'),'error');
			$this->redirect('this');
		}
	}

}
