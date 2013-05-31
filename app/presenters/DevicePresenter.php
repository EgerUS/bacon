<?php
/**
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        DeviceRepository.php 
 * Created:     31.5.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Class for device data manipulation
 * 
 * 
 */

use Grido\Grid,
    Grido\Components\Filters\Filter,
    Nette\Utils\Html,
	Nette\Application\UI\Form;

class DevicePresenter extends BasePresenter {

	/** @var Device\DeviceRepository */
	private $Drepo;
	
	/** @var Group\DeviceGroupRepository */
	private $DGrepo;
	
	/** @var Group\AuthenticationGroupRepository */
	private $AGrepo;

	/** @var Authenticator */
	private $auth;

	/**
	 * @param Device\DeviceRepository $DeviceRepository
	 * @param Group\DeviceRepository DeviceGroupRepository
	 * @param Authenticator auth
	 */
	public function __construct(Device\DeviceRepository $DeviceRepository, Group\DeviceGroupRepository $DeviceGroupRepository, Group\AuthenticationGroupRepository $AuthenticationGroupRepository, Authenticator $auth)
	{
		parent::__construct();
		$this->Drepo = $DeviceRepository;
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
		$fluent = $this->Drepo->getDeviceData();
		
        $grid->setModel($fluent);

        $grid->addColumnText('host', 'Host')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('devices.host');
		
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
		
        $grid->addColumnText('username', 'Username')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('devices.username');
		
        $grid->addColumnText('password', 'Password')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('devices.password');
		
        $grid->addColumnText('active', 'Active')
				->setSortable()
				->setFilterNumber()
					->setSuggestion()
				->setColumn('devices.active');
		
        $grid->addColumnText('description', 'Description')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('devices.description');
		
		$grid->addActionHref('edit', 'Edit')
				->setIcon('pencil');

		$grid->addActionHref('delete', 'Delete')
				->setIcon('trash')
				->setConfirm(function($item) use ($translator) {
					return $translator->translate('Are you sure you want to delete \'%s\' ?',$item->host);
				});

		$operations = array('delete' => 'Delete');
		$grid->setOperations($operations, callback($this, 'gridOperationsHandler'))
				->setConfirm('delete', $this->translator->translate('Are you sure you want to delete %i items ?'))
				->setPrimaryKey('id');
		
		$grid->setDefaultSort(array('host' => 'asc'));
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
		!count($this->Drepo->getDeviceData($query))
			? $this->flashMessage($this->translator->translate('Device does not exist'), 'error') && $this->redirect('default')
			: $this->setView('edit');
    }
	
    public function actionDelete()
    {
        $id = $this->getParam('id');
        $id = explode(',', $id);
		foreach ($id as $key => $device_id) {
			$device = $this->Drepo->getDeviceData(array('select' => 'id, host', 'where' => 'id=\''.$device_id.'\''))->fetch();
			if (isset($device->id)) {
				if ($this->Drepo->deleteDevice($device->id))	{
					$this->flashMessage($this->translator->translate('Device \'%s\' successfully deleted', $device->host), 'success');
				} else {
					$this->flashMessage($this->translator->translate('Device \'%s\' failed to delete', $device->host), 'error');
				}
			} else {
				$this->flashMessage($this->translator->translate('Device does not exist'), 'error');
			}
		}
        $this->redirect('default');
	}

	protected function createComponentDeviceAddForm()
	{
		$groups = $this->DGrepo->getDeviceGroupTree();
		$query = array('select' => 'id, groupname');
		$authGroups = $this->AGrepo->getAuthenticationGroupData($query)->fetchPairs();
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addText('host', 'Host', 30, 100)
				->setRequired('Please, enter hostname or IP')
				->setAttribute('placeholder', $this->translator->translate('Enter hostname or IP...'))
				->addRule(Form::MAX_LENGTH, 'Host must be at max %d characters long', 100)
				->setAttribute('autofocus','TRUE')
				->setOption('input-prepend', Html::el('i')->class('icon-hdd'));
		$prompt = Html::el('option')->setText($this->translator->translate('none'))->class('prompt');
		$form->addSelect('parentId', 'Device group', $groups)
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
		//$form->onValidate[] = callback($this, 'validateDeviceGroupAddForm');
		//$form->onSuccess[] = $this->DeviceGroupAddFormSubmitted;
		return $form;
	}
}
