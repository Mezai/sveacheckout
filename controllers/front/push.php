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

        //supports only SE at the moment
        $country = 'SE';

    	$id = Tools::getValue('svea_order');
        file_put_contents("data.txt", 'svea_order id : '. $id , FILE_APPEND);

        $baseUrl = (int)Configuration::get('SVEACHECKOUT_MODE') === 1 ? Connector::PROD_BASE_URL : Connector::TEST_BASE_URL;

    	$conn = Connector::init((int)Configuration::get('SVEACHECKOUT_MERCHANT'), (string)Configuration::get('SVEACHECKOUT_SECRET'), $baseUrl);
        $checkoutClient = new CheckoutClient($conn);
    	
    	try {
    		$order = $checkoutClient->get(array(
                'orderId' => (int)$id
            ));
    		if ($order['Status'] === 'PaymentGuaranteed' || $order['Status'] === 'Final') {
                $cart = new Cart((int)$order['ClientOrderNumber']);
                if ($cart->orderExists()) {
                    die;
                }

                $id_customer = (int)Customer::customerExists($order['EmailAddress'], true, true);

                if ($id_customer > 0)
                {
                    $customer = new Customer($id_customer);
                } 
                else
                {
                    $billingAddress = $order['BillingAddress'];
                    $customer = new Customer();
                    $customer->firstname = $billingAddress['FirstName'];
                    $customer->lastname = $billingAddress['LastName'];
                    $customer->email = $order['EmailAddress'];
                    $customer->passwd = Tools::passwdGen(8, 'ALPHANUMERIC');
                    $customer->is_guest = 1;
                    $customer->id_default_group = (int)Configuration::get('PS_GUEST_GROUP', null, $cart->id_shop);
                    $customer->newsletter = 0;
                    $customer->optin = 0;
                    $customer->active = 1;
                    $customer->id_gender = 0;
                    $customer->add();
                }

                $delivery_address_id = 0;
                $invoice_address_id = 0;
                $invoice_iso = $order['BillingAddress']['CountryCode'];
                $shipping_iso = $order['ShippingAddress']['CountryCode'];

                $shipping_country_id = Country::getByIso($shipping_iso);
                $invoice_country_id = Country::getByIso($invoice_iso);

                $shipping = $order['ShippingAddress'];
                $billing = $order['BillingAddress'];

                
                if ($country === 'SE')
                {
                    foreach ($customer->getAddresses($cart->id_lang) as $address) {
                        if ($address['firstname'] == $shipping['FirstName'] && $address['lastname'] == $shipping['LastName'] && $address['city'] == $shipping['City'] && $address['address2'] == $shipping['CoAddress'] && $address['address1'] == $shipping['StreetAddress'] && $address['postcode'] == $shipping['PostalCode'] && $address['id_country'] == $shipping_country_id) {
                            $cart->id_address_delivery = $address['id_address'];
                            $delivery_address_id = $address['id_address'];
                        }

                        if ($address['firstname'] == $billing['FirstName'] && $address['lastname'] == $billing['LastName'] && $address['city'] == $billing['City'] && $address['address2'] == $billing['CoAddress'] && $address['address1'] == $billing['StreetAddress'] && $address['postcode'] == $billing['PostalCode'] && $address['id_country'] == $shipping_country_id) {
                            $cart->id_address_invoice = $address['id_address'];
                            $invoice_address_id = $address['id_address'];
                        }
                    }
                }

                if ($invoice_address_id == 0)
                {
                    $address = new Address();
                    $address->firstname = $billing['FirstName'];
                    $address->lastname = $billing['LastName'];

                    if ($country == 'SE') {
                        if (Tools::strlen($billing['CoAddress']) > 0)
                        {
                            $address->address1 = $billing['CoAddress'];
                            $address->address2 = $billing['StreetAddress'];

                        }
                        else
                        {
                            $address->address1 = $billing['StreetAddress'];
                        }
                    }

                    $address->postcode = $billing['PostalCode'];
                    $address->phone = $order['PhoneNumber'];
                    $address->phone_mobile = $order['PhoneNumber'];
                    $address->city = $billing['City'];
                    $address->id_country = $invoice_country_id;
                    $address->id_customer = $customer->id;
                    $address->alias = 'Svea Address';
                    $address->add();
                    $cart->id_address_invoice = $address->id;
                    $invoice_address_id = $address->id;
                }
                if ($delivery_address_id == 0)
                {
                    $address = new Address();
                    $address->firstname = $shipping['FirstName'];
                    $address->lastname = $shipping['LastName'];

                    if ($country == 'SE') {
                        if (Tools::strlen($shipping['CoAddress']) > 0)
                        {
                            $address->address1 = $shipping['CoAddress'];
                            $address->address2 = $shipping['StreetAddress'];

                        }
                        else
                        {
                            $addres->address1 = $shipping['StreetAddress'];
                        }
                    }

                    $address->city = $shipping['City'];
                    $address->postcode = $shipping['PostalCode'];
                    $address->phone = $order['PhoneNumber'];
                    $address->phone_mobile = $order['PhoneNumber'];
                    $address->id_country = $shipping_country_id;
                    $address->id_customer = $customer->id;
                    $address->alias = 'Svea Address';
                    $address->add();
                    $cart->id_address_delivery = $address->id;
                    $delivery_address_id = $address->id;

                }

                $cart->id_customer = $customer->id;
                $cart->secure_key = $customer->secure_key;
                $cart->update();

                Db::getInstance()->update('cart', array('id_customer' => pSQL($customer->id)), 'id_cart='.(int)$cart->id);
                Db::getInstance()->update('cart', array('secure_key' => pSQL($customer->secure_key)), 'id_cart='.(int)$cart->id);
                Cache::clean('objectmodel_cart_'.$cart->id.'_0_0');

                $amount = $cart->getOrderTotal(true, Cart::BOTH);

                $this->module->validateOrder(
                    $cart->id,
                    Configuration::get('PS_OS_PAYMENT'),
                    $amount,
                    $this->module->displayName,
                    $order['OrderId'],
                    array(
                        'transaction_id' => $order['OrderId']
                    ),
                    $cart->id_currency,
                    false,
                    $customer->secure_key
                );
    		}

            $ps_order = Order::getByCartId($cart->id);

            $ps_order->id_carrier = $cart->id_carrier;
            $ps_order->save();

            $ps_order->refreshShippingCost();

            Db::getInstance()->update('order_carrier', array('id_carrier' => pSQL($cart->id_carrier)), 'id_order='.(int)$ps_order->id);

    	} catch (\Exception $e) {
            Logger::addLog('Svea checkout error message ' . $e->getMessage() . ' and error code ' . $e->getCode());
    	}
    }
}