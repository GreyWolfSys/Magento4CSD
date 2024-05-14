<?php
namespace Altitude\SX\Observer;
class SaveToQuote implements \Magento\Framework\Event\ObserverInterface
{   
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
   //     $post = $this->getRequest()->getPostValue();
   //     $order_instructions = $post['order_instructions'];
        $event = $observer->getEvent();
        $quote = $event->getQuote();
    //    $quote->setData('order_instructions', $quote->getData('order_instructions'));
    }
}