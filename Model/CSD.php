<?php

namespace Altitude\CSD\Model;

use \ArrayObject;
use \SoapVar;
use \PDO;

class CSD extends \Magento\Framework\Model\AbstractModel
{
    protected $httpHeader;

    protected $urlInterface;

    protected $customerSession;

    protected $scopeConfig;

    protected $state;

    protected $resourceConnection;

    protected $customerRepositoryInterface;

    protected $productRepository;

    public function __construct(
        \Magento\Framework\HTTP\Header $httpHeader,
        \Magento\Framework\UrlInterface $urlInterface,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\State $state,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
        $this->httpHeader = $httpHeader;
        $this->urlInterface = $urlInterface;
        $this->scopeConfig = $scopeConfig;
        $this->state = $state;
        $this->customerSession = $customerSession;
        $this->resourceConnection = $resourceConnection;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
        $this->productRepository = $productRepository;
    }

    public function configMapping($key = "")
    {
        $mapping = [
            'apikey' => 'connectivity/webservices/apikey',
            'apiurl' => 'connectivity/webservices/apiurl',
            'gwcustno' => 'connectivity/webservices/gwcustno',


            'csdcustomerid' => 'defaults/gwcustomer/erpcustomerid',
            'createcustomer' => 'defaults/gwcustomer/createcustomer',
            'defaultcurrency' => 'defaults/gwcustomer/defaultcurrency',
            'cono' => 'defaults/gwcustomer/cono',
            'whse' => 'defaults/gwcustomer/whse',
            'importshipto' => 'defaults/gwcustomer/importshipto',
            'shipviaty' => 'defaults/gwcustomer/shipviaty',
            'flatshipvia' => 'defaults/gwcustomer/flatshipvia',
            'shipto2erp' => 'defaults/gwcustomer/shipto2erp',

            'slsrepin' => 'defaults/shoppingcart/slsrepin',
            'slsrepout' => 'defaults/shoppingcart/slsrepout',
            'takenby' => 'defaults/shoppingcart/takenby',
            'taxtakenby' => 'defaults/shoppingcart/taxtakenby',
            'defaultterms' => 'defaults/shoppingcart/defaultterms',
            'operinit' => 'defaults/shoppingcart/operinit',
            'transtype' => 'defaults/shoppingcart/transtype',
            'autoinvoice' => 'defaults/shoppingcart/autoinvoice',
            'sendtoerpinv' => 'defaults/shoppingcart/sendtoerpinv',
            'hidepmt' => 'defaults/shoppingcart/hidepmt',
            'blockpofordefault' => 'defaults/shoppingcart/blockpofordefault',
            'holdifover' => 'defaults/shoppingcart/holdifover',
            'taxfromquote' => 'defaults/shoppingcart/taxfromquote',

            'whselist' => 'defaults/products/whselist',
            'whsename' => 'defaults/products/whsename',

            'maxrecall' => 'connectivity/maxrecall/maxrecall',
            'maxrecalluid' => 'connectivity/maxrecall/maxrecalluid',
            'maxrecallpwd' => 'connectivity/maxrecall/maxrecallpwd',

            'hidenegativeinvoice' => 'defaults/display/hidenegativeinvoice',
            'simplifyinvoice' => 'defaults/display/simplifyinvoice',
            'invstartdate' => 'defaults/display/invstartdate',

            'orderaspo' => 'defaults/misc/orderaspo',
            'updateqty' => 'defaults/misc/updateqty',
            'alertemail' => 'defaults/misc/alertemail',
            'onlycheckproduct' => 'defaults/misc/onlycheckproduct',
            'potermscode' => 'defaults/misc/potermscode',
            'addonno' => 'defaults/misc/addonno',
            'shipbystage' => 'defaults/misc/shipbystage',
            'alloweditaddress' => 'defaults/gwcustomer/allow_edit_address',
            'listorbase' => 'defaults/misc/listorbase',

            'cenposuid' => "",
            'cenpospwd' => "",
            'cenposmerchid' => "",

            'shipping_methods' => "shipping_upcharge/general/shipping_methods",
            'upcharge_label' => "shipping_upcharge/general/upcharge_label",
            'payment_method' => "shipping_upcharge/general/payment_method",
            'upcharge_percent' => "shipping_upcharge/general/upcharge_percent",
            'waive_amount' => "shipping_upcharge/general/waive_amount"
        ];

        if ($key != "" && isset ($mapping[$key])) {
            return $mapping[$key];
        } elseif (strpos($key, '/') != false) {
            return $key;
        } else {
            return $mapping;
        }
    }


    // // Sorts array or items in ASC
    public function array_sort_by_column($arr, $col, $dir = SORT_ASC)
    {
        $sort_col = array();
        if ($dir == "desc") {
            $dir = SORT_DESC;
        } else {
            $dir = SORT_ASC;
        }

        if (str_contains($col, "dt") || str_contains($col, "date")) {
            foreach ($arr as $key => $part) {
                if (isset ($part[$col])) {
                    $sort_col[$key] = strtotime($part[$col]);
                } else {
                    // unset($arr[$key]);
                    $sort_col[$key] = "na";
                }
            }
        } else {
            foreach ($arr as $key => $row) {
                $sort_col[$key] = $row[$col];
                if (isset ($row[$col])) {
                    $sort_col[$key] = $row[$col];
                } else {
                    // unset($arr[$key]);
                    $sort_col[$key] = "na";
                }
            }
        }
        array_multisort($sort_col, $dir, $arr);
        return $arr;
    }


    public function getAllConfigValues()
    {
        $configValues = [];

        foreach ($this->configMapping() as $_config => $_configPath) {
            if (strpos($_config, "cenpos") !== false) {
                $configValues[$_config] = $_configPath;
            } else {

                $configValues[$_config] = $this->scopeConfig->getValue($_configPath);
            }
        }

        return $configValues;
    }

    public function getModuleList()
    {

        //if (!isset($_REQUEST["modulelist"])){
        //     return "";
        // }
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        try {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Getting list of modules *****************");
            $modList = $objectManager->get('\Magento\Framework\Module\FullModuleList');

            if (isset ($modList)) {
                $allModules = $modList->getAll();
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Showing list of modules *****************");
                $result = "";
                foreach ($allModules as $mod) {
                    //if (strpos($mod["name"],"Amazonx")===false && strpos($mod["name"],"Paypalx")===false && strpos($mod["name"],"Magentox")===false && strpos($mod["name"],"Dotdigitalx")===false && strpos($mod["name"],"Klarnax")===false && strpos($mod["name"],"Temandox")===false && strpos($mod["name"],"Vertexx")===false && strpos($mod["name"],"Yotpox")===false){
                    $result .= $mod["name"] . "," . $mod["setup_version"] . " \n";
                    //}
                }

                //ob_start();
                //var_dump($allModules);
                //$result = ob_get_clean();
                $this->gwLogCSV($result);
            }

        } catch (\Exception $e) {
            $erpAddress = "";
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Error getting module list " . json_encode($e->getMessage()));
        }
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Finished Getting list of modules *****************");
    }

    public function gwLogCSV($message = "")
    {
        //$debugEnabled = $this->scopeConfig->getValue('defaults/misc/debugenabled');

        if (1 == 1) {//($message != "" && $debugEnabled) {
            $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/altitude.csv');
            $logger = new \Zend_Log();
            $logger->addWriter($writer);
            if (is_string($message)) {
                $logger->info($message);
            } else {
                $logger->info(json_encode($message));
            }
        }
    }
    public function gwLogAPITime($message = "")
    {
        //$this->gwLogAPITime($result);

        if (1 == 1) {//($message != "" && $debugEnabled) {
            $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/altitudetimestamp.csv');
            $logger = new \Zend_Log();
            $logger->addWriter($writer);
            if (is_string($message)) {
                $logger->info($message);
            } else {
                $logger->info(json_encode($message));
            }
        }
    }
    public function getConfigValue($configName)
    {

        if (is_array($configName)) {
            $configs = [];
            foreach ($configName as $_config) {
                $configPath = $this->configMapping($_config);
                $_configKey = $_config;

                if (strpos($_config, "/") !== false) {
                    $_tmp = explode("/", $_config);
                    $_configKey = last($_tmp);
                }

                if (strpos($_config, "cenpos") !== false) {
                    $configs[$_configKey] = $configPath;
                } else {

                    $configs[$_configKey] = $this->scopeConfig->getValue($configPath);
                }
            }

            return $configs;
        } else {
            $configPath = $this->configMapping($configName);

            if (is_array($configPath)) {
                return null;
            } elseif (strpos($configName, "cenpos") !== false) {
                return $configPath;
            } else {

                return $this->scopeConfig->getValue($configPath);
            }
        }
    }

    public function LogAPICall($apiname, $moduleName = "")
    {
        // $this->getModuleList();
        try {
            $agent = $this->httpHeader->getHttpUserAgent();
            $url = $this->urlInterface->getCurrentUrl();
        } catch (\Exception $e) {
            $agent = "";
            $url = "";
        }

        if ($moduleName) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "$moduleName: API: " . $apiname . ";; agent: " . $agent . "; url: " . $url);
        } else {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "API: " . $apiname . ";; agent: " . $agent . "; url: " . $url);
        }
    }

    //$this->gwLogAPITime($result);
    public function LogAPITime($apiname, $entrytype, $moduleName = "", $start = "", $logid = "")
    {
        return 0;
        $apiprofilerEnabled = $this->scopeConfig->getValue('defaults/misc/apiprofiler');
        if ($apiprofilerEnabled == "0") {
            //error_log("no profiler");
            return "";
        } else {
            // error_log(" profiler running " . $apiprofilerEnabled);
        }
        $gwcustno = $this->getConfigValue('gwcustno');
        if (empty ($gwcustno))
            $gwcustno = 'na';
        try {

            $profilertype = str_replace(" ", "", "Altitude-API-" . $moduleName . "-" . $apiname);
            if ($entrytype == "request") {
                \Magento\Framework\Profiler::start($profilertype);
                return microtime();
            } elseif ($entrytype == "result") {
                \Magento\Framework\Profiler::stop($profilertype);
            } else {
                //can't get here
                return "";
            }
            try {
                $agent = $this->httpHeader->getHttpUserAgent();
                $url = $this->urlInterface->getCurrentUrl();
            } catch (\Exception $e) {
                $agent = "";
                $url = "";
            }

            if ($moduleName) {
            } else {
                $moduleName = "unknown";
            }

            /*db entry*/
            $dbhost = "10.0.61.59";//"cloud9.greywolf.com";
            $dbuser = "logger";
            $dbpass = "&nzM89wcG";
            $db = "lamplogging";
            $port = "3306";
            $charset = 'utf8mb4';
            $bConnected = false;
            //if (!isset($_SESSION["gws-unique-id"] )){
            //    $_SESSION["gws-unique-id"] =uniqid('gws-',true) . '-' . rand(1000, 9999);;
            //}
            //$unique_id=$_SESSION["gws-unique-id"] ;

            //$unique_id=session_id();

            if (!isset ($unique_id) && isset ($_SESSION["gws-unique-id"])) {
                $unique_id = $_SESSION["gws-unique-id"];
            }
            if (!isset ($unique_id)) {
                session_start();
                $unique_id = session_id();
                $_SESSION["gws-unique-id"] = $unique_id;
            }
            if (empty ($unique_id)) {
                $unique_id = uniqid('gws-', true) . '-' . rand(1000, 9999);
                $_SESSION["gws-unique-id"] = $unique_id;
            }
            //$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "unique id csd.php=" . $unique_id . " *** " );
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $dsn = "mysql:host=$dbhost;dbname=$db;charset=$charset;port=$port";

            try {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $urlInterface = $objectManager->get(\Magento\Framework\UrlInterface::class);
                $currentUrl = $urlInterface->getCurrentUrl();
            } catch (\Exception $e) {
                error_log("url apiprofiler Catch Exception: " . json_encode($e->getMessage()));
                $currentUrl = "";
            }

            try {
                $customerSession = $objectManager->get('Magento\Customer\Model\Session');
                if ($customerSession->isLoggedIn()) {
                    $customerData = $customerSession->getCustomer();
                    $email = $customerSession->getCustomer()->getEmail();
                } else {
                    $email = "na";
                }
            } catch (\Exception $e) {
                error_log("customer apiprofiler Catch Exception: " . json_encode($e->getMessage()));
                $email = "na";
            }

            try {
                $remote = $objectManager->get('Magento\Framework\HTTP\PhpEnvironment\RemoteAddress');
                $ip = $remote->getRemoteAddress();
            } catch (\Exception $e) {
                error_log("ip apiprofiler Catch Exception: " . json_encode($e->getMessage()));
                $ip = "na";
            }

            try {
                $pdo = new \PDO($dsn, $dbuser, $dbpass, $options);
                $bConnected = true;
            } catch (\Exception $e) {
                error_log("conn apiprofiler Catch Exception: " . json_encode($e->getMessage()));

                $bConnected = false;
            }

            if ($bConnected) {
                try {
                    $t = explode(" ", microtime());
                    $dTime = date('Y-m-d H:i:s', $t[1]) . substr((string) $t[0], 1, 4);
                    if ($entrytype == "request") {
                        $start_time = $dTime;
                        $end_time = "";
                        $duration = "";
                    } elseif ($entrytype == "result") {
                        $end_time = $dTime;
                        $startMicro = explode(" ", $start);
                        $start_time = date('Y-m-d H:i:s', $startMicro[1]) . substr((string) $startMicro[0], 1, 4);
                        $duration = ($this->milliseconds(implode(" ", $t)) - $this->milliseconds($start)) / 1000;
                    } else {
                        $end_time = "";
                        $start_time = "";
                        $duration = "";
                    }
                    try {
                        $servername = $_SERVER['SERVER_NAME'];
                    } catch (\Exception $e) {
                        $servername = 'na';
                    }
                    //$row[] =$dTime;
                    $row[] = $gwcustno;
                    $row[] = $currentUrl;
                    $row[] = $email;
                    $row[] = $ip;
                    $row[] = $apiname;
                    $row[] = $moduleName;
                    $row[] = str_replace(',', '', $agent);
                    $row[] = $unique_id;

                    $row[] = $start_time;
                    $row[] = $end_time;
                    $row[] = $end_time;
                    $row[] = $duration;
                    $row[] = $logid;
                    $row[] = $servername;
                    $row[] = "API";
                    $stmt = $pdo->prepare("INSERT INTO Altitude_Profiler (`customer_id`,`url`,`email_address`,`ip_address`,`event`,`module_name` ,`agent`,`run_id`,start_time,end_time,datestamp,duration,log_id,source, entry_type) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $stmt->execute($row);
                    return \microtime(true);

                } catch (\Exception $e) {
                    $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Catch Exception on APIProfiler insert: " . json_encode($e->getMessage()));
                    return '';
                }
            } else {
                return (float) \microtime(true);
            }

        } catch (\Exception $e) {
            $this->gwLog('Caught exception logging apiprofiler: ' . json_encode($e->getMessage()));
            return '';
        }
    }
    public function milliseconds($mt)
    {
        $mt = explode(' ', $mt);
        return ((int) $mt[1]) * 1000 + ((int) round($mt[0] * 1000));
    }


    public function urlInterface()
    {
        return $this->urlInterface;
    }

    public function gwLog($trace = "", $message = "")
    {
        if ($message == "") {
            $message = $trace;
        } else {
            $message = $trace . $message;
        }

        $debugEnabled = "1";//$this->scopeConfig->getValue('defaults/misc/debugenabled');

        if ($message != "" && $debugEnabled == 1) {
            $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/altitude.log');
            $logger = new \Zend_Log();
            $logger->addWriter($writer);
            if (is_string($message)) {
                $logger->info($message);
            } else {
                $logger->info(json_encode($message));
            }
        }
    }
    public function jwLog($message = "")
    {
        //return "";
        //$debugEnabled = $this->scopeConfig->getValue('defaults/misc/debugenabled');

        if ($message != "") {
            $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/jason.log');
            $logger = new \Zend_Log();
            $logger->addWriter($writer);
            if (is_string($message)) {
                $logger->info($message);
            } else {
                $logger->info(json_encode($message));
            }
            $this->gwLog($message);
        }
    }

    public function getWsdlMapUrl($apikey, $apiName)
    {
        $apiUrl = $this->getConfigValue('apiurl');

        return [
            'wsdlUrl' => $apiUrl . "wsdl.aspx?result=wsdl&apikey=$apikey&api=$apiName",
            'mapUrl' => $apiUrl . "ws.aspx?result=ws&apikey=$apikey&api=$apiName"
        ];
    }

    public function createSoapClient($apikey, $apiName)
    {
        $getWsdlMapUrl = $this->getWsdlMapUrl($apikey, $apiName);

        try {
            $client = new \SoapClient(
                null,
                [
                    'location' => $getWsdlMapUrl['mapUrl'],
                    'uri' => str_replace("&", '$amp;', $getWsdlMapUrl['wsdlUrl']),
                    'trace' => 1,
                    'use' => SOAP_LITERAL,
                    'soap_version' => SOAP_1_2,
                    'connection_timeout' => 1,
                ]
            );

            return $client;
        } catch (\Exception $e) {
        }

        return false;
    }

    public function df_is_admin()
    {
        try {
            return 'adminhtml' === $this->state->getAreaCode();
        } catch (\Exception $e) {
            // $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            //  $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
            return false;
        } catch (Exception $e) {
            // $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            //  $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
            return false;
        }
    }

    public function TrimWHSEName($name, $trimchar)
    {
        if (strpos($name, $trimchar) !== false) {
            $name = strstr($name, $trimchar, true);
        }

        return $name;
    }

    public function makeRESTRequest($map_url, $request, $username, $password)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $map_url);
        //curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, "".$xmlrequest);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $content = curl_exec($ch);

        if (curl_errno($ch)) {
            return "";
            print curl_error($ch);
        } else {
            curl_close($ch);
        }

        return $content;
    }

    public function botDetector()
    {
        $userAgent = $this->httpHeader->getHttpUserAgent();

        if (!empty ($userAgent)) {
            $userAgent = strtolower($userAgent);
            $botIdentifiers = [
                'bot',
                'slurp',
                'crawler',
                'spider',
                'curl',
                'facebook',
                'fetch',
                'linkchecker',
                'semrush',
                'xenu',
                'google',
                'amasty_fpc',
            ];

            // See if one of the identifiers is in the UA string.
            foreach ($botIdentifiers as $_bot) {
                if (strpos($userAgent, $_bot) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getModuleName($className)
    {
        $module = explode("\\", $className);

        if (isset ($module[1])) {
            return "GWS_" . $module[1];
        } else {
            return $className;
        }
    }

    public function getSession()
    {
        return $this->customerSession;
    }
    public function get_string_between($string, $start, $end)
    {
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0)
            return '';
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    public function SalesCustomerPricingSelect($cono, $prod, $whse, $custno, $shipto, $qty, $moduleName = "")
    {
        $apiname = "SalesCustomerPricingSelect";

        $this->LogAPICall($apiname);
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "api prod=" . $prod);

        try {
            $apikey = $this->getConfigValue('apikey');
            $operinit = $this->getConfigValue('operinit');
            $client = $this->createSoapClient($apikey, $apiname);

            $params1 = (object) [];
            $params1->cono = $cono;
            $params1->prod = $prod;
            $params1->whse = $whse;
            $params1->custno = $custno;
            $params1->shipto = $shipto;
            $params1->operinit = $operinit;
            $params1->qty = $qty;
            $params1->APIKey = $apikey;

            $rootparams = (object) [];
            $rootparams->SalesCustomerPricingSelectRequestContainer = $params1;

            $result = (object) [];
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result //
            $result = $client->SalesCustomerPricingSelectRequest($rootparams);

            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>")); //request/result





            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }
    }

    public function SalesCustomerPricingList($cono, $custno, $whse, $shipto, $qty, $productlist, $moduleName = "")
    {//status=bad, save for next version_compare
        return "";
        $apiname = "SalesCustomerPricingList";
        $this->LogAPICall($apiname);

        try {
            $apikey = $this->getConfigValue('apikey');
            $client = $this->createSoapClient($apikey, $apiname);

            $paramsHead = new ArrayObject();
            $thisparam = array(
                'cono' => $cono,
                'custno' => $custno,
                'whse' => $whse,
                'shipto' => $shipto,
                'qty' => $qty,
                'APIKey' => $apikey
            );
            $params[] = new \SoapVar($thisparam, SOAP_ENC_OBJECT);
            /* $paramsHead[] = new SoapVar($cono, XSD_DECIMAL, null, null, 'cono');
             $paramsHead[] = new SoapVar($apikey, XSD_STRING, null, null, 'APIKey');
             $paramsHead[] = new SoapVar($custno, XSD_STRING, null, null, 'custno');
             $paramsHead[] = new SoapVar($whse, XSD_STRING, null, null, 'whse');
             $paramsHead[] = new SoapVar($shipto, XSD_STRING, null, null, 'shipto');
             $paramsHead[] = new SoapVar($qty, XSD_STRING, null, null, 'qty');*/

            // $paramsDetail = new ArrayObject();
            // $paramsHead->append($productlist);

            $productParams = new \ArrayObject();
            $productParams[] = new \SoapVar(array('product' => $prod), SOAP_ENC_OBJECT);
            // $productParams[] = new SoapVar($prod, XSD_STRING, null, null, 'product');
            $thisparamLines = array('SalesCustomerPricingListLinesRequestContainer' => $productParams->getArrayCopy());
            $params->append(
                new SoapVar(
                    $thisparamLines,
                    SOAP_ENC_OBJECT,
                    null,
                    null,
                    'SalesCustomerPricingListProductRequestContainer'
                )
            );

            // $rootparams = new ArrayObject();
            // $rootparams->append(new SoapVar($params, SOAP_ENC_OBJECT, null, null, 'SalesCustomerPricingListRequestContainer'));

            $rootparams = (object) [];
            $rootparams->SalesCustomerPricingListRequestContainer = $params->getArrayCopy();

            $result = (object) [];
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result ////request/result
            $result = $client->SalesCustomerPricingListRequest($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }
    }


    public function SalesCustomerSelect($cono, $custno, $moduleName = "")
    {
        $apiname = "SalesCustomerSelect";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $operinit = $this->getConfigValue('operinit');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object) [];
        $params1->cono = $cono;
        $params1->operinit = $operinit;
        $params1->custno = $custno;
        $params1->APIKey = $apikey;
        $rootparams = (object) [];
        $rootparams->SalesCustomerSelectRequestContainer = $params1;
        $result = (object) [];

        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result //
            $result = $client->SalesCustomerSelectRequest($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>")); //request/result
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
        }
    }
    public function SalesOrderAddonsSelect($cono, $custno, $shipto, $orderno, $ordersuf, $moduleName = "")
    {
        $apiname = "SalesOrderAddonsSelect";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $operinit = $this->getConfigValue('operinit');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object) [];
        $params1->cono = $cono;
        $params1->operinit = $operinit;
        $params1->custno = $custno;
        $params1->shipto = $shipto;
        $params1->orderno = $orderno;
        $params1->ordersuf = $ordersuf;
        $params1->APIKey = $apikey;
        $rootparams = (object) [];
        $rootparams->SalesOrderAddonsSelectRequestContainer = $params1;
        $result = (object) [];

        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result //
            $result = $client->SalesOrderAddonsSelectRequest($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>")); //request/result
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
        }
    }

    public function SalesShipToList($cono, $custno, $moduleName = "")
    {
        $apiname = "SalesShipToList";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $operinit = $this->getConfigValue('operinit');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object) [];
        $params1->cono = $cono;
        $params1->operinit = $operinit;
        $params1->custno = $custno;
        $params1->APIKey = $apikey;
        $rootparams = (object) [];
        $rootparams->SalesShipToListRequestContainer = $params1;
        $result = (object) [];

        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result // //request/result
            $result = $client->SalesShipToListRequest($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
        }
    }
    public function ItemsWarehouseProductSelect($cono, $brsprod, $brswhse, $brsstatustype, $moduleName = "")
    {
        $apiname = "ItemsWarehouseProductSelect";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $operinit = $this->getConfigValue('operinit');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object) [];
        $params1->cono = $cono;

        $params1->operinit = $operinit;
        $params1->prod = $brsprod;
        $params1->whse = $brswhse;

        $params1->APIKey = $apikey;
        $rootparams = (object) [];
        $rootparams->ItemsWarehouseProductSelectRequestContainer = $params1;
        $result = (object) [];

        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result ////request/result
            $result = $client->ItemsWarehouseProductSelectRequest($rootparams);

            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }
    }
    public function dumpvar($var)
    {
        ob_start();
        var_dump($var);
        $result = ob_get_clean();
        return $result;
    }

    public function ItemsWarehouseProductList($cono, $brsprod, $brswhse = "", $moduleName = "")
    {
        //$brswhse = array("3000");
        //$this->gwLog($this->dumpvar($brswhse));
        //$this->gwLog($this->dumpvar($brsprod));

        //$this->gwLog(is_array($brswhse));


        $url = $this->urlInterface()->getCurrentUrl();
        if ($this->botDetector()) {
            return "";
        }
        if (strpos($url, 'cartquickpro/cart/delete') !== false) {
            return "";
        }
        $apiname = "ItemsWarehouseProductList";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        $paramsHead = new \ArrayObject();
        $thisparam = array(
            'cono' => $cono,
            'APIKey' => $apikey
        );


        $paramsHead[] = new SoapVar($operinit, XSD_DECIMAL, null, null, '$operinit');

        if ($brswhse != "") {
            $paramsHead[] = new SoapVar($brswhse, XSD_STRING, null, null, 'whse');
        }
        $paramsHead[] = new SoapVar($prod, XSD_DECIMAL, null, null, 'prod');


        $rootparams = (object) [];
        $rootparams->ItemsWarehouseProductListRequestContainer = $paramsHead->getArrayCopy();
        //$rootparams->append(new SoapVar($paramsHead, SOAP_ENC_OBJECT, null, null, 'ItemsWarehouseProductListRequestContainer'));

        $this->gwLog(json_encode($rootparams));
        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result // //request/result
            $result = $client->ItemsWarehouseProductList($rootparams);
            //$this->jwLog("REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            //$this->gwLog(json_decode(json_encode($result), true));
            return json_decode(json_encode($result), true);
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }
    }

    public function SalesCustomerQuantityPricingList($cono, $whse, $csdcustno, $product, $moduleName = "")
    {//status=bad, save for next version_compare
        return "";
        if ($this->botDetector()) {
            return "";
        }
        $apiname = "SalesCustomerQuantityPricingList";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $operinit = $this->getConfigValue('operinit');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object) [];
        $params1->cono = $cono;
        $params1->operinit = $operinit;

        $params1->prod = $product->getSku();
        $params1->whse = $whse;
        $params1->custno = $csdcustno;
        $params1->APIKey = $apikey;
        $rootparams = (object) [];
        $rootparams->SalesCustomerQuantityPricingListRequestContainer = $params1;
        $soapResult = (object) [];

        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result // //request/result
            $soapResult = $client->SalesCustomerQuantityPricingList($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            $response = json_decode(json_encode($soapResult), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }
    }

    public function SalesShipToInsertUpdate($csdcustno, $shipto, $addressData, $email, $moduleName = "")
    {
        $apiname = "SalesShipToInsertUpdate";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        $configs = $this->getConfigValue(['cono', 'csdcustomerid', 'whse', 'slsrepin', 'defaultterms', 'shipviaty', 'slsrepout']);
        extract($configs);


        $name = $addressData["firstname"] . ' ' . $addressData["lastname"];
        $addr1 = $addressData["street"];
        $addr2 = '';
        $state = $addressData["state"];
        $city = $addressData["city"];
        $zipcd = $addressData["postcode"];
        $countrycd = $addressData["country_id"];
        $phoneno = $addressData["telephone"];
        $paramsShipTo = (object) [];
        $paramsShipTo->cono = $cono;
        $paramsShipTo->custno = $csdcustno;
        $paramsShipTo->operinit = $this->getConfigValue('operinit');
        $paramsShipTo->shipto = $shipto;
        $paramsShipTo->name = $name;
        $paramsShipTo->addr1 = $addr1;
        $paramsShipTo->addr2 = $addr2;
        $paramsShipTo->city = $city;
        $paramsShipTo->state = $state;
        $paramsShipTo->zipcd = $zipcd;
        $paramsShipTo->countrycd = $countrycd;
        $paramsShipTo->phoneno = $phoneno;
        $paramsShipTo->shipviaty = $shipviaty;
        $paramsShipTo->slsrepin = $slsrepin;
        $paramsShipTo->slsrepout = $slsrepout;
        $paramsShipTo->whse = $whse;
        $paramsShipTo->pricetype = "";
        $paramsShipTo->poreqfl = "";
        $paramsShipTo->bofl = "";
        $paramsShipTo->subfl = "";
        $paramsShipTo->taxablety = "";
        $paramsShipTo->taxcert = "";
        $paramsShipTo->nontaxtype = "";
        $paramsShipTo->statecd = "";
        $paramsShipTo->taxauth = "";
        $paramsShipTo->salesterr = "";
        $paramsShipTo->dunsno = "";
        $paramsShipTo->custpo = "";
        $paramsShipTo->termstype = $defaultterms;
        $paramsShipTo->user1 = "";
        $paramsShipTo->user2 = "";
        $paramsShipTo->user3 = "";
        $paramsShipTo->user4 = "";
        $paramsShipTo->user5 = "";
        $paramsShipTo->user6 = 0;
        $paramsShipTo->user7 = 0;
        $paramsShipTo->user8 = "";
        $paramsShipTo->user9 = "";
        $paramsShipTo->email = $email;
        $paramsShipTo->pricecd = "";
        $paramsShipTo->transproc = "";
        $paramsShipTo->addon1 = 0;
        $paramsShipTo->addon2 = 0;
        $paramsShipTo->addon3 = 0;
        $paramsShipTo->addon4 = 0;
        $paramsShipTo->addon5 = 0;
        $paramsShipTo->addon6 = 0;
        $paramsShipTo->addon7 = 0;
        $paramsShipTo->addon8 = 0;
        $paramsShipTo->inbndfrtfl = "";
        $paramsShipTo->outbndfrtfl = "";
        $paramsShipTo->statustype = "";
        $paramsShipTo->shipinstr = "";
        $paramsShipTo->pocontctnm = "";
        $paramsShipTo->pophoneno = "";
        $paramsShipTo->addr3 = "";
        $paramsShipTo->jobdesc = "";
        $paramsShipTo->transdt = "";
        $paramsShipTo->APIKey = $apikey;
        $rootparams = (object) [];
        $rootparams->SalesShipToInsertUpdateRequestContainer = $paramsShipTo;

        $soapResult = (object) [];
        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result ////request/result
            $soapResult = $client->SalesShipToInsertUpdate($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            $response = json_decode(json_encode($soapResult), true);
            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }
    }







    public function SalesCustomerInsert(
        $cono,
        $custno,
        $statecd,
        $name,
        $addr1,
        $addr2,
        $city,
        $state,
        $zipcd,
        $phoneno,
        $faxphoneno,
        $siccd,
        $termstype,
        $custtype,
        $salester,
        $bofl,
        $subfl,
        $minord,
        $maxord,
        $taxcert,
        $shipviaty,
        $whse,
        $slsrepin,
        $slsrepout,
        $shipreqfl,
        $taxauth,
        $taxablety,
        $nontaxtype,
        $creditmgr,
        $dunsno,
        $user1,
        $user2,
        $user3,
        $user4,
        $user5,
        $user6,
        $user7,
        $user8,
        $user9,
        $countrycd,
        $countycd,
        $email,
        $pricetype,
        $pricecd,
        $transproc,
        $addon1,
        $addon2,
        $addon3,
        $addon4,
        $addon5,
        $addon6,
        $addon7,
        $addon8,
        $inbndfrtfl,
        $outbndfrtfl,
        $custpo,
        $addr3,
        $moduleName = ""
    ) {
        $apiname = "SalesCustomerInsert";
        $this->LogAPICall($apiname, $moduleName);

        try {
            $apikey = $this->getConfigValue('apikey');
            $operinit = $this->getConfigValue('operinit');
            $client = $this->createSoapClient($apikey, $apiname);

            $params1 = (object) [];
            $params1->cono = $cono;
            $params1->operinit = $operinit;
            $params1->custno = $custno;
            $params1->statecd = $statecd;
            $params1->name = $name;
            $params1->addr1 = $addr1;
            $params1->addr2 = $addr2;
            $params1->city = $city;
            $params1->state = $state;
            $params1->zipcd = $zipcd;
            $params1->phoneno = $phoneno;
            $params1->faxphoneno = $faxphoneno;
            $params1->siccd = $siccd;
            $params1->termstype = $termstype;
            $params1->custtype = $custtype;
            $params1->salester = $salester;
            $params1->bofl = $bofl;
            $params1->subfl = $subfl;
            $params1->minord = $minord;
            $params1->maxord = $maxord;
            $params1->taxcert = $taxcert;
            $params1->shipviaty = $shipviaty;
            $params1->whse = $whse;
            $params1->slsrepin = $slsrepin;
            $params1->slsrepout = $slsrepout;
            $params1->shipreqfl = $shipreqfl;
            $params1->taxauth = $taxauth;
            $params1->taxablety = $taxablety;
            $params1->nontaxtype = $nontaxtype;
            $params1->creditmgr = $creditmgr;
            $params1->dunsno = $dunsno;
            $params1->user1 = $user1;
            $params1->user2 = $user2;
            $params1->user3 = $user3;
            $params1->user4 = $user4;
            $params1->user5 = $user5;
            $params1->user6 = $user6;
            $params1->user7 = $user7;
            $params1->user8 = $user8;
            $params1->user9 = $user9;
            $params1->countrycd = $countrycd;
            $params1->countycd = $countycd;
            $params1->email = $email;
            $params1->pricetype = $pricetype;
            $params1->pricecd = $pricecd;
            $params1->transproc = $transproc;
            $params1->addon1 = $addon1;
            $params1->addon2 = $addon2;
            $params1->addon3 = $addon3;
            $params1->addon4 = $addon4;
            $params1->addon5 = $addon5;
            $params1->addon6 = $addon6;
            $params1->addon7 = $addon7;
            $params1->addon8 = $addon8;
            $params1->inbndfrtfl = $inbndfrtfl;
            $params1->outbndfrtfl = $outbndfrtfl;
            $params1->custpo = $custpo;
            $params1->addr3 = $addr3;

            $params1->APIKey = $apikey;
            $rootparams = (object) [];
            $rootparams->SalesCustomerInsertRequestContainer = $params1;
            $result = (object) [];
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result ////request/result
            $result = $client->SalesCustomerInsertRequest($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }
    }


    public function SalesOrderList($cono, $custno, $orderno, $ordersuf, $transtype, $whse, $custpo, $stagecd, $shipto, $floorplancustfl, $bmoddate, $emoddate, $moduleName = "")
    {
        $apiname = "SalesOrderList";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $operinit = $this->getConfigValue('operinit');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object) [];
        $params1->cono = $cono;
        $params1->operinit = $operinit;
        $params1->orderno = $orderno;
        $params1->ordersuf = $ordersuf;
        $params1->custno = $custno;
        $params1->transtype = $transtype;
        $params1->whse = $whse;
        $params1->custpo = $custpo;
        $params1->stagecd = $stagecd;
        $params1->shipto = $shipto;
        $params1->floorplancustfl = $floorplancustfl;
        $params1->brsbmoddt = $bmoddate;
        $params1->brsemoddt = $emoddate;
        $params1->APIKey = $apikey;
        $rootparams = (object) [];
        $rootparams->SalesOrderListRequestContainer = $params1;
        $result = (object) [];

        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result // //request/result
            $result = $client->SalesOrderListRequest($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
        }
    }

    public function SalesOrderSelect($cono, $orderno, $ordersuf, $moduleName = "")
    {
        // $this->gwLog('Calling SalesOrderSelect');
        $apiname = "SalesOrderSelect";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $operinit = $this->getConfigValue('operinit');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object) [];
        $params1->cono = $cono;
        $params1->operinit = $operinit;

        $params1->orderno = $orderno;
        $params1->ordersuf = $ordersuf;
        $params1->APIKey = $apikey;
        $rootparams = (object) [];
        $rootparams->SalesOrderSelectRequestContainer = $params1;
        $result = (object) [];
        try {
            $result = $client->SalesOrderSelectRequest($rootparams);
            $response = json_decode(json_encode($result), true);
            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }
    }

    public function SalesPackagesSelect($cono, $orderno, $ordersuf, $moduleName = "")
    {
        $apiname = "SalesPackagesSelect";
        //$this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $operinit = $this->getConfigValue('operinit');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object) [];
        $params1->cono = $cono;
        $params1->operinit = $operinit;

        $params1->orderno = $orderno;
        $params1->ordersuf = $ordersuf;
        $params1->APIKey = $apikey;
        $rootparams = (object) [];
        $rootparams->SalesPackagesSelectRequestContainer = $params1;
        $result = (object) [];

        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result // //request/result
            $result = $client->SalesPackagesSelectRequest($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }
    }

    public function SalesOrderLinesSelect($cono, $orderno, $ordersuf, $moduleName = "")
    {
        $apiname = "SalesOrderLinesSelect";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $operinit = $this->getConfigValue('operinit');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object) [];
        $params1->cono = $cono;

        $params1->operinit = $operinit;
        $params1->orderno = $orderno;
        $params1->ordersuf = $ordersuf;

        $params1->APIKey = $apikey;
        $rootparams = (object) [];
        $rootparams->SalesOrderLinesSelectRequestContainer = $params1;
        $result = (object) [];

        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result ////request/result
            $result = $client->SalesOrderLinesSelectRequest($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }
    }

    public function SalesShipToSelect($cono, $custno, $shipto, $moduleName = "")
    {
        $apiname = "SalesShipToSelect";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $operinit = $this->getConfigValue('operinit');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object) [];
        $params1->cono = $cono;
        $params1->operinit = $operinit;

        $params1->custno = $custno;
        $params1->shipto = $shipto;
        $params1->APIKey = $apikey;
        $rootparams = (object) [];
        $rootparams->SalesShipToSelectRequestContainer = $params1;
        $result = (object) [];

        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result ////request/result
            $result = $client->SalesShipToSelectRequest($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }
    }

    public function SalesCustomerInvoiceList($cono, $custno, $moduleName = "")
    {
        $apiname = "SalesCustomerInvoiceList";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $operinit = $this->getConfigValue('operinit');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object) [];
        $params1->cono = $cono;
        $params1->operinit = $operinit;

        $params1->custno = $custno;
        $params1->APIKey = $apikey;
        $rootparams = (object) [];
        $rootparams->SalesCustomerInvoiceListRequestContainer = $params1;
        $result = (object) [];

        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result ////request/result
            $result = $client->SalesCustomerInvoiceListRequest($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }
    }

    public function ItemsWarehouseList($cono, $whID, $moduleName = "")
    {  //status=bad, save for next version_compare
        return "";
        $apiname = "ItemsWarehouseList";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $operinit = $this->getConfigValue('operinit');
        $client = $this->createSoapClient($apikey, $apiname);

        try {
            $params = (object) [];
            $params->cono = $cono;
            $params1->operinit = $operinit;

            $params->brswhse = $whID;
            $params->APIKey = $apikey;

            $rootparams = (object) [];
            $rootparams->ItemsWarehouseListRequestContainer = $params;
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result ////request/result
            $result = $client->ItemsWarehouseListRequest($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));

            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }
    }

    public function ItemsProductSelect($cono, $brsprod, $brsprodcat, $brskittype, $brsstatustype, $moduleName = "")
    {
        $apiname = "ItemsProductSelect";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $operinit = $this->getConfigValue('operinit');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object) [];
        $params1->cono = $cono;

        $params1->operinit = $operinit;
        $params1->prod = $brsprod;

        $params1->APIKey = $apikey;

        $rootparams = (object) [];
        $rootparams->ItemsProductSelectRequestContainer = $params1;

        $result = (object) [];

        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result // //request/result
            $result = $client->ItemsProductSelectRequest($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "");
        }
    }

    public function SalesShipToInsertUpdate2($params, $moduleName = "")
    {
        $apiname = "SalesShipToInsertUpdate";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        $params->APIKey = $apikey;
        $rootparams = (object) [];
        $rootparams->SalesShipToInsertUpdateRequestContainer = $params;

        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result ////request/result
            $result = $client->SalesShipToInsertUpdate($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));

            return json_decode(json_encode($result), true);

            exit;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "\n");
        }
    }





    public function SalesOrderInsert($header, $moduleName = "")
    {
        $apiname = "SalesOrderInsert";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        //$header->APIKey = $apikey;
        $client = $this->createSoapClient($apikey, $apiname);

        //$rootparams = new ArrayObject();
        //$rootparams->append(new SoapVar($header, SOAP_ENC_OBJECT, null, null, 'SalesOrderInsertRequestContainer'));
        $rootparams = (object) [];
        $rootparams->SalesOrderInsertRequestContainer = $header->getArrayCopy();
        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); ////request/result

            $result = $client->SalesOrderInsertUpdate($rootparams);

            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            return json_decode(json_encode($result), true);
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()));
        }
    }

    public function UpdateOrderWithCCAuthNo($order, $CCAuthNo, $moduleName = "")
    {
        $orderid = $order->getIncrementId();
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Updating order table with Auth #" . $CCAuthNo);

        try {
            $sql = "INSERT INTO `gws_GreyWolfOrderFieldUpdate` (`orderid`, `dateentered`, `CCAuthNo`) VALUES ('$orderid', now(), '" . $CCAuthNo . "')";

            if ($this->resourceConnection->getConnection()->query($sql) === true) {
                // echo "New record created successfully";
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order field  updated successfully for order " . $orderid);
            }
        } catch (\Exception $e) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Catch Exception: " . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to open update order table");
        }
    }

    public function UpdateOrderWithRapidConnectInfo($order, $moduleName = "")
    {
        $orderid = $order->getIncrementId();
        $CCSaveFields = $_SESSION["CCSaveFields"];
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Updating order table with Rapid Connect info ");

        try {
            $sql = "INSERT INTO `gws_GreyWolfOrderFieldUpdate` (`orderid`,`dateentered`,`TransactionID`,`STAN`,`LocalDateTime`,`TXNDateTime`,`CCNo`,`CCExp`,`CCCCV`,`AuthID`,`TxnAmt`,`RefNum`,`CCAuthNo`,`ClientRef`,`CardType`,`ResponseCode`,`CardLevelResult`,`ACI`,`TXNResponse`)
    			VALUES ('$orderid',now(),'" . $CCSaveFields["TransactionID"] . "','" . $CCSaveFields["STAN"] . "','" . $CCSaveFields["LocalDateTime"] . "','" . $CCSaveFields["TXNDateTime"] . "','" . $CCSaveFields["CCNo"] . "', '" . $CCSaveFields["CCExp"] . "','" . $CCSaveFields["CCCCV"] . "','" . $CCSaveFields["AuthID"] . "','" . $CCSaveFields["TxnAmt"] . "','" . $CCSaveFields["RefNum"] . "','" . $CCSaveFields["AuthID"] . "','" . $CCSaveFields["ClientRef"] . "','" . $CCSaveFields["CardType"] . "','" . $CCSaveFields["ResponseCode"] . "','" . $CCSaveFields["CardLevelResult"] . "','" . $CCSaveFields["ACI"] . "','" . $CCSaveFields["TXNResponse"] . "')";

            if ($this->resourceConnection->getConnection()->query($sql) === true) {
                // echo "New record created successfully";
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order field  updated successfully for order " . $orderid);
                unset($_SESSION["CCSaveFields"]);
            }
        } catch (\Exception $e) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to open update order table");
        }
    }

    public function SendToGreyWolf($invoice, $moduleName = "")
    {
        $sendtoerpinv = $this->getConfigValue('sendtoerpinv');
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Initiating order process...");
        try {
            if ($sendtoerpinv == 1) {
                if ($invoice->getUpdatedAt() == $invoice->getCreatedAt()) {
                    $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Creating ERP order");
                    \Magento\Framework\Profiler::start("Altitude-SubmitOrder-i-SendToGreyWolf");
                    if ($this->SubmitOrder($invoice, $moduleName) == true) {
                        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order Created");
                    } else {
                        //populate missing data table
                        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Queueing  order");
                        $this->InsertOrderQueue($invoice);
                    }
                    \Magento\Framework\Profiler::stop("Altitude-SubmitOrder-i-SendToGreyWolf");
                } else {
                    $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order/Invoice dates do not match");
                }
            } elseif ($sendtoerpinv == 0) {
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Creating ERP order");
                \Magento\Framework\Profiler::start("Altitude-SubmitOrder-o-SendToGreyWolf");
                if ($this->SubmitOrder($invoice, $moduleName) == true) {
                    $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order Created");
                } else {
                    //populate missing data table
                    $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Queueing  order");
                    $this->InsertOrderQueue($invoice);
                }
                \Magento\Framework\Profiler::stop("Altitude-SubmitOrder-o-SendToGreyWolf");
            }
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            //$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "REQUEST:\n" . htmlentities($client->__getLastRequest()) . "\n");
        }

        return true;
    }

    public function SubmitOrder($invoice, $moduleName = "", $forceQuote = false)
    {
        $configs = $this->getConfigValue([
            'csdcustomerid',
            'cono',
            'whse',
            'slsrepin',
            'defaultterms',
            'operinit',
            'transtype',
            'shipviaty',
            'flatshipvia',
            'slsrepout',
            'holdifover',
            'shipto2erp',
            'potermscode',
            'orderaspo',
            'sendtoerpinv',
            'apikey',
            'alloweditaddress',
            'takenby',
            'alertemail',
            'taxtakenby'
        ]);
        extract($configs);
        if ($forceQuote) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "temp order for tax");
            $sendtoerpinv = 0;
        }
        if ($sendtoerpinv == "1") {
            $order = $invoice->getOrder();
        } else {
            $order = $invoice;
        }

        $orderincid = $order->getIncrementId();

        $shipto2erp = $alloweditaddress;

        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Processing/submit order " . $orderincid . "" . " Increment ID=" . $orderincid);
        $url = $this->urlInterface()->getCurrentUrl();

        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "url for cart " . $url . "" . " Increment ID=" . $orderincid);
        if ($forceQuote) { //cartquickpro/sidebar/
            if (
                strpos($url, 'cartquickpro/wishlist_index') !== false ||
                strpos($url, 'customer/account') !== false ||
                strpos($url, 'cartquickpro/sidebar') !== false ||
                strpos($url, 'cartquickpro/cart/add') !== false ||
                strpos($url, 'cartquickpro/cart/updateItemOption') !== false ||
                strpos($url, 'checkout/cart/updateItemQty') !== false ||
                strpos($url, 'checkout/cart/updatePost') !== false ||
                strpos($url, 'checkout/cart') !== false ||
                strpos($url, 'wishlist/index') !== false ||
                strpos($url, 'csdorders/customer') !== false ||
                strpos($url, 'carts/mine/estimate-shipping-methodcsd') !== false ||
                strpos($url, 'xxxcarts/mine/payment-information') !== false
            ) { //carts/mine/payment-information
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "skipping " . $url . "");
                return 0;
            }
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Quote instead of PreInsert" . " Increment ID=" . $orderincid);
            $orderid = $order->getId();
        } else {
            $orderid = $order->getId();
            $payment = $order->getPayment();
        }
        try {
            if (isset ($payment)) {
                $poNumber = $payment->getPoNumber();
            } else {
                $poNumber = "";
            }
        } catch (\Exception $ePO) {
            $poNumber = "";
        }
        if (isset ($payment)) {
            $method = $payment->getMethodInstance();
            $methodTitle = $method->getTitle();
            $methodcode = $payment->getMethod();
        } else {
            $method = "";
            $methodTitle = "";
            $methodcode = $defaultterms;
        }
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "methodcode =" . $methodcode);
        $shipping_address = $order->getShippingAddress()->getData();

        $erpAddress = "";
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        try {
            $addressId = $shipping_address["customer_address_id"];
            $addressOBJ = $objectManager->get('\Magento\Customer\Api\AddressRepositoryInterface');
            $addressObject = $addressOBJ->getById($addressId);
            try {
                if (isset ($addressObject)) {
                    $erpAddress = $addressObject->getCustomAttribute("ERPAddressID")->getValue(); //->GetValue() ;
                }
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $erpAddress = "";
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "No erp address2 - " . json_encode($e->getMessage()) . " Increment ID=" . $orderincid);
            } catch (\Exception $e1) {
                $erpAddress = "";
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "No erp address2 - " . json_encode($e->getMessage()) . " Increment ID=" . $orderincid);
            } catch (\Throwable $e1) { // For PHP 7
                $erpAddress = "";
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "No erp address2 - " . json_encode($e1->getMessage()) . " Increment ID=" . $orderincid);
            } catch (Exception $e1) {
                $erpAddress = "";
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "No erp address2 - " . json_encode($e1->getMessage()) . " Increment ID=" . $orderincid);
            }
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Using shipto: " . $erpAddress . " .... " . " Increment ID=" . $orderincid);
        } catch (\Exception $e) {
            $erpAddress = "";
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "No erp address - " . json_encode($e->getMessage()) . " Increment ID=" . $orderincid);
        }
        //  $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Checking shipto again");
        if ($erpAddress == "") {
            try {
                if (isset ($addressObject)) {
                    //   $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Checking shipto again3");
                    if (null !== $addressObject->getCustomAttribute("ERPAddressID")) {
                        //   $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Checking shipto again4");
                        $erpAddress = $addressObject->getCustomAttribute("ERPAddressID")->getValue(); //->GetValue() ;
                        //   $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Checking shipto again5");
                    }
                }
            } catch (\Exception $e1) {
                //   $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Checking shipto again4");
                $erpAddress = "";
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "No erp address21 - " . json_encode($e1->getMessage()) . " Increment ID=" . $orderincid);
            }
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Using shipto:: " . $erpAddress . " Increment ID=" . $orderincid);
        }
        //$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "done checking shipto ");
        $billing_address = $order->getBillingAddress()->getData();
        if ($sendtoerpinv == "1" || 1 == 1) {
            $items = $invoice->getAllItems();
        } else {
            $items = $order->getAllVisibleItems();
        }
        $total = $invoice->getGrandTotal();

        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();

        $custno = $csdcustomerid;

        $customerSession2 = $objectManager->get('Magento\Customer\Model\Session');
        $customerData = $customerSession2->getCustomer();
        $customerID = $customerData->getId();
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "customerID =" . $customerID . " Increment ID=" . $orderincid);
        $shipvia = "";

        if ($order->getCustomerIsGuest()) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "customer is guest" . " Increment ID=" . $orderincid);
            $custno = $csdcustomerid;
            if ($forceQuote) {
                return "";
            }
        } else {
            $customer = $objectManager->create('Magento\Customer\Model\Customer')->load($order->getCustomerId());
            $custno = $customer->getData('csd_custno');
            $custTaxable = $customer->getData('taxabletype');
            $warehouse = $customer->getData('whse');
            $shipvia = $customer->getData('erpshipvia');

            if (!empty ($warehouse)) {
                $whse = $warehouse;
            }

            if ($custno) {
                //	$custno=$customerData['csd_custno']
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "set csd custno" . " Increment ID=" . $orderincid);
            } else {
                // Not Logged In
                $custno = $csdcustomerid;
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "csd custno is default" . " Increment ID=" . $orderincid);
            }
        }

        if ($forceQuote && !isset ($shipping_address["region_id"]) && !$order->getCustomerIsGuest()) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "address not set, using default" . " Increment ID=" . $orderincid);
            $shipping_address = $customer->getDefaultShipping();
        }
        $tableName = 'directory_country_region';
        try {
            if (isset ($shipping_address["region_id"])) {
                $region = $objectManager->create('Magento\Directory\Model\Region')->load($shipping_address["region_id"]); // Region Id

                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "region id:: " . $shipping_address["region_id"] . " Increment ID=" . $orderincid);

                $statecd = $region->getData()['code'];
            } else {
                $statecd = "";
            }
        } catch (\Exception $e1) {
            $statecd = "";
        }
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "state code:: " . $statecd . " Increment ID=" . $orderincid);
        //******************************************
        $batchnm = substr(date("YmdHi") . rand(), -8);  //set for unique bath name and ship to name


        // $custno = $csdcustomerid;
        if (empty ($shipvia)) {
            $shipvia = $shipviaty;
        }

        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "CSD CustNo for order== " . $custno . " Increment ID=" . $orderincid);
        if ($custno == $csdcustomerid) {

            $erpAddress = "";
        }
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "erpAddress= " . $erpAddress . " Increment ID=" . $orderincid);

        if ($erpAddress == "") {
            $shipto = $batchnm;
        } else {
            $shipto = $erpAddress;
        }
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "shipto= " . $shipto . " Increment ID=" . $orderincid);
        if (!empty ($erpAddress)) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "chekcing shipto" . " Increment ID=" . $orderincid);
            $whse = $this->getWhseFromShipTo($whse, $cono, $custno, $erpAddress);
        }
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "1whse= " . $whse . " Increment ID=" . $orderincid);
        if (!$forceQuote) {
            if (empty ($whse) && empty ($erpAddress)) {
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "fetching wshe" . " Increment ID=" . $orderincid);
                $whse = $this->getWhse($whse, $statecd);
            }
        }
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "2whse= " . $whse . " Increment ID=" . $orderincid);

        $TermsToUse = $defaultterms;
        if ($methodcode == "purchaseorder" && isset ($potermscode)) {
            $TermsToUse = $potermscode;
        } elseif ($methodcode == "cashondelivery") {
            $TermsToUse = "cod";
        } elseif ((stripos($methodcode, "credit") !== false) || (stripos($methodcode, "authnetcimX") !== false)) { //authnetcim
            $TermsToUse = "cc";
        }

        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "method=" . $methodcode . " Increment ID=" . $orderincid);
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "TermsToUse=" . $TermsToUse . " Increment ID=" . $orderincid);
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "shipto== " . $shipto . " Increment ID=" . $orderincid);

        //change shipviaty ****************************************************************
        $shippingMethod = $order->getShippingMethod();
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "shipmeth in: " . $shippingMethod . " Increment ID=" . $orderincid);
        if (empty ($flatshipvia)) {
            $flatshipvia = "";
        }
        if (isset ($shippingMethod) && strpos($shippingMethod, "_") !== false) {
            $shippingMethod = substr($shippingMethod, 0, stripos($shippingMethod, '_'));
            //$shippingMethod = $shipviaty;
        }

        if (isset ($shippingMethod) && (strpos($shippingMethod, "customershipping") !== false || strpos($shippingMethod, "flatxxx") !== false)) {
            $shippingMethod = $shipvia;
            $shippingMethod = $shipviaty;
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "shipmeth out3: " . $shippingMethod . " Increment ID=" . $orderincid);
        }
        if (isset ($shippingMethod) && strlen($shippingMethod) > 4) {
            $shippingMethod = substr($shippingMethod, 0, 4);
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "shipmeth out4: " . $shippingMethod . " Increment ID=" . $orderincid);
        }

        if (isset ($shippingMethod) && strpos($shippingMethod, "flat") !== false) {
            if (empty ($flatshipvia)) {
                $shippingMethod = $shipviaty;
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "shipmeth out1: " . $shippingMethod . " flatcode=" . $flatshipvia . " Increment ID=" . $orderincid);
            } else {
                $shippingMethod = $flatshipvia;
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "shipmeth out2: " . $shippingMethod . " Increment ID=" . $orderincid);
            }
        } elseif (isset ($shippingMethod) && strpos($shippingMethod, "free") !== false) {
            $shippingMethod = $shipviaty;
        }
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "shipmeth out: " . $shippingMethod . " --- " . $url . " Increment ID=" . $orderincid);
        $shipviaty = $shippingMethod;
        try {
            if (isset ($shipping_address["firstname"]) && isset ($shipping_address["lastname"])) {
                $name = $shipping_address["firstname"] . ' ' . $shipping_address["lastname"];
            } else {
                $name = "";
            }
        } catch (\Exception $e1) {
            //
            $name = "";
        }
        if ($forceQuote) {

        }
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "url for street " . $url . " - " . $forceQuote . " Increment ID=" . $orderincid);
        try {
            if (isset ($shipping_address["street"])) {

                $addr1 = $shipping_address["street"];
            } else {
                $addr1 = "";
            }
        } catch (\Exception $e1) {
            return 0;
            //this appears to always be caused by the preinsert pages
        }
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "url after street " . $url . "" . " Increment ID=" . $orderincid);
        $addr2 = '';
        $email = '';
        $city = '';
        $zipcd = '';
        $countrycd = '';
        $phoneno = '';
        $faxphoneno = '';
        if (isset ($shipping_address["city"]))
            $city = $shipping_address["city"];
        $state = $statecd;
        if (isset ($shipping_address["postcode"]))
            $zipcd = $shipping_address["postcode"];
        if (isset ($shipping_address["country_id"]))
            $countrycd = $shipping_address["country_id"];
        if (isset ($shipping_address["telephone"]))
            $phoneno = $shipping_address["telephone"];
        if (isset ($shipping_address["fax"]))
            $faxphoneno = $shipping_address["fax"];  //$shipping_address[""];
        $pricetype = '';
        $poreqfl = '';
        $bofl = '';
        $subfl = '';
        $taxablety = '';
        $taxcert = '';
        $nontaxtype = '';
        $taxauth = '';
        $salesterr = '';
        $dunsno = '';
        $custpo = '';
        $termstype = $TermsToUse;
        $user1 = '';
        $user2 = '';
        $user3 = '';
        $user4 = '';
        $user5 = '';
        $user6 = 0;
        $user7 = 0;
        $user8 = '';
        $user9 = '';
        if (isset ($shipping_address["email"]))
            $email = $shipping_address["email"];
        $pricecd = '';
        $transproc = '';
        $addon1 = 0;
        $addon2 = 0;
        $addon3 = 0;
        $addon4 = 0;
        $addon5 = 0;
        $addon6 = 0;
        $addon7 = 0;
        $addon8 = 0;
        $inbndfrtfl = '';
        $outbndfrtfl = '';
        $statustype = '';
        $shipinstr = '';
        $pocontctnm = '';
        $pophoneno = '';
        $addr3 = '';
        $jobdesc = '';
        $transdt = '';

        $paramsShipTo = (object) [];
        $paramsShipTo->cono = $cono;

        $paramsShipTo->custno = $custno;
        $paramsShipTo->shipto = $shipto;
        $paramsShipTo->name = $name;
        $paramsShipTo->addr1 = $addr1;
        $paramsShipTo->addr2 = $addr2;
        $paramsShipTo->city = $city;
        $paramsShipTo->state = $state;
        $paramsShipTo->zipcd = $zipcd;
        $paramsShipTo->countrycd = $countrycd;
        $paramsShipTo->phoneno = $phoneno;
        $paramsShipTo->faxphoneno = $faxphoneno;
        $paramsShipTo->shipviaty = $shipviaty;
        $paramsShipTo->slsrepin = $slsrepin;
        $paramsShipTo->slsrepout = $slsrepout;
        $paramsShipTo->whse = $whse;
        $paramsShipTo->pricetype = $pricetype;
        $paramsShipTo->poreqfl = $poreqfl;
        $paramsShipTo->bofl = $bofl;
        $paramsShipTo->subfl = $subfl;
        $paramsShipTo->taxablety = $taxablety;
        $paramsShipTo->taxcert = $taxcert;
        $paramsShipTo->nontaxtype = $nontaxtype;
        $paramsShipTo->statecd = $statecd;
        $paramsShipTo->taxauth = $taxauth;
        $paramsShipTo->salesterr = $salesterr;
        $paramsShipTo->dunsno = $dunsno;
        $paramsShipTo->custpo = $custpo;
        $paramsShipTo->termstype = $TermsToUse;
        $paramsShipTo->user1 = $user1;
        $paramsShipTo->user2 = $user2;
        $paramsShipTo->user3 = $user3;
        $paramsShipTo->user4 = $user4;
        $paramsShipTo->user5 = $user5;
        $paramsShipTo->user6 = $user6;
        $paramsShipTo->user7 = $user7;
        $paramsShipTo->user8 = $user8;
        $paramsShipTo->user9 = $user9;
        $paramsShipTo->email = $email;
        $paramsShipTo->pricecd = $pricecd;
        $paramsShipTo->transproc = $transproc;
        $paramsShipTo->addon1 = $addon1;
        $paramsShipTo->addon2 = $addon2;
        $paramsShipTo->addon3 = $addon3;
        $paramsShipTo->addon4 = $addon4;
        $paramsShipTo->addon5 = $addon5;
        $paramsShipTo->addon6 = $addon6;
        $paramsShipTo->addon7 = $addon7;
        $paramsShipTo->addon8 = $addon8;
        $paramsShipTo->inbndfrtfl = $inbndfrtfl;
        $paramsShipTo->outbndfrtfl = $outbndfrtfl;
        $paramsShipTo->statustype = $statustype;
        $paramsShipTo->shipinstr = $shipinstr;
        $paramsShipTo->pocontctnm = $pocontctnm;
        $paramsShipTo->pophoneno = $pophoneno;
        $paramsShipTo->addr3 = $addr3;
        $paramsShipTo->jobdesc = $jobdesc;
        $paramsShipTo->transdt = $transdt;

        $paramsShipTo->operinit = $operinit;


        //end salesshiptoinsert test

        //salesorderinsert

        $placedby = $slsrepin;
        if (!isset ($takenby)) {
            $takenby = $slsrepin;
        }
        $sourcepros = '';

        //$poNumber
        if ($orderaspo == 1) {
            $custpo = $orderincid;//'Web Order #'
        } else {
            $custpo = $poNumber;//'Web Order #'
        }
        if (empty ($custpo)) {
            $custpo = $orderincid;
        }
        $refer = $orderincid;
        $reqshipdt = '';
        $shipinstr = '';

        $shiptonm = $name;
        $shiptoaddr1 = $addr1;
        $shiptoaddr2 = $addr2 . ' .';
        $shiptocity = $city;
        $shiptost = $statecd;
        $shiptozip = $zipcd;
        $enterdt = date("m/d/Y");
        $transdt = date("m/d/Y");
        $bofl = '';
        $subfl = '';
        $terms = $TermsToUse;
        $printprice = 'y';
        $wodiscamt = 0;
        $wodiscoverfl = '';
        $wodiscpct = 0;
        $wodisctype = '';
        $orderdisp = '';
        $createsalesorderlinesyesno = 'yes';
        $inbndfrtfl = '';
        $outbndfrtfl = '';
        $user1 = '';
        $user2 = '';
        $user3 = '';
        $user4 = '';
        $user5 = '';
        $user6 = 0;
        $user7 = 0;
        $user8 = '';
        $user9 = '';
        $user10 = '';
        $user11 = '';
        $user12 = '';
        $user13 = '';
        $user14 = '';
        $user15 = '';
        $user16 = '';
        $user17 = '';
        $user18 = '';
        $user19 = '';
        $user20 = '';
        $user21 = '';
        $user22 = '';
        $user23 = '';
        $user24 = '';
        $borelfl = '';
        $removebatchnameyesno = '';
        $pmflyesno = '';
        $SalesOrderInsert = '';
        $taxauthcountryCAUSorAU = '';
        $taxauthstate = '';
        $taxauthcounty = '';
        $taxauthcity = '';
        $taxauthother1 = '';
        $taxauthother2 = '';
        $taxable = '';
        $route = '';
        if ($holdifover != "") {
            if ($total > $holdifover) {
                $user24 = 'h';
            }
        }

        $lineno = '1';
        $price = 0;
        $descrip1 = '';
        $descrip2 = '';
        $qtyord = 1;
        $shipprod = '';
        $reqprod = '';
        $enterdt = '';
        $unit = 'ea';
        $lncomm = '';
        $cprintfl = '';
        $lndiscamt = '';
        $lndisctype = '';
        $SalesOrderLinesInsert = '';
        $rushfl = '';
        $taxablefl = '';
        $prodcostSRLA = '';
        $directshipyesno = '';
        $lineuser1 = '';
        $lineuser2 = '';
        $lineuser3 = '';
        $lineuser4 = '';
        $lineuser5 = '';
        $lineuser6 = 0;
        $lineuser7 = 0;
        $lineuser8 = '';
        $lineuser9 = '';
        $lineuser10 = '';
        $lineuser11 = '';
        $lineuser12 = '';
        $lineuser13 = '';
        $lineuser14 = '';
        $lineuser15 = '';
        $lineuser16 = '';
        $lineuser17 = '';
        $lineuser18 = '';
        $lineuser19 = '';
        $lineuser20 = '';
        $lineuser21 = '';
        $lineuser22 = '';
        $lineuser23 = '';
        $lineuser24 = '';
        $pdrecno = 0;
        $nonstockcost = 0;
        $prodcat = '';
        $approvety = '';


        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "apikey= " . $apikey . " Increment ID=" . $orderincid);
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "shipto= " . $shipto . " Increment ID=" . $orderincid);
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "shipvia= " . $shipviaty . " Increment ID=" . $orderincid);
        $paramsHead = new \ArrayObject();
        /*$paramsHead[] = new SoapVar($cono, XSD_DECIMAL, null, null, 'cono');
        $paramsHead[] = new SoapVar($operinit, XSD_STRING, null, null, 'operinit');
        $paramsHead[] = new SoapVar($transtype, XSD_STRING, null, null, 'transtype');
        $paramsHead[] = new SoapVar($batchnm, XSD_STRING, null, null, 'batchnm');
        $paramsHead[] = new SoapVar($whse, XSD_STRING, null, null, 'whse');
        $paramsHead[] = new SoapVar($custno, XSD_DECIMAL, null, null, 'custno');
        $paramsHead[] = new SoapVar($slsrepin, XSD_STRING, null, null, 'slsrepin');
        $paramsHead[] = new SoapVar($wodiscamt, XSD_DECIMAL, null, null, 'wodiscamt');
        $paramsHead[] = new SoapVar($wodiscpct, XSD_DECIMAL, null, null, 'wodiscpct');
        $paramsHead[] = new SoapVar($createsalesorderlinesyesno, XSD_STRING, null, null, 'createsalesorderlinesyesno');
        $paramsHead[] = new SoapVar($user6, XSD_DECIMAL, null, null, 'user6');
        $paramsHead[] = new SoapVar($user7, XSD_DECIMAL, null, null, 'user7');
        $paramsHead[] = new SoapVar($apikey, XSD_STRING, null, null, 'APIKey');
        $paramsHead[] = new SoapVar($placedby, XSD_STRING, null, null, 'placedby');
        $paramsHead[] = new SoapVar($takenby, XSD_STRING, null, null, 'takenby');
        $paramsHead[] = new SoapVar($custpo, XSD_STRING, null, null, 'custpo');
        $paramsHead[] = new SoapVar($enterdt, XSD_STRING, null, null, 'enterdt');
        $paramsHead[] = new SoapVar($transdt, XSD_STRING, null, null, 'transdt');
        $paramsHead[] = new SoapVar($terms, XSD_STRING, null, null, 'terms');
        $paramsHead[] = new SoapVar($printprice, XSD_STRING, null, null, 'printprice');
        $paramsHead[] = new SoapVar($slsrepout, XSD_STRING, null, null, 'slsrepout');*/
        if ($forceQuote) {
            $transtype = "so";
            $custpo = "TaxCalc:" . $custpo;
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "takenby tax" . $taxtakenby . " Increment ID=" . $orderincid);
            if (!empty ($taxtakenby)) {
                $takenby = $taxtakenby;
            }
        }
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "creating param head object, terms=" . $terms . ", transtype=" . $transtype . " Increment ID=" . $orderincid);
        $thisparam = array(
            'cono' => $cono,
            'operinit' => $operinit,
            'transtype' => $transtype,
            'batchnm' => $batchnm,
            'whse' => $whse,
            'custno' => $custno,
            'wodiscamt' => $wodiscamt,
            'wodiscpct' => $wodiscpct, //,'slsrepin' => $slsrepin 
            'createsalesorderlinesyesno' => $createsalesorderlinesyesno,
            'user6' => $user6,
            'user7' => $user7,
            'APIKey' => $apikey,
            'placedby' => $placedby,
            'takenby' => $takenby,
            'custpo' => $custpo,
            'enterdt' => $enterdt,
            'transdt' => $transdt,
            'terms' => $terms,
            'printprice' => $printprice,//'slsrepout' =>$slsrepout,
            'shipviaty' => $shipviaty,
            'refer' => $refer
        );
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "add in optional fields to  param head object" . " Increment ID=" . $orderincid);
        if ($erpAddress == "" && $shipto2erp == "1" && ($custno != $csdcustomerid)) {
            // $paramsHead[] = new SoapVar($shipto, XSD_STRING, null, null, 'shipto');
            $thisparam = array_merge($thisparam, array('shipto' => $shipto));
        } elseif (isset ($erpAddress)) {
            //$paramsHead[] = new SoapVar($erpAddress, XSD_STRING, null, null, 'shipto');
            $thisparam = array_merge($thisparam, array('shipto' => $erpAddress));
        }


        if (false) { //taxing fields
            //  $thisparam=array_merge( $thisparam, array('taxauthcountryCAUSorAU' => $countrycd));
            $thisparam = array_merge($thisparam, array('taxauthstate' => $shiptost));
            //   $thisparam=array_merge( $thisparam, array('taxauthcounty' => $taxauthcounty));
            //   $thisparam=array_merge( $thisparam, array('taxauthcity' => $shiptocity));
            //   $thisparam=array_merge( $thisparam, array('taxauthother1' => $taxauthother1));
            //   $thisparam=array_merge( $thisparam, array('taxauthother2' => $taxauthother2));
        }
        //$paramsHead[] = new SoapVar($shipviaty, XSD_STRING, null, null, 'shipviaty');

        if ($shipto2erp == "1" || ($custno == $csdcustomerid) || $forceQuote) { //|| !isset($erpAddress)
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "shiptost = " . $shiptost);
            /*$paramsHead[] = new SoapVar($shiptonm, XSD_STRING, null, null, 'shiptonm');
            $paramsHead[] = new SoapVar($shiptoaddr1, XSD_STRING, null, null, 'shiptoaddr1');
            $paramsHead[] = new SoapVar($shiptocity, XSD_STRING, null, null, 'shiptocity');
            $paramsHead[] = new SoapVar($shiptost, XSD_STRING, null, null, 'shiptost');
            $paramsHead[] = new SoapVar($shiptozip, XSD_STRING, null, null, 'shiptozip');*/
            $thisparam = array_merge($thisparam, array('shiptonm' => $shiptonm));
            $thisparam = array_merge($thisparam, array('shiptoaddr1' => $shiptoaddr1));
            $thisparam = array_merge($thisparam, array('shiptoaddr2' => $shiptoaddr2));
            $thisparam = array_merge($thisparam, array('shiptocity' => $shiptocity));
            $thisparam = array_merge($thisparam, array('shiptost' => $shiptost));
            $thisparam = array_merge($thisparam, array('shiptozip' => $shiptozip));
        }

        if ($user24 != '') {
            //$paramsHead[] = new SoapVar($user24, XSD_STRING, null, null, 'user24');
            $thisparam = array_merge($thisparam, array('user24' => $user24));
        }
        if ($custTaxable == "y") {
            $thisparam = array_merge($thisparam, array('taxable' => "Y"));
        }
        $addonno = $this->getConfigValue('addonno');
        //$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "addon = " . $addonno . " Increment ID=" . $orderincid);
        if (!empty ($addonno) && is_numeric($addonno)) { //check for freight
            $shippingAmount = (float) $order->getShippingAmount();
            //$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "shippingAmount = " . $shippingAmount . " Increment ID=" . $orderincid);
            if ($shippingAmount > 0) {
                $addonamt = $shippingAmount;
                $thisparam = array_merge($thisparam, array('addonamt1' => $addonamt));
                $thisparam = array_merge($thisparam, array('addonno1' => $addonno));
            }
        }
        $paramsHead[] = new \SoapVar($thisparam, SOAP_ENC_OBJECT);
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "done creating param head object" . " Increment ID=" . $orderincid);
        $lineno = 0;
        $haslines = false;

        $paramsHead[] = new SoapVar('original', XSD_STRING, null, null, 'actionType');
        //customer sub table ********************************************************************************************************
        $paramsCustomer = new ArrayObject;
        $paramsCustomer[] = new SoapVar($addr1, XSD_DECIMAL, null, null, 'address1');
        $paramsCustomer[] = new SoapVar($addr2, XSD_DECIMAL, null, null, 'address2');
        $paramsCustomer[] = new SoapVar($city, XSD_DECIMAL, null, null, 'city');
        $paramsCustomer[] = new SoapVar($countrycd, XSD_DECIMAL, null, null, 'countryCd');
        $paramsCustomer[] = new SoapVar($name, XSD_DECIMAL, null, null, 'name');
        $paramsCustomer[] = new SoapVar($phoneno, XSD_DECIMAL, null, null, 'phone');
        $paramsCustomer[] = new SoapVar($zipcd, XSD_DECIMAL, null, null, 'postalCd');
        $paramsCustomer[] = new SoapVar($state, XSD_DECIMAL, null, null, 'state');
        $paramsCustomer[] = new SoapVar($custno, XSD_DECIMAL, null, null, 'custNo');

        $paramsHead->append(new SoapVar($paramsCustomer, SOAP_ENC_OBJECT, null, null, 'OrderCustomerRequestContainer'));
        //terms sub table ********************************************************************************************************
        $paramsTerms = new ArrayObject;
        $paramsTerms[] = new SoapVar($terms, XSD_DECIMAL, null, null, 'csdTermsCd');
        $paramsHead->append(new SoapVar($paramsTerms, SOAP_ENC_OBJECT, null, null, 'OrderTermsRequestContainer'));


        //item sub table ********************************************************************************************************
        $lineno = 0;
        foreach ($items as $item) {
            $name = $item->getName();
            $type = $item->getSku();
            $id = $item->getProductId();
            $qty = $item->getQty();

            $price = $item->getPrice();
            if ($price == 0) {
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Skipping item has parent item" . " Increment ID=" . $orderincid);
                continue;
            }
            $lineno = $lineno + 1;
            try {
                //	   	$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Getting CSD Product 1");
                //	$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , " unit====" . $item->getData("unitstock"));
                $getunit = ItemsProductSelect($cono, $operinit, $type);
                if (isset ($getunit["unitstock"])) {
                    $unit = $getunit["unitstock"];
                } else {
                    $unit = 'ea';
                }
            } catch (\Exception $eUnit) {
                $unit = 'ea';
            }

            if (!isset ($unit)) {
                $unit = 'ea';
            }
            $description = $item["description"];
            $paramsDetail = new ArrayObject; //(object)array();  //this needs to be multidimensional
            //************************************************************************
            $paramsDetail[] = new SoapVar($lineno, XSD_DECIMAL, null, null, 'lineIden');
            $paramsDetail[] = new SoapVar($description, XSD_DECIMAL, null, null, 'descrip');
            $paramsDetail[] = new SoapVar($unit, XSD_DECIMAL, null, null, 'qtyUom');
            $paramsDetail[] = new SoapVar($qty, XSD_DECIMAL, null, null, 'qtyOrd');
            $paramsDetail[] = new SoapVar($type, XSD_STRING, null, null, 'sellerProd');
            $paramsDetail[] = new SoapVar($taxablefl, XSD_DECIMAL, null, null, 'taxfl');


            $paramsDetail[] = new SoapVar($unit, XSD_STRING, null, null, 'unit');
            $paramsDetail[] = new SoapVar($price, XSD_STRING, null, null, 'price');
            $paramsHead->append(new SoapVar($paramsDetail, SOAP_ENC_OBJECT, null, null, 'OrderLinesRequestContainer'));
        }

        if ($erpAddress == "" && $shipto2erp == "1" && ($custno != $csdcustomerid) && !$forceQuote) {
            $gcnlship = $this->SalesShipToInsertUpdate2($paramsShipTo, $moduleName);
        }

        /*ob_start();
        var_dump($paramsHead);
        $resultparam = ob_get_clean();
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "instead of insert");
        $this->gwLog($resultparam);*/
        //return false; //need to test address stuff


        if ($haslines) {

            $gcnl = SalesOrderInsert($paramsHead);

        } else {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "no lines on order" . " Increment ID=" . $orderincid);
            return true;
        }

        if (!$forceQuote) {
            if (isset ($_SESSION["cpReferenceNumber"])) {
                $this->UpdateOrderWithCCAuthNo($order, $_SESSION["cpAutorizationNumber"], $moduleName);
            }

            if (isset ($_SESSION["CCSaveFields"])) {
                $this->UpdateOrderWithRapidConnectInfo($order, $moduleName);
            }
        }
        //$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "checking SalesOrderInsert results");
        if (is_null($gcnl)) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "gcnl is null" . " Increment ID=" . $orderincid);

            return false;
        } elseif ($gcnl["errorcd"] != '000-000') {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "errorcd is not 000" . " Increment ID=" . $orderincid);

            return false;
        } elseif (isset ($gcnl["ordernumber"])) {
            $orderno = $gcnl["ordernumber"];

            //$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "order=" . $orderno);
            if ($orderno != "0") {
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order No = " . $orderno . " Increment ID=" . $orderincid);


                //check order transtype = altitude transtype

                //update CSD_OrderNo field

                $this->UpdateOrderWithERPOrderNo($order, $orderno, $moduleName);
                $authNumber = $order->getPayment()->getData('last_trans_id');
                if (isset ($authNumber)) {
                    $this->SalesOrderNotesInsert($cono, $orderno, "", "Credit card authorization number: " . $authNumber);
                }

                $this->InsertOrderLog($invoice, $moduleName);
                if (isset ($_SESSION["cpReferenceNumber"])) {
                    $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Not Sending CC to CSD");

                    $transcd = "auth";
                    $amount = $_SESSION["cpAmount"];
                    $mediacd = $_SESSION["cpCardType"];//"VISA";//card type
                    $processno = $_SESSION["cpCardLastFourDigitst"]; //last4
                    $preauthno = "";//
                    $authno = $_SESSION["cpAutorizationNumber"];
                    $cpresponse = $_SESSION["cpReferenceNumber"];
                } else {
                    $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "No CC Info");
                }

                return true;

            } else {
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "no order number" . " Increment ID=" . $orderincid);

                return false;
            }
        } else {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "no order number set " . " Increment ID=" . $orderincid);

            return false;
        }
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "final");
    }

    public function SalesOrderPreInsert($quote, $moduleName = "")
    {
        $configs = $this->getConfigValue([
            'csdcustomerid',
            'cono',
            'whse',
            'slsrepin',
            'defaultterms',
            'operinit',
            'transtype',
            'shipviaty',
            'slsrepout',
            'holdifover',
            'shipto2erp',
            'potermscode',
            'orderaspo',
            'sendtoerpinv',
            'apikey'
        ]);
        extract($configs);

        if ($quote->getShippingAddress()) {
            $shipping_address = $quote->getShippingAddress()->getData();
        } else {
            return 0;
        }

        $erpAddress = "";
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $items = $quote->getAllItems();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();

        $region = $objectManager->create('Magento\Directory\Model\Region')->load($shipping_address["region_id"]); // Region Id
        if ($region->getData()) {
            $statecd = $region->getData()['code'];
        } else {
            return 0;
        }

        $countrycd = $shipping_address["country_id"];
        $custno = $csdcustomerid;

        $customerSession2 = $objectManager->get('Magento\Customer\Model\Session');
        $customerData = $customerSession2->getCustomer();

        if (!$customerSession2->isLoggedIn()) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "customer is guest");
            $custno = $csdcustomerid;
        } else {
            $customer = $objectManager->create('Magento\Customer\Model\Customer')->load($customerData->getId());
            $custno = $customer->getData('csd_custno');
            $warehouse = $customer->getData('whse');
            $shipvia = $customer->getData('erpshipvia');

            if (!empty ($warehouse)) {
                $whse = $warehouse;
            }

            if ($custno) {
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "set csd custno...");
            } else {
                // Not Logged In
                $custno = $csdcustomerid;
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "csd custno is default");
            }
        }

        $paramsHead = new \ArrayObject();
        /*  $paramsHead[] = new SoapVar($cono, XSD_DECIMAL, null, null, 'cono');
          $paramsHead[] = new SoapVar($whse, XSD_STRING, null, null, 'whse');
          $paramsHead[] = new SoapVar($custno, XSD_DECIMAL, null, null, 'custno');
          $paramsHead[] = new SoapVar($countrycd, XSD_STRING, null, null, 'taxauthcountryCAUSorAU');
          $paramsHead[] = new SoapVar($statecd, XSD_DECIMAL, null, null, 'taxauthstate');
          if ($shipping_address["city"] != "") {
              $paramsHead[] = new SoapVar($shipping_address["city"], XSD_DECIMAL, null, null, 'taxauthcity');
          }
          $paramsHead[] = new SoapVar($apikey, XSD_DECIMAL, null, null, 'APIKey');*/
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "preinsert 1");
        $thisparam = array(
            'cono' => $cono,
            'whse' => $whse,
            'custno' => $custno,
            'taxauthcountryCAUSorAU' => $countrycd,
            'taxauthstate' => $statecd,
            'APIKey' => $apikey
        );
        if ($shipping_address["city"] != "") {
            $thisparam = array_merge($thisparam, array('taxauthcity' => $shipping_address["city"]));
        }
        $paramsHead[] = new \SoapVar($thisparam, SOAP_ENC_OBJECT);
        $lineno = 0;
        $haslines = false;
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "preinsert 2");

        foreach ($items as $item) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "preinsert 2.01");
            if ($sendtoerpinv == "1") {
                try {
                    /*if ($item->getOrderItem()->getParentItem()) {
                        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Skipping parent item");
                        continue;
                    }*/
                } catch (\Exception $e) {
                    $this->gwLog(json_encode($e->getMessage()));
                }
            }
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "preinsert 2.1");
            $lineno = $lineno + 1;
            $name = $item->getName();
            $type = $item->getSku();
            $id = $item->getProductId();
            $qty = $item->getQty();
            $price = $item->getPrice();

            try {
                //$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Getting CSD Product");
                $getunit = $this->ItemsProductSelect($cono, $type, '', '', '', $moduleName);
                if (isset ($getunit["unitstock"])) {
                    $unit = $getunit["unitstock"];
                    //$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "unit=" . $unit);
                } else {
                    $unit = 'ea';
                    //$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "!unit=" . $unit);
                }
            } catch (\Exception $e) {
                $unit = 'ea';
                //$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "@unit=" . $unit . " " . json_encode($e->getMessage()));
            }

            if (!isset ($unit)) {
                $unit = 'ea';
            }

            $description = $item["description"];
            $paramsDetail = new ArrayObject();

            /*   $paramsDetail[] = new SoapVar($lineno, XSD_DECIMAL, null, null, 'lineno');
               $paramsDetail[] = new SoapVar($qty, XSD_DECIMAL, null, null, 'qtyord');
               $paramsDetail[] = new SoapVar($type, XSD_STRING, null, null, 'shipprod');
               $paramsDetail[] = new SoapVar($unit, XSD_STRING, null, null, 'unit');
               $paramsDetail[] = new SoapVar($price, XSD_STRING, null, null, 'price');
               $paramsHead->append(new SoapVar($paramsDetail, SOAP_ENC_OBJECT, null, null, 'SalesOrderLinesPreInsertRequestContainer'));*/

            $paramsDetail[] = new \SoapVar(array('lineno' => $lineno), SOAP_ENC_OBJECT);
            $paramsDetail[] = new \SoapVar(array('qtyord' => $qty), SOAP_ENC_OBJECT);
            $paramsDetail[] = new \SoapVar(array('shipprod' => $type), SOAP_ENC_OBJECT);
            $paramsDetail[] = new \SoapVar(array('unit' => $unit), SOAP_ENC_OBJECT);
            $paramsDetail[] = new \SoapVar(array('price' => $price), SOAP_ENC_OBJECT);
            $paramsDetail[] = new \SoapVar(array('taxable' => "Y"), SOAP_ENC_OBJECT);
            $thisparamLines = array('SalesOrderLinesPreInsertRequestContainer' => $paramsDetail->getArrayCopy());
            $paramsHead->append(
                new SoapVar(
                    $thisparamLines,
                    SOAP_ENC_OBJECT,
                    null,
                    null,
                    'SalesOrderLinesPreInsertRequestContainer'
                )
            );

            $haslines = true;
        }

        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "preinsert 3");
        if ($haslines) {
            $apiname = "SalesOrderPreInsert";
            $this->LogAPICall($apiname, $moduleName);
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "apikey: " . $apikey);
            $client = $this->createSoapClient($apikey, $apiname);

            /*$rootparams = new ArrayObject();
            $rootparams->append(new SoapVar($paramsHead, SOAP_ENC_OBJECT, null, null, 'SalesOrderPreInsertRequestContainer'));*/
            $rootparams = (object) [];
            $rootparams->SalesOrderPreInsertRequestContainer = $paramsHead->getArrayCopy();
            try {
                $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result // //request/result
                $result = $client->SalesOrderPreInsert($rootparams);
                $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
                $taxAmount = json_decode(json_encode($result), true);
                if (isset ($taxAmount['totaltaxamt'])) {
                    return $taxAmount['totaltaxamt'];
                }
            } catch (\Exception $e) {
                $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()));
            }
        }

        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "preinsert 4");
        return 0;
    }

    public function SalesOrderPaymentInsert($custno, $invno, $invsuf, $amt, $operinit, $moduleName = "")
    { //status=bad, save for next version_compare
        return "";
        $apiname = "SalesOrderPaymentInsert";
        $this->LogAPICall($apiname, $moduleName);

        $configs = $this->getConfigValue(['apikey', 'cono']);
        extract($configs);
        $client = $this->createSoapClient($apikey, $apiname);

        $params = (object) [];
        $params->cono = $cono;
        $params->custno = $custno;
        $params->gwsinv = $invno;
        $params->gwsinvsuf = $invsuf;
        $params->amount = $amt;
        $params->operinit = $operinit;
        $params->APIKey = $apikey;
        $rootparams = (object) [];
        $rootparams->SalesOrderPaymentInsertRequestContainer = $params;

        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result // //request/result
            $result = $client->SalesOrderPaymentInsert($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            return json_decode(json_encode($result), true);
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "REQUEST:\n" . htmlentities($client->__getLastRequest()));
        }
    }
    public function SendAlert($subject, $body)
    {
        try {
            $this->gwLog('Sending alert email: ' . $subject);
            $configs = $this->getConfigValue(['apikey', 'cono', 'transtype', 'alertemail']);
            extract($configs);
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $storeManager = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
            $storeId = $storeManager->getStore()->getId();
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $scopeConfig = $objectManager->create('\Magento\Framework\App\Config\ScopeConfigInterface');
            $email = $scopeConfig->getValue('trans_email/ident_support/email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $transport = $objectManager->create('Magento\Framework\Mail\Template\TransportBuilder');
            $templateVars = array(
                'body' => $body,
                'subject' => $subject
            );
            //$this->gwLog('Sending email to : ' . $alertemail)  ;      
            $emails = explode(",", $alertemail);
            $emails = array_map('trim', $emails);
            // ob_start();
            // var_dump($emails);
            // $result = ob_get_clean();
            // $this->gwLog('exploded emails: ' . $result)  ;
            $data = $transport
                ->setTemplateIdentifier("send_email_alert_template")//get template id in your create in backend to use variable in backend you should use this tpye format etc . {{var body}} for body  {{var subject}} for subject
                ->setTemplateOptions(['area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars($templateVars)
                ->setFrom(['name' => 'Order Support', 'email' => $email])
                ->addTo($emails)
                ->getTransport();
            $data->sendMessage();
            // $this->gwLog('Sent alert email from ' . $email);
        } catch (\Exception $e) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Error sending alert email: " . json_encode($e->getMessage()));
        }
    }
    public function IncrementERPOrderNo($increment_id, $ERPOrderNo, $ERPOrderSuf)
    {
        $ERPOrderSuf = $ERPOrderSuf + 1;
        $ERPOrderSuf = str_pad($ERPOrderSuf, 2, '0', STR_PAD_LEFT);
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Updating order table with ERP ##" . $ERPOrderNo . '-' . $ERPOrderSuf);
        $dbConnection = $this->resourceConnection->getConnection();
        //***************
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "updating sales_order");
        try {
            $sql = "update mg_sales_order set ext_order_id='" . $ERPOrderNo . "-" . $ERPOrderSuf . "', CSD_OrderNo='" . $ERPOrderNo . "', CSD_OrderSuf='" . $ordersuf . "'  where increment_id='" . $increment_id . "';";
            //$this->gwLog($sql);
            if ($dbConnection->query($sql) === true) {
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order field  updated successfully for order " . $ERPOrderNo);
            }
        } catch (\Exception $e) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Catch error: " . json_encode($e->getMessage()));
        }
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "updating sales_order_grid");
        try {
            $sql = "update mg_sales_order_grid set ext_order_id='" . $ERPOrderNo . "-" . $ERPOrderSuf . "' where increment_id='" . $increment_id . "';";
            // $this->gwLog($sql);
            if ($dbConnection->query($sql) === true) {
                // echo "New record created successfully";
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Grid ext order no updated: " . $ERPOrderNo);
            }
        } catch (\Exception $e) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to insert update order field table");
        }
    }
    public function UpdateOrderWithERPOrderNo($order, $ERPOrderNo, $moduleName = "")
    {
        $configs = $this->getConfigValue(['apikey', 'cono', 'transtype']);
        extract($configs);

        $orderid = $order->getIncrementId();
        $ordersuf = "0";
        $dbConnection = $this->resourceConnection->getConnection();

        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Updating order table with ERP ##" . $ERPOrderNo);

        try {
            $gcnlOrder = $this->SalesOrderSelect($cono, $ERPOrderNo, "", $moduleName);
            $ordersuf = $gcnlOrder["ordersuf"];
        } catch (\Exception $e) {
            $ordersuf = "00";
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to get order suffix, assuming 0" . json_encode($e->getMessage()));
        }
        if ($ordersuf == "")
            $ordersuf = "00";
        $ordersuf = str_pad($ordersuf, 2, '0', STR_PAD_LEFT);
        $order->setExtOrderId($ERPOrderNo . "-" . $ordersuf);

        //transtype
        try {
            //$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Checking transtype");
            if (isset ($gcnlOrder["transtype"])) {
                if ($transtype <> $gcnlOrder["transtype"]) {
                    //$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "sending email for transtype " . $gcnlOrder["transtype"]);
                    //SendAlter("test subject","test body");
                    $this->SendAlert("Order transtype error in order " . $ERPOrderNo . "-" . $ordersuf, "There is an error in order " . $ERPOrderNo . "-" . $ordersuf . " (" . $orderid . "). Expected transtype " . $transtype . ", got " . $gcnlOrder["transtype"] . ".");
                }
            }
        } catch (\Exception $e) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Catch error checking transtype: " . json_encode($e->getMessage()));
        }
        //***************
        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "updating sales_order");
        try {
            $sql = "update mg_sales_order set ext_order_id='" . $ERPOrderNo . "-" . $ordersuf . "', CSD_OrderNo='" . $ERPOrderNo . "', CSD_OrderSuf='" . $ordersuf . "'  where increment_id='" . $orderid . "';";
            //$this->gwLog($sql);
            if ($dbConnection->query($sql) === true) {
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order grid field  updated successfully for order " . $ERPOrderNo);
            }
        } catch (\Exception $e) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Catch error: " . json_encode($e->getMessage()));
        }
        //**********************

        //    $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "altering gws_GreyWolfOrderFieldUpdate");
        try {
            $query = "ALTER TABLE `gws_GreyWolfOrderFieldUpdate` ADD COLUMN IF NOT EXISTS `suffix_list` varchar(255) AFTER `dateprocessed`;";

            if ($dbConnection->query($sql) === true) {
            }
        } catch (\Exception $e) {
        }
        //*********************
        //***************
        //    $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "updating mg_sales_order_grid");
        try {
            $sql = "update mg_sales_order_grid set ext_order_id='" . $ERPOrderNo . "-" . $ordersuf . "' where increment_id='" . $orderid . "';";
            // $this->gwLog($sql);
            if ($dbConnection->query($sql) === true) {
                // echo "New record created successfully";
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Grid ext order no: " . $orderid);
            }
        } catch (\Exception $e) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to insert update order field table");
        }

        //  $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "updating gws_GreyWolfOrderQueue");
        try {
            $sql = "update `gws_GreyWolfOrderQueue` set `dateprocessed`=now() where `orderid`='$orderid' ";
            //$this->gwLog($sql);
            if ($dbConnection->query($sql) === true) {
                // echo "New record created successfully";
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Grid ext order no: " . $orderid);
            }
        } catch (\Exception $e) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to insert update order field table");
        }

        $sql = "select * FROM gws_GreyWolfOrderFieldUpdate WHERE orderid='" . $orderid . "'";

        $result = $dbConnection->fetchAll($sql);
        if (count($result)) {
            $sql = "update `gws_GreyWolfOrderFieldUpdate` set `ERPOrderNo`='" . $ERPOrderNo . "', `ERPSuffix`= '" . $ordersuf . "' where `orderid`='" . $orderid . "'";
        } else {
            $sql = "INSERT INTO `gws_GreyWolfOrderFieldUpdate` (`orderid`,`dateentered`,`ERPOrderNo`,`ERPSuffix`) VALUES ('$orderid',now(),'" . $ERPOrderNo . "', '" . $ordersuf . "')";
        }

        try {
            if ($dbConnection->query($sql) === true) {
                // echo "New record created successfully";
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order field  table updated successfully for order " . $orderid);
            }
        } catch (\Exception $e) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to insert update order field table");
        }

        $payment = $order->getPayment();
        if (isset ($payment)) {
            $paymentMethod = (string) $payment->getMethod();
        } else {
            $paymentMethod = "";
        }
        if (strpos($paymentMethod, "authorizenet") === false && strpos($paymentMethod, "anet_") === false) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "not Authorize: $paymentMethod");
        } else {
            $additionalInfo = $payment->getData('additional_information');
            $authNo = "";

            if (isset ($additionalInfo['authCode']) && $additionalInfo['authCode'] != "") {
                $authNo = $additionalInfo['authCode'];
                $transID = $payment->getData('last_trans_id');

                if ($transID == "" && isset ($additionalInfo['transactionId'])) {
                    $transID = $additionalInfo['transactionId'];
                }

                $authAmount = $payment->getAmountAuthorized();
                $exp = $order->getPayment()->getCcExpMonth() . '/' . $order->getPayment()->getCcExpYear();

                $lastfour = $order->getPayment()->getCcLast4();
                $methodTitle = (isset ($additionalInfo['cardType'])) ? $additionalInfo['cardType'] : "";

                switch ($methodTitle) {
                    case "visa":
                        $methodTitle = "12";
                        break;
                    case "mastercard":
                        $methodTitle = "13";
                        break;
                    case "amex":
                        $methodTitle = "14";
                        break;
                    case "discover":
                        $methodTitle = "15";
                        break;
                    default:
                        $methodTitle = "16";
                        break;
                }
                $sql = "update `gws_GreyWolfOrderFieldUpdate` set `TransactionID`='" . $transID . "', `AuthID`= '" . $authNo . "', `CCAuthNo`= '" . $authNo . "', `CCNo`= '" . $lastfour . "', `CardType`= '" . $methodTitle . "', `TxnAmt`= '" . $authAmount . "' where `orderid`='" . $orderid . "'";

                try {
                    if ($dbConnection->query($sql) === true && false) { //temporarily disabling
                        //status=bad, save for next version
                        // echo "New record created successfully";
                        $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order field  table updated successfully for order " . $orderid);

                        $orderno = $ERPOrderNo;
                        $ordersuf = $ordersuf;
                        if ($ordersuf == "")
                            $ordersuf = "00";
                        $amount = $authAmount;
                        $authamt = $authAmount;
                        $bankno = 0;
                        $cardno = $lastfour;
                        $charmediaauth = $transID;
                        $cmm = "";
                        $commcd = 0;
                        $createdt = date('m/d/Y');
                        $currproc = "";
                        $mediaauth = 0;
                        $mediacd = $methodTitle;
                        $origamt = $authAmount;
                        $origproccd = 0;
                        $preauthno = 0;
                        $processcd = 0;
                        $processno = 0;
                        $respdt = date('m/d/Y');
                        $response = $authNo;
                        $saleamt = $authAmount;
                        $statustype = "";
                        $submitdt = date('m/d/Y');
                        $transcd = "";
                        $transdt = date('m/d/Y');
                        $user1 = "";
                        $user2 = "";
                        $user3 = "";
                        $user4 = "";
                        $user5 = "";
                        $user6 = 0;
                        $user7 = 0;
                        $user8 = "";
                        $user9 = "";
                        $exp = "";
                        //status=bad, save for next version
                        //will be SalesOrderPaymentInsert	
                        $this->SalesCreditCardAuthInsert($cono, $orderno, $ordersuf, $amount, $authamt, $bankno, $cardno, $charmediaauth, $cmm, $commcd, $createdt, $currproc, $mediaauth, $mediacd, $origamt, $origproccd, $preauthno, $processcd, $processno, $respdt, $response, $saleamt, $statustype, $submitdt, $transcd, $transdt, $user1, $user2, $user3, $user4, $user5, $user6, $user7, $user8, $user9, $exp);
                    }
                } catch (\Exception $e) {
                    $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
                }
            }
        }
    }

    public function InsertOrderLog($invoiceno, $moduleName = "")
    {
        return "";
        /*global $sendtoerpinv;
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        if ($sendtoerpinv == "0") {
            $order = $invoiceno->getOrder();
            $orderid = $order->getIncrementId();
        } else {
            $order = $invoiceno;
            $orderid = $order->getIncrementId();
        }
        $customerSession = $om->get('Magento\Customer\Model\Session');
        $user = $order->getCustomerEmail();
        $ip = $_SERVER["REMOTE_ADDR"];
        global $db_host,$db_port,$db_username,$db_password, $db_primaryDatabase;

        // Connect to the database, using the predefined database variables in /assets/repository/mysql.php
        $dbConnection = new mysqli($db_host, $db_username, $db_password, $db_primaryDatabase);

        // If there are errors (if the no# of errors is > 1), print out the error and cancel loading the page via exit();
        if (mysqli_connect_errno()) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Could not connect to MySQL databse: " . mysqli_connect_error());
            exit();
        }
        try {
            $grandtotal = $order->getGrandTotal();
        } catch (\Exception $e) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "ERROR getting data: " . json_encode($e->getMessage()));
            $grandtotal = 0;
        }

        $sql = "INSERT INTO `gws_GreyWolfLog` (`dateentered`,`user`,`IP`,`LogType`,`LogData`,`LogType2`,`LogData2`) VALUES (now(),'$user','$ip','Order','$orderid','Amount','$grandtotal' )";
        if ($dbConnection->query($sql) === true) {
            // echo "New record created successfully";
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "New order log record created successfully for order " . $orderid);
        } else {
            // echo "Error: " . $sql . "<br>" . $dbConnection->error;
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "Error: " . $sql . "..." . $dbConnection->error . " ... order " . $orderid);
        }*/
    }



    public function UpdateOrderFieldProcessed($orderid, $DateEntered, $moduleName = "")
    {
        try {
            //$dbConnection = $this->resourceConnection->getConnection();
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

            $directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
            $envFilePath = $directory->getRoot() . '/app/etc/env.php';
            $configResult = include $envFilePath;
            $conn = $configResult['db']['connection']['default'];
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

            $sql = "update `gws_GreyWolfOrderFieldUpdate` set `dateprocessed`=now() where `orderid`='$orderid' and dateentered='" . $DateEntered . "';";
            if ($dbConnection->query($sql) === true) {
                // echo "New record created successfully";
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order queue record updated successfully for order " . $orderid);
            }
        } catch (\Exception $e) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to close row in order field update table: " . json_encode($e->getMessage()));
        }
    }

    public function UpdateOrderQueue($orderid, $moduleName = "")
    {
        try {
            //$dbConnection = $this->resourceConnection->getConnection();

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

            $directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
            $envFilePath = $directory->getRoot() . '/app/etc/env.php';
            $configResult = include $envFilePath;
            $conn = $configResult['db']['connection']['default'];
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

            $sql = "update `gws_GreyWolfOrderQueue` set `dateprocessed`=now() where `orderid`='$orderid' ";
            if ($dbConnection->query($sql) === true) {
                // echo "New record created successfully";
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order queue record updated successfully for order " . $orderid);
            }
        } catch (\Exception $e) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to close row in order field update table: " . json_encode($e->getMessage()));
        }
    }

    public function UpdateOrderStatusAndState($orderincid, $state)
    {
        try {
            $dbConnection = $this->resourceConnection->getConnection();

            $sql = "update `mg_sales_order` set `state`='$state', `status`='$state' where `increment_id`='$orderincid' ";
            //$this->gwLog($sql);
            if ($dbConnection->query($sql) === true) {
                // echo "New record created successfully";
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order queue record updated successfully for order " . $orderid);
            }
        } catch (\Exception $e) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to order status " . json_encode($e->getMessage()));
        }
    }
    public function UpdateOrderStatusAndStateGrid($orderincid, $state)
    {
        try {
            $dbConnection = $this->resourceConnection->getConnection();

            $sql = "update `mg_sales_order_grid` set  `status`='$state' where `increment_id`='$orderincid' ";
            //  $this->gwLog($sql);
            if ($dbConnection->query($sql) === true) {
                // echo "New record created successfully";
                $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Order queue record updated successfully for order " . $orderid);
            }
        } catch (\Exception $e) {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Failed to update order grid status: " . json_encode($e->getMessage()));
        }
    }
    public function InsertOrderQueue($invoiceno)
    {
        $dbConnection = $this->resourceConnection->getConnection();
        $sendtoerpinv = $this->getConfigValue('sendtoerpinv');

        if ($sendtoerpinv == 1) {
            $order = $invoiceno->getOrder();
        } else {
            $order = $invoiceno;
        }

        $orderid = $order->getIncrementId();

        $sql = "INSERT INTO `gws_GreyWolfOrderQueue` (`orderid`,`dateentered`) VALUES ('$orderid', now()) ";
        if ($dbConnection->query($sql) === true) {
            // echo "New record created successfully";
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "New order queue record created successfully for order " . $orderid);
        } else {
            $this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Error: " . $sql . "..." . " ... order " . $orderid);
        }
    }

    public function SalesOrderNotesInsert($cono, $orderno, $ordersuf, $notes = "", $securefl = "", $globalfl = "", $globalflsuf = "", $moduleName = "")
    {
        $apiname = "SalesOrderNotesInsert";
        $this->LogAPICall($apiname, $moduleName);

        $apikey = $this->getConfigValue('apikey');
        $client = $this->createSoapClient($apikey, $apiname);

        $params1 = (object) [];
        $params1->cono = $cono;
        $params1->orderno = $orderno;
        $params1->ordersuf = $ordersuf;
        $params1->notes1 = $notes;
        $params1->securefl = $securefl;
        $params1->globalfl = $globalfl;
        $params1->globalflsuf = $globalflsuf;
        $params1->APIKey = $apikey;
        $rootparams = (object) [];
        $rootparams->SalesOrderNotesInsertRequestContainer = $params1;
        $result = (object) [];

        try {
            $dTime = $this->LogAPITime($apiname, "request", $moduleName, ""); //request/result ////request/result
            $result = $client->SalesOrderNotesInsertRequest($rootparams);
            $this->LogAPITime($apiname, "result", $moduleName, $dTime, $this->get_string_between($client->__getLastResponse(), "<requestId>", "</requestId>"));
            $response = json_decode(json_encode($result), true);

            return $response;
        } catch (\Exception $e) {
            $this->gwLog('Caught exception: ' . json_encode($e->getMessage()));
        }
    }
    public function getWhseFromShipTo($whse, $cono, $custno, $erpAddress)
    {
        $this->gwLog('starting shipto');
        $this->gwLog($cono);
        $shipTo = $this->SalesShipToSelect($cono, $custno, $erpAddress, 'getWhseFromShipTo');
        $this->gwLog('post SSTS');
        if (isset ($shipTo)) {
            if (!empty ($shipTo["whse"])) {
                $whse = $shipTo["whse"];
                $this->gwLog('found whse  ' . $whse);
            }
        }
        return $whse;
    }
    public function getWhseAndWhseList($region = "")
    {
        $IswhereHouses = $this->getConfigValue('shipping_upcharge/inventory_availabilities/is_dis_inventory_availability');
        $whereHouses = $this->getConfigValue('shipping_upcharge/inventory_availabilities/inventory_availability');
        $whse = $whselist = '';
        if ($IswhereHouses && !empty ($whereHouses)) {
            $whereHousesData = array_values(json_decode($whereHouses, true));
            foreach ($whereHousesData as $wvalue) {
                if ($wvalue['province'] == $region && !empty ($region)) {
                    $warehousData = explode(',', $wvalue['warehouses']);
                    if (!empty ($warehousData) && count($warehousData) > 0) {
                        $whse = $warehousData[0];
                        $whselist = $wvalue['warehouses'];
                    }
                }
            }
        }
        //$this->getWhse($whse,$region);
        return ['whse' => $whse, 'whselist' => $whselist];
    }
    public function getWhse($whse = "", $region = "")
    {
        //	status=bad, save for next version_compare
        return "";
        $cono = $this->getConfigValue('cono');
        //$this->gwLog('getting single whse from ' . $whse );
        $IswhereHouses = $this->getConfigValue('shipping_upcharge/inventory_availabilities/is_dis_inventory_availability');
        $whereHouses = $this->getConfigValue('shipping_upcharge/inventory_availabilities/inventory_availability');
        //$this->gwLog('checking whse values' );
        if ($IswhereHouses && !empty ($whereHouses)) {

            $gcWhse = $this->ItemsWarehouseList($cono, "", "getWhse");
            if (isset ($gcWhse)) {
                $whereHousesData = array_values(json_decode($whereHouses, true));
                foreach ($whereHousesData as $wvalue) {
                    if ($wvalue['province'] == $region && !empty ($region)) {
                        if (isset ($gcWhse["ItemsWarehouseListResponseContainerItems"])) {
                            //$this->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "multiple records");
                            foreach ($gcWhse["ItemsWarehouseListResponseContainerItems"] as $item) {
                                if (strpos($wvalue['warehouses'], $item["whse"]) !== false) {
                                    if ($wvalue['province'] == "AB") {
                                        $whse = "1100";
                                        break;
                                    } elseif ($wvalue['province'] == $item["state"]) {
                                        $whse = $item["state"] = $item["whse"];
                                        break 2;
                                    }
                                }
                            }
                        } else {
                            //$this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": " , "single record");
                            if (strpos($wvalue['warehouses'], $gcWhse["whse"]) !== false) {
                                if ($wvalue['province'] == "AB") {
                                    $whse = "1100";
                                    break;
                                } elseif ($wvalue['province'] == $gcWhse["state"]) {
                                    $whse = $gcWhse["state"] = $gcWhse["whse"];
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
        $this->gwLog('Setting single whse to ' . $whse);
        return $whse;
    }
}
