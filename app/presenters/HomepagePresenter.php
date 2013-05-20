<?php

/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter
{

	public function renderDefault()
	{
		$this->template->anyVariable = 'any value';
	}

	protected function startup()
    {
        parent::startup();

		$this->isInRole();
	}
}
