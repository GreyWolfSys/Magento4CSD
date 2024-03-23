<?php

namespace Altitude\CSD\Model;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http;
use Psr\Log\LoggerInterface;

class GlobalLogger implements ObserverInterface
{
	protected $_logger;
	public function __construct(
		LoggerInterface $logger
	) {
		$this->_logger = $logger;
	}
	public function execute(\Magento\Framework\Event\Observer $observer)
	{
		$gcnl = SalesCustomerList(1000);
		//echo (is_array($gcnl));

		//exit;
		try {
			foreach ($gcnl as $item) {
				if (count($item) > 1) {
					foreach ($item as $x => $x_value) {
						echo $x . " = " . $x_value . "; ";
					}
					echo "<br> Cono=" . $item["CONO"] . "<br>";
				} else {
					foreach ($gcnl as $x => $x_value) {
						echo $x . " = " . $x_value . "; ";
					}
					echo "<br> Cono=" . $gcnl["CONO"] . "<br>";
					break;
				}
				echo "<br>";
			}
		} catch (Exception $e) {
			echo "Error";
		}
		exit;
		//require_once ("lib/phpinfo.php");
		$request = $observer->getRequest();
		$fullName = $request->getPathInfo();
		$this->_logger->info('Global: ' . $fullName);
		return $this;
	}



}
