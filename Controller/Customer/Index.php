<?php

namespace Altitude\CSD\Controller\Customer;

class Index extends \Altitude\CSD\Controller\CustomerAbstract
{
    public function execute()
    {
        $this->_view->loadLayout();

        $this->_view->renderLayout();
    }
}
