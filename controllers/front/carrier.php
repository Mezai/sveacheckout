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

class SveaCheckoutCarrierModuleFrontController extends ModuleFrontController {
	
	public $display_column_left = false;
	public $display_column_right = false;
	public $ssl = true;

	public function postProcess()
	{
		parent::postProcess();	

        if (Tools::getIsset('delivery_option'))
		{
			if ($this->validateDeliveryOption(Tools::getValue('delivery_option')))
				$this->context->cart->setDeliveryOption(Tools::getValue('delivery_option'));
			if (!$this->context->cart->update())
				$this->context->smarty->assign('klarna_carrier_error', Tools::displayError('Could not save carrier selection'));
		}
		CartRule::autoRemoveFromCart($this->context);
		CartRule::autoAddToCart($this->context);

		$this->redirectWithNotifications($this->getCartSummaryURL());
	}

	private function getCartSummaryURL()
    {
        return $this->context->link->getPageLink(
            'cart',
            null,
            $this->context->language->id,
            array(
                'action' => 'show'
            )
        );
    }

	public function validateDeliveryOption($delivery_option)
	{
		if (!is_array($delivery_option))
			return false;
		foreach ($delivery_option as $option)
		{
			if (!preg_match('/(\d+,)?\d+/', $option))
		return false;
		}
		return true;
	}
}