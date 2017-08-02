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

use \Svea\Checkout\CheckoutClient;
use \Svea\Checkout\Transport\Connector;

class SveaCheckoutConfirmationModuleFrontController extends ModuleFrontController {
	
	public $display_column_left = false;
	public $display_column_right = false;
	public $ssl = true;


	
	public function initContent()
	{
		parent::initContent();

		if (!$this->context->cookie->__isset('svea_order_id'))
			Tools::redirect($this->context->link->getPageLink(
            'cart',
            null,
            $this->context->language->id,
            array(
                'action' => 'show'
            )
        ));

		$checkoutMerchantId = Configuration::get('SVEACHECKOUT_MERCHANT');
        $checkoutSecret = Configuration::get('SVEACHECKOUT_SECRET');

        $baseUrl = (int)Configuration::get('SVEACHECKOUT_MODE') === 1 ? \Svea\Checkout\Transport\Connector::PROD_BASE_URL : \Svea\Checkout\Transport\Connector::TEST_BASE_URL;

		$conn = Connector::init($checkoutMerchantId, $checkoutSecret, $baseUrl);
        $checkoutClient = new CheckoutClient($conn);

		$order = $checkoutClient->get(array('orderId' => (int)$this->context->cookie->__get('svea_order_id')));

		$this->context->smarty->assign(array(
			'snippet' => $order['Gui']['Snippet']
		));

		$this->context->cookie->__unset('svea_order_id');

		$this->setTemplate('module:sveacheckout/views/templates/front/confirmation.tpl');
	}
}
