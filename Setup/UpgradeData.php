<?php

namespace Altitude\CSDCustomers\Setup;

use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Sales\Setup\SalesSetupFactory;

class UpgradeData implements UpgradeDataInterface
{
    private $customerSetupFactory;

    /**
     * Sales setup factory
     *
     * @var SalesSetupFactory
     */
    protected $salesSetupFactory;

    /**
     * Constructor
     *
     * @param \Magento\Customer\Setup\CustomerSetupFactory $customerSetupFactory
     */
    public function __construct(
        CustomerSetupFactory $customerSetupFactory,
        SalesSetupFactory $salesSetupFactory
    ) {
        $this->customerSetupFactory = $customerSetupFactory;
        $this->salesSetupFactory = $salesSetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function upgrade(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {

        if (version_compare($context->getVersion(), "1.0.1", "<")) {

            #if ( version_compare($context->getVersion(), '3.1.8', '<' )) {
            $erpshipvia = $this->eavConfig->getAttribute(Customer::ENTITY, 'erpshipvia');
            $erpshipvia->setFrontendLabel('CSD Ship Via')->save();

            $erpshipviadesc = $this->eavConfig->getAttribute(Customer::ENTITY, 'erpshipviadesc');
            $erpshipviadesc->setFrontendLabel('CSD Ship Via Desc')->save();
            #}

            

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();

            try {
                $query = "CREATE TABLE IF NOT EXISTS `gws_GreyWolfOrderFieldUpdate` (
                    `ID` int(11) unsigned NOT NULL auto_increment,
                    `orderid` varchar(255) NOT NULL default '',
                    `ERPOrderNo` varchar(255) DEFAULT NULL,
                    `ERPSuffix` varchar(255) DEFAULT NULL,
                    `CCAuthNo` varchar(255) DEFAULT NULL,
                    `dateentered` DATETIME DEFAULT NULL,
                    `dateprocessed` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`ID`)
                );
                ALTER TABLE `gws_GreyWolfOrderFieldUpdate` ADD COLUMN IF NOT EXISTS `shipping_upcharge` DECIMAL(20,4) AFTER `CCAuthNo`;";

                $connection->multiQuery($query);
            } catch (\Exception $e) {
            }

            $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);
           
            $MyAttribute = $customerSetup->getEavConfig()->getAttribute('customer', 'whse');
            $MyAttribute->setData(
                'used_in_forms',
                ['adminhtml_customer_address']
            );
            $MyAttribute->save();
            
            // insert attribute
            $customerSetup->addAttribute('customer', 'whse', [
                'label' => 'Warehouse',
                'type' => 'varchar',
                'input' => 'text',
                'position' => 45,
                'visible' => true,
                'required' => false,
                'system' => 0
            ]);

            $MyAttribute = $customerSetup->getEavConfig()->getAttribute('customer', 'whse');
            $MyAttribute->setData(
                'used_in_forms',
                ['adminhtml_customer']
            );
            $MyAttribute->save();

    		//$customerSetup->removeAttribute( \Magento\Customer\Model\Customer::ENTITY, 'warehouse');

              /** @var \Magento\Sales\Setup\SalesSetup $salesSetup */
            $salesSetup = $this->salesSetupFactory->create(['setup' => $setup]);

            /**
             * Add 'NEW_ATTRIBUTE' attributes for order
             */
            $options = ['type' => 'varchar', 'visible' => true, 'required' => false];
            $salesSetup->addAttribute('order', 'CSD_OrderNo', $options);
            $salesSetup->addAttribute('order', 'CC_AuthNo', $options);
            $salesSetup->addAttribute('order', 'CSD_OrderSuf', $options);


            $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
                $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
                $connection = $resource->getConnection();

                $DBERROR=true;
            try {
                $sqlExternal="ALTER TABLE `sales_order_grid` ADD COLUMN `ext_order_id` VARCHAR(255) NULL DEFAULT NULL COMMENT 'External Order ID'";
                if(!$connection->query($sqlExternal)){
                    $this->_logger->addDebug("Ext_order_id add failed: " . $dbConnection->errno . ") " . $dbConnection->error);
                }
            } catch (\Exception $e){
                $DBERROR=false;  //this will fail if it already exists, and that's ok
            }

            try {
                $querycheck='SELECT 1 FROM `gws_GreyWolfOrderQueue`';
                $query_result=$connection->query($querycheck);
                if ($query_result !== FALSE) {
                    $DBERROR=false;
                }
            } catch (\Exception $e){
                $DBERROR=true;
            }


            if ($DBERROR==false) {
                // table exists, proceed
            } else {
                // table does not exist, create here.
                $queryCreateUsersTable = "CREATE TABLE IF NOT EXISTS `gws_GreyWolfOrderQueue` (
                `ID` int(11) unsigned NOT NULL auto_increment,
                `orderid` varchar(255) NOT NULL default '',
                `dateentered` DATETIME DEFAULT NULL,
                `dateprocessed` DATETIME DEFAULT NULL,
                PRIMARY KEY  (`ID`)
                )";
                if(!$connection->query($queryCreateUsersTable)){
                    $this->_logger->addDebug("Order table creation failed: " . $connection->errno . ") " . $connection->error);

                }
            }

            //fields to update later
            try {
                $querycheck='SELECT 1 FROM `gws_GreyWolfOrderFieldUpdate`';
                $query_result=$connection->query($querycheck);
                if ($query_result !== FALSE){
                    $DBERROR=false;
                }
            } catch (\Exception $e){
                $DBERROR=true;
            }


            if ($DBERROR==false) {
                // table exists, proceed
            } else {
                // table does not exist, create here.
                $queryCreateUsersTable = "CREATE TABLE IF NOT EXISTS `gws_GreyWolfOrderFieldUpdate` (
                `ID` int(11) unsigned NOT NULL auto_increment,
                `orderid` varchar(255) NOT NULL default '',
                `ERPOrderNo` varchar(255) DEFAULT NULL,
                `ERPSuffix` varchar(255) DEFAULT NULL,
                `CCAuthNo` varchar(255) DEFAULT NULL,
                `dateentered` DATETIME DEFAULT NULL,
                `dateprocessed` DATETIME DEFAULT NULL,
                PRIMARY KEY  (`ID`)
                )";
                if(!$connection->query($queryCreateUsersTable)){
                    $this->_logger->addDebug("Table creation failed: " . $dbConnection->errno . ") " . $dbConnection->error);
                }
            }
            try {
                $querycheck='SELECT 1 FROM `gws_GreyWolfLog`';
                $query_result=$connection->query($querycheck);
                if ($query_result !== FALSE){
                    $DBERROR=false;
                }
            } catch (\Exception $e){
                $DBERROR=true;
            }


            if ($DBERROR==false) {
                // table exists, proceed
            }
            else {
                // table does not exist, create here.

                $queryCreateUsersTable = "CREATE TABLE IF NOT EXISTS `gws_GreyWolfLog` (
                          `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
                          `dateentered` datetime DEFAULT NULL,
                          `user` varchar(255) DEFAULT NULL,
                          `IP` varchar(255) DEFAULT NULL,
                          `LogType` varchar(255) DEFAULT NULL,
                          `LogData` varchar(255) DEFAULT NULL,
                          `LogType2` varchar(255) DEFAULT NULL,
                          `LogData2` varchar(235) DEFAULT NULL,
                          PRIMARY KEY (`ID`)
                )";
                if(!$connection->query($queryCreateUsersTable)){
                    $this->_logger->addDebug("Log table creation failed: " . $connection->errno . ") " . $connection->error);

                }
            }
              
            $setup->endSetup();
        }
    }
}
