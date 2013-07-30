<?php
/** 
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        FilesPresenter.php 
 * Created:     29.7.2013 
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

class FilesPresenter extends BasePresenter {

	/** @var Files\FilesRepository */
	private $FilesRepo;

	/** @var Authenticator */
	private $auth;

	/**
	 * @param Files\FilesRepository FilesRepository
	 * @param Authenticator auth
	 */
	public function __construct(Files\FilesRepository $FilesRepository, Authenticator $auth)
	{
		parent::__construct();
		$this->FilesRepo = $FilesRepository;
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

		/** Get files data */
		$fluent = $this->FilesRepo->getFilesData();
		
        $grid->setModel($fluent);

		$grid->addColumnText('dateTime', 'Date and time')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		$grid->getColumn('dateTime')->headerPrototype->style = 'width: 11%;';
		
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

		$grid->addColumnText('filename', 'Filename')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
        $grid->addColumnText('filePath', 'File path')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
//		$grid->addActionHref('view', 'View')
//				->setIcon('eye-open');

		$grid->setDefaultSort(array('dateTime' => 'desc'));
        $grid->setFilterRenderType(Filter::RENDER_INNER);
        $grid->setExporting();
    }

    public function actionView($id)
    {
		$query = array('select' => 'id', 'where' => 'id=\''.$id.'\'');
		!count($this->FilesRepo->getFilesData($query))
			? $this->flashMessage($this->translator->translate('Record of this file does not exist'), 'error') && $this->redirect('default')
			: $this->setView('view');
    }

}