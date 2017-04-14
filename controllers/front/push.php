<?php
/**
* 2007-2017 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2017 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use Svea\WebPay\WebPay;
use Svea\WebPay\Config\ConfigurationService;
use SveaCheckout\CheckoutOrder;
use PrestaShop\PrestaShop\Core\Foundation\IoC\Container;
use SveaCheckout\CreateOrderStatus;

class SveaCheckoutPushModuleFrontController extends ModuleFrontController
{
	public $display_column_left = false;
    public $display_column_right = false;
    public $ssl =  true;

    private $checkoutOrder;

    public function __construct()
    {

    	//$this->checkoutOrder = $status;

    	$container = new Container();
    	$container->bind('\\SveaCheckout\\CreateOrderStatus', '\\SveaCheckout\\CreateOrderStatus', true);

    	$status = $this->get('\\SveaCheckout\\CreateOrderStatus');
    	dump($status);
    	dump(CheckoutOrder::class);
    	header('HTTP/1.1 200 OK', true, 200);
                    exit;
    }


    public function initContent()
    {


    	parent::initContent();
    	header('HTTP/1.1 200 OK', true, 200);
        exit;

    	$id = Tools::getValue('svea_order');

    	$config = ConfigurationService::getTestConfig();
		$orderBuilder = WebPay::checkout($config);
		$orderBuilder->setCheckoutOrderId((int)$id)
    		->setCountryCode('SE');
    	
    	try {
    		$order = $orderBuilder->getOrder();

    		file_put_contents("data.txt", print_r($order, true), FILE_APPEND);

    		if ($order['Status'] === 'PaymentGuaranteed') {
    		
    			$this->setOrderPending($order);
    		}


    	} catch (\Exception $e) {

    	}
    }


}