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

namespace SveaCheckout;

use Language;
use OrderState;
use Configuration;

class CreateOrderStatus implements StepInterface {

	public function create()
	{
		if (!Configuration::get('SVEACHECKOUT_OS_PENDING')) {
			$pending = new OrderState();
			$pending->name = array();
			foreach (Language::getLanguages() as $language) {
				$pending->name[$language['id_lang']] = 'Pending order';
			}
			$pending->send_email = false;
            $pending->color = '#0080FF';
            $pending->hidden = false;
            $pending->delivery = false;
            $pending->logoable = true;
            $pending->invoice = false;
            $pending->paid = false;
            $pending->save();
            
            Configuration::updateValue('SVEACHECKOUT_OS_PENDING', (int)$pending->id);
		}
	}
}