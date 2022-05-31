<?php

/**
 * 2007-2015 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
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
 *  @copyright 2007-2015 PrestaShop SA
 *  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit();
}

class Zenkipay extends PaymentModule
{
    private $error = [];
    private $validation = [];

    public function __construct()
    {
        $this->sandbox_url = 'https://dev-gateway.zenki.fi/public/v1/merchants/plugin/token';
        $this->url = 'https://uat-gateway.zenki.fi/public/v1/merchants/plugin/token';

        $this->name = 'zenkipay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'PayByWallet, Inc';

        parent::__construct();
        $warning = $this->l('Are you sure you want uninstall this module?');
        $this->displayName = 'Zenkipay';
        $this->description = $this->l('Accept cryptos from any wallet, any coin at your check out page!');
        $this->confirmUninstall = $this->l($warning);
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
    }

    /**
     * Zenkipay's module installation
     *
     * @return boolean Install result
     */
    public function install()
    {
        $ret =
            parent::install() &&
            $this->registerHook('payment') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayPaymentTop') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('displayMobileHeader') &&
            Configuration::updateValue('ZENKIPAY_MODE', 0);

        return $ret;
    }

    /**
     * Zenkipay's module uninstallation (Configuration values, database tables...)
     *
     * @return boolean Uninstall result
     */
    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Hook to the top a payment page
     *
     * @param array $params Hook parameters
     * @return string Hook HTML
     */
    public function hookDisplayPaymentTop($params)
    {
        Logger::addLog('Zenkipay hookDisplayPaymentTop', 1, null, null, null, true);
        return;
    }

    public function hookDisplayMobileHeader()
    {
        return $this->hookHeader();
    }

    /**
     * Load Javascripts and CSS related to the Zenkipay's module
     * Only loaded during the checkout process
     *
     * @return string HTML/JS Content
     */
    public function hookHeader($params)
    {
        if (!$this->active) {
            return;
        }

        if (
            Tools::getValue('module') === 'onepagecheckoutps' ||
            Tools::getValue('controller') === 'order-opc' ||
            Tools::getValue('controller') === 'orderopc' ||
            Tools::getValue('controller') === 'order'
        ) {
            $this->context->controller->addCSS($this->_path . 'views/css/zenkipay-prestashop.css');
            // $this->context->controller->registerJavascript('remote-zenkipay-js', 'https://dev-resources.zenki.fi/zenkipay/script/zenkipay.js', ['position' => 'bottom', 'server' => 'remote']);
            $this->context->controller->registerJavascript('remote-zenkipay-js', 'https://uat-resources.zenki.fi/zenkipay/script/zenkipay.js', ['position' => 'bottom', 'server' => 'remote']);
        }
    }

    /**
     * Hook to the new PS 1.7 payment options hook
     *
     * @param array $params Hook parameters
     * @return array|bool
     * @throws Exception
     * @throws SmartyException
     */
    public function hookPaymentOptions($params)
    {
        Logger::addLog('Zenkipay hookPaymentOptions');
        if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            return false;
        }

        /** @var Cart $cart */
        $cart = $params['cart'];
        if (!$this->active) {
            return false;
        }

        try {
            $externalOption = new PaymentOption();
            $externalOption
                ->setCallToActionText($this->l('Zenkipay'))
                ->setForm($this->generateForm($cart))
                ->setModuleName($this->name)
                ->setLogo($this->_path . 'views/img/logo.png')
                // ->setLogo('https://sbox.tshirts.boutique/wp-content/plugins/zenkipay/assets/icons/logo.png')
                ->setAdditionalInformation($this->context->smarty->fetch('module:zenkipay/views/templates/front/payment_infos.tpl'));

            return [$externalOption];
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::addLog('Zenkipay - hookPaymentOptions MSG ' . $e->getMessage(), 3, null, null, null, true);
                Logger::addLog('Zenkipay - hookPaymentOptions FILE ' . $e->getFile() . ', LINE' . $e->getLine(), 3, null, null, null, true);
                Logger::addLog('Zenkipay - hookPaymentOptions TRACE ' . $e->getTraceAsString(), 4, $e->getCode(), null, null, true);
            }

            $this->context->cookie->__set('zenkipay_error', $e->getMessage());
        }
    }

    protected function generateForm($cart)
    {
        $pk = Configuration::get('ZENKIPAY_MODE') ? Configuration::get('ZENKIPAY_PUBLIC_KEY_LIVE') : Configuration::get('ZENKIPAY_PUBLIC_KEY_TEST');

        if (!empty($this->context->cookie->zenkipay_error)) {
            $this->context->smarty->assign('zenkipay_error', $this->context->cookie->zenkipay_error);
            $this->context->cookie->__set('zenkipay_error', null);
        }

        $country = $cart->getTaxCountry();
        $products = $cart->getProducts();
        $formatted_products = [];

        foreach ($products as $product) {
            array_push($formatted_products, ['itemId' => $product['id_product'], 'quantity' => $product['cart_quantity'], 'price' => round($product['price_wt'], 2), 'thumbnailUrl' => '']);
        }

        $data = [
            'js_dir' => _PS_JS_DIR_,
            'pk' => $pk,
            'products' => $formatted_products,
            'total' => round($cart->getOrderTotal(), 2),
            'module_dir' => $this->_path,
            'currency' => $this->context->currency->iso_code,
            'country' => $country->iso_code,
            'action' => $this->context->link->getModuleLink($this->name, 'validation', [], Tools::usingSecureMode()),
        ];

        $this->context->smarty->assign($data);

        return $this->context->smarty->fetch('module:zenkipay/views/templates/front/cc_form.tpl');
    }

    /**
     * Process a payment
     *
     */
    public function processPayment($zenkipay_trx_id)
    {
        if (!$this->active) {
            return;
        }

        $mail_detail = '';
        $payment_method = 'zenkipay';
        $cart = $this->context->cart;
        $display_name = $this->l('Zenkipay');

        try {
            $order_status = Configuration::get('PS_OS_PAYMENT');
            $amount = $cart->getOrderTotal();

            $message =
                $this->l('Transaction Details:') .
                "\n\n" .
                $this->l('Transaction ID:') .
                ' ' .
                $zenkipay_trx_id .
                "\n" .
                $this->l('Payment method:') .
                ' ' .
                Tools::ucfirst($payment_method) .
                "\n" .
                $this->l('Amount:') .
                ' $' .
                number_format($amount, 2) .
                ' ' .
                Tools::strtoupper($this->context->currency->iso_code) .
                "\n" .
                $this->l('Status:') .
                ' ' .
                $this->l('Paid') .
                "\n" .
                $this->l('Processed on:') .
                ' ' .
                date('Y-m-d H:i:s') .
                "\n" .
                $this->l('Mode:') .
                ' ' .
                (Configuration::get('ZENKIPAY_MODE') ? $this->l('Live') : $this->l('Test')) .
                "\n";

            /* Create the PrestaShop order in database */
            $detail = ['{detail}' => $mail_detail];
            Logger::addLog($message, 1, null, null, null, true);

            $this->validateOrder((int) $this->context->cart->id, (int) $order_status, $amount, $display_name, $message, $detail, null, false, $this->context->customer->secure_key);
            $new_order = new Order((int) $this->currentOrder);
            if (Validate::isLoadedObject($new_order)) {
                $payment = $new_order->getOrderPaymentCollection();
                if (isset($payment[0])) {
                    $payment[0]->transaction_id = pSQL($zenkipay_trx_id);
                    $payment[0]->save();
                }
            }

            /** Redirect the user to the order confirmation page history */
            $redirect =
                __PS_BASE_URI__ .
                'index.php?controller=order-confirmation&id_cart=' .
                (int) $this->context->cart->id .
                '&id_module=' .
                (int) $this->id .
                '&id_order=' .
                (int) $this->currentOrder .
                '&key=' .
                $this->context->customer->secure_key;

            Logger::addLog($redirect, 1, null, null, null, true);

            Tools::redirect($redirect);

            /** catch the Zenkipay error */
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::addLog('Zenkipay - Payment transaction failed ' . $e->getMessage(), 1, null, 'Cart', (int) $this->context->cart->id, true);
                Logger::addLog('Zenkipay - Payment transaction failed ' . $e->getTraceAsString(), 4, $e->getCode(), 'Cart', (int) $this->context->cart->id, true);
            }

            $this->context->cookie->__set('zenkipay_error', $e->getMessage());

            Tools::redirect('index.php?controller=order&step=1');
        }
    }

    /**
     * Check settings requirements to make sure the Zenkipay's module will work properly
     *
     * @return boolean Check result
     */
    public function checkSettings()
    {
        if (Configuration::get('ZENKIPAY_MODE')) {
            return Configuration::get('ZENKIPAY_PUBLIC_KEY_LIVE') != '' && Configuration::get('ZENKIPAY_PRIVATE_KEY_LIVE') != '';
        } else {
            return Configuration::get('ZENKIPAY_PUBLIC_KEY_TEST') != '' && Configuration::get('ZENKIPAY_PRIVATE_KEY_TEST') != '';
        }
    }

    /**
     * Check technical requirements to make sure the Zenkipay's module will work properly
     *
     * @return array Requirements tests results
     */
    public function checkRequirements()
    {
        $tests = ['result' => true];

        $tests['curl'] = [
            'name' => $this->l('The PHP cURL extension must be enabled on your server.'),
            'result' => extension_loaded('curl'),
        ];

        if (Configuration::get('ZENKIPAY_MODE')) {
            $tests['ssl'] = [
                'name' => $this->l('SSL must be enabled (before going live).'),
                'result' => Configuration::get('PS_SSL_ENABLED') || (!empty($_SERVER['HTTPS']) && Tools::strtolower($_SERVER['HTTPS']) != 'off'),
            ];
        }

        $tests['configuration'] = [
            'name' => $this->l('You must create a Zenkipay account to obtain your public key.'),
            'result' => $this->getMerchantInfo(),
        ];

        $tests['php56'] = [
            'name' => $this->l('Your server must have PHP 5.6 or later.'),
            'result' => version_compare(PHP_VERSION, '5.6.0', '>='),
        ];

        foreach ($tests as $k => $test) {
            if ($k != 'result' && !$test['result']) {
                $tests['result'] = false;
            }
        }

        return $tests;
    }

    /**
     * Display the Back-office interface of the Zenkipay's module
     *
     * @return string HTML/JS Content
     */
    public function getContent()
    {
        $this->context->controller->addCSS([$this->_path . 'views/css/zenkipay-prestashop-admin.css']);

        $errors = [];

        /** Update Configuration Values when settings are updated */
        if (Tools::isSubmit('SubmitZenkipay')) {
            $configuration_values = [
                'ZENKIPAY_MODE' => Tools::getValue('zenkipay_mode'),
                'ZENKIPAY_PUBLIC_KEY_TEST' => trim(Tools::getValue('zenkipay_public_key_test')),
                'ZENKIPAY_PUBLIC_KEY_LIVE' => trim(Tools::getValue('zenkipay_public_key_live')),
            ];

            foreach ($configuration_values as $configuration_key => $configuration_value) {
                Configuration::updateValue($configuration_key, $configuration_value);
            }

            $mode = Configuration::get('ZENKIPAY_MODE') ? 'LIVE' : 'TEST';

            if (!$this->getMerchantInfo()) {
                $errors[] = 'Zenkipay key is incorrect.';
                Configuration::deleteByName('ZENKIPAY_PUBLIC_KEY_' . $mode);
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->error[] = $error;
            }
        }

        $requirements = $this->checkRequirements();

        foreach ($requirements as $key => $requirement) {
            if ($key != 'result') {
                $this->validation[] = $requirement;
            }
        }

        if ($requirements['result']) {
            $validation_title = $this->l('All checks were successful, now you can start using Zenkipay.');
        } else {
            $validation_title = $this->l('At least one problem was found in order to start using Zenkipay. Please solve the problems and refresh this page.');
        }

        $zenkipay_dashboard = Configuration::get('ZENKIPAY_MODE') ? 'https://portal-uat.zenki.fi' : 'https://portal-dev.zenki.fi';

        $this->context->smarty->assign([
            'receipt' => $this->_path . 'views/img/recibo.png',
            'zenkipay_form_link' => $_SERVER['REQUEST_URI'],
            'zenkipay_configuration' => Configuration::getMultiple(['ZENKIPAY_MODE', 'ZENKIPAY_PUBLIC_KEY_TEST', 'ZENKIPAY_PUBLIC_KEY_LIVE']),
            'zenkipay_ssl' => Configuration::get('PS_SSL_ENABLED'),
            'zenkipay_validation' => $this->validation,
            'zenkipay_error' => empty($this->error) ? false : $this->error,
            'zenkipay_validation_title' => $validation_title,
            'zenkipay_dashboard' => $zenkipay_dashboard,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
    }

    public function getPath()
    {
        return $this->_path;
    }

    public function getMerchantInfo()
    {
        $payload = Configuration::get('ZENKIPAY_MODE') ? Configuration::get('ZENKIPAY_PUBLIC_KEY_LIVE') : Configuration::get('ZENKIPAY_PUBLIC_KEY_TEST');
        $url = Configuration::get('ZENKIPAY_MODE') ? $this->url : $this->sandbox_url;

        if (strlen($payload) == 0) {
            return false;
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:text/plain']);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $result = curl_exec($ch);

        if ($result === false) {
            Logger::addLog('Curl error ' . curl_errno($ch) . ': ' . curl_error($ch), 1, null, null, null, true);
        }

        curl_close($ch);

        $array = json_decode($result, true);

        if (array_key_exists('access_token', $array)) {
            return true;
        }

        return false;
    }
}
