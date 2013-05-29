<?php

/**
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        DeviceGroupPresenter.php 
 * Created:     28.5.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Presenter for device group administration
 * 
 * 
 */

use Grido\Grid,
    Grido\Components\Filters\Filter,
    Nette\Utils\Html,
	Nette\Application\UI\Form;

class DeviceGroupPresenter extends BasePresenter {

	/** @var Group\DeviceGroupRepository */
	private $DGrepo;
	
	/** @var Group\AuthenticationGroupRepository */
	private $AGrepo;

	/** @var Authenticator */
	private $auth;

	/**
	 * @param Group\DeviceGroupRepository DeviceGroupRepository
	 * @param Authenticator auth
	 */
	public function __construct(Group\DeviceGroupRepository $DeviceGroupRepository, Group\AuthenticationGroupRepository $AuthenticationGroupRepository, Authenticator $auth)
	{
		parent::__construct();
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

		/** Get users data */
		$fluent = $this->DGrepo->getDeviceGroupData();
		
        $grid->setModel($fluent);

        $grid->addColumnText('groupname', 'Group name')
				->setSortable()
				->setFilterText()
					->setSuggestion();

        $grid->addColumnText('parentgroupname', 'Parent group')
				->setSortable()
				->setFilterText()
					->setSuggestion();

        $grid->addColumnText('authenticationgroupname', 'Authentication group')
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
					return $translator->translate('Are you sure you want to delete \'%s\' ?',$item->groupname);
				});

		$operations = array('delete' => 'Delete');
		$grid->setOperations($operations, callback($this, 'gridOperationsHandler'))
				->setConfirm('delete', $this->translator->translate('Are you sure you want to delete %i items ?'))
				->setPrimaryKey('id');
		
		$grid->setDefaultSort(array('parentgroupname' => 'asc','groupname' => 'asc'));
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
		!count($this->DGrepo->getDeviceGroupData($query))
			? $this->flashMessage($this->translator->translate('Group does not exist'), 'error') && $this->redirect('default')
			: $this->setView('edit');
    }
	
    public function actionDelete()
    {
        $id = $this->getParam('id');
        $id = explode(',', $id);
		foreach ($id as $key => $group_id) {
			$group = $this->DGrepo->getDeviceGroupData(array('select' => 'id, groupname', 'where' => 'id=\''.$group_id.'\''))->fetch();
			if (isset($group->id)) {
				if ($this->DGrepo->isDeviceGroupUsed($group->id))	{
					$this->flashMessage($this->translator->translate('Group \'%s\' could not be deleted because it is used', $group->groupname), 'error');
				} else {
					if ($this->DGrepo->deleteDeviceGroup($group->id))	{
						$this->flashMessage($this->translator->translate('Group \'%s\' successfully deleted', $group->groupname), 'success');
					} else {
						$this->flashMessage($this->translator->translate('Group \'%s\' failed to delete', $group->groupname), 'error');
					}
				}
			} else {
				$this->flashMessage($this->translator->translate('Group does not exist'), 'error');
			}
		}
        $this->redirect('default');
	}

	protected function createComponentDeviceGroupAddForm()
	{
		$groups = $this->DGrepo->getDeviceGroupTree();
		$query = array('select' => 'id, groupname');
		$authGroups = $this->AGrepo->getAuthenticationGroupData($query)->fetchPairs();
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addText('groupname', 'Group name', 30, 100)
				->setRequired('Please, enter group name')
				->setAttribute('placeholder', $this->translator->translate('Enter group name...'))
				->addRule(Form::MAX_LENGTH, 'Group name must be at max %d characters long', 100)
				->setAttribute('autofocus','TRUE')
				->setOption('input-prepend', Html::el('i')->class('icon-th'));
		$prompt = Html::el('option')->setText($this->translator->translate('none'))->class('prompt');
		$form->addSelect('parentId', 'Parent group', $groups)
				->setOption('input-prepend', Html::el('i')->class('icon-sitemap'))
				->setPrompt($prompt);
		$form->addSelect('authenticationGroupId', 'Authentication group', $authGroups)
				->setOption('input-prepend', Html::el('i')->class('icon-key'))
				->setPrompt($prompt);
		$form->addTextArea('description', 'Description', 30, 3)
				->setAttribute('placeholder', $this->translator->translate('Enter description...'))
				->addRule(Form::MAX_LENGTH, 'Description must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-pencil'));
		$form->addSubmit('save', 'Create')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onValidate[] = callback($this, 'validateDeviceGroupAddForm');
		$form->onSuccess[] = $this->DeviceGroupAddFormSubmitted;
		return $form;
	}

	public function validateDeviceGroupAddForm($form)
	{
		$values = $form->getValues();

		if (!$values->groupname) {
			$form->addError($this->translator->translate('Please, enter group name'));
		} elseif (strlen($values->groupname) > 100) {
			$form->addError($this->translator->translate('Group name must be at max %d characters long', 100));
		} elseif ($this->DGrepo->getDeviceGroupData(array('select' => 'groupname', 'where' => 'groupname=\''.$values->groupname.'\''))->fetch()) {
			$form->addError($this->translator->translate('Group \'%s\' already exists', $values->groupname));
		}
		if ($values->parentId && !$this->DGrepo->getDeviceGroupData(array('select' => 'id', 'where' => 'id=\''.$values->parentId.'\''))->fetch()) {
			$form->addError($this->translator->translate('Parent group does not exist'));
		}
		if ($values->authenticationGroupId && !$this->AGrepo->getAuthenticationGroupData(array('select' => 'id', 'where' => 'id=\''.$values->authenticationGroupId.'\''))->fetch()) {
			$form->addError($this->translator->translate('Authentication group does not exist'));
		}
		if (strlen($values->description) > 255) {
			$form->addError($this->translator->translate('Description must be at max %d characters long', 255));
		}
	}

	public function DeviceGroupAddFormSubmitted(Form $form)
	{
		$values = $form->getValues();
		if (!$values->parentId) { $values->parentId = 0; }
		if (!$values->authenticationGroupId) { $values->authenticationGroupId = 0; }
		
		if ($this->DGrepo->addDeviceGroup($values))
		{
			$this->flashMessage($this->translator->translate('Group \'%s\' successfully created',$values->groupname), 'success');
		} else {
			$this->flashMessage($this->translator->translate('Group \'%s\' cannot be created',$values->groupname), 'error');
		}
		$this->redirect('default');
	}

	protected function createComponentDeviceGroupEditForm()
	{
		$id = $this->getParam('id');
		$query = array('where' => 'id=\''.$id.'\'');
		$groupData = $this->DGrepo->getDeviceGroupData($query)->fetch();
		$groupData->hash = md5(serialize($groupData));
		$groups = $this->DGrepo->getDeviceGroupTree();
		$query = array('select' => 'id, groupname');
		$authGroups = $this->AGrepo->getAuthenticationGroupData($query)->fetchPairs();
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addHidden('id', $groupData->id);
		$form->addHidden('_groupname', $groupData->groupname);
		$form->addHidden('hash', $groupData->hash);
		$form->addText('groupname', 'Group name', 30, 100)
				->setValue($groupData->groupname)
				->setRequired('Please, enter group name')
				->setAttribute('placeholder', $this->translator->translate('Enter group name...'))
				->addRule(Form::MAX_LENGTH, 'Group name must be at max %d characters long', 100)
				->setAttribute('autofocus','TRUE')
				->setOption('input-prepend', Html::el('i')->class('icon-th'));
		$prompt = Html::el('option')->setText($this->translator->translate('none'))->class('prompt');
		$form->addSelect('parentId', 'Parent group', $groups)
				->setValue($groupData->pid)
				->setOption('input-prepend', Html::el('i')->class('icon-sitemap'))
				->setPrompt($prompt);
		$form->addSelect('authenticationGroupId', 'Authentication group', $authGroups)
				->setValue($groupData->aid)
				->setOption('input-prepend', Html::el('i')->class('icon-key'))
				->setPrompt($prompt);
		$form->addTextArea('description', 'Description', 30, 3)
				->setValue($groupData->description)
				->setAttribute('placeholder', $this->translator->translate('Enter description...'))
				->addRule(Form::MAX_LENGTH, 'Description must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-pencil'));
		$form->addSubmit('save', 'Save')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onValidate[] = callback($this, 'validateDeviceGroupEditForm');
		$form->onSuccess[] = $this->DeviceGroupEditFormSubmitted;
		return $form;
	}

	public function validateDeviceGroupEditForm($form)
	{
		$values = $form->getValues();

		if (!$values->groupname) {
			$form->addError($this->translator->translate('Please, enter group name'));
		} elseif (strlen($values->groupname) > 100) {
			$form->addError($this->translator->translate('Group name must be at max %d characters long', 100));
		} elseif (($values->groupname != $values->_groupname) && ($this->DGrepo->getDeviceGroupData(array('select' => 'groupname', 'where' => 'groupname=\''.$values->groupname.'\''))->fetch())) {
			$form->addError($this->translator->translate('Group \'%s\' already exists', $values->groupname));
		}
		if ($values->parentId && !$this->DGrepo->getDeviceGroupData(array('select' => 'id', 'where' => 'id=\''.$values->parentId.'\''))->fetch()) {
			$form->addError($this->translator->translate('Parent group does not exist'));
		}
		if ($values->authenticationGroupId && !$this->AGrepo->getAuthenticationGroupData(array('select' => 'id', 'where' => 'id=\''.$values->authenticationGroupId.'\''))->fetch()) {
			$form->addError($this->translator->translate('Authentication group does not exist'));
		}
		if (strlen($values->description) > 255) {
			$form->addError($this->translator->translate('Description must be at max %d characters long', 255));
		}
	}

	public function DeviceGroupEditFormSubmitted(Form $form)
	{
		$values = $form->getValues();
		$query = array('where' => 'id=\''.$values->id.'\'');
		$groupData = $this->DGrepo->getDeviceGroupData($query)->fetch();
		$groupData->hash = md5(serialize($groupData));

		if ($groupData->hash === $values->hash)
		{
			$groupValues = array('groupname'			 => $values->groupname,
								 'parentId'				 => $values->parentId,
								 'authenticationGroupId' => $values->authenticationGroupId,
								 'description'			 => $values->description);

			if ($this->DGrepo->updateDeviceGroup($values->id, $groupValues))
			{
				$this->flashMessage($this->translator->translate('Group \'%s\' succesfully updated', $groupData->groupname), 'success');
				$this->redirect('default');
			} else {
				$this->flashMessage($this->translator->translate('Update of group \'%s\' failed', $groupData->groupname), 'error');
			}
		} else {
			$this->flashMessage($this->translator->translate('Database data changes during modification. Please modify data again.'),'error');
			$this->redirect('this');
		}
	}
}
