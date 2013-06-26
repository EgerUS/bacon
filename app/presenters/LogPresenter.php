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
    Nette\Utils\Html;

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
	 * Create datagrid
	 */
	protected function createComponentGrid($name)
    {
		$translator = $this->translator;
        $grid = new Grid($this, $name);
		$grid->setTranslator($this->translator);

		/** Get users data */
		$fluent = $this->LogRepo->getLogData();
		
        $grid->setModel($fluent);

        $grid->addColumnText('dateTime', 'Date and time')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
        $grid->addColumnText('deviceHost', 'Host')
				->setSortable()
				->setFilterText()
					->setSuggestion();

        $grid->addColumnText('deviceGroupName', 'Group name')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
        $grid->addColumnText('messageType', 'Severity')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
        $grid->addColumnText('message', 'Message')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
		$grid->setDefaultSort(array('dateTime' => 'desc'));
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

        
}
