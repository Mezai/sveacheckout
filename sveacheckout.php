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

require dirname(__FILE__) . '/vendor/autoload.php';


use SveaCheckout\CreateOrderStatus;
use SveaCheckout\CreateOrderTabs;
use PrestaShop\PrestaShop\Adapter\Carrier\CarrierDataProvider;
use Svea\Checkout\CheckoutClient;
use Svea\Checkout\Transport\Connector;

class sveacheckout extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    public function __construct()
    {
        $this->name = 'sveacheckout';
        $this->version = '1.0.0';
        $this->author = 'JET';
        $this->tab = 'payments_gateways';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();
        $this->displayName = $this->l('Svea checkout');
        $this->description = $this->l('Lets your customers pay via Svea checkout');
        if (!extension_loaded('curl')) {
            $this->warning = $this->l('You need to activate curl to use Svea checkout');
        }
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('header')
            && $this->registerHook('displayShoppingCart')
            && $this->registerHook('displayShoppingCartFooter')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayCarrierList');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->unregisterHook('header');
    }


    public function prepareCarriers()
    {
        $id_country = Country::getByiso('se');
        $delivery_option = $this->context->cart->getDeliveryOption(
            new Country($id_country),
            false,
            false
        );

        $free_shipping = false;
        foreach ($this->context->cart->getCartRules() as $rule) {
            if ($rule['free_shipping']) {
                $free_shipping = true;
                break;
            }
        }


        $carrier = new CarrierDataProvider();
        $delivery_options = $carrier->getCarriers(
               $this->context->language->id,
               true,
               false,
               false,
               null,
               CarrierCore::ALL_CARRIERS
           );

        $this->smarty->assign(array(
            'delivery_options' => $delivery_options,
            'delivery_option' => $delivery_option,
            'gift' => $this->context->cart->gift,
            'id_address' => $this->context->cart->id_address_delivery,
            'recyclablePackAllowed' => (int)Configuration::get('PS_RECYCLABLE_PACK'),
        ));
    }

    public function hookDisplayShoppingCartFooter()
    {
        $order = null;
        $locale = 'sv-Se';
        $checkoutMerchantId = Configuration::get('SVEACHECKOUT_MERCHANT');
        $checkoutSecret = Configuration::get('SVEACHECKOUT_SECRET');

        $baseUrl = (int)Configuration::get('SVEACHECKOUT_MODE') === 1 ? \Svea\Checkout\Transport\Connector::PROD_BASE_URL : \Svea\Checkout\Transport\Connector::TEST_BASE_URL;

        $conn = Connector::init($checkoutMerchantId, $checkoutSecret, $baseUrl);
        $checkoutClient = new CheckoutClient($conn);


        $cart = $this->context->cart;

        $cms = new CMS(
            (int)Configuration::get('SVEACHECKOUT_TERMS'),
            (int)$this->context->cookie->id_lang
        );

        $linkConditions = $this->context->link->getCMSLink($cms, $cms->link_rewrite, Tools::usingSecureMode());
        $pushPage = $this->context->link->getModuleLink('sveacheckout', 'push', array(), Tools::usingSecureMode());
        $pushPage .= '?svea_order={checkout.order.uri}';

        $confirmationUri = $this->context->link->getModuleLink('sveacheckout', 'confirmation', array(),
            Tools::usingSecureMode()
        );

        $checkoutUri = $this->context->link->getPageLink(
            'cart',
            null,
            $this->context->language->id,
            array(
                'action' => 'show'
            )
        );

        $data = array(
            "countryCode" => $this->context->country->iso_code,
            "currency" => $this->context->currency->iso_code,
            "locale" => $locale,
            "clientOrderNumber" => (int)$this->context->cart->id,
            'cart' => array(
                'items' => array(
                )
            ),
            'presetValues' => array(
            ),
            'merchantSettings' => array(
                "termsUri" => $linkConditions,
                "checkoutUri" => $checkoutUri,
                "confirmationUri" => $confirmationUri,
                "pushUri" => $pushPage
            ),

        );

        if ((int)Configuration::get('SVEACHECKOUT_ISCOMPANY') === 1 || (int)Configuration::get('SVEACHECKOUT_ISCOMPANY') === 2) {
            $data['presetValues'][] = array(
                'typeName' => 'isCompany',
                'value' => (int)Configuration::get('SVEACHECKOUT_ISCOMPANY') === 1 ? true : false,
                'isReadonly' => true
            );

        }

        foreach ($cart->getProducts() as $product) {
            $data['cart']['items'][] = array(
                'articleNumber' => $product['reference'],
                'name' => $product['name'],
                'quantity' => (int)$product['quantity'] * 100,
                'unitPrice' => $product['price_wt'] * 100,
                "discountPercent" => 0,
                "vatPercent" => (int)$product['rate'] * 100,
                'temporaryReference' => $product['reference']

            );
        }

        $shipping_price_wt = $this->context->cart->getOrderTotal(true, Cart::ONLY_SHIPPING);
        $shipping_price = $this->context->cart->getOrderTotal(false, Cart::ONLY_SHIPPING);

        $carrier = new Carrier($this->context->cart->id_carrier);
        $carrieraddress = new Address($this->context->cart->id_address_delivery);
        $carriertaxrate = $carrier->getTaxesRate($carrieraddress);

        if ($shipping_price > 0) {
            $data['cart']['items'][] = array(
                "type" => "shipping_fee",
                "articleNumber" => "",
                "name" => strip_tags($carrier->name),
                "quantity" => 100,
                "unitPrice" => $shipping_price_wt * 100,
                "vatPercent" => (int)$carriertaxrate * 100
            );
        }

        $this->prepareCarriers();

        if ($this->context->cookie->__isset('svea_order_id')) {
            // resume session
            try {
                $order = $checkoutClient->update($data);
            } catch (\Exception $e) {
                $order = null;
                $this->context->cookie->__unset('svea_order_id');
            }
        }

        if ($order == null) {

            try {
                $order = $checkoutClient->create($data);
            } catch(\Exception $e) {
                Logger::addLog('Svea order failed with message ' . $e->getMessage() . 'and error code ' . $e->getCode());
            }
        }

        
        
        
        $orderId = $order['OrderId'];
        $guiSnippet = $order['Gui']['Snippet'];
        var_dump($orderId);

        $this->context->cookie->__set('svea_order_id', $orderId);


        if (isset($guiSnippet)) {
            $this->context->smarty->assign(array(
                'snippet' => $guiSnippet,
                'id_address' => $this->context->cart->id_address_invoice
            ));
            return $this->display(__FILE__, 'sveacheckout.tpl');
        }
    }

    public function hookDisplayShoppingCart()
    {
        return $this->display(__FILE__, 'carriers.tpl');
    }

    public function hookDisplayHeader()
    {
        $this->context->controller->registerStylesheet(
            'module-sveacheckout-styles',
            'modules/'.$this->name.'/views/css/checkout.css',
            [
              'media' => 'all',
              'priority' => 200,
            ]
        );

        $this->context->controller->registerJavascript(
            'module-sveacheckout-script',
            'modules/'.$this->name.'/views/js/sveacheckout.js',
            [
                'priority' => 200,
                'attribute' => 'async',
            ]

        );
    }

    public function renderForm()
    {
        $isCompany = array(
            array(
                'id_option' => 0,
                'name' => 'None'
            ),
            array(
                'id_option' => 1,
                'name' => 'Company'
            ),
            array(
                'id_option' => 2,
                'name' => 'Private person'
            ),
        );

        $fields_form = array(
        'form' => array(
          'legend' => array(
            'title' => $this->l('SveaCheckout settings'),
            'icon' => 'icon-cogs',
          ),
          'input' => array(
            array(
                'type' => 'text',
                'label' => $this->l('Merchant id'),
                'desc' => $this->l('Fill in the merchant id'),
                'name' => 'SVEACHECKOUT_MERCHANT',
                'required' => true
            ),
            array(
                'type' => 'text',
                'label' => $this->l('Secret'),
                'desc' => $this->l('Fill in the secret'),
                'name' => 'SVEACHECKOUT_SECRET',
                'required' => true
            ),
            array(
                'label' => $this->trans('Page for the Terms and conditions', array(), 'Admin.Shopparameters.Feature'),
                'desc' => $this->trans('Choose the page which contains your store\'s terms and conditions of use.', array(), 'Admin.Shopparameters.Help'),
                'validation' => 'isInt',
                'type' => 'select',
                'name' => 'SVEACHECKOUT_TERMS',
                'options' => array(
                     'query'=> CMS::listCms($this->context->language->id),
                     'id' => 'id_cms',
                     'name' => 'meta_title'
                     ),
                
            ),
            array(
                'type' => 'switch',
                'label' => $this->l('Live mode'),
                'desc' => $this->l('Select test or live mode'),
                'name' => 'SVEACHECKOUT_MODE',
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->l('Yes'),
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->l('No'),
                    ),
                ),
            ),
            array(
                'type' => 'select',
                'label' => $this->l('Preset customer type'),
                'desc' => $this->l('Select to preset the customer type in checkout'),
                'name' => 'SVEACHECKOUT_ISCOMPANY',
                'options' => array(
                    'query' => $isCompany,
                    'id' => 'id_option',
                    'name' => 'name'
                ),

            ),
          ),
          'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'button pull-right',
                    ),
              ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')
        ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'saveBtn';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).
        '&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
            );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'SVEACHECKOUT_MODE' => Tools::getValue('SVEACHECKOUT_MODE', Configuration::get('SVEACHECKOUT_MODE')),
            'SVEACHECKOUT_MERCHANT' => Tools::getValue('SVEACHECKOUT_MERCHANT', Configuration::get('SVEACHECKOUT_MERCHANT')),
            'SVEACHECKOUT_SECRET' => Tools::getValue('SVEACHECKOUT_SECRET', Configuration::get('SVEACHECKOUT_SECRET')),
            'SVEACHECKOUT_TERMS' => Tools::getValue('SVEACHECKOUT_TERMS', Configuration::get('SVEACHECKOUT_TERMS')),
            'SVEACHECKOUT_ISCOMPANY' => Tools::getValue('SVEACHECKOUT_ISCOMPANY', Configuration::get('SVEACHECKOUT_ISCOMPANY')),
         );
    }
    protected function _postProcess()
    {
        if (Tools::isSubmit('saveBtn')) {
            Configuration::updateValue('SVEACHECKOUT_MERCHANT', Tools::getValue('SVEACHECKOUT_MERCHANT'));
            Configuration::updateValue('SVEACHECKOUT_SECRET', Tools::getValue('SVEACHECKOUT_SECRET'));
            Configuration::updateValue('SVEACHECKOUT_MODE', Tools::getValue('SVEACHECKOUT_MODE'));
            Configuration::updateValue('SVEACHECKOUT_TERMS', Tools::getValue('SVEACHECKOUT_TERMS'));
            Configuration::updateValue('SVEACHECKOUT_ISCOMPANY', Tools::getValue('SVEACHECKOUT_ISCOMPANY'));

        }

        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Global'));
    }

    protected function _postValidation()
    {
        if (Tools::isSubmit('saveBtn')) {
            if (!Tools::getValue('SVEACHECKOUT_SECRET')) {
                $this->_postErrors[] = $this->trans('Secret is required', array(), 'Modules.SveaCheckout.Admin');
            }

            if (!Tools::getValue('SVEACHECKOUT_MERCHANT')) {
                $this->_postErrors[] = $this->trans('Merchant id is required', array(), 'Modules.SveaCheckout.Admin');
            }
        }
    }


    public function getContent()
    {
        if (Tools::isSubmit('saveBtn')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->renderForm();

        return $this->_html;
    }
}
