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
 * Description: Presenter for device administration
 * 
 * 
 */

use Grido\Grid,
    Grido\Components\Filters\Filter,
    Nette\Utils\Html,
	Nette\Application\UI\Form,
	Nette\Utils\Finder;

class DevicePresenter extends BasePresenter {

	/** @var Device\DeviceRepository */
	private $Drepo;
	
	/** @var Device\DeviceSourceRepository */
	private $DSrepo;

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
	public function __construct(Device\DeviceRepository $DeviceRepository, Device\DeviceSourceRepository $DeviceSourceRepository, Group\DeviceGroupRepository $DeviceGroupRepository, Group\AuthenticationGroupRepository $AuthenticationGroupRepository, Authenticator $auth)
	{
		parent::__construct();
		$this->Drepo = $DeviceRepository;
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
		
        $grid->addColumnText('scriptClass', 'Script class')
				->setSortable()
				->setFilterNumber()
					->setSuggestion()
				->setColumn('devices.scriptClass');
		
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
		
        $grid->addColumnText('deviceSourceName', 'Source')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('devicesources.sourcename');
		
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
		foreach (Finder::findFiles('*.php')->from('../app/model/Commands') as $file) {
			$class = substr(strrchr($file, "Commands/"), 9, -4 );
			$scriptClasses[$class] = $class;
		}
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addText('host', 'Host', 30, 100)
				->setRequired('Please, enter hostname or IP')
				->setAttribute('placeholder', $this->translator->translate('Enter hostname or IP...'))
				->addRule(Form::MAX_LENGTH, 'Host must be at max %d characters long', 100)
				->setAttribute('autofocus','TRUE')
				->setOption('input-prepend', Html::el('i')->class('icon-hdd'));
		$prompt = Html::el('option')->setText($this->translator->translate('none'))->class('prompt');
		$form->addSelect('deviceGroupId', 'Device group', $groups)
				->setOption('input-prepend', Html::el('i')->class('icon-sitemap'))
				->setPrompt($prompt);
		$form->addSelect('authenticationGroupId', 'Authentication group', $authGroups)
				->setOption('input-prepend', Html::el('i')->class('icon-key'))
				->setPrompt($prompt);
		$form->addText('username', 'Username', 30, 100)
				->setAttribute('placeholder', $this->translator->translate('Enter username...'))
				->addRule(Form::MAX_LENGTH, 'Username must be at max %d characters long', 100)
				->setOption('input-prepend', Html::el('i')->class('icon-user'));
		$form->addText('password', 'Password', 30, 100)
				->setAttribute('placeholder', $this->translator->translate('Enter user password...'))
				->addRule(Form::MAX_LENGTH, 'Password must be at max %d characters long', 100)
				->setOption('input-prepend', Html::el('i')->class('icon-key'));
		$prompt = Html::el('option')->setText($this->translator->translate('Select script class...'))->class('prompt');
		$form->addSelect('scriptClass', 'Script class', $scriptClasses)
				->setRequired('Please, select script class')
				->setOption('input-prepend', Html::el('i')->class('icon-bolt'))
				->setPrompt($prompt);
		$form->addCheckbox('active', 'Active')
				->setAttribute('class','checkbox')
				->setDefaultValue(true);
		$form->addTextArea('description', 'Description', 30, 3)
				->setAttribute('placeholder', $this->translator->translate('Enter description...'))
				->addRule(Form::MAX_LENGTH, 'Description must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-pencil'));
		$form->addSubmit('save', 'Create')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onValidate[] = callback($this, 'validateDeviceAddForm');
		$form->onSuccess[] = $this->DeviceAddFormSubmitted;
		return $form;
	}
	
	public function validateDeviceAddForm($form)
	{
		$values = $form->getValues();

		if (!$values->host) {
			$form->addError($this->translator->translate('Please, enter hostname or IP'));
		} elseif (strlen($values->host) > 100) {
			$form->addError($this->translator->translate('Host must be at max %d characters long', 100));
		} elseif ($this->Drepo->getDeviceData(array('select' => 'host', 'where' => 'host=\''.$values->host.'\''))->fetch()) {
			$form->addError($this->translator->translate('Host \'%s\' already exists', $values->host));
		}
		if (strlen($values->username) > 100) {
			$form->addError($this->translator->translate('Username must be at max %d characters long', 100));
		}
		if (strlen($values->password) > 100) {
			$form->addError($this->translator->translate('Password must be at max %d characters long', 100));
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
		if (!$values->authenticationGroupId && !$values->username && !$values->password) {
			$form->addError($this->translator->translate('Please, select authentication group or enter username and/or password'));
		}
		if ($values->authenticationGroupId && ($values->username || $values->password)) {
			$form->addError($this->translator->translate('Cannot be used an authentication group along with the username and password'));
		}
		if (!$values->scriptClass) {
			$form->addError($this->translator->translate('Please, select script class'));
		} elseif (!file_exists("../app/model/Commands/".$values->scriptClass.".php")) {
			$form->addError($this->translator->translate('Script class does not exist'));
		}
	}

	public function DeviceAddFormSubmitted(Form $form)
	{
		$values = $form->getValues();
		if (!$values->deviceGroupId) { $values->deviceGroupId = 0; }
		if (!$values->authenticationGroupId) { $values->authenticationGroupId = 0; }
		
		if ($this->Drepo->addDevice($values))
		{
			$this->flashMessage($this->translator->translate('Device \'%s\' successfully created',$values->host), 'success');
		} else {
			$this->flashMessage($this->translator->translate('Device \'%s\' cannot be created',$values->host), 'error');
		}
		$this->redirect('default');
	}

	protected function createComponentDeviceEditForm()
	{
		$groups = $this->DGrepo->getDeviceGroupTree();
		$query = array('select' => 'id, groupname');
		$authGroups = $this->AGrepo->getAuthenticationGroupData($query)->fetchPairs();
		foreach (Finder::findFiles('*.php')->from('../app/model/Commands') as $file) {
			$class = substr(strrchr($file, "Commands/"), 9, -4 );
			$scriptClasses[$class] = $class;
		}
		$id = $this->getParam('id');
		$query = array('where' => 'devices.id=\''.$id.'\'');
		$deviceData = $this->Drepo->getDeviceData($query)->fetch();
		$deviceData->hash = md5(serialize($deviceData));
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addHidden('id', $deviceData->id);
		$form->addHidden('_host', $deviceData->host);
		$form->addHidden('_deviceSourceId', $deviceData->deviceSourceId);
		$form->addHidden('hash', $deviceData->hash);
		$form->addText('host', 'Host', 30, 100)
				->setValue($deviceData->host)
				->setRequired('Please, enter hostname or IP')
				->setAttribute('placeholder', $this->translator->translate('Enter hostname or IP...'))
				->addRule(Form::MAX_LENGTH, 'Host must be at max %d characters long', 100)
				->setAttribute('autofocus','TRUE')
				->setOption('input-prepend', Html::el('i')->class('icon-hdd'));
		$prompt = Html::el('option')->setText($this->translator->translate('manual'))->class('prompt');
		$form->addSelect('deviceSourceId', 'Source', $deviceData->sid ? array($deviceData->sid => $deviceData->deviceSourceName) : array(10 => "aaa"))
				->setValue($deviceData->sid)
				->setOption('input-prepend', Html::el('i')->class('icon-link'))
				->setPrompt($prompt);
		!$deviceData->sid ? $form['deviceSourceId']->setDisabled() : NULL;
		$prompt = Html::el('option')->setText($this->translator->translate('none'))->class('prompt');
		$form->addSelect('deviceGroupId', 'Device group', $groups)
				->setValue($deviceData->gid)
				->setOption('input-prepend', Html::el('i')->class('icon-sitemap'))
				->setPrompt($prompt);
		$form->addSelect('authenticationGroupId', 'Authentication group', $authGroups)
				->setValue($deviceData->aid)
				->setOption('input-prepend', Html::el('i')->class('icon-key'))
				->setPrompt($prompt);
		$form->addText('username', 'Username', 30, 100)
				->setValue($deviceData->username)
				->setAttribute('placeholder', $this->translator->translate('Enter username...'))
				->addRule(Form::MAX_LENGTH, 'Username must be at max %d characters long', 100)
				->setOption('input-prepend', Html::el('i')->class('icon-user'));
		$form->addText('password', 'Password', 30, 100)
				->setValue($deviceData->password)
				->setAttribute('placeholder', $this->translator->translate('Enter user password...'))
				->addRule(Form::MAX_LENGTH, 'Password must be at max %d characters long', 100)
				->setOption('input-prepend', Html::el('i')->class('icon-key'));
		$form->addSelect('scriptClass', 'Script class', $scriptClasses)
				->setRequired('Please, select script class')
				->setValue($deviceData->scriptClass)
				->setOption('input-prepend', Html::el('i')->class('icon-bolt'));
		$form->addCheckbox('active', 'Active')
				->setValue($deviceData->active)
				->setAttribute('class','checkbox');
		$form->addTextArea('description', 'Description', 30, 3)
				->setValue($deviceData->description)
				->setAttribute('placeholder', $this->translator->translate('Enter description...'))
				->addRule(Form::MAX_LENGTH, 'Description must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-pencil'));
		$form->addSubmit('save', 'Save')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onValidate[] = callback($this, 'validateDeviceEditForm');
		$form->onSuccess[] = $this->DeviceEditFormSubmitted;
		return $form;
	}
	
	public function validateDeviceEditForm($form)
	{
		$values = $form->getValues();

		if (!$values->host) {
			$form->addError($this->translator->translate('Please, enter hostname or IP'));
		} elseif (strlen($values->host) > 100) {
			$form->addError($this->translator->translate('Host must be at max %d characters long', 100));
		} elseif (($values->host != $values->_host) && ($this->Drepo->getDeviceData(array('select' => 'host', 'where' => 'host=\''.$values->host.'\''))->fetch())) {
			$form->addError($this->translator->translate('Host \'%s\' already exists', $values->host));
		}
		if (strlen($values->username) > 100) {
			$form->addError($this->translator->translate('Username must be at max %d characters long', 100));
		}
		if (strlen($values->password) > 100) {
			$form->addError($this->translator->translate('Password must be at max %d characters long', 100));
		}
		if (isset($values->deviceSourceId) && $values->deviceSourceId != $values->_deviceSourceId) {
			$form->addError($this->translator->translate('Source can be changed only to \'manual\''));
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
		if (!$values->authenticationGroupId && !$values->username && !$values->password) {
			$form->addError($this->translator->translate('Please, select authentication group or enter username and/or password'));
		}
		if ($values->authenticationGroupId && ($values->username || $values->password)) {
			$form->addError($this->translator->translate('Cannot be used an authentication group along with the username and password'));
		}
		if (!$values->scriptClass) {
			$form->addError($this->translator->translate('Please, select script class'));
		} elseif (!file_exists("../app/model/Commands/".$values->scriptClass.".php")) {
			$form->addError($this->translator->translate('Script class does not exist'));
		}
	}

	public function DeviceEditFormSubmitted(Form $form)
	{
		$values = $form->getValues();
		$query = array('where' => 'devices.id=\''.$values->id.'\'');
		$deviceData = $this->Drepo->getDeviceData($query)->fetch();
		$deviceData->hash = md5(serialize($deviceData));

		if ($deviceData->hash === $values->hash)
		{
			$deviceValues = array('host'					=> $values->host,
								  'deviceGroupId'			=> $values->deviceGroupId,
								  'authenticationGroupId'	=> $values->authenticationGroupId,
								  'username'				=> $values->username,
								  'password'				=> $values->password,
								  'scriptClass'				=> $values->scriptClass,
								  'description'				=> $values->description);

			if (isset($values->deviceSourceId)) {
				$deviceValues['deviceSourceId'] = $values->deviceSourceId;
			} else {
				$deviceValues['deviceSourceId'] = 0;
			}
			if ($this->Drepo->updateDevice($values->id, $deviceValues))
			{
				$this->flashMessage($this->translator->translate('Device \'%s\' succesfully updated', $deviceData->host), 'success');
				$this->redirect('default');
			} else {
				$this->flashMessage($this->translator->translate('Update of device \'%s\' failed', $deviceData->host), 'error');
			}
		} else {
			$this->flashMessage($this->translator->translate('Database data changes during modification. Please modify data again.'),'error');
			$this->redirect('this');
		}
	}

	protected function createComponentDeviceAddFromSourceForm()
	{
		$query = array('select' => 'id, sourceName');
		$sources = $this->DSrepo->getDeviceSourceData($query)->fetchPairs();
		$form = new Form();
		$form->setTranslator($this->translator);
		$prompt = Html::el('option')->setText($this->translator->translate('Select source...'))->class('prompt');
		$form->addSelect('id', 'Source', $sources)
				->setRequired('Please, select device source')
				->setOption('input-prepend', Html::el('i')->class('icon-link'))
				->setPrompt($prompt);
		$form->addSubmit('save', 'Load')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onValidate[] = callback($this, 'validateDeviceAddFromSourceForm');
		$form->onSuccess[] = $this->DeviceAddFromSourceFormSubmitted;
		return $form;
	}

	public function validateDeviceAddFromSourceForm($form)
	{
		$values = $form->getValues();

		if (!$values->id) {
			$form->addError($this->translator->translate('Please, select device source'));
		} elseif (!$this->DSrepo->getDeviceSourceData(array('select' => 'id', 'where' => 'id=\''.$values->id.'\''))->fetch()) {
			$form->addError($this->translator->translate('Source does not exist'));
		}
	}

	public function DeviceAddFromSourceFormSubmitted(Form $form)
	{
		$values = $form->getValues();
		$result = $this->DSrepo->getDevicesFromSource($values->id);
		!$result->added && !$result->updated
			? $this->flashMessage($this->translator->translate('No devices loaded from source \'%s\'', $result->sourceName), 'error')
			: NULL;
		$result->added
			? $this->flashMessage($this->translator->translate('From source \'%s\' were added %d devices', $result->sourceName, $result->added), 'success')
			: NULL;
		$result->updated
			? $this->flashMessage($this->translator->translate('From source \'%s\' were updated %d devices', $result->sourceName, $result->updated), 'success')
			: NULL;
		$this->redirect('default');
	}
}
