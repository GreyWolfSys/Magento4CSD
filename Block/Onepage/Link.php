<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Altitude\CSD\Block\Onepage;

/**
 * One page checkout cart link
 *
 * @api
 * @author      Magento Core Team <core@magentocommerce.com>
 * @since 100.0.2
 */
class Link extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    protected $_checkoutHelper;

    protected $_cart;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Magento\Checkout\Model\Cart $cart,
        array $data = []
    ) {
        $this->_checkoutHelper = $checkoutHelper;
        $this->_checkoutSession = $checkoutSession;
        parent::__construct($context, $data);
        $this->_cart = $cart;
        $this->_isScopePrivate = true;
    }

    /**
     * @return string
     */
    public function getCheckoutUrl()
    {
        return $this->getUrl('checkout');
    }

    /**
     * @return bool
     */
    public function isDisabled()
    {
        return !$this->_checkoutSession->getQuote()->validateMinimumAmount();
    }

    /**
     * @return bool
     */
    public function isPossibleOnepageCheckout()
    {
        return $this->_checkoutHelper->canOnepageCheckout();
    }

    public function getButtonText()
    {
        $buttonText = "Proceed to Checkout";
        $allItems = $this->_cart->getQuote()->getAllVisibleItems();

        foreach ($allItems as $_item) {
            $_name = $_item->getName();
            if (strpos($_name, "Invoice") !== false) {
                $buttonText = "Pay invoice(s)";
                break;
            }
        }

        return $buttonText;
    }
}
