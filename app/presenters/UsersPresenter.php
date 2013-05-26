<?php
/**
 * @author      Jiri Eger <jiri@eger.us>
 * @link        http://github.com/EgerUS/bacon
 * 
 * Project:     bacon 
 * File:        UsersPresenter.php 
 * Created:     22.5.2013 
 * Encoding:    UTF-8 
 * 
 * Description: Presenter for users administration
 * 
 * 
 */

use Grido\Grid,
    Grido\Components\Filters\Filter,
    Grido\Components\Columns\Column,
    Nette\Utils\Html,
	Nette\Application\UI\Form;

class UsersPresenter extends BasePresenter {

	/** @var DibiConnection */
    private $db;

	/** @var User\ProfileRepository */
	private $userRepository;

	/** @var Authenticator */
	private $auth;

	/**
	 * @param User\UserRepository userRepository
	 * @param Authenticator auth
	 */
	public function __construct(User\UserRepository $userRepository, Authenticator $auth)
	{
		parent::__construct();
		$this->userRepository = $userRepository;
		$this->auth = $auth;
	}

	public function startup()
	{
		parent::startup();
		
		if (!$this->isInRole('admin'))
		{
			$this->redirect('Profile:');
		}
		
		$this->db = $this->context->dibi->connection;
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
		$fluent = $this->userRepository->getUserData();
		
        $grid->setModel($fluent);

        $grid->addColumn('username', 'Username')
				->setSortable()
				->setFilter()
				->setSuggestion();

        $grid->addColumn('description', 'Description')
				->setSortable()
				->setFilter()
                ->setSuggestion();

        $grid->addColumn('disabled', 'Disabled')
				->setSortable()
				->setFilter()
                ->setSuggestion();
		
        $grid->addColumn('email', 'Email')
				->setSortable()
				->setFilter()
                ->setSuggestion();
		
        $grid->addColumn('role', 'Role')
				->setSortable()
				->setFilter()
                ->setSuggestion();

		$grid->addAction('edit', 'Edit')
				->setIcon('pencil');

		$grid->addAction('delete', 'Delete')
				->setIcon('trash')
				->setConfirm(function($item) use ($translator) {
					return $translator->translate('Are you sure you want to delete \'%s\' ?',$item->username);
				});

		$operations = array('delete' => 'Delete');
		$grid->setOperations($operations, callback($this, 'gridOperationsHandler'))
				->setConfirm('delete', $this->translator->translate('Are you sure you want to delete %i items ?'))
				->setPrimaryKey('id');
		
		$grid->setDefaultSort(array('username' => 'asc'));
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
		$query = array('id' => $id);
		!count($this->getRadcheckData($query))
			? $this->redirect('default')
			: $this->setView('edit');
    }
	
    public function actionDelete()
    {
        $id = $this->getParam('id');
        $id = explode(',', $id);
		foreach ($id as $key => $value) {
			$user = $this->userRepository->getUserData(array('select'=>'username', 'where'=>'id=\''.$value.'\''))->fetch();
			if ($this->userRepository->deleteUser($value))
			{
				$this->flashMessage($this->translator->translate('User \'%s\' successfully deleted', $user->username), 'success');
			} else {
				$this->flashMessage($this->translator->translate('User failed to delete'), 'error');
			}
		}
        $this->redirect('default');
	}
	
	
	protected function createComponentUserAddForm()
	{
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addText('username', 'Username', 30, 64)
				->setRequired('Please, enter username')
				->setAttribute('placeholder', $this->translator->translate('Enter username...'))
				->addRule(Form::MAX_LENGTH, 'Username must be at max %d characters long', 64)
				->setAttribute('autofocus','TRUE')
				->setOption('input-prepend', Html::el('i')->class('icon-user'));
		$form->addSelect('role','Role', array('admin'=>'admin', 'user'=>'user'))
				->setDefaultValue('admin')
				->setRequired('Please, select user role')
				->setOption('input-prepend', Html::el('i')->class('icon-briefcase'));
		$form->addText('password', 'Password', 30, 255)
				->setRequired('Please, enter user password')
				->setAttribute('placeholder', $this->translator->translate('Enter user password...'))
				->addRule(Form::MAX_LENGTH, 'Password must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-key'));
		$form->addText('email', 'Email', 30, 255)
				->setRequired('Please, enter user email')
				//->addRule(Form::EMAIL, 'Please, enter user email')
				->setAttribute('placeholder', $this->translator->translate('Enter user email...'))
				->addRule(Form::MAX_LENGTH, 'Email must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-envelope-alt'));
		$form->addTextArea('description', 'Description')
				->setRequired('Please, enter user description')
				->setAttribute('placeholder', $this->translator->translate('Enter user description...'))
				->addRule(Form::MAX_LENGTH, 'Description must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-pencil'));
		$form->addSubmit('save', 'Create')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onValidate[] = callback($this, 'validateUserAddForm');
		$form->onSuccess[] = $this->UserAddFormSubmitted;
		return $form;
	}

	public function validateUserAddForm($form)
	{
		$values = $form->getValues();

		if (!$values->username) {
			$form->addError($this->translator->translate('Please, enter username'));
		} elseif (strlen($values->username) > 64) {
			$form->addError($this->translator->translate('Username must be at max %d characters long', 64));
		} elseif ($this->userRepository->getUserData(array('select'=>'username', 'where'=>'username=\''.$values->username.'\''))->fetch()) {
			$form->addError($this->translator->translate('User \'%s\' already exists', $values->username));
		}
		if (!$values->role) {
			$form->addError($this->translator->translate('Please, select user role'));
		} elseif (strlen($values->role) > 20) {
			$form->addError($this->translator->translate('Role must be at max %d characters long', 20));
		}
		if (!$values->password) {
			$form->addError($this->translator->translate('Please, enter user password'));
		} elseif (strlen($values->password) > 255) {
			$form->addError($this->translator->translate('Password must be at max %d characters long', 255));
		}
		if (!$values->email) {
			$form->addError($this->translator->translate('Please, enter user email'));
		} elseif (strlen($values->email) > 255) {
			$form->addError($this->translator->translate('Email must be at max %d characters long', 255));
		} elseif (!filter_var($values->email, FILTER_VALIDATE_EMAIL)) {
			$form->addError($this->translator->translate('Email \'%s\' is not valid', $values->email));
		}
		if (!$values->description) {
			$form->addError($this->translator->translate('Please, enter user description'));
		} elseif (strlen($values->description) > 255) {
			$form->addError($this->translator->translate('Description must be at max %d characters long', 255));
		}

	}

	public function UserAddFormSubmitted(Form $form)
	{
		$values = $form->getValues();

		if ($this->userRepository->addUser($values))
		{
			$this->flashMessage($this->translator->translate('User \'%s\' successfully created',$values->username), 'success');
		} else {
			$this->flashMessage($this->translator->translate('Creation failed'), 'error');
		}
		$this->redirect('default');
	}
	
	protected function createComponentUserEditForm()
	{
		$id = $this->getParam('id');
		$query = array('id' => $id);
		$radcheckData = $this->getRadcheckData($query);
		$radcheckData = $radcheckData[0];
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addHidden('id', $radcheckData->id);
		$form->addHidden('hash', $radcheckData->hash);
		$form->addText('username', 'Username:', 30, 64)
				->setValue($radcheckData->username)
				->setRequired('Please, enter username')
				->setDisabled();
		$form->addSelect('role','Role:', array('admin'=>'admin', 'user'=>'user'))
				->setValue($radcheckData->role)
				->setRequired('Please, select role');
		$form->addCheckbox('disabled','Disabled:')
				->setValue($radcheckData->disabled);
		$form->addText('desc', 'Description:', 30, 255)
				->setValue($radcheckData->description)
				->setRequired('Please, enter description')
				->setAttribute('placeholder', $this->translator->translate('Enter user description...'))
				->addRule(Form::MAX_LENGTH, 'Description must be at max %d characters long', 255);
		$form->addText('email', 'Email:', 30, 255)
				->setValue($radcheckData->email)
				->setRequired('Please, enter email')
				->setAttribute('placeholder', $this->translator->translate('Enter user email...'))
				->addRule(Form::MAX_LENGTH, 'Email must be at max %d characters long', 255);
		$form->addText('value', 'Password:', 30, 253)
				->setAttribute('placeholder', $this->translator->translate('Fill in for password change...'))
				->addRule(Form::MAX_LENGTH, 'Password must be at max %d characters long', 253);
		$radcheckData->attribute === 'Crypt-Password'
				? $crypt = TRUE
				: $crypt = FALSE;
		$form->addCheckbox('crypt', 'Crypt password:')
				->setValue($crypt);
		$groupsData = $this->db->select('groupname')->from('radgroupcheck')->groupBy('groupname')->fetchPairs();
		$groups = array();
		foreach ($groupsData as $id => $value) {
			$groups[$value] = $value;
		}
		$prompt = Html::el('option')->setText($this->translator->translate('Select:'))->class('prompt');
		$form->addSelect('groupname', 'Group:', $groups)
				->setValue($radcheckData->groupname)
				->setPrompt($prompt)
				->setRequired('Please, select group');
		$form->addDatePicker('datefrom', 'Active from:')
				->setValue($radcheckData->datefrom)
				->addRule($form::FILLED, 'You must pick some date');
		$form->addDatePicker('dateto', 'Active to:')
				->setValue($radcheckData->dateto)
				->addRule($form::FILLED, 'You must pick some date');
		$form->addSubmit('save', 'Save')
				->setAttribute('class','btn btn-small btn-block btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onSuccess[] = $this->radcheckEditFormSubmitted;
		return $form;
	}

	public function radcheckEditFormSubmitted(Form $form)
	{
		$values = $form->getValues();
		$query = array('id' => $values->id);
		$userData = $this->getRadcheckData($query);
		$userData = $userData[0];
		if ($userData->hash === $values->hash)
		{
			$userValues = array('op'       => ':=',
								'datefrom' => $values->datefrom,
								'dateto'   => $values->dateto,
								'desc'     => $values->desc,
								'email'    => $values->email,
								'disabled' => $values->disabled,
								'role'     => $values->role);

			if ($values->value)
			{
				$values->crypt === TRUE
						? $userValues['attribute'] = 'Crypt-Password'
						: $userValues['attribute'] = 'Cleartext-Password';

				$values->crypt === TRUE
						? $userValues['value'] = $this->auth->calculateHash($values->value)
						: $userValues['value'] = $values->value;
			}

			try {
				$this->db->update('radcheck', $userValues)->where(array('id'=>$userData->id))->execute();
				if ($this->db->affectedRows())
				{
					if ($values->groupname != $userData->groupname)
					{
						$this->db->update('radusergroup', array('groupname' => $values->groupname))
									->where(array('username'=>$userData->username, 'groupname'=>$userData->groupname))
									->execute();
					}
					$this->flashMessage($this->translator->translate('Succesfully modified'), 'success');
				} else {
					$this->flashMessage($this->translator->translate('Modification failed'), 'error');
				}
			} catch (\DibiException $e) {
				$this->flashMessage($this->translator->translate('Modification failed'), 'error');
			}
			$this->redirect('default');
		} else {
			$this->flashMessage($this->translator->translate('Database data changes during modification. Please modify data again.'),'error');
			$this->redirect('this');
		}
	}
}