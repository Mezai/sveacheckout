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


use Svea\WebPay\WebPay;
use Svea\WebPay\WebPayItem;
use Svea\WebPay\Config\ConfigurationService;
use Svea\WebPay\Checkout\Model\PresetValue;
use SveaCheckout\CreateOrderStatus;
use SveaCheckout\CreateOrderTabs;
use PrestaShop\PrestaShop\Adapter\Carrier\CarrierDataProvider;

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

        $steps = array(
            new CreateOrderStatus,
            new CreateOrderTabs
        );

        foreach ($steps as $step) {
           $step->create();
        }
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
        $config = ConfigurationService::getTestConfig();
        $orderBuilder = WebPay::checkout($config);

        $cart = $this->context->cart;
        $products = $cart->getProducts();

        foreach ($products as $product) {
            $item = WebPayItem::orderRow()
            ->setAmountIncVat($product['price_wt'])
            ->setVatPercent((int)$product['rate'])
            ->setQuantity((int)$product['quantity'])
            ->setArticleNumber($product['reference'])
            ->setTemporaryReference($product['reference'])
            ->setName($product['name']);

            $orderBuilder->addOrderRow($item);
        }

        $shipping_price_wt = $this->context->cart->getOrderTotal(true, Cart::ONLY_SHIPPING);
        $shipping_price = $this->context->cart->getOrderTotal(false, Cart::ONLY_SHIPPING);

        $carrier = new Carrier($this->context->cart->id_carrier);
        $carrieraddress = new Address($this->context->cart->id_address_delivery);
        $carriertaxrate = $carrier->getTaxesRate($carrieraddress);

        if ($shipping_price > 0) {
            $shippingItem = WebPayItem::shippingFee()
            ->setAmountIncVat($shipping_price_wt)
            ->setVatPercent((int)$carriertaxrate)
            ->setName(strip_tags($carrier->name));

            $orderBuilder->addFee($shippingItem);
        }

        if ($this->context->cookie->__isset('svea_order_id')) {
            // resume the session
            $orderBuilder->setCheckoutOrderId((int)$this->context->cookie->__get('svea_order_id'))
                ->setCountryCode('SE'); // optional line of code

               $this->prepareCarriers(); 
            try {
                $orderBuilder->getOrder();
                $order = $orderBuilder->updateOrder();
            } catch (\Exception $e) {
                $order = null;
                $this->context->cookie->__unset('svea_order_id');
            }
        }

        if ($order == null) {
            $this->prepareCarriers();

            $cms = new CMS(
                (int)Configuration::get('SVEACHECKOUT_TERMS'),
                (int)$this->context->cookie->id_lang
            );

            $link_conditions = $this->context->link->getCMSLink($cms, $cms->link_rewrite, Tools::usingSecureMode());
            $pushPage = $this->context->link->getModuleLink('sveacheckout', 'push', array(), Tools::usingSecureMode());
            $pushPage .= '?svea_order={checkout.order.uri}';
            $orderBuilder->setCountryCode(Tools::strtoupper($this->context->country->iso_code))
            ->setCurrency($this->context->currency->iso_code)
            ->setClientOrderNumber(rand(1000010, 900000))
            ->setCheckoutUri(
                $this->context->link->getPageLink(
                'cart',
                null,
                $this->context->language->id,
                array(
                    'action' => 'show'
                )
            ))
            ->setConfirmationUri(
                $this->context->link->getModuleLink('sveacheckout', 'confirmation',
                    array(),
                    Tools::usingSecureMode()
            ))
            ->setPushUri(
                    $pushPage
                )
            ->setTermsUri($link_conditions)
            ->setLocale($locale);
    
            try {
                $order = $orderBuilder->createOrder();
            } catch (\Exception $e) {
                Logger::addLog('Svea order failed with message ' . $e->getMessage() . 'and error code ' . $e->getCode());
            }
        }
        $this->context->cookie->__set('svea_order_id', $order['OrderId']);

        if (isset($order['Gui']['Snippet'])) {
            $this->context->smarty->assign(array(
                'snippet' => $order['Gui']['Snippet']
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
         );
    }
    protected function _postProcess()
    {
        if (Tools::isSubmit('saveBtn')) {
            Configuration::updateValue('SVEACHECKOUT_MERCHANT', Tools::getValue('SVEACHECKOUT_MERCHANT'));
            Configuration::updateValue('SVEACHECKOUT_SECRET', Tools::getValue('SVEACHECKOUT_SECRET'));
            Configuration::updateValue('SVEACHECKOUT_MODE', Tools::getValue('SVEACHECKOUT_MODE'));
            Configuration::updateValue('SVEACHECKOUT_TERMS', Tools::getValue('SVEACHECKOUT_TERMS'));
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
