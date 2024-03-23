<?php

namespace Altitude\CSD\Block;

use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\View\Element\Template;

class CSDOrderBlock extends Template
{
    protected $_product = null;

    protected $_registry;

    protected $_productFactory;

    protected $io;

    protected $csd;

    protected $dir;

    protected $checkoutSession;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\Filesystem\Io\File $io,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Filesystem\DirectoryList $dir,
        \Altitude\CSD\Helper\Data $helper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Altitude\CSD\Model\CSD $csd,
        array $data = []
    ) {
        $this->_registry = $registry;
        $this->_productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->_context = $context;
        $this->_cart = $cart;
        $this->csd = $csd;

        $this->directoryList = $dir;
        $this->io = $io;
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context, $data);
    }

    public function PayInvoice($invoiceno, $invoicesuf, $amount, $forwardtocheckout)
    {
        global $apikey, $apiurl, $csdcustomerid, $cono, $whse, $slsrepin, $defaultterms, $operinit, $transtype, $shipviaty, $slsrepout, $updateqty, $invstartdate;
        global $maxrecall, $maxrecalluid, $maxrecallpwd;

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');

        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $webID = $storeManager->getStore()->getWebsiteId();

        $state = $objectManager->get('Magento\Framework\App\State');

        $amount = money_format('%.2n', (floatval($amount)));

        if ($customerSession->isLoggedIn()) {
            $customer = $customerSession->getCustomer();
            $custno = $customer['csd_custno'];
            //	echo ($this->DisplayOrders($customer['csd_custno'],'invoice'));
            try {
                //hidenegativeinvoice
            } catch (\Exception $ee) {
                $paymtdt = "";
            }
        } else {
            //	echo "Not logged in";
            return;
        }

        //  $state->setAreaCode('frontend');
        $suffix = str_pad($invoicesuf, 2, "0", STR_PAD_LEFT);

        $sku = $custno . '-' . $invoiceno . '-' . $suffix;

        try { //https://magento.stackexchange.com/questions/106902/check-if-product-with-sku-exists-in-magento-2
            $_product = $this->productRepository->get($sku);
            //  $product;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            //  $this->csd->gwLog ( $e->getMessage());
            // $product = false;
            $_product = $objectManager->create('Magento\Catalog\Model\Product');
        }

        $this->csd->gwLog('Adding to cart');
        $this->csd->gwLog('Customer ' . $custno . ' Invoice ' . $invoiceno . '-' . $suffix);
        $this->csd->gwLog('Amount ' . $amount);

        $_product->setName('Customer ' . $custno . ' Invoice ' . $invoiceno . '-' . $suffix);
        $_product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE);
        $_product->setAttributeSetId(4);
        $_product->setSku($sku);
        $_product->setWebsiteIds([$webID]);
        $_product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE);
        $_product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $_product->setPrice($amount);

        $imageData = $this->helper->getProductImageData();
        $imageFile = $this->directoryList->getPath('media') . '/import/paid_invoice.jpg';

        if (!$this->io->fileExists($imageFile)) {
            $this->io->write($imageFile, $this->helper->getProductImageData(), 0644);
        }

        $_product->addImageToMediaGallery($imageFile, ['image', 'small_image', 'thumbnail'], false, false);

        $params = [
            'product' => $sku,
            'price' => $amount,
            'qty' => 1
        ];

        $_product->setStockData(
            [
                'use_config_manage_stock' => 0, //'Use config settings' checkbox
                'manage_stock' => 1, //manage stock
                'min_sale_qty' => 1, //Minimum Qty Allowed in Shopping Cart
                'max_sale_qty' => 1, //Maximum Qty Allowed in Shopping Cart
                'is_in_stock' => 1, //Stock Availability
                'qty' => 1 //qty
            ]
        );
        $this->csd->gwLog('Amount ' . $amount);
        $_product->setPrice($amount);
        $_product->setIsSuperMode(true);
        $_product->setCustomPrice($amount);
        $_product->setOriginalCustomPrice($amount);
        $_product->save();
        $_product->setPrice($amount);

        $emptyCart = $objectManager
            ->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue(
                'defaults/shoppingcart/emptyallnoninvoice',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );

        try {
            $this->_cart->addProduct($_product, $params);
            $_product->setCustomPrice($amount);
            $_product->setOriginalCustomPrice($amount);
            $_product->setPrice($amount);

            if ($emptyCart) {
                $cart = $this->_cart;
                $quoteItems = $this->checkoutSession->getQuote()->getItemsCollection();
                foreach ($quoteItems as $item) {
                    if (strpos($item->getName(), "Invoice") === false) {
                        $cart->removeItem($item->getId())->save();
                    }
                }
                $this->_cart->save();
            }
        } catch (\Exception $e) {
        }

        // $this->messageManager->addSuccess(__('Add to cart successfully.'));

        if ($forwardtocheckout == true) {
            $this->_cart->save();
            $url = '?invoice=' . $invoiceno . '&invoicesuf=' . $invoicesuf . '&paycart=cart';
            $url = $this->getUrl('checkout/cart');
            header('Location: ' . $url);
            die();
        }
        //TODO announce item added to cart
    }

    public function HasPOD($Order)
    {
        return true;
        global $maxrecall, $maxrecalluid, $maxrecallpwd;
        $map_url = $maxrecall . "/Viewer/RetrieveDocument/D1097/[{'KeyID':'119','UserValue':'" . $Order . "'}]";
        // echo ($map_url . ' - ' .$maxrecalluid . ' - ' .$maxrecallpwd );
        $request = '';
        $username = $maxrecalluid; //'maxadmin2';
        $password = $maxrecallpwd; //'paperless';
        /*   $result1 = $this->makeRESTRequest($map_url, $request, $username, $password);
           flush();
           if (strpos($result1, "Unable to render") !== false) {
               return false;
           } else {
               return true;
           }
          */
    }

    public function ShowOrder($item, $invstartdate, $invenddate, &$total, &$didthis)
    {
        global $maxrecall, $maxrecalluid, $maxrecallpwd;
        $didthis = "|";
        $result = "";
        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "start: " . $invstartdate . " end: " . $invenddate);

        if (isset ($_GET["search"])) {
            $search = $_GET["search"];
            ob_start();
            var_dump($item);
            $varresult = ob_get_clean();
            if (strpos($varresult, $search) === false) {
                return;
            }
        }

        $orderhead = $item;
        if ((strtotime($invstartdate) <= strtotime($orderhead["enterdt"])) && (strtotime($invenddate) >= strtotime($orderhead["enterdt"]) && (strpos($didthis, $orderhead["orderno"] . $orderhead["ordersuf"]) === false))) {
            $didthis .= $orderhead["orderno"] . $orderhead["ordersuf"] . "|";
            //$result .= $orderhead["orderno"] . ' ' . $orderhead["amount"];
            //var_dump ($orderhead);
            //echo '<br><Br>';
            try {
                if (isset ($orderhead["paymtdt"])) {
                    $paymtdt = $orderhead["paymtdt"];
                } else {
                    $paymtdt = "";
                }
            } catch (\Exception $ee) {
                $paymtdt = "";
            }
            $result .= '<tr class=orderheader>';
            $result .= '<td data-th="Order Number"><a href="?order=' . $orderhead["orderno"] . '&ordersuf=' . $orderhead["ordersuf"] . '&detail=true" alt="View Order" title="View Order">' . $orderhead["orderno"] . '</a>';

            //  $result .= ' (<a href="?order=' . $orderhead["orderno"] . '&ordersuf=' . $orderhead["ordersuf"] . '&pdf=true" alt="View Order" title="View Order">PDF</a>)';
            /* if ($this->HasPOD($orderhead["orderno"] )){
                 if ($orderhead["totlineamt"]>0 && isset($maxrecall)) {
                     $result .= ' (<a href="?order=' . $orderhead["orderno"]  . '&ordersuf=' . $orderhead["ordersuf"]  . '&pod=true" alt="View POD" title="View POD">POD</a>)';
                 }
             }*/
            $result .= '</td>';
            $result .= '<td data-th="Suffix">' . $orderhead["ordersuf"] . '</td>';
            $result .= '<td data-th="Date">' . $orderhead["enterdt"] . '</td>';
            $result .= '<td data-th="PO #">' . $orderhead["custpo"] . '</td>';
            $result .= '<td data-th="Order Type">' . $orderhead["typedesc"] . '</td>';
            $result .= '<td data-th="Terms">' . $orderhead["termsdesc"] . '</td>';
            $result .= '<td data-th="Stage">' . $orderhead["stagedesc"] . '</td>';
            $result .= '<td data-th="Date">' . $orderhead["promisedt"] . '</td>';
            $result .= '<td data-th="" align=right>$' . money_format('%.2n', (floatval($orderhead["totlineamt"]))) . '</td>';
            $total += $orderhead["totlineamt"];

            $result .= '</tr>';
        } //if date

        return $result;
    }

    public function ShowInvoice($item, $invstartdate, $invenddate, &$total, &$paidtotal, &$didthis, $status)
    {
        global $maxrecall, $maxrecalluid, $maxrecallpwd, $hidenegativeinvoice, $simplifyinvoice;
        global $gotocart;
        $result = "";
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
        $custneginvoice = "";
        $search = "";

        static $invoicecount = 0;

        if (isset ($_GET["search"])) {
            $search = $_GET["search"];
            ob_start();
            var_dump($item);
            $varresult = ob_get_clean();
            if (strpos($varresult, $search) === false) {
                return;
            }
        }
        if (isset ($_POST["shipto"]) && $_POST["shipto"] != "all") {
            $shipto = $_POST["shipto"];
            if (strpos($item["shipto"], $shipto) === false) {
                return;
            }
        }

        if ($customerSession->isLoggedIn()) {
            $customer = $customerSession->getCustomer();
            //	echo ($this->DisplayOrders($customer['csd_custno'],'invoice'));
            try {
                //hidenegativeinvoice
                $custneginvoice = $customer['hidenegativeinvoice'];
            } catch (\Exception $ee) {
                $paymtdt = "";
            }
        } else {
            return;
        }
        $custneginvoice = strtoupper($custneginvoice);
        if ($custneginvoice == "Y") {
            $hidenegativeinvoice = 1;
        } elseif ($custneginvoice == "N") {
            $hidenegativeinvoice = 0;
        }

        $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "start: " . $invstartdate . " end: " . $invenddate);
        $invoice = $item;
        if (isset ($invoice["invdt"]) && isset ($invoice["invno"]) && $invoice["invno"] > 0 && (strpos($didthis, $invoice["invno"] . $invoice["invsuf"]) === false) && (strtotime($invstartdate) <= strtotime($invoice["invdt"])) && (strtotime($invenddate) >= strtotime($invoice["invdt"]))) {
            if ($invoice["amount"] > 0 or $hidenegativeinvoice == 0) {
                $didthis .= $invoice["invno"] . $invoice["invsuf"] . "|";
                //  echo $didthis . "<br>" . strpos($didthis,$invoice["invno"] . $invoice["invsuf"] ) ;
                //$result .= $invoice["invno"] . ' ' . $invoice["amount"];
                //var_dump ($invoice);
                //echo '<br><Br>';
                try {
                    if (isset ($invoice["paymtdt"])) {
                        $paymtdt = $invoice["paymtdt"];
                    } else {
                        $paymtdt = "";
                    }
                } catch (\Exception $ee) {
                    $paymtdt = "";
                }
                $result .= '<tr class=invoiceheader>';

                $result .= '<td><input type=checkbox name="paybox' . $invoicecount . '" id="paybox' . $invoicecount . '">';
                $result .= '<input type=hidden id="payinvoiceno' . $invoicecount . '" name="payinvoiceno' . $invoicecount . '" value="' . $invoice["invno"] . '">';
                $result .= '<input type=hidden id="paysuf' . $invoicecount . '" name="paysuf' . $invoicecount . '" value="' . $invoice["invsuf"] . '">';
                $result .= '</td>';

                if (isset ($_POST["paybox" . $invoicecount]) && isset ($_POST["payinvoiceno" . $invoicecount]) && isset ($_POST["paysuf" . $invoicecount])) {
                    if ($_POST["paybox" . $invoicecount] = "on" && $_POST["payinvoiceno" . $invoicecount] == $invoice["invno"] && $_POST["paysuf" . $invoicecount] == $invoice["invsuf"]) {
                        $this->PayInvoice($invoice["invno"], $invoice["invsuf"], $invoice["amount"], false);
                        $gotocart = true;
                    }
                }

                $invoicecount++;
                $result .= '<td>';
                $result .= '<a href="?invoice=' . $invoice["invno"] . '&invoicesuf=' . $invoice["invsuf"] . '&detail=true" alt="View Invoice" title="View Invoice">' . $invoice["invno"] . '</a>';
                $result .= '</td>';
                $result .= '<td>0' . $invoice["invsuf"] . '</td>';
                if (isset ($maxrecall)) {
                    if ($invoice["amount"] > 0) {
                        // $result .= ' <a href="?order=' . $invoice["invno"] . '&ordersuf=' . $invoice["invsuf"] . '&pdf=true" alt="View Invoice PDF" title="View Invoice PDF">' . $invoice["invno"] . '</a>';
                        // $result .= ' (<a href="?order=' . $invoice["invno"]  . '&ordersuf=' . $invoice["invsuf"]  . '&pod=true" alt="View POD" title="View POD">POD</a>)';
                        $result .= ' <td><a href="?order=' . $invoice["invno"] . '&ordersuf=' . $invoice["invsuf"] . '&pod=true" alt="View POD" title="View&nbsp;POD">View POD</a></td>';
                    } else {
                        $result .= ' <td />';
                    }
                }
                $result .= '<td>' . $invoice["invdt"] . '</td>';
                $result .= '<td>' . $invoice["termstype"] . '</td>';
                $result .= '<td>';
                if ($invoice["statustype"] == "Active") {
                    $result .= 'Open';
                } else {
                    $result .= 'Closed';
                }
                $result .= '</td>';
                $result .= '<td>' . $invoice["duedt"] . '</td>';
                $result .= '<td align=right>$' . money_format('%.2n', (floatval($invoice["amount"]))) . '</td>';
                $total += $invoice["amount"];
                $result .= '<td>' . $paymtdt . '</td>';

                $result .= '<td align=right>$' . money_format('%.2n', (floatval($invoice["paymtamt"]))) . '</td>';
                $paidtotal += $invoice["paymtamt"];
                $result .= '</tr>';
            } //hideinvoicecheck
        } //if date

        return $result;
    }

    public function DisplayOrders($customer, $ordertype = "invoice")
    {
        global $apikey, $apiurl, $csdcustomerid, $cono, $whse, $slsrepin, $defaultterms, $operinit, $transtype, $shipviaty, $slsrepout, $updateqty, $invstartdate;
        global $maxrecall, $maxrecalluid, $maxrecallpwd, $simplifyinvoice;
        global $gotocart;
        $recordcount = 0;
        $moduleName = $this->csd->getModuleName(get_class($this));
        /*
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');

        echo $rootPath  =  $directory->getRoot();

        $output = exec($rootPath . '/fixit');
        echo "<pre>$output</pre>"; */

        $didthis = "|";
        $displaytype = "invoice"; //will add ui to switch this to order
        $result = "";

        if (isset ($_POST["ordertype"])) {
            $displaytype = $_POST["ordertype"];

        } else {

        }



        $displaytype = $ordertype;
        $invtodetail = "";
        //     $invstartdate="01/01/1971";
        $invenddate = date("m/d/Y", time()); //"01/01/2025";
        if (isset ($_GET["order"]) && isset ($_GET["ordersuf"])) {
            $invtodetail = $_GET["order"] . $_GET["ordersuf"];
            $invorderno = $_GET["order"];
            $invordersuf = $_GET["ordersuf"];
            $ordertype = 'order';
        }
        if (isset ($_GET["invoice"]) && isset ($_GET["invoicesuf"])) {
            $invtodetail = $_GET["invoice"] . $_GET["invoicesuf"];
            $invorderno = $_GET["invoice"];
            $invordersuf = $_GET["invoicesuf"];
            $ordertype = 'invoice';
        }

        if (isset ($_POST["order"]) && isset ($_POST["ordersuf"])) {
            $invtodetail = $_POST["order"] . $_POST["ordersuf"];
            $invorderno = $_POST["order"];
            $invordersuf = $_POST["ordersuf"];
            $ordertype = 'order';
        }
        if (isset ($_POST["invoice"]) && isset ($_POST["invoicesuf"])) {
            $invtodetail = $_POST["invoice"] . $_POST["invoicesuf"];
            $invorderno = $_POST["invoice"];
            $invordersuf = $_POST["invoicesuf"];
            $ordertype = 'invoice';
        }

        $displaytype = $ordertype;

        if (isset ($_GET["startdate"])) {
            $frmstartdate = $_GET["startdate"];
            if ($frmstartdate != "") {
                $invstartdate = $frmstartdate;
            }
        }
        if (isset ($_GET["enddate"])) {
            $frmenddate = $_GET["enddate"];
            if ($frmenddate != "") {
                $invenddate = $frmenddate;
            }
        }
        if (isset ($_POST["startdate"])) {
            $frmstartdate = $_POST["startdate"];
            if ($frmstartdate != "") {
                $invstartdate = $frmstartdate;
            }
        }
        if (isset ($_POST["enddate"])) {
            $frmenddate = $_POST["enddate"];
            if ($frmenddate != "") {
                $invenddate = $frmenddate;
            }
        }

        $pdf = "";
        if (isset ($_POST["pdf"])) {
            $pdf = $_POST["pdf"];
        }
        if (isset ($_GET["pdf"])) {
            $pdf = $_GET["pdf"];
        }
        $pod = "";
        $pay = "";
        $paycart = "";
        if (isset ($_POST["pod"])) {
            $pod = $_POST["pod"];
        }
        if (isset ($_GET["pod"])) {
            $pod = $_GET["pod"];
        }

        if (isset ($_POST["pay"])) {
            $pay = $_POST["pay"];
        }
        if (isset ($_GET["pay"])) {
            $pay = $_GET["pay"];
        }

        if (isset ($_POST["paycart"])) {
            $paycart = $_POST["paycart"];
        }
        if (isset ($_GET["paycart"])) {
            $paycart = $_GET["paycart"];
        }

        $result .= "<form action=# method=post>";

        if (!$customer) {
            $customer = $csdcustomerid;
        }

        if ($invtodetail == "" && !isset ($_GET["order"])) {
            //   $result .= '111111111111';
            $total = 0;
            $paidtotal = 0;
            $result .= '<table class="gwinvoicetable data table" border=0>';
            $result .= '<tr><td style="font-weight:bold;font-size: 18px;vertical-align: middle;text-align: center;" colspan=2>Start&nbsp;Date:</td><td style="vertical-align: middle;"><input type=text id=startdate name=startdate value="' . $invstartdate . '"></td>';
            $result .= '<td style="font-weight:bold;font-size: 18px;vertical-align: middle;text-align: center;" colspan=2>End&nbsp;Date:</td><td style="vertical-align: middle;"><input type=text id=enddate name=enddate value="' . $invenddate . '"></td>';
            if ($displaytype == "invoice" && $pdf != 'true' && $pod != 'true') {
                $GCShip = $this->csd->SalesShipToList($cono, $customer, $moduleName);
                $shiptolist = '';
                if (!isset ($GCShip["errordesc"]) && isset ($GCShip["SalesShipToListResponseContainerItems"])) {
                    foreach ($GCShip["SalesShipToListResponseContainerItems"] as $item) {
                        //   var_dump($item);
                        $shiptolist .= "<option value='" . $item["shipto"] . "'";
                        if (isset ($_POST["shipto"])) {
                            if ($item["shipto"] == $_POST["shipto"]) {
                                $shiptolist .= " selected ";
                            }
                        }

                        $shiptolist .= ">" . $item["name"] . " (" . $item["shipto"] . ")</option>";
                    }
                }
                $result .= '<td style="font-weight:bold;font-size: 18px;vertical-align: middle;text-align: center;" colspan=1>Ship&nbsp;To:</td>';
                $result .= '<td style="font-weight:bold;font-size: 18px;vertical-align: middle;text-align: center;" colspan=1>';
                $result .= '<select name="shipto" id="shipto">';
                $result .= '<option value="all">All</option>';
                $result .= $shiptolist;
                $result .= '</select></td>';
                $result .= '</td>';
            }
            $result .= '<td style="font-weight:bold;font-size: 18px;vertical-align: middle;text-align: center;" colspan=2>';
            $result .= '<input type=hidden name="ordertype" value="' . $displaytype . '">';
            $result .= '<input type=submit class="action subscribe primary"></td></tr></table></form>';
        }
        $result .= '<table class="gwinvoicetable data table" border=0><tr>';
        if ($pod == 'true') {
            $map_url = $maxrecall . "/Viewer/RetrieveDocument/D1097/[{'KeyID':'119','UserValue':'" . $invorderno . "'}]";
            // echo ($map_url . ' - ' .$maxrecalluid . ' - ' .$maxrecallpwd );
            $request = '';
            $username = $maxrecalluid; //'maxadmin2';
            $password = $maxrecallpwd; //'paperless';
            $result1 = $this->makeRESTRequest($map_url, $request, $username, $password);
            $result = str_replace("<object ", "<object style='min-height:750px;' ", $result1);
        } elseif ($pdf == 'true') {
            $map_url = $maxrecall . "/Viewer/RetrieveDocument/D140/[{'KeyID':'119','UserValue':'" . $invorderno . "'}]";
            // echo ($map_url . ' - ' .$maxrecalluid . ' - ' .$maxrecallpwd );
            $request = '';
            $username = $maxrecalluid; //'maxadmin2';
            $password = $maxrecallpwd; //'paperless';
            $result1 = $this->makeRESTRequest($map_url, $request, $username, $password);
            $result = str_replace("<object ", "<object style='min-height:750px;' ", $result1);
        } elseif (($displaytype == "order" || $displaytype == "quote") && $pdf != 'true' && $pod != 'true') {
            if ($invtodetail == "" && !isset ($_GET["order"])) {
                $total = 0;
                $paidtotal = 0;

                if ($displaytype == "order") {
                    $buttontext = "Show Invoices";
                    $url = '?ordertype=invoice&startdate=' . $invstartdate . '&enddate=' . $invenddate;
                } else {
                    $buttontext = "Show Orders";
                    $url = '?ordertype=order&startdate=' . $invstartdate . '&enddate=' . $invenddate;
                }

                $result .= "<tr>";
                $result .= "<th colspan=4 style='text-align:right;padding-right: 28px;'>";

                $result .= '<a href="#"><button type="button"  onclick="history.go(-1)" class="action subscribe primary" style="margin: 0 15px 0 -27px;width: 130px;">Back</button></a>';
                $result .= '<a href="' . $url . '"><button type="button" class="action subscribe primary" style="width: 130px;">' . __($buttontext) . '</button></a>';

                $result .= "<th colspan=9 style='text-align:right;padding-right: 28px;'><form method='get' action = '#'><span class='gwslabel'><input type=hidden id=ordertype name=ordertype value='order'></span>";
                $result .= "<span class='gwslabel'><input type=text id='search' name='search' style='width: 235px;margin-right:18px;'";
                if (isset ($_GET["search"])) {
                    $search = $_GET["search"];
                    $result .= " value='" . $search . "' ";
                }
                $result .= "> </span>";
                $result .= '<input class="action subscribe primary" title="Search" type="submit" value="Search" style="margin-right: -16px;"></form>';
                $result .= "</th></tr>";
                $result .= '<tr><th>Order Number</th>';
                $result .= '<th>Suffix</th>';
                $result .= '<th>Date</th>';
                $result .= '<th>PO&nbsp;#</th>';
                $result .= '<th>Order&nbsp;Type</th>';
                $result .= '<th>Terms</th>';
                $result .= '<th>Stage</th>';
                $result .= '<th>Promise&nbsp;Date</th>';
                $result .= '<th style="text-align:right;">Amount</th>';

                $result .= '</tr></thead>';
                $didthis = "|";
                try {
                    if ($displaytype == "order") {
                        $bStage = 0;
                        $eStage = 6;
                        $transtype = 'so';
                    } else {
                        $bStage = 0;
                        $eStage = 6;
                        $transtype = 'qu';
                    }

                    $gcnl = $this->csd->SalesOrderList($cono, $customer, "", "", $transtype, "", "", "", "", "", $invstartdate, $invenddate);
                    $noorder = false;

                    if (isset ($gcnl["errordesc"])) {
                        if ($gcnl["errordesc"] != "") {
                            $noorder = true;
                        } else {
                            $noorder = false;
                        }
                    } else {
                        $noorder = false;
                    }

                    if ($noorder == true) {
                        $result .= "<tr><td colspan=9>" . $gcnl["errordesc"] . "</td></tr>";
                    } else {
                        if (isset ($gcnl["SalesOrderListResponseContainerItems"])) {
                            foreach ($gcnl["SalesOrderListResponseContainerItems"] as $item) {
                                $result .= $this->ShowOrder($item, $invstartdate, $invenddate, $total, $didthis);
                                $recordcount++;
                            }
                        } else {
                            $result .= $this->ShowOrder($gcnl, $invstartdate, $invenddate, $total, $didthis);
                            $recordcount++;
                        }
                    }
                } catch (\Exception $e) {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "GWS Error: " . $e->getMessage());
                    $result .= '<table class="gwinvoicetable data table" border=0><tr><td>';
                    $result .= "<br>Error found.<br>";
                    $result .= '</td></tr></table></table></table>';
                }

                $result .= '<tr><td colspan=8 align=right><strong>Order&nbsp;Total:</td><td align=right>$' . money_format('%.2n', (floatval($total))) . '</td></tr>';
                $result .= '</table>';
            } else {
                try {
                    if (1 == 2) { // (isset($gcnl["errordesc"])){
                        //$result .= "<tr><td colspan=9>" .  $gcnl["errordesc"] . "</td></tr>";
                    } else {

                        //reorder ******************************************************
                        if (isset ($_POST["reorderitems"])) {
                            // $result .= "reorder";
                            if ($_POST["reorderitems"] == "yes") {
                                //reorder selected items
                                $iTotal = $_POST["totalitems"];
                                $paramsHead = new \ArrayObject(); //(object)array();

                                $itemsadded = 0;
                                $lineno = 0;
                                for ($i = 1; $i <= $iTotal; $i++) {
                                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Checking: " . $i);
                                    if (isset ($producttoadd)) {
                                        unset($producttoadd);
                                    }
                                    // $result .= $lineno;
                                    //***********************************reorder loop
                                    if (isset ($_POST['reorder' . $i])) {
                                        $lineno = $lineno + 1;
                                        $itemsadded += 1;

                                        $type = $_POST["reorderitem" . $i]; // $item->getSku();
                                        $qty = $_POST["reorderqty" . $i]; //$item->getQty();
                                        $unit = $_POST["reorderunit" . $i]; //'ea';

                                        try {
                                            $producttoadd = $this->productRepository->get($type);

                                            if ($producttoadd->getParentItem()) {
                                                $producttoadd = $producttoadd->getParentItem();
                                            }

                                            $this->csd->gwLog($i . "reorder product: " . $producttoadd->getId());
                                        } catch (\Magento\Framework\Exception\NoSuchEntityException $e5) {
                                            $this->_logger->addDebug('Product Error: ' . $e5->getMessage());
                                        }
                                        $testprice = 20;
                                        if (isset ($producttoadd)) {
                                            $this->_logger->addDebug('Getting prod id ' . $producttoadd->getId());

                                            $producttoadd->setPrice($testprice);
                                            $producttoadd->setBasePrice($testprice);
                                            $producttoadd->setCustomPrice($testprice);
                                            $producttoadd->setOriginalCustomPrice($testprice);

                                            //    $producttoadd->getProduct()->setIsSuperMode(true);

                                            $params = [
                                                //  'form_key' => $this->_formKey->getFormKey(),
                                                'product' => $producttoadd->getId(),
                                                //   'price' =>$testprice ,
                                                'qty' => $qty
                                            ];
                                            $this->_cart->setIsMultiShipping(false);

                                            $producttoadd->setPrice($testprice);
                                            $producttoadd->setCustomPrice($testprice);
                                            $producttoadd->setOriginalCustomPrice($testprice);
                                            $producttoadd->setBasePrice($testprice);
                                            $producttoadd->setIsSuperMode(true);

                                            $this->_cart->addProduct($producttoadd, $params);
                                            $this->_cart->save();
                                            $testprice = 50;
                                            foreach ($this->_cart->getQuote()->getAllVisibleItems() as $item /* @var $item Mage_Sales_Model_Quote_Item */) {
                                                if ($item->getParentItem()) {
                                                    $item = $item->getParentItem();
                                                }

                                                try {
                                                    $gcnl = SalesCustomerPricingSelect($cono, $item->getSku(), $whse, $customer, '', $qty);
                                                    if (isset ($gcnl["price"])) {
                                                        $testprice = $gcnl["price"];
                                                        if (!empty ($gcnl["pround"])) {
                                                            switch ($gcnl["pround"]) {
                                                                case 'u';
                                                                    $testprice = \ceil($testprice);
                                                                    break;
                                                                case 'd';
                                                                    $testprice = \floor($testprice);
                                                                    break;
                                                                case 'n';
                                                                    $testprice = \round($testprice);
                                                                    break;
                                                                default;
                                                                    break;
                                                            }
                                                        } //end pround check
                                                    }
                                                } catch (\Exception $e1) {
                                                    $this->csd->gwLog($e1->getMessage());
                                                    $testprice = 0;
                                                }

                                                $item->setPrice($testprice);
                                                $item->setBasePrice($testprice);
                                                $item->setCustomPrice($testprice);
                                                $item->setOriginalCustomPrice($testprice);
                                                $item->getProduct()->setIsSuperMode(true);

                                                $item->getQuote()->collectTotals();
                                                $subtotal = $item->getQuote()->getSubtotal();
                                                $grandTotal = $item->getQuote()->getGrandTotal();
                                                $updatedSubtotal = $item->getQuote()->setSubtotal($subtotal);
                                                $updatedGrandTotal = $item->getQuote()->setGrandTotal($grandTotal);
                                            }
                                            $this->_cart->save();
                                        }
                                    }

                                    //***********************************close reorder loop
                                }
                            }
                        } else {

                            //$result .= " no reorder";
                        }

                        $total = 0;

                        $Order = SalesOrderSelect($cono, $invorderno, $invordersuf);
                        $Customer = SalesCustomerSelect($cono, $Order["custno"]);
                        $Package = SalesPackagesSelect($cono, $invorderno, $invordersuf);
                        $orderhead = $Order;

                        //check order stage and other stats

                        if (1 == 1) {
                            try {
                                if (isset ($Order["custpo"])) {
                                    $custpo = $Order["custpo"];
                                } else {
                                    $custpo = "";
                                }
                            } catch (\Exception $epo) {
                                $custpo = "";
                            }
                            $result .= '<a href="#"><button type="button"  onclick="history.go(-1)" class="action subscribe primary" style="margin: 0 15px 0 0px;width: 130px;">' . __('Back') . '</button></a>';
                            $result .= '<table class="gworderbodytable data">';

                            $result .= "<tr><td width=50%/><td style='font-weight:bold;' align=right>Date:</td><td>" . $orderhead["enterdt"] . "</td></tr>";
                            $result .= "<tr><td /><td style='font-weight:bold;' align=right>Order&nbsp;Number:</td><td width:150px>" . $orderhead["orderno"] . "-0" . $orderhead["ordersuf"] . "</td></tr>";
                            $result .= "<tr><td /><td style='font-weight:bold;' align=right>PO&nbsp;Number:</td><td>" . $custpo . "</td></tr>";
                            $result .= "<tr><td /><td style='font-weight:bold;' align=right>Order Type:</td><td>" . $orderhead["typedesc"] . "</td></tr>";
                            $result .= "<tr><td /><td style='font-weight:bold;' align=right>Order Stage:</td><td>" . $orderhead["stagedesc"] . "</td></tr>";
                            $result .= "<tr><td /><td style='font-weight:bold;' align=right>Customer&nbsp;Number:</td><td>" . $customer . "</td></tr>";
                            $result .= '</table><br>';

                            $result .= '<div class="block block-order-details-view" style="margin-bottom:0px;">';
                            // $result .= '<div class="block-title"><strong>Order Information</strong></div>';
                            $result .= '<div class="block-content">';
                            $result .= '<div class="box box-order-billing-address">';
                            $result .= '<strong class="box-title"><span>Billing Address</span></strong>';
                            $result .= '<div class="box-content"><address>';
                            $result .= $Customer["name"] . "<br />";
                            $result .= (isset ($Customer["addr1"]) ? $Customer["addr1"] . "<br>" : "");
                            if ($Customer["addr2"] != "") {
                                $result .= $Customer["addr2"] . "<br>";
                            }
                            $result .= (isset ($Customer["city"]) ? $Customer["city"] . ", " : "");
                            $result .= (isset ($Customer["statecd"]) ? $Customer["statecd"] . " " : "");
                            $result .= (isset ($Customer["zipcd"]) ? $Customer["zipcd"] . "<br />" : "");
                            $result .= preg_replace('/\d{3}/', '$0-', str_replace('.', null, trim($Customer["phoneno"])), 2);
                            $result .= '</address>';
                            $result .= '</div>';
                            $result .= '</div>';

                            $result .= '<div class="box box-order-shipping-address">';
                            $result .= '<strong class="box-title"><span>Shipping Address</span></strong>';
                            $result .= '<div class="box-content"><address>';

                            $result .= (isset ($Order["shiptonm"]) ? $Order["shiptonm"] . "<br>" : "");
                            $result .= (isset ($Order["shiptoaddr1"]) ? $Order["shiptoaddr1"] . "<br>" : "");
                            if ($Order["shiptoaddr2"] != "") {
                                $result .= $Order["shiptoaddr2"] . "<br>";
                            }
                            $result .= (isset ($Order["shiptocity"]) ? $Order["shiptocity"] . ", " : "");
                            $result .= (isset ($Order["shiptost"]) ? $Order["shiptost"] . " " : "");
                            $result .= (isset ($Order["shiptozip"]) ? $Order["shiptozip"] : "");
                            $result .= '</address>';
                            $result .= '</div>';
                            $result .= '</div>';

                            $result .= '<div class="box box-order-shipping-method">';
                            $result .= '<div class="box-content">';
                            $result .= '<strong>Ship Date:</strong> ';
                            $result .= (isset ($Order["shipdt"]) ? $Order["shipdt"] : "") . "<br />";
                            $result .= '<strong>Ship Via:</strong> ';
                            $result .= (isset ($Order["shipviadesc"]) ? $Order["shipviadesc"] : "") . "<br />";
                            $result .= '<strong>Terms:</strong> ';
                            $result .= (isset ($Order["termsdesc"]) ? $Order["termsdesc"] : "") . "<br />";
                            $result .= '</div>';
                            $result .= '</div>';

                            $result .= '<div class="box box-order-shipping-method">';
                            $result .= '<div class="box-content">';
                            $result .= '<strong>Tracking Number:</strong> ';
                            $result .= (isset ($Order["trackerno"]) ? $Order["trackerno"] : "N/A") . "<br />";
                            $result .= '<strong>Shipped:</strong> ';
                            $result .= (isset ($Order["shippedfl"]) ? $Order["shippedfl"] : "N/A") . "<br />";
                            $result .= '</div>';
                            $result .= '</div>';

                            $result .= '</div>';
                            $result .= '</div>';

                            $result .= "<tr><td /><td style='font-weight:bold;' align=right>Customer&nbsp;Number:</td><td>" . $customer . "</td></tr>";
                            $result .= '</table></td></tr>';
                            $result .= '<tr><td><table border=0>';
                            $result .= '<tr><td style="font-weight:bold;" align=left colspan=3>Billing</td><td style="font-weight:bold;" align=left colspan=3>Shipping</td></tr>';
                            $result .= "<tr><td width='10%'/><td class=orderlinehead>" . $Customer["name"] . "</td><td /><td width='10%'/><td class=orderlinehead>" . (isset ($Order["shiptonm"]) ? $Order["shiptonm"] : "") . "</td><td/></tr>";
                            $result .= "<tr><td/><td class=orderlinehead>" . $Customer["addr1"] . "</td><td /><td/><td class=orderlinehead>" . (isset ($Order["shiptoaddr1"]) ? $Order["shiptoaddr1"] : "") . "</td><td/></tr>";
                            if ($Customer["addr2"] . (isset ($Order["shiptoaddr2"]) ? $Order["shiptoaddr2"] : "") != "") {
                                $result .= "<tr><td class=orderlinehead class=orderlinehead align=left>" . $Customer["addr2"] . "</td><td /><td>" . (isset ($Order["shiptoaddr2"]) ? $Order["shiptoaddr2"] : "") . "</td><td/></tr>";
                            }
                            //   $result .="<tr><td class=orderlinehead>" . $Customer["addr3"] . "</td><td /><td>" . $Order["shiptoaddr3"] . "</td></tr>";
                            $result .= "<tr><td/><td class=orderlinehead>" . $Customer["city"] . ", " . $Customer["statecd"] . "  " . $Customer["zipcd"] . "</td><td/><td /><td class=orderlinehead>" . (isset ($Order["shiptocity"]) ? $Order["shiptocity"] : "") . "," . (isset ($Order["shiptost"]) ? $Order["shiptost"] : "") . "  " . (isset ($Order["shiptozip"]) ? $Order["shiptozip"] : "") . "</td><td/></tr>";
                            $result .= "<tr><td/><td class=orderlinehead>" . preg_replace('/\d{3}/', '$0-', str_replace('.', null, trim($Customer["phoneno"])), 2) . "</td><td/><td /><td></td><td/></tr>";
                            $result .= '</table></td></tr>';

                            $result .= '<tr><td><table>';
                            $result .= '<tr><td style="font-weight:bold;" align=center>Ship Date</td><td style="font-weight:bold;" align=center>Ship Via</td><td style="font-weight:bold;" align=center>Terms</td></tr>';
                            $result .= "<tr><td align=center>" . (isset ($Order["shipdt"]) ? $Order["shipdt"] : "") . "</td><td align=center>" . (isset ($Order["shipviadesc"]) ? $Order["shipviadesc"] : "") . "</td><td align=center>" . (isset ($Order["termsdesc"]) ? $Order["termsdesc"] : "") . "</td></tr>";
                            $result .= "</table><table><tr><td align=center width='50%' style='font-weight:bold;'>Tracking Number</td><td align=center style='font-weight:bold;' width='50%' >Shipped</td></tr>";
                            $result .= "<tr><td  align=center>" . (isset ($Order["trackerno"]) ? $Order["trackerno"] : "N/A") . "</td><td  align=center>" . (isset ($Order["shippedfl"]) ? $Order["shippedfl"] : "N/A") . "</td></tr>";

                            $result .= '</table></td></tr>';

                            try { //lines
                                $gcnlLine = SalesOrderLinesSelect($cono, $orderhead["orderno"], $orderhead["ordersuf"]);
                                // var_dump($gcnlLine);
                                // exit;
                                if (1 == 2) { //(isset($gcnlLine["errordesc"])  ){
                                    $result .= "<p>Error retrieving lines</p>";
                                    // $this->_logger->addDebug ("GWS Error: " . $gcnl["errordesc"] );
                                } else {
                                    $result .= '<table class="gworderlinetable data table">';
                                    $result .= '<thead><tr class=orderlinehead style="font-weight:bold;" align=center><th>Reorder</th>';
                                    $result .= '<th align=left style="width:40px;">SKU</th>';
                                    $result .= '<th align=left>Description</th>';
                                    $result .= '<th align=right>Price</th>';
                                    $result .= '<th align=center>Unit</th>';
                                    $result .= '<th align=right>Qty&nbsp;Ordered</th>';
                                    $result .= '<th align=right>Qty&nbsp;Shipped</th>';
                                    $result .= '<th align=right>Net&nbsp;Amt</th>';
                                    $result .= '</tr></thead>';

                                    $chkCounter = 1;
                                    if (isset ($gcnlLine["cono"])) {
                                        $itemLine = $gcnlLine;
                                        //foreach($gcnlLine as $itemLine){
                                        $result .= '<tr class=orderline ><td data-th="Reorder" class="qty"><input type=checkbox id="reorder' . $chkCounter . '" name="reorder' . $chkCounter . '" value="' . $itemLine["shipprod"] . '"><input type=hidden id="reorderitem' . $chkCounter . '" name="reorderitem' . $chkCounter . '"  value="' . $itemLine["shipprod"] . '"></td>';
                                        //$result .='<td><a href="/index.php/catalog/product/view/id/:' . $itemLine["shipprod"] . '" alt="View Item" title="View Item">' . $itemLine["shipprod"] . '</a></td>';
                                        $result .= '<td data-th="SKU">' . $itemLine["shipprod"] . '</td>';
                                        $result .= '<td data-th="Description">' . $itemLine["proddesc"] . '</td>';
                                        $result .= '<td data-th="Price" class="qty">$' . money_format('%.2n', $itemLine["price"]) . '<input type=hidden id="reorderprice' . $chkCounter . '" name="reorderprice' . $chkCounter . '"  value="' . $itemLine["price"] . '"></td>';
                                        $result .= '<td data-th="Unit" class="qty">' . $itemLine["unit"] . '<input type=hidden id="reorderunit' . $chkCounter . '" name="reorderunit' . $chkCounter . '"  value="' . $itemLine["unit"] . '"></td>';
                                        $result .= '<td data-th="Qty Ordered" class="qty" style="text-align:center;">' . $itemLine["qtyord"] . '<input type=hidden id="reorderqty' . $chkCounter . '" name="reorderqty' . $chkCounter . '"  value="' . $itemLine["qtyord"] . '"></td>';
                                        $result .= '<td data-th="Qty Shipped" class="qty" style="text-align:center;" >' . $itemLine["qtyship"] . '</td>';
                                        $result .= '<td data-th="Net Amt" class="qty" style="text-align:right;">$' . money_format('%.2n', $itemLine["price"] * $itemLine["qtyord"]) . '</td>';
                                        $total += $itemLine["netamt"]; //($itemLine["price"] * $itemLine["qtyord"]);
                                        $chkCounter += 1;
                                        $result .= '</tr>';
                                        // }//foreach itemline
                                    } else {
                                        if (isset ($gcnlLine["SalesOrderLinesSelectResponseContainerItems"])) {
                                            foreach ($gcnlLine["SalesOrderLinesSelectResponseContainerItems"] as $itemLine) {
                                                $result .= '<tr class=orderline ><td data-th="Reorder" class="qty"><input type=checkbox id="reorder' . $chkCounter . '" name="reorder' . $chkCounter . '" value="' . $itemLine["shipprod"] . '"><input type=hidden id="reorderitem' . $chkCounter . '" name="reorderitem' . $chkCounter . '"  value="' . $itemLine["shipprod"] . '"></td>';
                                                //$result .='<td><a href="/index.php/catalog/product/view/id/:' . $itemLine["shipprod"] . '" alt="View Item" title="View Item">' . $itemLine["shipprod"] . '</a></td>';
                                                $result .= '<td data-th="SKU">' . $itemLine["shipprod"] . '</td>';
                                                $result .= '<td data-th="Description">' . $itemLine["proddesc"] . '</td>';
                                                $result .= '<td data-th="Price" class="qty">$' . money_format('%.2n', $itemLine["price"]) . '<input type=hidden id="reorderprice' . $chkCounter . '" name="reorderprice' . $chkCounter . '"  value="' . $itemLine["price"] . '"></td>';
                                                $result .= '<td data-th="Unit" class="qty">' . $itemLine["unit"] . '<input type=hidden id="reorderunit' . $chkCounter . '" name="reorderunit' . $chkCounter . '"  value="' . $itemLine["unit"] . '"></td>';
                                                $result .= '<td data-th="Qty Ordered" class="qty"  style="text-align:center;">' . $itemLine["qtyord"] . '<input type=hidden id="reorderqty' . $chkCounter . '" name="reorderqty' . $chkCounter . '"  value="' . $itemLine["qtyord"] . '"></td>';
                                                $result .= '<td data-th="Qty Shipped" class="qty"  style="text-align:center;">' . $itemLine["qtyship"] . '</td>';
                                                $result .= '<td data-th="Net Amt" class="qty" style="text-align:right;">$' . money_format('%.2n', $itemLine["price"] * $itemLine["qtyord"]) . '</td>';
                                                $total += ($itemLine["price"] * $itemLine["qtyord"]) + $total;
                                                $chkCounter += 1;
                                                $result .= '</tr>';
                                            } //foreach itemline
                                        }
                                    }
                                    $chkCounter -= 1;
                                    $result .= '<tr><td><input type=hidden id=reorderitems name=reorderitems value="yes"><input type=hidden id=totalitems name=totalitems value=' . $chkCounter . '><input type=submit value="Reorder"></td><td colspan=6 align=right><strong>Subotal:</td><td align=right>$' . money_format('%.2n', (floatval($total))) . '</td></tr>';
                                    $result .= '<tr><td colspan=7 align=right><strong>Tax:</td><td align=right>$' . money_format('%.2n', (floatval((isset ($Order["taxamt"]) ? $Order["taxamt"] : "")))) . '</td></tr>';
                                    $result .= '<tr><td colspan=7 align=right><strong>Total:</td><td align=right>$' . money_format('%.2n', (floatval($total + $Order["taxamt"]))) . '</td></tr></table></td></tr>';
                                } //if not line error
                            } //try lines
                            catch (\Exception $e) {
                                $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "GWS Error: " . $e->getMessage());
                                $result .= '<table class="gwinvoicetable data table" border=0><tr><td>';
                                $result .= "<br>Error found.<br>";
                                $result .= '</td></tr></table></table></table>';
                            }
                            //  break;
                        } //if active order
                        //    }//foreach

                        $result .= '</table>';
                    } // if not header error
                } //try header
                catch (\Exception $eheader) {
                    $this->_logger->addDebug("GWS Error: " . $eheader->getMessage());
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "GWS Error: " . $eheader->getMessage());
                    $result .= '<table class="gwinvoicetable data table" border=0><tr><td>';
                    $result .= "<br>Error found.<br>";
                    $result .= '</td></tr></table></table></table>';
                }

                // *******************************************
            } //detail or header
        } elseif ($pdf != 'true' && $pod != 'true') { // order or invoice
            //  $result .= '66666666666666';
            if ($invtodetail == "" && !isset ($_GET["invoice"])) {
                $total = 0;
                $paidtotal = 0;

                if ($displaytype == "order") {
                    $buttontext = "Show Invoices";
                    $url = '?ordertype=invoice&startdate=' . $invstartdate . '&enddate=' . $invenddate;
                } else {
                    $buttontext = "Show Orders";
                    $url = '?ordertype=order&startdate=' . $invstartdate . '&enddate=' . $invenddate;
                }

                $result .= "<tr>";
                $result .= "<th colspan=4 style='text-align:right;padding-right: 28px;'>";

                $result .= '<a href="#"><button type="button"  onclick="history.go(-1)" class="action subscribe primary" style="margin: 0 15px 0 -27px;width: 130px;">' . __('Back') . '</button></a>';

                $result .= '<a href="' . $url . '"><button type="button" class="action subscribe primary" style="width: 130px;">' . $buttontext . '</button></a>';
                $result .= "<th colspan=9 style='text-align:right;padding-right: 28px;'><form method='get' action = '#'><span class='gwslabel'><input type=hidden name=ordertype id=ordertype value=invoice></span>";
                $result .= "<span class='gwslabel'><input type=text id='search' name='search' style='width: 235px;margin-right: 18px;'";
                if (isset ($_GET["search"])) {
                    $search = $_GET["search"];
                    $result .= " value='" . __($search) . "' ";
                }
                $result .= "> </span>";
                $result .= '<input class="action subscribe primary" title="Search" type="submit" value="Search"  style="margin-right: -16px;"></form>';
                $result .= "</th></tr>";
                $result .= '<form action=# method=post><tr><th><span style="margin-left: -3px;">Pay</span></th>';
                $result .= '<th>Invoice&nbsp;#</th>';
                $result .= '<th style="width: 50px;">Suffix</th>';
                if (isset ($maxrecall)) {
                    $result .= '<th>POD</th>';
                }

                $result .= '<th>Invoice&nbsp;Date</th>';

                $result .= '<th>Terms</th>';

                $result .= '<th>Status</th>';
                //  $result .='<th>Stage</th>';
                //$Order["stagedesc"]
                $result .= '<th>Due Date</th>';
                $result .= '<th style="text-align:right;">Amt</th>';

                $result .= '<th>Pmt Date</th>';
                $result .= '<th style="text-align:right;">Payment Amt</th>';

                $result .= '</tr>';
                $didthis = "|";
                try {
                    $gcnl = SalesCustomerInvoiceList($cono, $customer);
                    //var_dump ($gcnl);
                    //exit;
                    if (isset ($gcnl["errordesc"])) {
                        if ($gcnl["errordesc"] != "") {
                            $noorder = true;
                        } else {
                            $noorder = false;
                        }
                    } else {
                        $noorder = false;
                    }

                    if ($noorder == true) {
                        $result .= "<tr><td colspan=9>" . $gcnl["errordesc"] . "</td></tr>";
                    } else {
                        if (isset ($gcnl["SalesCustomerInvoiceListResponseContainerItems"])) {
                            foreach ($gcnl["SalesCustomerInvoiceListResponseContainerItems"] as $item) {
                                $result .= $this->ShowInvoice($item, $invstartdate, $invenddate, $total, $paidtotal, $didthis, "!!!");//$Order["stagedesc"]
                                $recordcount++;
                            }
                        } else {
                            $result .= $this->ShowInvoice($gcnl, $invstartdate, $invenddate, $total, $paidtotal, $didthis, "222");
                            $recordcount++;
                        }

                        if ($gotocart == true) {
                            $this->_cart->save();
                            $url = $this->getUrl('checkout/cart');
                            header('Location: ' . $url);
                            die();
                        }
                    }
                } catch (\Exception $e) {
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "GWS Error: " . $e->getMessage());
                    $result .= '<table class="gwinvoicetable" border=0><tr><td>';
                    $result .= "<br>Error found.<br>" . $e->getMessage();
                    $result .= '</td></tr></table></table></table>';
                }
                $result .= '<input type=hidden id="startdate" name="startdate" value="' . $invstartdate . '">';
                $result .= '<input type=hidden id="enddate" name="enddate" value="' . $invenddate . '">';
                $result .= '<tr><td><input type=hidden name="recordcount" id="recordcount" value="' . $recordcount . '"><input class="action subscribe primary" title="Pay" value="Pay" type="submit"></td>';
                $result .= '<td colspan=7 align=right><strong>Invoice&nbsp;Total:</td><td align=right>$' . money_format('%.2n', (floatval($total))) . '</td><td align=right><strong>Paid&nbsp;Total:</strong></td><td align=right>$' . money_format('%.2n', (floatval($paidtotal))) . '</td></tr>';
                $result .= '</table>';
            } else { //show details

                // $result .= '777777777777777777';
                //************************************************************************************************************************************************
                //************************************************************************************************************************************************
                //************************************************************************************************************************************************
                //************************************************************************************************************************************************
                //************************************************************************************************************************************************

                try {
                    $gcnl = SalesCustomerInvoiceList($cono, $customer);
                    //var_dump ($gcnl);
                    //exit;
                    //  $result="";
                    if (isset ($gcnl["errordesc"])) {
                        $result .= "<tr><td colspan=9>" . $gcnl["errordesc"] . "</td></tr>";
                    } else {

                        //reorder ******************************************************
                        if (isset ($_POST["reorderitems"])) {
                            // $result .= "reorder";
                            if ($_POST["reorderitems"] == "yes") {
                                //reorder selected items
                                $iTotal = $_POST["totalitems"];
                                $paramsHead = new \ArrayObject();//(object)array();

                                $itemsadded = 0;
                                $lineno = 0;
                                for ($i = 1; $i <= $iTotal; $i++) {
                                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "Checking: " . $i);
                                    if (isset ($producttoadd)) {
                                        unset($producttoadd);
                                    }
                                    // $result .= $lineno;
                                    //***********************************reorder loop
                                    if (isset ($_POST['reorder' . $i])) {
                                        $lineno = $lineno + 1;
                                        $itemsadded += 1;

                                        $type = $_POST["reorderitem" . $i];  // $item->getSku();
                                        $qty = $_POST["reorderqty" . $i];  //$item->getQty();
                                        $unit = $_POST["reorderunit" . $i];  //'ea';

                                        try {
                                            $producttoadd = $this->productRepository->get($type);
                                            $this->csd->gwLog($i . "reorder product: " . $producttoadd->getId());
                                        } catch (\Magento\Framework\Exception\NoSuchEntityException $e5) {
                                            $this->_logger->addDebug('Product Error: ' . $e5->getMessage());
                                        }
                                        if (isset ($producttoadd)) {
                                            $this->_logger->addDebug('Getting prod id ' . $producttoadd->getId());
                                            $params = [
                                                'product' => $producttoadd->getId(),
                                                // 'price' =>"100" ,
                                                'qty' => $qty
                                            ];
                                            $this->_cart->addProduct($producttoadd, $params);
                                            $this->_cart->save();
                                        }
                                    }

                                    //***********************************close reorder loop
                                }
                            }
                        } else {

                            //$result .= " no reorder";
                        }

                        //done with reorder ********************************************
                        //   $result.="<style>gwinvoicebodytable, gwinvoicebodytable table, gwinvoicebodytable td{ padding:2px; }";

                        //   $result .="</style>";

                        $total = 0;
                        foreach ($gcnl["SalesCustomerInvoiceListResponseContainerItems"] as $item) {
                            $invoice = $item;
                            if ($invoice["invno"] . $invoice["invsuf"] == $invtodetail) {
                                $Order = SalesOrderSelect($cono, $invoice["invno"], $invoice["invsuf"]);
                                $Customer = SalesCustomerSelect($cono, $invoice["custno"]);
                                $Package = SalesPackagesSelect($cono, $invoice["invno"], $invoice["invsuf"]);
                                if (!isset ($Order["stagedesc"])) {
                                    foreach ($Order["SalesOrderSelectResponseContainerItems"] as $_order) {
                                        if ($_order["orderno"] == $invoice["invno"] && $_order["ordersuf"] == $invoice["invsuf"]) {
                                            $Order = $_order;
                                            break;
                                        }
                                    }
                                }
                            }

                            if ($invoice["invno"] . $invoice["invsuf"] == $invtodetail) {
                                try {
                                    if (isset ($Order["custpo"])) {
                                        $custpo = $Order["custpo"];
                                    } else {
                                        $custpo = "";
                                    }
                                } catch (\Exception $epo) {
                                    $custpo = "";
                                }
                                //**************************************

                                $result .= '<a href="#"><button type="button"  onclick="history.go(-1)" class="action subscribe primary" style="margin: 0 15px 10px 0px;width: 130px;">' . __('Back') . '</button></a><br>';
                                $result .= '<table class="gworderbodytable data">';

                                $result .= '<table class="gwinvoicebodytable data table"border=1 cellpadding=0 cellspacing=3px>';
                                $result .= '<tr><td><table >';
                                $result .= "<tr><td width=50%/><td style='font-weight:bold;' align=right>Date:</td><td>" . $invoice["invdt"] . "</td></tr>";
                                $result .= "<tr><td /><td style='font-weight:bold;' align=right>Invoice&nbsp;Number:</td><td width:150px>" . $invoice["invno"] . "-0" . $invoice["invsuf"] . "</td></tr>";
                                $result .= "<tr><td /><td style='font-weight:bold;' align=right>PO&nbsp;Number:</td><td>" . $custpo . "</td></tr>";
                                $result .= "<tr><td /><td style='font-weight:bold;' align=right>Stage:</td><td>" . $Order["stagedesc"] . "</td></tr>";
                                $result .= "<tr><td /><td style='font-weight:bold;' align=right>Customer&nbsp;Number:</td><td>" . $customer . "</td></tr>";
                                $result .= '</table></td></tr>';
                                $result .= '<tr><td><table border=0 class=" data table">';
                                $result .= '<tr><td /><td style="font-weight:bold;" align=left colspan=3>Billing</td><td style="font-weight:bold;" align=left colspan=3>Shipping</td></tr>';
                                $result .= "<tr><td width='10%'/><td class=invoicelinehead>" . $Customer["name"] . "</td><td /><td width='10%'/><td class=invoicelinehead>" . (isset ($Order["shiptonm"]) ? $Order["shiptonm"] : "") . "(" . $invoice["shipto"] . ")</td><td/></tr>";
                                $result .= "<tr><td/><td class=invoicelinehead>" . $Customer["addr1"] . "</td><td /><td/><td class=invoicelinehead>" . (isset ($Order["shiptoaddr1"]) ? $Order["shiptoaddr1"] : "") . "</td><td/></tr>";
                                if ($Customer["addr2"] . (isset ($Order["shiptoaddr2"]) ? $Order["shiptoaddr2"] : "") != "") {
                                    $result .= "<tr><td class=invoicelinehead class=invoicelinehead align=left>" . $Customer["addr2"] . "</td><td /><td/><td/><td>" . (isset ($Order["shiptoaddr2"]) ? $Order["shiptoaddr2"] : "") . "</td></tr>";
                                }
                                $result .= "<tr><td/><td class=invoicelinehead>" . $Customer["city"] . ", " . $Customer["statecd"] . "  " . $Customer["zipcd"] . "</td><td/><td /><td class=invoicelinehead>" . (isset ($Order["shiptocity"]) ? $Order["shiptocity"] : "") . "," . (isset ($Order["shiptost"]) ? $Order["shiptost"] : "") . "  " . (isset ($Order["shiptozip"]) ? $Order["shiptozip"] : "") . "</td></tr>";
                                $result .= "<tr><td/><td class=invoicelinehead>" . preg_replace('/\d{3}/', '$0-', str_replace('.', null, trim($Customer["phoneno"])), 2) . "</td><td/><td /><td></td><td/></tr>";
                                $result .= '</table></td></tr>';

                                $result .= '<tr><td><table class=" data table">';
                                $result .= '<tr><td style="font-weight:bold;" align=center>Ship Date</td><td style="font-weight:bold;" align=center>Ship Via</td><td style="font-weight:bold;" align=center>Terms</td></tr>';
                                $result .= "<tr><td align=center>" . (isset ($Order["shipdt"]) ? $Order["shipdt"] : "") . "</td><td align=center>" . (isset ($Order["shipviadesc"]) ? $Order["shipviadesc"] : "") . "</td><td align=center>" . (isset ($Order["termsdesc"]) ? $Order["termsdesc"] : "") . "</td></tr>";
                                $result .= "</table>";
                                if ($simplifyinvoice != 1) {
                                    $result .= "<table class=\" data table\"><tr><td align=center width='50%' style='font-weight:bold;'>Tracking Number</td><td align=center style='font-weight:bold;' width='50%' >Shipped</td></tr>";
                                    $result .= "<tr><td  align=center>" . (isset ($Order["trackerno"]) ? $Order["trackerno"] : "N/A") . "</td><td  align=center>" . (isset ($Order["shippedfl"]) ? $Order["shippedfl"] : "N/A") . "</td></tr>";
                                    $result .= '</table>';
                                }
                                $result .= '</td></tr>';
                                $result .= '</address>';
                                $result .= '</div>';
                                $result .= '</div>';

                                $result .= '</div>';
                                $result .= '</div>';

                                try { //lines
                                    $gcnlLine = SalesOrderLinesSelect($cono, $invoice["invno"], $invoice["invsuf"]);
                                    // var_dump($gcnlLine);
                                    // exit;
                                    if (1 == 2) { //(isset($gcnlLine["errordesc"])  ){
                                        $result .= "<tr><td colspan=9>Error retrieving lines</td></tr>";
                                        // $this->_logger->addDebug ("GWS Error: " . $gcnl["errordesc"] );
                                    } else {
                                        $result .= '<tr><td colspan=5><table class="gwinvoicelinetable data table">';
                                        $result .= '<tr class=invoicelinehead style="font-weight:bold;" align=center>';
                                        if ($simplifyinvoice != 1) {
                                            $result .= '<td>Reorder</td>';
                                        } else {
                                            $result .= '<td></td>';
                                        }
                                        $result .= '<td align=left>SKU</td>';
                                        $result .= '<td align=left>Description</td>';
                                        $result .= '<td align=right>Price</td>';
                                        $result .= '<td align=center>Unit</td>';
                                        $result .= '<td align=right>Qty&nbsp;Ordered</td>';
                                        $result .= '<td align=right>Qty&nbsp;Shipped</td>';
                                        $result .= '<td align=right>Net&nbsp;Amt</td>';

                                        $result .= '</tr>';
                                        $chkCounter = 1;
                                        if (isset ($gcnlLine["cono"])) {
                                            $itemLine = $gcnlLine;
                                            //  $uomqty=round($itemLine["price"]>0  ? $itemLine["netamt"] /  $itemLine["price"] : 1);

                                            // echo $uomqty . "###";
                                            //foreach($gcnlLine as $itemLine){
                                            $result .= '<tr class=invoiceline >';
                                            if ($simplifyinvoice != 1) {
                                                $result .= '<td align=center><input type=checkbox id="reorder' . $chkCounter . '" name="reorder' . $chkCounter . '" value="' . $itemLine["shipprod"] . '"><input type=hidden id="reorderitem' . $chkCounter . '" name="reorderitem' . $chkCounter . '"  value="' . $itemLine["shipprod"] . '"></td>';
                                            } else {
                                                $result .= '<td></td>';
                                            }
                                            //$result .='<td><a href="/index.php/catalog/product/view/id/:' . $itemLine["shipprod"] . '" alt="View Item" title="View Item">' . $itemLine["shipprod"] . '</a></td>';
                                            $result .= '<td>' . $itemLine["shipprod"] . '</td>';
                                            $result .= '<td>' . $itemLine["proddesc"] . '</td>';
                                            $result .= '<td align=right>$' . money_format('%.2n', $itemLine["price"]) . '<input type=hidden id="reorderprice' . $chkCounter . '" name="reorderprice' . $chkCounter . '"  value="' . $itemLine["price"] . '"></td>';
                                            $result .= '<td  align=center>' . $itemLine["unit"] . '<input type=hidden id="reorderunit' . $chkCounter . '" name="reorderunit' . $chkCounter . '"  value="' . $itemLine["unit"] . '"></td>';
                                            $result .= '<td align=right >' . $itemLine["qtyord"] . '<input type=hidden id="reorderqty' . $chkCounter . '" name="reorderqty' . $chkCounter . '"  value="' . $itemLine["qtyord"] . '"></td>';
                                            $result .= '<td align=right >' . $itemLine["qtyship"] . '</td>';//
                                            $result .= '<td align=right>$' . money_format('%.2n', $itemLine["netamt"]) . '</td>';
                                            $total += $itemLine["netamt"];
                                            $chkCounter += 1;
                                            $result .= '</tr>';
                                            // }//foreach itemline
                                        } else {
                                            foreach ($gcnlLine["SalesOrderLinesSelectResponseContainerItems"] as $itemLine) {
                                                // $uomqty=round($itemLine["price"]>0  ? $itemLine["netamt"] /  $itemLine["price"] : 1);
                                                // echo $uomqty . "###";
                                                $result .= '<tr class=invoiceline >';
                                                if ($simplifyinvoice != 1) {
                                                    $result .= '<td align=center><input type=checkbox id="reorder' . $chkCounter . '" name="reorder' . $chkCounter . '" value="' . $itemLine["shipprod"] . '"><input type=hidden id="reorderitem' . $chkCounter . '" name="reorderitem' . $chkCounter . '"  value="' . $itemLine["shipprod"] . '"></td>';
                                                } else {
                                                    $result .= '<td></td>';
                                                }
                                                //$result .='<td><a href="/index.php/catalog/product/view/id/:' . $itemLine["shipprod"] . '" alt="View Item" title="View Item">' . $itemLine["shipprod"] . '</a></td>';
                                                $result .= '<td>' . $itemLine["shipprod"] . '</td>';
                                                $result .= '<td>' . $itemLine["proddesc"] . '</td>';
                                                $result .= '<td align=right>$' . money_format('%.2n', $itemLine["price"]) . '<input type=hidden id="reorderprice' . $chkCounter . '" name="reorderprice' . $chkCounter . '"  value="' . $itemLine["price"] . '"></td>';
                                                $result .= '<td align=center>' . $itemLine["unit"] . '<input type=hidden id="reorderunit' . $chkCounter . '" name="reorderunit' . $chkCounter . '"  value="' . $itemLine["unit"] . '"></td>';
                                                $result .= '<td align=right >' . $itemLine["qtyord"] . '<input type=hidden id="reorderqty' . $chkCounter . '" name="reorderqty' . $chkCounter . '"  value="' . $itemLine["qtyord"] . '"></td>';
                                                $result .= '<td align=right >' . $itemLine["qtyship"] . '</td>';//$itemLine["qtyship"] * ($itemLine["stkqtyord"]>1 ? 1 : 1 ). '-'
                                                $result .= '<td align=right>$' . money_format('%.2n', $itemLine["netamt"]) . '</td>';
                                                $total += $itemLine["netamt"];
                                                $chkCounter += 1;
                                                $result .= '</tr>';
                                            }//foreach itemline
                                        }
                                        $chkCounter -= 1;
                                        $result .= '<tr><td><input type=hidden id=reorderitems name=reorderitems value="yes"><input type=hidden id=totalitems name=totalitems value=' . $chkCounter . '>';
                                        if ($simplifyinvoice != 1) {
                                            $result .= '<input type=submit value="Reorder">';
                                        }
                                        $result .= '</td><td colspan=6 align=right><strong>Subotal:</td><td align=right>$' . money_format('%.2n', (floatval($total))) . '</td></tr>';
                                        $result .= '<tr><td colspan=7 align=right><strong>Tax:</td><td align=right>$' . money_format('%.2n', (floatval((isset ($Order["taxamt"]) ? $Order["taxamt"] : "")))) . '</td></tr>';
                                        $result .= '<tr><td colspan=7 align=right><strong>Total:</td><td align=right>$' . money_format('%.2n', (floatval($invoice["amount"]))) . '</td></tr></table></td></tr>';
                                        if ($Order["stagecd"] == "4") {
                                            //$Order("stagecd")
                                            if ($paycart !== 'cart') {
                                                $result .= '<tr><td colspan=8 align=right><a href="?invoice=' . $invoice["invno"] . '&invoicesuf=' . $invoice["invsuf"] . '&pay=true"><button type="button" class="action subscribe primary">Pay Invoice</button></a></td></tr></table></td></tr>';
                                            }
                                            if ($pay == 'true' && $paycart !== 'cart') {
                                                $this->PayInvoice($invorderno, $invordersuf, $invoice["amount"], true);
                                            }
                                        }
                                    } //if not line error
                                } //try lines
                                catch (\Exception $e) {
                                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "GWS Error: " . $e->getMessage());
                                    $result .= '<table class="gwinvoicetable" border=0><tr><td>';
                                    $result .= "<br>Error found.<br>" . $e->getMessage();
                                    $result .= '</td></tr></table></table></table>';
                                }
                                break;
                            }//if active invoice
                        }//foreach

                        $result .= '</table>';
                    } // if not header error
                } //try header
                catch (\Exception $eheader) {
                    $this->_logger->addDebug("GWS Error: " . $eheader->getMessage());
                    $this->csd->gwLog(__CLASS__ . "/" . __FUNCTION__ . ": ", "GWS Error: " . $eheader->getMessage());
                    $result .= '<table class="gwinvoicetable" border=0><tr><td>';
                    $result .= "<br>Error found.<br>" . $eheader->getMessage();
                    $result .= '</td></tr></table></table></table>';
                }

                // *******************************************
            } //detail or header
        }

        //$result .= "</form>";
        return $result;
    }

    //function
}
