<?php

namespace Altitude\CSD\Cron;

use Magento\Sales\Api\Data\OrderInterface;

class UpdateOrderFields
{
    protected $csd;

    protected $order;

    protected $resourceConnection;

    public function __construct(
        OrderInterface $order,
        \Altitude\CSD\Model\CSD $csd,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->order = $order;
        $this->csd = $csd;
        $this->resourceConnection = $resourceConnection;
    }

    /**
       * Write to system.log
       *
       * @return void
       */
    public function UpdateFields($dbConnection, $column)
    {

    //$this->csd->gwLog ("Adding field " . $column);
        $query = "IF NOT EXISTS( SELECT NULL
            FROM INFORMATION_SCHEMA.COLUMNS
           	WHERE table_name = 'gws_GreyWolfOrderFieldUpdate'
            	AND table_schema = 'db_name'
				AND column_name = 'columnname')
			THEN
            	ALTER TABLE `gws_GreyWolfOrderFieldUpdate` ADD `" . $column . "` varchar(50) NULL;
            END IF;";
        $query_result = $dbConnection->query($query);
    }

    public function execute()
    {
		$moduleName = $this->csd->getModuleName(get_class($this));
        $this->csd->gwLog('Updating ERP Order Field Cron');
      //  $this->csd->gwLog('Opening DB connection');

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
        $envFilePath =  $directory->getRoot() .'/app/etc/env.php' ;
        $configResult = include $envFilePath;
        $conn= $configResult['db']['connection']['default'];
        $db_host = $conn['host']; //'localhost';
        $db_port = '3306';
        $db_username = $conn['username']; //'magento_mg3';
        $db_password = $conn['password'];//'G.Vu1O755x587yQnw8u50';
        $db_primaryDatabase = $conn['dbname']; //'magento_mg3';


		$dbConnection = new \mysqli($db_host, $db_username, $db_password, $db_primaryDatabase);

		if (mysqli_connect_errno()) {
			error_log("Could not connect to MySQL databse: " . mysqli_connect_error());
			exit();
		}

        // Connect to the database, using the predefined database variables in /assets/repository/mysql.php
       // $dbConnection = $this->resourceConnection->getConnection();

        $sql = "UPDATE mg_sales_order_grid o2 INNER join mg_sales_order o1 ON (o1.entity_id=o2.entity_id) SET o2.ext_order_id=o1.ext_order_id WHERE o1.ext_order_id IS NOT NULL AND o2.ext_order_id IS NULL ";
        try {
            $result = $dbConnection->query($sql);
        } catch (\Exception $e) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "ERROR syncing ext_order_id data: " . $e->getMessage());
        }
        try {
            $this->UpdateFields($dbConnection, 'TransactionID');
            $this->UpdateFields($dbConnection, 'STAN');
            $this->UpdateFields($dbConnection, 'LocalDateTime');
            $this->UpdateFields($dbConnection, 'TXNDateTime');
            $this->UpdateFields($dbConnection, 'CCNo');
            $this->UpdateFields($dbConnection, 'CCExp');
            $this->UpdateFields($dbConnection, 'CCCCV');
            $this->UpdateFields($dbConnection, 'AuthID');
            $this->UpdateFields($dbConnection, 'TxnAmt');
            $this->UpdateFields($dbConnection, 'RefNum');
            $this->UpdateFields($dbConnection, 'ClientRef');
            $this->UpdateFields($dbConnection, 'CardType');
            $this->UpdateFields($dbConnection, 'ResponseCode');
            $this->UpdateFields($dbConnection, 'CardLevelResult');
            $this->UpdateFields($dbConnection, 'ACI');
            $this->UpdateFields($dbConnection, 'TXNResponse');
            $this->UpdateFields($dbConnection, 'suffix_list');
        } catch (\Exception $e) {
            //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "ERROR getting data: " . $e->getMessage());
            //exit;
        }

    //    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Checking Order Field Table");
        $querycheck = 'SELECT 1 FROM `gws_GreyWolfOrderFieldUpdate`';
		$query_result = $dbConnection->query($querycheck);

        if ($query_result === false) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Order field update table does not exist");
            exit;
        }

        //check table for orders to process
    //    $this->csd->gwLog('Getting results');
        $sql = "SELECT *  FROM `gws_GreyWolfOrderFieldUpdate` WHERE `dateprocessed` is null; ";

        $dbConnection = $this->resourceConnection->getConnection();
        try {
            $result = $dbConnection->fetchAll($sql);
        } catch (\Exception $e) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "ERROR getting data: " . $e->getMessage());
            exit;
        }

        try {
            if (count($result)) {
               // $this->csd->gwLog($result->num_rows . ' records found');
                // output data of each row
                foreach ($result as $row) {
                    //submit orders
                    try {
                        $DateEntered = $row["dateentered"];
                        if (isset($row["ERPOrderNo"])) {
                            $erpOrderNo = $row["ERPOrderNo"] . '';
                        } else {
                            $erpOrderNo = "";
                        }
                        if (isset($row["ERPSuffix"])) {
                            $ERPSuffix = $row["ERPSuffix"] . '';
                        } else {
                            $ERPSuffix = "";
                        }
                        if (isset($row["CCAuthNo"])) {
                            $CCauthNo = $row["CCAuthNo"] . '';
                        } else {
                            $CCauthNo = "";
						}

                        // $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Building query");
                        $sql = "update `mg_sales_order` set `status`=`status` ";
                        if ($erpOrderNo != "") {
                            $sql .= ", `CSD_OrderNo`='" . $erpOrderNo . "'";
                        }

                        //	$this->csd->gwLog($sql);
                        if ($ERPSuffix != "") {
                            $sql .= ", `CSD_OrderSuf`='" . $ERPSuffix . "'";
                        }
                        //	$this->csd->gwLog($sql);

                        if ($CCauthNo != "") {
                            $sql .= ", `CC_AuthNo`='" . $CCauthNo . "'";
                        }
                        //	$this->csd->gwLog($sql);

                        $sql .= " where `increment_id`='" . $row["orderid"] . "' ";
                  //      $this->csd->gwLog('***' . $sql);
                        if ($dbConnection->query($sql) === true) {
                            // echo "New record created successfully";
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Order field  updated successfully for order " . $row["orderid"]);
                            $sql = "update mg_sales_order_grid set ext_order_id='" . $erpOrderNo . "-" . $ERPSuffix . "' where increment_id='" . $row["orderid"] . "';";

                            $this->csd->UpdateOrderFieldProcessed($row["orderid"], $DateEntered);
                        }

                        $sql = "update mg_sales_order_grid set ext_order_id='" . $erpOrderNo . "-" . $ERPSuffix . "' where increment_id='" . $row["orderid"] . "';";
				//		$this->csd->gwLog($sql);

                        if ($dbConnection->query($sql) === true) {
                            // echo "New record created successfully";
                            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Order grid field  updated successfully for order " . $row["orderid"]);
                            $this->csd->UpdateOrderFieldProcessed($row["orderid"], $DateEntered);
                        }
                         $this->csd->UpdateOrderFieldProcessed($row["orderid"], $DateEntered);
                    } catch (\Exception $e) {
                        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Failed to insert update order field table: " . $e->getMessage());
                    }
                }
            } else {
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "0 results");
            }
        } catch (\Exception $e) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "ERROR! getting data: " . $e->getMessage());
        }
    }
}
