<?php

namespace Altitude\CSD\Observer\Sales;

class OrderInvoiceSaveAfter implements \Magento\Framework\Event\ObserverInterface
{
    protected $csd;

    protected $objectManager;

    protected $customerRepositoryInterface;

    public function __construct(
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
        \Altitude\CSD\Model\CSD $csd,
        \Magento\Framework\App\ObjectManager $objectManager
    ) {
        $this->csd = $csd;
        $this->objectManager = $objectManager;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
    }

    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        //this claims funds that are previoulsy authorized
        global $csdcustomerid, $sendtoerpinv;

        if ($sendtoerpinv == "0") {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "settlement processing");
            $invoice = $observer->getEvent()->getInvoice();
            $order = $invoice->getOrder();
            $orderincid = $order->getIncrementId();
            $orderid = $order->getId();
            $payment = $order->getPayment();

            $sendpaymenttoERP = false;

            $SavedFieldData = $this->GetSavedFieldData($orderincid);

            if ($processor == "Rapid Connect") {
                $obj_GMFMessageVariants = $this->CreateCreditSaleRequest($SavedFieldData);
                $clientRef = $this->GenerateClientRef($obj_GMFMessageVariants);
                $result = $this->SerializeToXMLString($obj_GMFMessageVariants);

                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Settlement request:");
                $this->csd->gwLog($result);

                $TxnResponse = $this->SendMessage($result, $clientRef);
                $VarResponse = $this->DeSerializeXMLString($TxnResponse);
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Settlement response:");
                $this->csd->gwLog($VarResponse);

                $RespGrp = $VarResponse["CreditResponse"]["RespGrp"];
                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "respcode=" . $RespGrp["RespCode"]);

                if ($RespGrp["RespCode"] != "000") {
                    $this->csd->gwLog('Failed auth request.' . $RespGrp["ErrorData"]);
                    // throw new \Exception('Failed credit card authorization request.');
                    throw new \Magento\Framework\Exception\LocalizedException(__('Failed auth request.'));
                } else {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Auth= " . $RespGrp["AuthID"]);
                    $sendpaymenttoERP = true;
                }
            } elseif ($processor == "Chase") {
            }

            if ($sendpaymenttoERP == true) {
                //status=bad, save for next version
                //TODO: implement new API
                return "";
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $custno = $csdcustomerid;

                $customerSession2 = $this->csd->getSession();
                $customerData = $customerSession2->getCustomer();

                if ($order->getCustomerIsGuest()) {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "customer is guest");
                    $custno = $csdcustomerid;
                } else {
                    if ($order->getCustomerId()) {
                        $customer = $this->_customerRepositoryInterface->getById($customerId);
                        $custno = $customer->getData('csd_custno');
                    } else {
                        $custno = $csdcustomerid;
                    }

                    if (!$custno) {
                        $custno = $csdcustomerid;
                    }
                }
                //status=bad, save for next version
                SalesOrderPaymentInsert($custno, $invno, $invsuf, $amt);
                //processing is not done yet.
                $payment->setIsTransactionClosed(1);
            }
        }

        return true;
    }

    public function GetSavedFieldData($orderincid)
    {
        global $db_host, $db_port, $db_username, $db_password, $db_primaryDatabase;
        global $apikey, $apiurl, $csdcustomerid, $cono, $whse, $slsrepin, $defaultterms, $operinit, $transtype, $shipviaty, $slsrepout;

        try {
            $sql = "select orderid,ERPOrderNo,ERPSuffix, CCAuthNo, dateentered, dateprocessed, TransactionID, STAN, LocalDateTime, TXNDateTime, CCNo, CCExp, CCCCV, AuthID, TxnAmt, RefNum, ClientRef, CardType, ResponseCode FROM gws_GreyWolfOrderFieldUpdate WHERE orderid='" . $orderincid . "'";
            $dbConnection = new \mysqli($db_host, $db_username, $db_password, $db_primaryDatabase);
            //	$this->csd->gwLog($sql);
            $result = $dbConnection->query($sql);
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            if ($result->num_rows > 0) {
                // output data of each row
                $this->csd->gwLog($result->num_rows . ' CC records found');
                while ($row = $result->fetch_assoc()) {

                    //$incrementid=$row[""];

                    $CCSaveFields = [
                        'TransactionID' => $row["TransactionID"],
                        'STAN' => $row["STAN"],
                        'LocalDateTime' => $row["LocalDateTime"],
                        'TXNDateTime' => $row["TXNDateTime"],
                        'CCNo' => $row["CCNo"],
                        'CCExp' => $row["CCExp"],
                        'CCCCV' => $row["CCCCV"],
                        'AuthID' => $row["AuthID"],
                        'TxnAmt' => $row["TxnAmt"],
                        'RefNum' => $row["RefNum"],
                        'ClientRef' => $row["ClientRef"],
                        'CardType' => $row["CardType"],
                        'ResponseCode'->$row["ResponseCode"],
                        'ERPOrderNo'->$row["ERPOrderNo"],
                        'ERPSuffix'->$row["ERPSuffix"]
                    ];
                }

                return $CCSaveFields;
            }
        } catch (\Exception $e) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to open update order table: " . $e->getMessage());
        }
        try {
            $dbConnection->close();
        } catch (\Exception $e) {
            $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to close db connection: " . $e->getMessage());
        }
    }

    public function CreateCreditSaleRequest($SavedFieldData)
    {
        /* Based on the GMF Specification, fields that are mandatory or related to
        this transaction should be populated.*/

        $currdatestr = date('Ymdhis', time());
        //$this->csd->gwLog($currdatestr);
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $rctppid = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payments/rapidconnect/rctppid');
        $rcgroupid = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payments/rapidconnect/rcgroupid');
        $rcmerchantid = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payments/rapidconnect/rcmerchantid');
        $rctid = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payments/rapidconnect/rctid');
        $rcdid = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payments/rapidconnect/rddid');

        //GMF - create object for GMFMessageVariants
        $obj_GMFMessageVariants = new \GMFMessageVariants();

        //Credit Request - create object for CreditRequestDetails
        $obj_CreditRequestDetails = new \CreditRequestDetails();

        //Common Group - create object for CommonGrp
        $obj_CommonGrp = new \CommonGrp();
        $cardname = "";
        switch ($SavedFieldData["CardType"]) {
            case "VISA":
                $cardname = "Visa";
                break;
            case "MASTERCARD":
                $cardname = "MasterCard";
                break;
            case "AMERICAN EXPRESS":
                $cardname = "American Express";
                break;
            case "DISCOVER":
                $cardname = "Discover";
                break;
        }

        $stan = rand(pow(10, 6 - 1), pow(10, 6) - 1);
        //populate common transaction fields
        $obj_CommonGrp->setPymtType("Credit");	//Payment Type = Credit
        $obj_CommonGrp->setTxnType("Completion");	//Transaction Type = Sale
        $obj_CommonGrp->setLocalDateTime($currdatestr);	//Local Txn Date-Time
        $obj_CommonGrp->setTrnmsnDateTime($currdatestr);	//Local Transmission Date-Time

        $obj_CommonGrp->setSTAN($stan);	//System Trace Audit Number"100003"
        $obj_CommonGrp->setRefNum($currdatestr);	//Reference Number
        $obj_CommonGrp->setTPPID($rctppid);	//TPP ID		//This is dummy value. Please use the actual value

        $obj_CommonGrp->setTermID($rctid);	//Terminal ID		//This is dummy value. Please use the actual value
        $obj_CommonGrp->setMerchID($rcmerchantid);	//Merchant ID	//This is dummy value. Please use the actual value x

        $obj_CommonGrp->setPOSEntryMode("011");	//Entry Mode for the transaction
        $obj_CommonGrp->setPOSCondCode("00");		// POS Cond Code = 00-Normal Presentment
        $obj_CommonGrp->setTermCatCode("01");		// Terminal Category Code = 01-POS
        $obj_CommonGrp->setTermEntryCapablt("04");	// Terminal Entry Capability for the POS

        $obj_CommonGrp->setTxnAmt($SavedFieldData["TxnAmt"]);	//Transaction Amount = $8.68

        $obj_CommonGrp->setTxnCrncy("840");	// Transaction Currency = 840-US Country Code
        $obj_CommonGrp->setTermLocInd("1");	// Location Indicator for the POS
        $obj_CommonGrp->setCardCaptCap("1");	// Card capture capibility for the terminal
        $obj_CommonGrp->setGroupID($rcgroupid);	// Group ID 	//This is dummy value. Please use the actual value x
        //add CommonGrp to CreditRequestDetails object
        $obj_CreditRequestDetails->setCommonGrp($obj_CommonGrp);

        //Orig Group
        $obj_OrigGrp = new \CardGrp();//cc_number
        $obj_OrigGrp->setOrigAuthIDData($CCSaveFields["AuthID"]);
        $obj_OrigGrp->setOrigTranDateTimeData($CCSaveFields["TXNDateTime"]);
        $obj_OrigGrp->setOrigLocalDateTimeData($CCSaveFields["LocalDateTime"]);
        $obj_OrigGrp->setOrigSTANData($CCSaveFields["STAN"]);
        $obj_OrigGrp->setOrigRespCodeData($CCSaveFields["ResponseCode"]);
        //add $obj_OrigGrp to CreditRequestDetails object
        $obj_CreditRequestDetails->setCardGrp($obj_OrigGrp);

        //Card Group - create object for CardGrp
        $obj_CardGrp = new \CardGrp();//cc_number
        $obj_CardGrp->setAcctNum($CCSaveFields["CCNo"]);	//Card Acct Number 4012000033330026
        $obj_CardGrp->setCardExpiryDate($CCSaveFields["CCExp"]);	//Card Exp Date "20200412"
        $obj_CardGrp->setCardType($cardname);	//Card Type
        //add CardGrp to CreditRequestDetails object
        $obj_CreditRequestDetails->setCardGrp($obj_CardGrp);

        //Additional Amount Group - create object for AddtlAmtGrp
        $obj_AddtlAmtGrp = new \AddtlAmtGrp();
        $obj_AddtlAmtGrp->setAddAmt($SavedFieldData["TxnAmt"]);
        $obj_AddtlAmtGrp->setAddAmtCrncy("840");
        $obj_AddtlAmtGrp->setAddAmtType("FirstAuthAmt");
        //add AddtlAmtGrp to CreditRequestDetails object
        $obj_CreditRequestDetails->setAddtlAmtGrp($obj_AddtlAmtGrp);

        //Additional Amount Group - create object for AddtlAmtGrp
        $obj_AddtlAmtGrp = new \AddtlAmtGrp();
        $obj_AddtlAmtGrp->setAddAmt($SavedFieldData["TxnAmt"]);
        $obj_AddtlAmtGrp->setAddAmtCrncy("840");
        $obj_AddtlAmtGrp->setAddAmtType("TotalAuthAmt ");
        //add AddtlAmtGrp to CreditRequestDetails object
        $obj_CreditRequestDetails->setAddtlAmtGrp($obj_AddtlAmtGrp);

        if ($cardname == "Visa") {
            //Visa Group - create object for VisaGrp
            $obj_VisaGrp = new \VisaGrp();
            $obj_VisaGrp->setACI("Y");	//ACI Indicator
            $obj_VisaGrp->setVisaBID("12345");	//Visa Business ID
            $obj_VisaGrp->setVisaAUAR("111111111111");	//Visa AUAR
            //add VisaGrp to CreditRequestDetails object
            $obj_CreditRequestDetails->setVisaGrp($obj_VisaGrp);
        }

        //Visa ECOMMGrp - create object for ECOMMGrp
        $obj_ECOMMGrp = new \ECOMMGrp();
        $obj_ECOMMGrp->setEcommTxnIndData("01");	//ACI Indicator
        //add ECOMMGrp to CreditRequestDetails object
        // 	$obj_CreditRequestDetails -> setECOMMGrp($obj_ECOMMGrp);

        //assign CreditRequest to the GMF object
        $obj_GMFMessageVariants->setCreditRequest($obj_CreditRequestDetails);

        return $obj_GMFMessageVariants;
    }

    //Serialize GMF object to XML payload
    public function SerializeToXMLString(\GMFMessageVariants $gmfMesssageObj)
    {	//create XML serializer instance using PEAR
        $serializer = new \XML_Serializer(["indent" => ""]);

        $serializer->setOption("rootAttributes", [
            "xmlns:xsi" => "http://www.w3.org/2001/XMLSchema-instance",
            "xmlns:xsd" => "http://www.w3.org/2001/XMLSchema",
            "xmlns" => "com/firstdata/Merchant/gmfV1.1"
        ]);

        //perform serialization
        $result = $serializer->serialize($gmfMesssageObj);

        //check result code and return XML Payload
        if ($result == true) {
            return str_replace("GMFMessageVariants", "GMF", $serializer->getSerializedData());
        } else {
            return "Serizalion Failed";
        }
    }

    //deSerialize response
    public function DeSerializeXMLString($response)
    {	//create XML serializer instance using PEAR

        //  $this->csd->gwLog ( "Response Payload: ");
        // $this->csd->gwLog ( $response );
        $arr = explode('<Payload>', $response);
        $important = $arr[1];
        $arr = explode('</Payload>', $important);
        $important = $arr[0];
        $response = trim($important);

        //  $this->csd->gwLog ( $response );
        $serializer = new \XML_Unserializer();

        $serializer->setOption("rootAttributes", [
            "xmlns:xsi" => "http://www.w3.org/2001/XMLSchema-instance",
            "xmlns:xsd" => "http://www.w3.org/2001/XMLSchema",
            "xmlns" => "com/firstdata/Merchant/gmfV1.1"
        ]);

        //perform serialization
        $result = $serializer->unserialize($response, false);

        //check result code and return XML Payload
        if ($result == true) {
            $response1 = $serializer->getUnserializedData();

            return $response1;
        } else {
            return "Deserizalion Failed";
        }
    }

    //Send GMF transaction to Datawire using HTTP POST
    public function SendMessage($gmfXMLPayload, $clientRef)
    {
        //Build GMF XML Payload to be sent to Datawire
        $gmfXMLPayload = '<?xml version="1.0" encoding="UTF-8"?>' . $gmfXMLPayload;
        $gmfXMLPayload = str_replace('&', '&amp;', $gmfXMLPayload);
        $gmfXMLPayload = str_replace('<', '&lt;', $gmfXMLPayload);
        $gmfXMLPayload = str_replace('>', '&gt;', $gmfXMLPayload);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $rctppid = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payments/rapidconnect/rctppid');
        $rcgroupid = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payments/rapidconnect/rcgroupid');
        $rcmerchantid = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payments/rapidconnect/rcmerchantid');
        $rctid = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payments/rapidconnect/rctid');
        $rcdid = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payments/rapidconnect/rddid');

        $auth = $rcgroupid . $rcmerchantid . '|' . str_pad($rctid, 8, "0", STR_PAD_LEFT);
        //auth 10001RCTST0000056668
        //  $this->csd->gwLog ("00018090839698053142");
        //  $this->csd->gwLog ($rcdid);
        //Build request message
        // DID and App values are dummy values. Please use the actual values
        $theReqData = '<?xml version="1.0" encoding="utf-8"?>
            <Request xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema" Version="3" ClientTimeout="30"
            xmlns="http://securetransport.dw/rcservice/xml">
            <ReqClientID><DID>' . $rcdid . '</DID><App>RAPIDCONNECTSRS</App><Auth>' . $auth . '</Auth>
            <ClientRef>' . $clientRef . '</ClientRef></ReqClientID><Transaction><ServiceID>160</ServiceID>
            <Payload>' . $gmfXMLPayload . '
            </Payload></Transaction>
            </Request>
        ';

        //$this->csd->gwLog (  $theReqData );
        //exit;
        //Initiate HTTP Post using CURL PHP library
        $url = 'https://stg.dw.us.fdcnet.biz/rc';
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $theReqData); //set POST data
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $response = curl_exec($ch);

        if ($response === false) {
            $resp_error = curl_error($ch);
            $this->csd->gwLog('Curl error: ' . $resp_error);
        } else {
            //Send transaction to Datawire and wait for response
            //Replace XML tags in response payload, for readability
            $response = str_replace('&amp;', '&', $response);
            $response = str_replace('&lt;', '<', $response);
            $response = str_replace('&gt;', '>', $response);
        }

        //Release CURL PHP http handle
        curl_close($ch);

        //Return the XML Response Payload
        return $response;
    }
}
