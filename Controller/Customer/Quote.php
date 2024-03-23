<?php

namespace Altitude\CSD\Controller\Customer;

class Quote extends \Altitude\CSD\Controller\CustomerAbstract
{
    public function execute()
    {
        $this->_view->loadLayout();

        $this->_view->renderLayout();
    }
}
