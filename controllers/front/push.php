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

use Svea\Checkout\CheckoutClient;
use Svea\Checkout\Transport\Connector;

class SveaCheckoutPushModuleFrontController extends ModuleFrontController
{
	public $display_column_left = false;
    public $display_column_right = false;
    public $ssl =  true;

    public function initContent()
    {

    	parent::initContent();


    	$id = Tools::getValue('svea_order');
        file_put_contents("data.txt", 'svea_order id : '. $id , FILE_APPEND);

        $baseUrl = (int)Configuration::get('SVEACHECKOUT_MODE') === 1 ? Connector::PROD_BASE_URL : Connector::TEST_BASE_URL;

    	$conn = Connector::init((int)Configuration::get('SVEACHECKOUT_MERCHANT'), (string)Configuration::get('SVEACHECKOUT_SECRET'), $baseUrl);
        $checkoutClient = new CheckoutClient($conn);
    	
    	try {
    		$order = $checkoutClient->get(array(
                'orderId' => (int)$id
            ));

    		file_put_contents("data.txt", print_r($order, true), FILE_APPEND);

    		if ($order['Status'] === 'PaymentGuaranteed' || $order['Status'] === 'Final') {

                $billingAddress = $order['BillingAddress'];

                $customer = new Customer();
                $customer->firstname = $billingAddress['FirstName'];
                $customer->lastname = $billingAddress['LastName'];
                $customer->email = $order['EmailAddress'];
                $customer->passwd = Tools::passwdGen(8, 'ALPHANUMERIC');
                $customer->is_guest = 1;
                $customer->id_default_group = (int)Configuration::get('PS_GUEST_GROUP', null);
                $customer->newsletter = 0;
                $customer->optin = 0;
                $customer->active = 1;
                $customer->id_gender = 0;
                $customer->add();
    		      
    			$amount = $this->getSveaOrderTotal($order);

                $cart = new Cart((int)$order['ClientOrderNumber']);
                $this->module->validateOrder(
                    $cart->id,
                    Configuration::get('PS_OS_PAYMENT'),
                    $amount,
                    $this->module->displayName,
                    $order['OrderId'],
                    array(
                        'transaction_id' => $order['OrderId']
                    ),
                    null,
                    false,
                    $customer->secure_key
                );
    		}


    	} catch (\Exception $e) {
            Logger::addLog('Svea checkout error message ' . $e->getMessage() . ' and error code ' . $e->getCode());
    	}
    }


    private function getSveaOrderTotal($order)
    {
        $amount = 0;
        foreach ($order['Cart']['Items'] as $key => $value) {
            $amount = $key['Quantity'] * $key['UnitPrice'];
        }
        return $amount;
    }


}