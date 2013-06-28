<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        CronPresenter.php 
 * Created:     28.6.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Presenter for cron administration
 * 
 * 
 */ 

use Grido\Grid,
    Grido\Components\Filters\Filter,
    Nette\Utils\Html,
	Nette\Application\UI\Form;

class CronPresenter extends BasePresenter {

	/** @var Cron\CronRepository */
	private $Crepo;
	
	/** @var Device\DeviceRepository */
	private $Drepo;
	
	/** @var Group\DeviceGroupRepository */
	private $DGrepo;
	
	/** @var Script\ScriptRepository */
	private $Srepo;

	/** @var Commands\ScriptCommandsRepository */
	private $SCrepo;

	/** @var Authenticator */
	private $auth;

	const REGEX_CRON_MINUTE = "((([\*]{1}){1})|((\*\/){0,1}(([0-9]{1}){1}|(([1-5]{1}){1}([0-9]{1}){1}){1})))";
	const REGEX_CRON_HOUR = "((([\*]{1}){1})|((\*\/){0,1}(([0-9]{1}){1}|(([1]{1}){1}([0-9]{1}){1}){1}|([2]{1}){1}([0-3]{1}){1})))";
	const REGEX_CRON_DAYOFMONTH = "((([\*]{1}){1})|((\*\/){0,1}(([1-9]{1}){1}|(([1-2]{1}){1}([0-9]{1}){1}){1}|([3]{1}){1}([0-1]{1}){1})))";
	const REGEX_CRON_MONTH = "((([\*]{1}){1})|((\*\/){0,1}(([1-9]{1}){1}|(([1-2]{1}){1}([0-9]{1}){1}){1}|([3]{1}){1}([0-1]{1}){1}))|(jan|feb|mar|apr|may|jun|jul|aug|sep|okt|nov|dec))";
	const REGEX_CRON_DAYOFWEEK = "((([\*]{1}){1})|((\*\/){0,1}(([0-7]{1}){1}))|(sun|mon|tue|wed|thu|fri|sat))";
	
	/**
	 * @param Cron\CronRepository $CronRepository
	 * @param Device\DeviceRepository $DeviceRepository
	 * @param Group\DeviceGroupRepository $DeviceGroupRepository
	 * @param Script\ScriptRepository $ScriptRepository
	 * @param Commands\ScriptCommandsRepository $ScriptCommandsRepository
	 * @param Authenticator auth
	 */
	public function __construct(Cron\CronRepository $CronRepository, Device\DeviceRepository $DeviceRepository, Group\DeviceGroupRepository $DeviceGroupRepository, Script\ScriptRepository $ScriptRepository, Commands\ScriptCommandsRepository $ScriptCommandsRepository, Authenticator $auth)
	{
		parent::__construct();
		$this->Crepo = $CronRepository;
		$this->Drepo = $DeviceRepository;
		$this->DGrepo = $DeviceGroupRepository;
		$this->Srepo = $ScriptRepository;
		$this->SCrepo = $ScriptCommandsRepository;
		$this->auth = $auth;
	}

	public function startup()
	{
		parent::startup();
		
		if (!$this->isInRole('admin') && $this->action != 'exec')
		{
			$this->redirect('Profile:');
		}
	}

	public function actionExec($id) {
		try {
			$cron = $this->Crepo->getCronData(array('where' => 'cron.id = '.$id))->fetch();
// tady budu predavat data do SCrepo a tam pak zpracuju skript
			
		} catch (DibiException $exc) {
			echo $exc;
		}
		exit;
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
		$fluent = $this->Crepo->getCronData();
		
        $grid->setModel($fluent);

        $grid->addColumnText('cronName', 'Cron name')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('cron.cronName');
		
        $grid->addColumnText('deviceHost', 'Device')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('devices.host');
		
        $grid->addColumnText('deviceGroupName', 'Group')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('devicegroups.groupname');
		
        $grid->addColumnText('minute', 'Minute')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('cron.minute');

        $grid->addColumnText('hour', 'Hour')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('cron.hour');

        $grid->addColumnText('dayOfMonth', 'Day of month')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('cron.dayOfMonth');

        $grid->addColumnText('month', 'Month')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('cron.month');

        $grid->addColumnText('dayOfWeek', 'Day of week')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('cron.dayOfWeek');

        $grid->addColumnText('scriptName', 'Script')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('scripts.scriptName');
		
        $grid->addColumnText('description', 'Description')
				->setSortable()
				->setFilterText()
					->setSuggestion()
				->setColumn('cron.description');

		$grid->addActionHref('edit', 'Edit')
				->setIcon('pencil');

		$grid->addActionHref('delete', 'Delete')
				->setIcon('trash')
				->setConfirm(function($item) use ($translator) {
					return $translator->translate('Are you sure you want to delete \'%s\' ?',$item->cronName);
				});

		$operations = array('delete' => 'Delete');
		$grid->setOperations($operations, callback($this, 'gridOperationsHandler'))
				->setConfirm('delete', $this->translator->translate('Are you sure you want to delete %i items ?'))
				->setPrimaryKey('id');
		
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
		!count($this->Crepo->getCronData($query))
			? $this->flashMessage($this->translator->translate('Cron does not exist'), 'error') && $this->redirect('default')
			: $this->setView('edit');
    }
	
    public function actionDelete()
    {
        $id = $this->getParam('id');
        $id = explode(',', $id);
		foreach ($id as $key => $cron_id) {
			$cron = $this->Crepo->getCronData(array('select' => 'id, cronName', 'where' => 'id=\''.$cron_id.'\''))->fetch();
			if (isset($cron->id)) {
				if ($this->Crepo->deleteCron($cron->id))	{
					$this->flashMessage($this->translator->translate('Cron \'%s\' successfully deleted', $cron->cronName), 'success');
				} else {
					$this->flashMessage($this->translator->translate('Cron \'%s\' failed to delete', $cron->cronName), 'error');
				}
			} else {
				$this->flashMessage($this->translator->translate('Cron does not exist'), 'error');
			}
		}
        $this->redirect('default');
	}
        
	protected function createComponentCronAddForm()
	{
		$groups = $this->DGrepo->getDeviceGroupTree();
		$query = array('select' => 'id, host');
		$devices = $this->Drepo->getDeviceData($query)->fetchPairs();
		$query = array('select' => 'id, scriptName');
		$scripts = $this->Srepo->getScriptData($query)->fetchPairs();
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addText('cronName', 'Cron name', 30, 100)
				->setRequired('Please, enter cron name')
				->setAttribute('placeholder', $this->translator->translate('Enter cron name...'))
				->addRule(Form::MAX_LENGTH, 'Cron name must be at max %d characters long', 100)
				->setAttribute('autofocus','TRUE')
				->setOption('input-prepend', Html::el('i')->class('icon-time'));
		$prompt = Html::el('option')->setText($this->translator->translate('Select device...'))->class('prompt');
		$form->addSelect('deviceId', 'Device', $devices)
				->setOption('input-prepend', Html::el('i')->class('icon-hdd'))
				->setPrompt($prompt);
		$prompt = Html::el('option')->setText($this->translator->translate('Select group...'))->class('prompt');
		$form->addSelect('deviceGroupId', 'Device group', $groups)
				->setOption('input-prepend', Html::el('i')->class('icon-sitemap'))
				->setPrompt($prompt);
		$form->addText('minute', 'Minute', 30, 100)
				->setRequired('Please, enter cron minute')
				->setAttribute('placeholder', $this->translator->translate('Enter cron minute...'))
				->addRule(Form::MAX_LENGTH, 'Cron minute must be at max %d characters long', 100)
				->addRule(Form::PATTERN, 'Cron minute value has invalid format', self::REGEX_CRON_MINUTE)
				->setOption('input-prepend', Html::el('i')->class('icon-time'));
		$form->addText('hour', 'Hour', 30, 100)
				->setRequired('Please, enter cron hour')
				->setAttribute('placeholder', $this->translator->translate('Enter cron hour...'))
				->addRule(Form::MAX_LENGTH, 'Cron hour must be at max %d characters long', 100)
				->addRule(Form::PATTERN, 'Cron hour value has invalid format', self::REGEX_CRON_HOUR)
				->setOption('input-prepend', Html::el('i')->class('icon-time'));
		$form->addText('dayOfMonth', 'Day of month', 30, 100)
				->setRequired('Please, enter cron day of month')
				->setAttribute('placeholder', $this->translator->translate('Enter cron day of month...'))
				->addRule(Form::MAX_LENGTH, 'Cron day of month must be at max %d characters long', 100)
				->addRule(Form::PATTERN, 'Cron day of month value has invalid format', self::REGEX_CRON_DAYOFMONTH)
				->setOption('input-prepend', Html::el('i')->class('icon-time'));
		$form->addText('month', 'Month', 30, 100)
				->setRequired('Please, enter cron month')
				->setAttribute('placeholder', $this->translator->translate('Enter cron month...'))
				->addRule(Form::MAX_LENGTH, 'Cron month must be at max %d characters long', 100)
				->addRule(Form::PATTERN, 'Cron month value has invalid format', self::REGEX_CRON_MONTH)
				->setOption('input-prepend', Html::el('i')->class('icon-time'));
		$form->addText('dayOfWeek', 'Day of week', 30, 100)
				->setRequired('Please, enter cron day of week')
				->setAttribute('placeholder', $this->translator->translate('Enter cron day of week...'))
				->addRule(Form::MAX_LENGTH, 'Cron day of week must be at max %d characters long', 100)
				->addRule(Form::PATTERN, 'Cron day of week value has invalid format', self::REGEX_CRON_DAYOFWEEK)
				->setOption('input-prepend', Html::el('i')->class('icon-time'));
		$prompt = Html::el('option')->setText($this->translator->translate('Select script...'))->class('prompt');
		$form->addSelect('scriptId', 'Script', $scripts)
				->setRequired('Please, select script')
				->setOption('input-prepend', Html::el('i')->class('icon-bolt'))
				->setPrompt($prompt);
		$form->addTextArea('description', 'Description', 30, 3)
				->setAttribute('placeholder', $this->translator->translate('Enter description...'))
				->addRule(Form::MAX_LENGTH, 'Description must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-pencil'));
		$form->addSubmit('save', 'Create')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onValidate[] = callback($this, 'validateCronAddForm');
		$form->onSuccess[] = $this->CronAddFormSubmitted;
		return $form;
	}
	
	public function validateCronAddForm($form)
	{
		$values = $form->getValues();

		if (!$values->cronName) {
			$form->addError($this->translator->translate('Please, enter cron name'));
		} elseif (strlen($values->cronName) > 100) {
			$form->addError($this->translator->translate('Cron name must be at max %d characters long', 100));
		} elseif ($this->Crepo->getCronData(array('select' => 'cronName', 'where' => 'cronName=\''.$values->cronName.'\''))->fetch()) {
			$form->addError($this->translator->translate('Cron \'%s\' already exists', $values->cronName));
		}
		
		if (!$values->deviceId && !$values->deviceGroupId) {
			$form->addError($this->translator->translate('Please select device or device group'));
		}
		
		if ($values->deviceId && !$this->Drepo->getDeviceData(array('select' => 'id', 'where' => 'id=\''.$values->deviceId.'\''))->fetch()) {
			$form->addError($this->translator->translate('Device does not exist'));
		}
		if ($values->deviceGroupId && !$this->DGrepo->getDeviceGroupData(array('select' => 'id', 'where' => 'id=\''.$values->deviceGroupId.'\''))->fetch()) {
			$form->addError($this->translator->translate('Group does not exist'));
		}
		if ($values->deviceId && $values->deviceGroupId) {
			$form->addError($this->translator->translate('Cannot be used an device along with the device group. Please select only one of them.'));
		}

		if (!$values->minute) {
			$form->addError($this->translator->translate('Please, enter cron minute'));
		} elseif (strlen($values->minute) > 100) {
			$form->addError($this->translator->translate('Cron minute must be at max %d characters long', 100));
		} elseif (!preg_match(self::REGEX_CRON_MINUTE, $values->minute)) {
			$form->addError($this->translator->translate('Cron minute value has invalid format'));
		}

		if (!$values->hour) {
			$form->addError($this->translator->translate('Please, enter cron hour'));
		} elseif (strlen($values->hour) > 100) {
			$form->addError($this->translator->translate('Cron hour must be at max %d characters long', 100));
		} elseif (!preg_match(self::REGEX_CRON_HOUR, $values->hour)) {
			$form->addError($this->translator->translate('Cron hour value has invalid format'));
		}

		if (!$values->dayOfMonth) {
			$form->addError($this->translator->translate('Please, enter cron day of month'));
		} elseif (strlen($values->dayOfMonth) > 100) {
			$form->addError($this->translator->translate('Cron day of month must be at max %d characters long', 100));
		} elseif (!preg_match(self::REGEX_CRON_DAYOFMONTH, $values->dayOfMonth)) {
			$form->addError($this->translator->translate('Cron day of month value has invalid format'));
		}

		if (!$values->month) {
			$form->addError($this->translator->translate('Please, enter cron month'));
		} elseif (strlen($values->month) > 100) {
			$form->addError($this->translator->translate('Cron month must be at max %d characters long', 100));
		} elseif (!preg_match(self::REGEX_CRON_MONTH, $values->month)) {
			$form->addError($this->translator->translate('Cron month value has invalid format'));
		}

		if (!$values->dayOfWeek) {
			$form->addError($this->translator->translate('Please, enter cron day of week'));
		} elseif (strlen($values->dayOfWeek) > 100) {
			$form->addError($this->translator->translate('Cron day of week must be at max %d characters long', 100));
		} elseif (!preg_match(self::REGEX_CRON_DAYOFWEEK, $values->dayOfWeek)) {
			$form->addError($this->translator->translate('Cron day of week value has invalid format'));
		}

		if (!$values->scriptId) {
			$form->addError($this->translator->translate('Please, select script'));
		} elseif ($values->scriptId && !$this->Srepo->getScriptData(array('select' => 'id', 'where' => 'id=\''.$values->scriptId.'\''))->fetch()) {
			$form->addError($this->translator->translate('Script does not exist'));
		}
		if (strlen($values->description) > 255) {
			$form->addError($this->translator->translate('Description must be at max %d characters long', 255));
		}
	}

	public function CronAddFormSubmitted(Form $form)
	{
		$values = $form->getValues();
		if (!$values->deviceId) { $values->deviceId = 0; }
		if (!$values->deviceGroupId) { $values->deviceGroupId = 0; }
		
		if ($this->Crepo->addCron($values))
		{
			$this->flashMessage($this->translator->translate('Cron \'%s\' successfully created',$values->cronName), 'success');
		} else {
			$this->flashMessage($this->translator->translate('Cron \'%s\' cannot be created',$values->cronName), 'error');
		}
		$this->redirect('default');
	}

	protected function createComponentCronEditForm()
	{
		$groups = $this->DGrepo->getDeviceGroupTree();
		$query = array('select' => 'id, host');
		$devices = $this->Drepo->getDeviceData($query)->fetchPairs();
		$query = array('select' => 'id, scriptName');
		$scripts = $this->Srepo->getScriptData($query)->fetchPairs();
		$id = $this->getParam('id');
		$query = array('where' => 'cron.id=\''.$id.'\'');
		$cronData = $this->Crepo->getCronData($query)->fetch();
		$cronData->hash = md5(serialize($cronData));
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addHidden('id', $cronData->id);
		$form->addHidden('_cronName', $cronData->cronName);
		$form->addHidden('hash', $cronData->hash);
		$form->addText('cronName', 'Cron name', 30, 100)
				->setValue($cronData->cronName)
				->setRequired('Please, enter cron name')
				->setAttribute('placeholder', $this->translator->translate('Enter cron name...'))
				->addRule(Form::MAX_LENGTH, 'Cron name must be at max %d characters long', 100)
				->setAttribute('autofocus','TRUE')
				->setOption('input-prepend', Html::el('i')->class('icon-time'));
		$prompt = Html::el('option')->setText($this->translator->translate('Select device...'))->class('prompt');
		$form->addSelect('deviceId', 'Device', $devices)
				->setValue($cronData->did)
				->setOption('input-prepend', Html::el('i')->class('icon-hdd'))
				->setPrompt($prompt);
		$prompt = Html::el('option')->setText($this->translator->translate('Select group...'))->class('prompt');
		$form->addSelect('deviceGroupId', 'Device group', $groups)
				->setValue($cronData->gid)
				->setOption('input-prepend', Html::el('i')->class('icon-sitemap'))
				->setPrompt($prompt);
		$form->addText('minute', 'Minute', 30, 100)
				->setValue($cronData->minute)
				->setRequired('Please, enter cron minute')
				->setAttribute('placeholder', $this->translator->translate('Enter cron minute...'))
				->addRule(Form::MAX_LENGTH, 'Cron minute must be at max %d characters long', 100)
				->addRule(Form::PATTERN, 'Cron minute value has invalid format', self::REGEX_CRON_MINUTE)
				->setOption('input-prepend', Html::el('i')->class('icon-time'));
		$form->addText('hour', 'Hour', 30, 100)
				->setValue($cronData->hour)
				->setRequired('Please, enter cron hour')
				->setAttribute('placeholder', $this->translator->translate('Enter cron hour...'))
				->addRule(Form::MAX_LENGTH, 'Cron hour must be at max %d characters long', 100)
				->addRule(Form::PATTERN, 'Cron hour value has invalid format', self::REGEX_CRON_HOUR)
				->setOption('input-prepend', Html::el('i')->class('icon-time'));
		$form->addText('dayOfMonth', 'Day of month', 30, 100)
				->setValue($cronData->dayOfMonth)
				->setRequired('Please, enter cron day of month')
				->setAttribute('placeholder', $this->translator->translate('Enter cron day of month...'))
				->addRule(Form::MAX_LENGTH, 'Cron day of month must be at max %d characters long', 100)
				->addRule(Form::PATTERN, 'Cron day of month value has invalid format', self::REGEX_CRON_DAYOFMONTH)
				->setOption('input-prepend', Html::el('i')->class('icon-time'));
		$form->addText('month', 'Month', 30, 100)
				->setValue($cronData->month)
				->setRequired('Please, enter cron month')
				->setAttribute('placeholder', $this->translator->translate('Enter cron month...'))
				->addRule(Form::MAX_LENGTH, 'Cron month must be at max %d characters long', 100)
				->addRule(Form::PATTERN, 'Cron month value has invalid format', self::REGEX_CRON_MONTH)
				->setOption('input-prepend', Html::el('i')->class('icon-time'));
		$form->addText('dayOfWeek', 'Day of week', 30, 100)
				->setValue($cronData->dayOfWeek)
				->setRequired('Please, enter cron day of week')
				->setAttribute('placeholder', $this->translator->translate('Enter cron day of week...'))
				->addRule(Form::MAX_LENGTH, 'Cron day of week must be at max %d characters long', 100)
				->addRule(Form::PATTERN, 'Cron day of week value has invalid format', self::REGEX_CRON_DAYOFWEEK)
				->setOption('input-prepend', Html::el('i')->class('icon-time'));
		$prompt = Html::el('option')->setText($this->translator->translate('Select script...'))->class('prompt');
		$form->addSelect('scriptId', 'Script', $scripts)
				->setValue($cronData->sid)
				->setRequired('Please, select script')
				->setOption('input-prepend', Html::el('i')->class('icon-bolt'))
				->setPrompt($prompt);
		$form->addTextArea('description', 'Description', 30, 3)
				->setValue($cronData->description)
				->setAttribute('placeholder', $this->translator->translate('Enter description...'))
				->addRule(Form::MAX_LENGTH, 'Description must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-pencil'));
		$form->addSubmit('save', 'Save')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onValidate[] = callback($this, 'validateCronEditForm');
		$form->onSuccess[] = $this->CronEditFormSubmitted;
		return $form;
	}
	
	public function validateCronEditForm($form)
	{
		$values = $form->getValues();

		if (!$values->cronName) {
			$form->addError($this->translator->translate('Please, enter cron name'));
		} elseif (strlen($values->cronName) > 100) {
			$form->addError($this->translator->translate('Cron name must be at max %d characters long', 100));
		} elseif (($values->cronName != $values->_cronName) && ($this->Crepo->getCronData(array('select' => 'cronName', 'where' => 'cronName=\''.$values->cronName.'\''))->fetch())) {
			$form->addError($this->translator->translate('Cron \'%s\' already exists', $values->cronName));
		}
		
		if (!$values->deviceId && !$values->deviceGroupId) {
			$form->addError($this->translator->translate('Please select device or device group'));
		}
		
		if ($values->deviceId && !$this->Drepo->getDeviceData(array('select' => 'id', 'where' => 'id=\''.$values->deviceId.'\''))->fetch()) {
			$form->addError($this->translator->translate('Device does not exist'));
		}
		if ($values->deviceGroupId && !$this->DGrepo->getDeviceGroupData(array('select' => 'id', 'where' => 'id=\''.$values->deviceGroupId.'\''))->fetch()) {
			$form->addError($this->translator->translate('Group does not exist'));
		}
		if ($values->deviceId && $values->deviceGroupId) {
			$form->addError($this->translator->translate('Cannot be used an device along with the device group. Please select only one of them.'));
		}

		if (!$values->minute) {
			$form->addError($this->translator->translate('Please, enter cron minute'));
		} elseif (strlen($values->minute) > 100) {
			$form->addError($this->translator->translate('Cron minute must be at max %d characters long', 100));
		} elseif (!preg_match(self::REGEX_CRON_MINUTE, $values->minute)) {
			$form->addError($this->translator->translate('Cron minute value has invalid format'));
		}

		if (!$values->hour) {
			$form->addError($this->translator->translate('Please, enter cron hour'));
		} elseif (strlen($values->hour) > 100) {
			$form->addError($this->translator->translate('Cron hour must be at max %d characters long', 100));
		} elseif (!preg_match(self::REGEX_CRON_HOUR, $values->hour)) {
			$form->addError($this->translator->translate('Cron hour value has invalid format'));
		}

		if (!$values->dayOfMonth) {
			$form->addError($this->translator->translate('Please, enter cron day of month'));
		} elseif (strlen($values->dayOfMonth) > 100) {
			$form->addError($this->translator->translate('Cron day of month must be at max %d characters long', 100));
		} elseif (!preg_match(self::REGEX_CRON_DAYOFMONTH, $values->dayOfMonth)) {
			$form->addError($this->translator->translate('Cron day of month value has invalid format'));
		}

		if (!$values->month) {
			$form->addError($this->translator->translate('Please, enter cron month'));
		} elseif (strlen($values->month) > 100) {
			$form->addError($this->translator->translate('Cron month must be at max %d characters long', 100));
		} elseif (!preg_match(self::REGEX_CRON_MONTH, $values->month)) {
			$form->addError($this->translator->translate('Cron month value has invalid format'));
		}

		if (!$values->dayOfWeek) {
			$form->addError($this->translator->translate('Please, enter cron day of week'));
		} elseif (strlen($values->dayOfWeek) > 100) {
			$form->addError($this->translator->translate('Cron day of week must be at max %d characters long', 100));
		} elseif (!preg_match(self::REGEX_CRON_DAYOFWEEK, $values->dayOfWeek)) {
			$form->addError($this->translator->translate('Cron day of week value has invalid format'));
		}

		if (!$values->scriptId) {
			$form->addError($this->translator->translate('Please, select script'));
		} elseif ($values->scriptId && !$this->Srepo->getScriptData(array('select' => 'id', 'where' => 'id=\''.$values->scriptId.'\''))->fetch()) {
			$form->addError($this->translator->translate('Script does not exist'));
		}
		if (strlen($values->description) > 255) {
			$form->addError($this->translator->translate('Description must be at max %d characters long', 255));
		}
	}

	public function CronEditFormSubmitted(Form $form)
	{
		$values = $form->getValues();
		$query = array('where' => 'cron.id=\''.$values->id.'\'');
		$cronData = $this->Crepo->getCronData($query)->fetch();
		$cronData->hash = md5(serialize($cronData));

		if ($cronData->hash === $values->hash)
		{
			$cronValues = array('cronName'		=> $values->cronName,
								'deviceId'		=> $values->deviceId,
								'deviceGroupId'	=> $values->deviceGroupId,
								'minute'		=> $values->minute,
								'hour'			=> $values->hour,
								'dayOfMonth'	=> $values->dayOfMonth,
								'month'			=> $values->month,
								'dayOfWeek'		=> $values->dayOfWeek,
								'scriptId'		=> $values->scriptId,
								'description'	=> $values->description);

			if (isset($values->deviceId)) {
				$cronValues['deviceId'] = $values->deviceId;
			} else {
				$cronValues['deviceId'] = 0;
			}
			if (isset($values->deviceGroupId)) {
				$cronValues['deviceGroupId'] = $values->deviceGroupId;
			} else {
				$cronValues['deviceGroupId'] = 0;
			}
			if ($this->Crepo->updateCron($values->id, $cronValues))
			{
				$this->flashMessage($this->translator->translate('Cron task \'%s\' succesfully updated', $cronData->cronName), 'success');
				$this->redirect('default');
			} else {
				$this->flashMessage($this->translator->translate('Update of cron task \'%s\' failed', $cronData->cronName), 'error');
			}
		} else {
			$this->flashMessage($this->translator->translate('Database data changes during modification. Please modify data again.'),'error');
			$this->redirect('this');
		}
	}
	
}
