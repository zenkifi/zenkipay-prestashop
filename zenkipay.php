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
    private $webhook_signing_secret;
    private $rsa_private_key;
    private $purchase_data_version = 'v1.1.0';
    private $api_url = 'https://api.zenki.fi';
    private $url = 'https://prod-gateway.zenki.fi';
    private $js_url = 'https://resources.zenki.fi/zenkipay/script/zenkipay.js';

    public function __construct()
    {
        $this->name = 'zenkipay';
        $this->tab = 'payments_gateways';
        $this->version = '1.5.0';
        $this->author = 'PayByWallet, Inc';
        $this->webhook_signing_secret = Configuration::get('ZENKIPAY_WEBHOOK_SIGNING_SECRET');
        $this->rsa_private_key = Configuration::get('ZENKIPAY_RSA_PRIVATE_KEY');

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
            $this->createPendingState() &&
            $this->registerHook('payment') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayPaymentTop') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('displayMobileHeader') &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            $this->registerHook('actionAdminOrdersTrackingNumberUpdate') &&
            Configuration::updateValue('ZENKIPAY_MODE', 0) &&
            Configuration::updateValue('ZENKIPAY_PUBLIC_KEY_LIVE', '') &&
            Configuration::updateValue('ZENKIPAY_PUBLIC_KEY_TEST', '') &&
            Configuration::updateValue('ZENKIPAY_RSA_PRIVATE_KEY', '') &&
            Configuration::updateValue('ZENKIPAY_WEBHOOK_SIGNING_SECRET', '');

        return $ret;
    }

    /**
     * Zenkipay's module uninstallation (Configuration values, database tables...)
     *
     * @return boolean Uninstall result
     */
    public function uninstall()
    {
        return parent::uninstall() && Configuration::deleteByName('ZENKIPAY_PUBLIC_KEY_LIVE') && Configuration::deleteByName('ZENKIPAY_PUBLIC_KEY_TEST');
    }

    /**
     * Catches the order's tracking number and send it to Zenkipay
     * @param mixed $params
     *
     * @return void
     */
    public function hookActionAdminOrdersTrackingNumberUpdate($params)
    {
        try {
            $order = $params['order'];
            if ($order->payment == 'Zenkipay' && $order->getCurrentState() == Configuration::get('PS_OS_PAYMENT')) {
                $payment = $order->getOrderPayments();

                $data = [['orderId' => $payment[0]->transaction_id, 'merchantOrderId' => $order->id, 'trackingId' => $order->shipping_number]];
                $this->handleTrackingNumber($data);
            }
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - TrackingNumberUpdate getMessage ' . $e->getMessage(), 3, $e->getCode(), null, null, true);
            Logger::addLog('Zenkipay - TrackingNumberUpdate getLine ' . $e->getLine(), 3, $e->getCode(), null, null, true);
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
            'result' => $this->validateZenkipayKey(),
        ];

        $tests['php56'] = [
            'name' => $this->l('Your server must have PHP 5.6 or later.'),
            'result' => version_compare(PHP_VERSION, '5.6.0', '>='),
        ];

        $tests['rsa'] = [
            'name' => $this->l('You must enter your RSA private key to sign the transactions.'),
            'result' => $this->validateRSAPrivateKey(Configuration::get('ZENKIPAY_RSA_PRIVATE_KEY')),
        ];

        foreach ($tests as $k => $test) {
            if ($k != 'result' && !$test['result']) {
                $tests['result'] = false;
            }
        }

        return $tests;
    }

    private function createPendingState()
    {
        $state = new OrderState();
        $languages = Language::getLanguages();
        $names = [];

        foreach ($languages as $lang) {
            $names[$lang['id_lang']] = $this->l('Awaiting Zenkipay payment');
        }

        $state->name = $names;
        $state->color = '#4169E1';
        $state->logable = true;
        $state->module_name = 'zenkipay';
        $templ = [];

        foreach ($languages as $lang) {
            $templ[$lang['id_lang']] = 'zenkipay';
        }

        $state->template = $templ;

        if ($state->save()) {
            try {
                Configuration::updateValue('PS_OS_ZENKIPAY_PAYMENT', $state->id);
            } catch (Exception $e) {
                Logger::addLog($e->getMessage(), 3, null, null, null, true);
            }
        }

        return true;
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
                'ZENKIPAY_RSA_PRIVATE_KEY' => trim(Tools::getValue('zenkipay_rsa_private_key')),
                'ZENKIPAY_WEBHOOK_SIGNING_SECRET' => trim(Tools::getValue('zenkipay_webhook_signing_secret')),
            ];

            foreach ($configuration_values as $configuration_key => $configuration_value) {
                Configuration::updateValue($configuration_key, $configuration_value);
            }

            $mode = Configuration::get('ZENKIPAY_MODE') ? 'LIVE' : 'TEST';

            if (!$this->validateZenkipayKey()) {
                $errors[] = $this->l('Zenkipay key is incorrect.');
                Configuration::deleteByName('ZENKIPAY_PUBLIC_KEY_' . $mode);
            }

            if (!$this->validateRSAPrivateKey(trim(Tools::getValue('zenkipay_rsa_private_key')))) {
                $errors[] = $this->l('Invalid RSA private key has been provided.');
                Configuration::deleteByName('ZENKIPAY_RSA_PRIVATE_KEY');
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

        $zenkipay_dashboard = 'https://portal.zenki.fi';

        $this->context->smarty->assign([
            'zenkipay_form_link' => $_SERVER['REQUEST_URI'],
            'zenkipay_configuration' => Configuration::getMultiple([
                'ZENKIPAY_MODE',
                'ZENKIPAY_PUBLIC_KEY_TEST',
                'ZENKIPAY_PUBLIC_KEY_LIVE',
                'ZENKIPAY_RSA_PRIVATE_KEY',
                'ZENKIPAY_WEBHOOK_SIGNING_SECRET',
            ]),
            'zenkipay_ssl' => Configuration::get('PS_SSL_ENABLED'),
            'zenkipay_validation' => $this->validation,
            'zenkipay_error' => empty($this->error) ? false : $this->error,
            'zenkipay_validation_title' => $validation_title,
            'zenkipay_dashboard' => $zenkipay_dashboard,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
    }

    /**
     * Hook to the top a payment page
     *
     * @param array $params Hook parameters
     * @return string Hook HTML
     */
    public function hookDisplayPaymentTop($params)
    {
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
            $this->context->controller->registerJavascript('remote-zenkipay-js', $this->js_url, ['position' => 'bottom', 'server' => 'remote']);
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
                ->setAdditionalInformation($this->context->smarty->fetch('module:zenkipay/views/templates/front/payment_infos.tpl'));

            return [$externalOption];
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::addLog('Zenkipay - hookPaymentOptions MSG ' . $e->getMessage(), 3, null, null, null, true);
                Logger::addLog('Zenkipay - hookPaymentOptions FILE ' . $e->getFile() . ', LINE' . $e->getLine(), 3, null, null, null, true);
            }

            $this->context->cookie->__set('zenkipay_error', $e->getMessage());
        }
    }

    /**
     * Realizar reembolso
     *
     * @link https://devdocs.prestashop.com/1.7/modules/concepts/hooks/list-of-hooks/
     * @param type $params
     * @return type
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $order_id = $params['id_order'];
        $new_order_state = $params['newOrderStatus'];

        try {
            $order = new Order((int) $order_id);
            if ($order->payment != 'Zenkipay' && $new_order_state->id != Configuration::get('PS_OS_REFUND')) {
                return;
            }

            $payment = $order->getOrderPayments();
            $data = [
                'title' => 'PrestaShop refund request #' . $order_id,
                'description' => 'Refund request originated by PrestaShop.',
                'orderId' => $payment[0]->transaction_id,
            ];

            $this->createDispute($data);
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - hookActionOrderStatusPostUpdate getMessage ' . $e->getMessage(), 3, $e->getCode(), null, null, true);
            Logger::addLog('Zenkipay - hookActionOrderStatusPostUpdate getLine ' . $e->getLine(), 3, $e->getCode(), null, null, true);
        }

        return;
    }

    protected function generateForm($cart)
    {
        if (!empty($this->context->cookie->zenkipay_error)) {
            $this->context->smarty->assign('zenkipay_error', $this->context->cookie->zenkipay_error);
            $this->context->cookie->__set('zenkipay_error', null);
        }

        try {
            $data = [
                'js_dir' => _PS_JS_DIR_,
                'module_dir' => $this->_path,
                'action' => $this->context->link->getModuleLink($this->name, 'validation', [], Tools::usingSecureMode()),
            ];

            $this->context->smarty->assign($data);

            return $this->context->smarty->fetch('module:zenkipay/views/templates/front/form.tpl');
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - generateForm' . $e->getMessage(), 3, $e->getCode(), null, null, true);

            return false;
        }
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

        Logger::addLog('Zenkipay - zenkipay_trx_id: ' . $zenkipay_trx_id, 1, null, 'Cart', (int) $this->context->cart->id, true);

        $mail_detail = '';
        $payment_method = 'zenkipay';
        $cart = $this->context->cart;
        $display_name = 'Zenkipay';

        try {
            // $order_status = Configuration::get('PS_OS_PAYMENT');
            $order_status = Configuration::get('PS_OS_ZENKIPAY_PAYMENT');
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
            $this->validateOrder((int) $this->context->cart->id, (int) $order_status, $amount, $display_name, $message, $detail, null, false, $this->context->customer->secure_key);
            $new_order = new Order((int) $this->currentOrder);

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

            Tools::redirect($redirect);
            /** catch the Zenkipay error */
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::addLog('Zenkipay - Payment transaction failed ' . $e->getMessage(), 3, null, 'Cart', (int) $this->context->cart->id, true);
                Logger::addLog('Zenkipay - Payment transaction failed ' . $e->getLine(), 4, $e->getCode(), 'Cart', (int) $this->context->cart->id, true);
            }

            $this->context->cookie->__set('zenkipay_error', $e->getMessage());

            Tools::redirect('index.php?controller=order&step=1');
        }
    }

    /**
     * Display a confirmation message after an order has been placed
     *
     * @param array Hook parameters
     */
    public function hookPaymentReturn($params)
    {
        if (!isset($params['order']) || $params['order']->module != $this->name) {
            Logger::addLog('Orden no existe', 3, null, null, null, true);
            return false;
        }

        $this->context->controller->registerJavascript('remote-zenkipay-js', $this->js_url, ['position' => 'bottom', 'server' => 'remote']);

        /** @var Order $order */
        $order = $params['order'];
        $cart = new Cart((int) $order->id_cart);
        $currency = Currency::getIsoCodeById((int) $cart->id_currency);
        $pk = Configuration::get('ZENKIPAY_MODE') ? Configuration::get('ZENKIPAY_PUBLIC_KEY_LIVE') : Configuration::get('ZENKIPAY_PUBLIC_KEY_TEST');

        $country = $cart->getTaxCountry();
        $products = $cart->getProducts();
        $formatted_products = [];
        $shopperEmail = '';
        $summary = $cart->getSummaryDetails();

        if (property_exists($cart, 'id_customer')) {
            $customer = new Customer((int) $cart->id_customer);
            $shopperEmail = $customer->email;
        }

        foreach ($products as $product) {
            array_push($formatted_products, [
                'itemId' => $product['id_product'],
                'productName' => $product['name'],
                'productDescription' => strip_tags($product['description_short']),
                'quantity' => $product['cart_quantity'],
                'price' => $this->formatNumber($product['price']), // without taxes
                'metadata' => [
                    'price_wt' => $this->formatNumber($product['price_wt']),
                    'reduction_applies' => $product['reduction_applies'] === true ? 'true' : 'false',
                    'reduction_type' => (string) $product['reduction_type'],
                    'reduction' => $this->formatNumber($product['reduction']),
                    'reduction_without_tax' => $this->formatNumber($product['reduction_without_tax']),
                ],
            ]);
        }

        $purchase_data = [
            'version' => $this->purchase_data_version,
            'zenkipayKey' => $pk,
            'merchantOrderId' => $order->id,
            'shopperEmail' => $shopperEmail,
            'shopperCartId' => $cart->id,
            'items' => $formatted_products,
            'country' => $country->iso_code,
            'purchaseSummary' => [
                'currency' => $currency,
                'totalItemsAmount' => $this->formatNumber($summary['total_products']), // without taxes
                'shipmentAmount' => $this->formatNumber($summary['total_shipping_tax_exc']), // without taxes
                'subtotalAmount' => $this->formatNumber($summary['total_price_without_tax']), // without taxes
                'taxesAmount' => $this->formatNumber($summary['total_tax']),
                'discountAmount' => $this->formatNumber($summary['total_discounts']),
                'grandTotalAmount' => $this->formatNumber($cart->getOrderTotal()),
                'localTaxesAmount' => 0,
                'importCosts' => 0,
            ],
        ];

        $payload = json_encode($purchase_data);
        $signature = $this->generateSignature($payload);

        $data = [
            'js_dir' => _PS_JS_DIR_,
            'pk' => $pk,
            'purchase_data' => $payload,
            'signature' => $signature,
        ];

        $this->context->smarty->assign($data);

        $template = './views/templates/hook/order_confirmation.tpl';

        return $this->display(__FILE__, $template);
    }

    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Checks if the Zenkipay key is valid
     *
     * @return boolean
     */
    protected function validateZenkipayKey()
    {
        $result = $this->getAccessToken();
        if (array_key_exists('access_token', $result)) {
            return true;
        }

        return false;
    }

    /**
     * Get Zenkipay access token
     *
     * @return array
     */
    protected function getAccessToken()
    {
        $payload = Configuration::get('ZENKIPAY_MODE') ? Configuration::get('ZENKIPAY_PUBLIC_KEY_LIVE') : Configuration::get('ZENKIPAY_PUBLIC_KEY_TEST');
        $url = $this->url . '/public/v1/merchants/plugin/token';

        if (strlen($payload) == 0) {
            return [];
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
            return [];
        }

        curl_close($ch);
        return json_decode($result, true);
    }

    /**
     * Updates Zenkipay's merchantOrderId after WooCommerce register the order
     *
     * @param mixed $zenkipay_order_id
     * @param mixed $order_id
     *
     * @return boolean
     */
    protected function updateZenkipayOrder($zenkipay_order_id, $order_id)
    {
        try {
            $zenkipay_key = Configuration::get('ZENKIPAY_MODE') ? Configuration::get('ZENKIPAY_PUBLIC_KEY_LIVE') : Configuration::get('ZENKIPAY_PUBLIC_KEY_TEST');
            $url = $this->url . '/v1/orders/' . $zenkipay_order_id;
            $data = json_encode(['zenkipayKey' => $zenkipay_key, 'merchantOrderId' => $order_id]);
            $method = 'PATCH';
            $result = $this->customRequest($url, $method, $data);

            Logger::addLog('Zenkipay - updateZenkipayOrder ' . $result, 1, null, null, null, true);

            return true;
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - updateZenkipayOrder: ' . $e->getMessage(), 1, null, 'Cart', (int) $this->context->cart->id, true);
            Logger::addLog('Zenkipay - updateZenkipayOrder: ' . $e->getTraceAsString(), 4, $e->getCode(), 'Cart', (int) $this->context->cart->id, true);
            return false;
        }
    }

    protected function handleTrackingNumber($data)
    {
        try {
            $url = $this->api_url . '/v1/api/tracking';
            $method = 'POST';

            $result = $this->customRequest($url, $method, $data);

            Logger::addLog('Zenkipay - handleTrackingNumber ' . $result, 1, null, null, null, true);

            return true;
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - handleTrackingNumber: ' . $e->getMessage(), 1, null, 'Cart', (int) $this->context->cart->id, true);
            Logger::addLog('Zenkipay - handleTrackingNumber: ' . $e->getTraceAsString(), 4, $e->getCode(), 'Cart', (int) $this->context->cart->id, true);
            return false;
        }
    }

    protected function createDispute($data)
    {
        try {
            $url = $this->api_url . '/v1/api/disputes';
            $method = 'POST';

            $result = $this->customRequest($url, $method, $data);

            Logger::addLog('Zenkipay - createDispute ' . $result, 1, null, null, null, true);

            return true;
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - createDispute: ' . $e->getMessage(), 1, null, 'Cart', (int) $this->context->cart->id, true);
            Logger::addLog('Zenkipay - createDispute: ' . $e->getTraceAsString(), 4, $e->getCode(), 'Cart', (int) $this->context->cart->id, true);
            return false;
        }
    }

    protected function customRequest($url, $method, $data)
    {
        $token_result = $this->getAccessToken();

        if (!array_key_exists('access_token', $token_result)) {
            Logger::addLog('Zenkipay - customRequest: Error al obtener access_token ', 3, null, null, null, true);
            throw new PrestaShopException('Invalid access token');
        }

        $headers = ['Accept: */*', 'Content-Type: application/json', 'Authorization: Bearer ' . $token_result['access_token']];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        $result = curl_exec($ch);

        if ($result === false) {
            Logger::addLog('Curl error ' . curl_errno($ch) . ': ' . curl_error($ch), 3, null, null, null, true);
            throw new PrestaShopException(curl_error($ch));
        }

        curl_close($ch);

        return $result;
    }

    /**
     * Checks if the plain RSA private key is valid
     *
     * @param string $plain_rsa_private_key Plain RSA private key
     *
     * @return boolean
     */
    protected function validateRSAPrivateKey($plain_rsa_private_key)
    {
        if (empty($plain_rsa_private_key)) {
            return false;
        }

        try {
            $private_key = openssl_pkey_get_private($plain_rsa_private_key);

            if (is_resource($private_key)) {
                $public_key = openssl_pkey_get_details($private_key);

                if (is_array($public_key) && isset($public_key['key'])) {
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - validateRSAPrivateKey ' . $e->getTraceAsString(), 3, null, null, null, true);
            return false;
        }
    }

    /**
     * Generates payload signature using the RSA private key
     *
     * @param string $payload Purchase data
     *
     * @return string
     */
    protected function generateSignature($payload)
    {
        $rsa_private_key = openssl_pkey_get_private(Configuration::get('ZENKIPAY_RSA_PRIVATE_KEY'));
        openssl_sign($payload, $signature, $rsa_private_key, 'RSA-SHA256');
        return base64_encode($signature);
    }

    /**
     * @return string
     */
    public function getWebhookSigningSecret()
    {
        return $this->webhook_signing_secret;
    }

    protected function formatNumber($value, $decimals = 2)
    {
        return number_format($value, $decimals, '.', '');
    }

    /**
     * Decrypt message with RSA private key
     *
     * @param  base64_encoded string holds the encrypted message.
     * @param  integer $chunk_size Chunking by bytes to feed to the decryptor algorithm (512).
     *
     * @return String decrypted message.
     */
    public function RSADecyrpt($encrypted_msg)
    {
        $ppk = openssl_pkey_get_private($this->rsa_private_key);
        $encrypted_msg = base64_decode($encrypted_msg);

        // Decrypt the data in the small chunks
        $a_key = openssl_pkey_get_details($ppk);
        $chunk_size = ceil($a_key['bits'] / 8);

        $offset = 0;
        $decrypted = '';

        while ($offset < strlen($encrypted_msg)) {
            $decrypted_chunk = '';
            $chunk = substr($encrypted_msg, $offset, $chunk_size);

            if (openssl_private_decrypt($chunk, $decrypted_chunk, $ppk)) {
                $decrypted .= $decrypted_chunk;
            } else {
                throw new PrestaShopException('Problem decrypting the message.');
            }
            $offset += $chunk_size;
        }
        return $decrypted;
    }
}
