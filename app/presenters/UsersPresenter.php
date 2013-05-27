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

        $grid->addColumnText('username', 'Username')
				->setSortable()
				->setFilterText()
					->setSuggestion();

        $grid->addColumnText('description', 'Description')
				->setSortable()
				->setFilterText()
					->setSuggestion();

        $grid->addColumnText('disabled', 'Disabled')
				->setSortable()
				->setFilterNumber()
					->setSuggestion();
		
        $grid->addColumnMail('email', 'Email')
				->setSortable()
				->setFilterText()
					->setSuggestion();
		
        $grid->addColumnText('role', 'Role')
				->setSortable()
				->setFilterText()
					->setSuggestion();

		$grid->addActionHref('edit', 'Edit')
				->setIcon('pencil');

		$grid->addActionHref('delete', 'Delete')
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
		$query = array('select' => 'id', 'where' => 'id=\''.$id.'\'');
		!count($this->userRepository->getUserData($query))
			? $this->flashMessage($this->translator->translate('User does not exist'), 'error') && $this->redirect('default')
			: $this->setView('edit');
    }
	
    public function actionDelete()
    {
        $id = $this->getParam('id');
        $id = explode(',', $id);
		foreach ($id as $key => $user_id) {
			if ($user_id == $this->getUser()->getId())
			{
				$this->flashMessage($this->translator->translate('You can not delete your user account'), 'error');
			} else {
				$user = $this->userRepository->getUserData(array('select' => 'username', 'where' => 'id=\''.$user_id.'\''))->fetch();
				if ($this->userRepository->deleteUser($user_id))
				{
					$this->flashMessage($this->translator->translate('User \'%s\' successfully deleted', $user->username), 'success');
				} else {
					$this->flashMessage($this->translator->translate('User failed to delete'), 'error');
				}
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
		$form->addPassword('password', 'Password', 30, 255)
				->setRequired('Please, enter user password')
				->setAttribute('placeholder', $this->translator->translate('Enter user password...'))
				->addRule(Form::MIN_LENGTH, 'Passwords must be at least %d characters long.', $this->context->params['user']['minPasswordLength'])
				->addRule(Form::MAX_LENGTH, 'Password must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-key'));
		$form->addText('email', 'Email', 30, 255)
				->setRequired('Please, enter user email')
				->addRule(Form::EMAIL, 'Please, enter user email')
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
		} elseif ($this->userRepository->getUserData(array('select' => 'username', 'where' => 'username=\''.$values->username.'\''))->fetch()) {
			$form->addError($this->translator->translate('User \'%s\' already exists', $values->username));
		}
		if (!$values->role) {
			$form->addError($this->translator->translate('Please, select user role'));
		} elseif (strlen($values->role) > 20) {
			$form->addError($this->translator->translate('Role must be at max %d characters long', 20));
		}
		if (!$values->password) {
			$form->addError($this->translator->translate('Please, enter user password'));
		} elseif (strlen($values->password) < $this->context->params['user']['minPasswordLength']) {
			$form->addError($this->translator->translate('Passwords must be at least %d characters long.', $this->context->params['user']['minPasswordLength']));
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
		$query = array('where' => 'id=\''.$id.'\'');
		$userData = $this->userRepository->getUserData($query)->fetch();
		$userData->hash = md5(serialize($userData));
		$form = new Form();
		$form->setTranslator($this->translator);
		$form->addHidden('id', $userData->id);
		$form->addHidden('hash', $userData->hash);
		$form->addText('username', 'Username', 30, 64)
				->setValue($userData->username)
				->setOption('input-prepend', Html::el('i')->class('icon-user'))
				->setDisabled();
		$form->addCheckbox('disabled','Disabled')
				->setValue($userData->disabled);
		$form->addSelect('role','Role', array('admin'=>'admin', 'user'=>'user'))
				->setValue($userData->role)
				->setRequired('Please, select user role')
				->setOption('input-prepend', Html::el('i')->class('icon-briefcase'));
		$form->addPassword('newPassword', 'New password', 30, 255)
				->setAttribute('placeholder', $this->translator->translate('Fill in for password change...'))
				->addRule(Form::MAX_LENGTH, 'Password must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-key'));
		$form->addPassword('confirmPassword', 'Confirm password', 30, 100)
				->addRule(Form::EQUAL, 'Passwords must match', $form['newPassword'])
				->setAttribute('placeholder', $this->translator->translate('Fill in for password change...'))
				->setOption('input-prepend', Html::el('i')->class('icon-key'));
		$form->addText('email', 'Email', 30, 255)
				->setValue($userData->email)
				->setRequired('Please, enter user email')
				->addRule(Form::EMAIL, 'Please, enter user email')
				->setAttribute('placeholder', $this->translator->translate('Enter user email...'))
				->addRule(Form::MAX_LENGTH, 'Email must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-envelope-alt'));
		$form->addTextArea('description', 'Description')
				->setValue($userData->description)
				->setRequired('Please, enter user description')
				->setAttribute('placeholder', $this->translator->translate('Enter user description...'))
				->addRule(Form::MAX_LENGTH, 'Description must be at max %d characters long', 255)
				->setOption('input-prepend', Html::el('i')->class('icon-pencil'));
		$form->addSubmit('save', 'Save')
				->setAttribute('class','btn btn-primary');
		$form->addProtection('Timeout occured, please try it again');
		$form->onValidate[] = callback($this, 'validateUserEditForm');
		$form->onSuccess[] = $this->UserEditFormSubmitted;
		return $form;
	}

	public function validateUserEditForm($form)
	{
		$values = $form->getValues();

		if ($values->id == $this->getUser()->getId())
		{
			if ($values->disabled) {
				$form->addError($this->translator->translate('You can not disable your user account'));
			}
			if ($values->role != $this->getUser()->getRoles()) {
				$form->addError($this->translator->translate('You can not change role of your user account'));
			}
		}
		if (!$values->role) {
			$form->addError($this->translator->translate('Please, select user role'));
		} elseif (strlen($values->role) > 20) {
			$form->addError($this->translator->translate('Role must be at max %d characters long', 20));
		}
		if (($values->newPassword || $values->confirmPassword) && ($values->newPassword != $values->confirmPassword)) {
			$form->addError($this->translator->translate('Passwords must match'));
		} elseif ($values->newPassword && strlen($values->newPassword) < $this->context->params['user']['minPasswordLength']) {
			$form->addError($this->translator->translate('Passwords must be at least %d characters long.', $this->context->params['user']['minPasswordLength']));
		} elseif (strlen($values->newPassword) > 255) {
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

	public function UserEditFormSubmitted(Form $form)
	{
		$values = $form->getValues();
		$query = array('where' => 'id=\''.$values->id.'\'');
		$userData = $this->userRepository->getUserData($query)->fetch();
		$userData->hash = md5(serialize($userData));
		
		if ($userData->hash === $values->hash)
		{
			$userValues = array('role'			=> $values->role,
								'disabled'		=> $values->disabled,
								'email'			=> $values->email,
								'description'	=> $values->description);

			if ($values->newPassword)
			{
				$userValues['password'] = $this->auth->calculateHash($values->newPassword);
			}

			if ($this->userRepository->updateUser($values->id, $userValues))
			{
				$this->flashMessage($this->translator->translate('User \'%s\' succesfully updated', $userData->username), 'success');
				$this->redirect('default');
			} else {
				$this->flashMessage($this->translator->translate('Update of user \'%s\' failed', $userData->username), 'error');
			}
		} else {
			$this->flashMessage($this->translator->translate('Database data changes during modification. Please modify data again.'),'error');
			$this->redirect('this');
		}
	}
}