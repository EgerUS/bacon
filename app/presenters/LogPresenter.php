<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        LogPresenter.php 
 * Created:     26.6.2013 
 * Encoding:    UTF-8 
 * 
 * Description: 
 * 
 * 
 */ 

use Grido\Grid,
    Grido\Components\Filters\Filter,
    Nette\Utils\Html,
	Nette\Utils\Strings,
	Nette\Application\UI\Form;

class LogPresenter extends BasePresenter {

	/** @var Log\LogRepository */
	private $LogRepo;

	/** @var Authenticator */
	private $auth;

	/**
	 * @param Log\LogRepository LogRepository
	 * @param Authenticator auth
	 */
	public function __construct(Log\LogRepository $LogRepository, Authenticator $auth)
	{
		parent::__construct();
		$this->LogRepo = $LogRepository;
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
	 * Create logs datagrid
	 */
	protected function createComponentGrid($name)
    {
		$translator = $this->translator;
        $grid = new Grid($this, $name);
		$grid->setTranslator($this->translator);

		/** Get users data */
		$fluent = $this->LogRepo->getLogData();
		
        $grid->setModel($fluent);

        $grid->addColumnText('logId', 'Log ID')
				->setSortable()
				->setFilterText()
					->setSuggestion();

		$grid->addColumnText('dateTime', 'Date and time')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		$grid->getColumn('dateTime')->headerPrototype->style = 'width: 11%;';
		
        $grid->addColumnText('severity', 'Severity')
				->setSortable()
				->setCustomRender(function($item) use($grid) {
					$severity = $item->severity;
					if ($severity === 'error') { $severity = 'important'; }
					return "<span class='label label-".$severity."'>".$item->severity."</span>";
				})
				->setFilterText()
					->setSuggestion();

		$grid->addColumnText('deviceHost', 'Host')
				->setSortable()
				->setFilterText()
					->setSuggestion();

        $grid->addColumnText('deviceGroupName', 'Group')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
        $grid->addColumnText('scriptName', 'Script')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
        $grid->addColumnText('class', 'Class')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
        $grid->addColumnText('message', 'Message')
				->setSortable()
				->setCustomRender(function($item) use($grid) {
					return Strings::truncate($item->message, 500);
				})
				->setFilterText()
					->setSuggestion();
		$grid->getColumn('message')->headerPrototype->style = 'width: 40%;';

		$grid->addActionHref('view', 'View')
				->setIcon('eye-open');

		$grid->setDefaultSort(array('logId' => 'desc'));
        $grid->setFilterRenderType(Filter::RENDER_INNER);
        $grid->setExporting();
    }

    public function actionView($id)
    {
		$query = array('select' => 'id', 'where' => 'id=\''.$id.'\'');
		!count($this->LogRepo->getLogData($query))
			? $this->flashMessage($this->translator->translate('Log record does not exist'), 'error') && $this->redirect('default')
			: $this->setView('view');
    }

	protected function createComponentLogViewForm()
	{
		$id = $this->getParam('id');
		$query = array('where' => 'id=\''.$id.'\'');
		$logData = $this->LogRepo->getLogData($query)->fetch();
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addText('logID', 'Log ID')
				->setValue($logData->logId)
				->setOption('input-prepend', Html::el('i')->class('icon-file-text'));
		$form->addText('dateTime', 'Date and time')
				->setValue($logData->dateTime)
				->setOption('input-prepend', Html::el('i')->class('icon-calendar'));
		$form->addText('severity', 'Severity')
				->setValue($logData->severity)
				->setOption('input-prepend', Html::el('i')->class('icon-warning-sign'));
		$form->addText('deviceHost', 'Host')
				->setValue($logData->deviceHost)
				->setOption('input-prepend', Html::el('i')->class('icon-hdd'));
		$form->addText('deviceGroupName', 'Group')
				->setValue($logData->deviceGroupName)
				->setOption('input-prepend', Html::el('i')->class('icon-th'));
		$form->addText('scriptName', 'Script name')
				->setValue($logData->scriptName)
				->setOption('input-prepend', Html::el('i')->class('icon-book'));
		$form->addText('class', 'Class')
				->setValue($logData->class)
				->setOption('input-prepend', Html::el('i')->class('icon-inbox'));
		$form->addTextArea('message', 'Message', 100, 20)
				->setValue($logData->message)
				->setOption('input-prepend', Html::el('i')->class('icon-pencil'));
		
		foreach ($form->getControls() as $control) {
			$control->controlPrototype->readonly = 'readonly';
		}
		return $form;
	}

	/**
	 * Create error logs datagrid
	 */
	protected function createComponentGridAlarmsError($name)
    {
		$translator = $this->translator;
        $gridErrors = new Grid($this, $name);
		$gridErrors->setTranslator($this->translator);

		/** Get error logs data */
		$fluent = $this->LogRepo->getLogData(array('where' => 'severity=\'error\' AND ack=0'));
		
        $gridErrors->setModel($fluent);

        $gridErrors->addColumnText('logId', 'Log ID')
				->setSortable()
				->setFilterText()
					->setSuggestion();

		$gridErrors->addColumnText('dateTime', 'Date and time')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		$gridErrors->getColumn('dateTime')->headerPrototype->style = 'width: 11%;';
		
        $gridErrors->addColumnText('severity', 'Severity')
				->setSortable()
				->setCustomRender(function($item) use($gridErrors) {
					$severity = $item->severity;
					if ($severity === 'error') { $severity = 'important'; }
					return "<span class='label label-".$severity."'>".$item->severity."</span>";
				})
				->setFilterText()
					->setSuggestion();

		$gridErrors->addColumnText('deviceHost', 'Host')
				->setSortable()
				->setFilterText()
					->setSuggestion();

        $gridErrors->addColumnText('deviceGroupName', 'Group')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
        $gridErrors->addColumnText('scriptName', 'Script')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
        $gridErrors->addColumnText('class', 'Class')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
        $gridErrors->addColumnText('message', 'Message')
				->setSortable()
				->setCustomRender(function($item) use($gridErrors) {
					return Strings::truncate($item->message, 500);
				})
				->setFilterText()
					->setSuggestion();
		$gridErrors->getColumn('message')->headerPrototype->style = 'width: 40%;';

		$gridErrors->addActionHref('ack', 'Acknowledge')
				->setIcon('ok')
				->setConfirm(function($item) use ($translator) {
					return $translator->translate('Are you sure you want to acknowledge error ?');
				});

		$operationsErrors = array('ack' => 'Acknowledge');
		$gridErrors->setOperations($operationsErrors, callback($this, 'gridOperationsHandler'))
				->setConfirm('ack', $this->translator->translate('Are you sure you want to acknowledge %i errors ?'))
				->setPrimaryKey('id');

		$gridErrors->setDefaultSort(array('logId' => 'desc'));
        $gridErrors->setFilterRenderType(Filter::RENDER_INNER);
        $gridErrors->setExporting();
    }

	/**
	 * Create warning logs datagrid
	 */
	protected function createComponentGridAlarmsWarning($name)
    {
		$translator = $this->translator;
        $grid = new Grid($this, $name);
		$grid->setTranslator($this->translator);

		/** Get warning logs data */
		$fluent = $this->LogRepo->getLogData(array('where' => 'severity=\'warning\' AND ack=0'));
		
        $grid->setModel($fluent);

        $grid->addColumnText('logId', 'Log ID')
				->setSortable()
				->setFilterText()
					->setSuggestion();

		$grid->addColumnText('dateTime', 'Date and time')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		$grid->getColumn('dateTime')->headerPrototype->style = 'width: 11%;';
		
        $grid->addColumnText('severity', 'Severity')
				->setSortable()
				->setCustomRender(function($item) use($grid) {
					$severity = $item->severity;
					if ($severity === 'error') { $severity = 'important'; }
					return "<span class='label label-".$severity."'>".$item->severity."</span>";
				})
				->setFilterText()
					->setSuggestion();

		$grid->addColumnText('deviceHost', 'Host')
				->setSortable()
				->setFilterText()
					->setSuggestion();

        $grid->addColumnText('deviceGroupName', 'Group')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
        $grid->addColumnText('scriptName', 'Script')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
        $grid->addColumnText('class', 'Class')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
        $grid->addColumnText('message', 'Message')
				->setSortable()
				->setCustomRender(function($item) use($grid) {
					return Strings::truncate($item->message, 500);
				})
				->setFilterText()
					->setSuggestion();
		$grid->getColumn('message')->headerPrototype->style = 'width: 40%;';

		$grid->addActionHref('ack', 'Acknowledge')
				->setIcon('ok')
				->setConfirm(function($item) use ($translator) {
					return $translator->translate('Are you sure you want to acknowledge warning ?');
				});

		$operations = array('ack' => 'Acknowledge');
		$grid->setOperations($operations, callback($this, 'gridOperationsHandler'))
				->setConfirm('ack', $this->translator->translate('Are you sure you want to acknowledge %i warnings ?'))
				->setPrimaryKey('id');

		$grid->setDefaultSort(array('logId' => 'desc'));
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

    public function actionAck()
    {
        $id = $this->getParam('id');
        $id = explode(',', $id);
		foreach ($id as $key => $log_id) {
			$log = $this->LogRepo->getLogData(array('select' => 'id', 'where' => 'id=\''.$log_id.'\''))->fetch();
			if (isset($log->id)) {
				if ($this->LogRepo->ackLog($log->id))	{
					$this->flashMessage($this->translator->translate('Log record successfully acknowledged'), 'success');
				} else {
					$this->flashMessage($this->translator->translate('Failed to acknowledge log record'), 'error');
				}
			} else {
				$this->flashMessage($this->translator->translate('Log record does not exist'), 'error');
			}
		}
        $this->redirect('alarms');
	}

}
