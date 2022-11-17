<?php
/**
 * 2007-2022 PrestaShop
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
 *  @copyright 2007-2022 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Zenkipay extends PaymentModule
{
    private $error = [];
    private $validation = [];
    private $webhook_signing_secret;
    private $purchase_data_version = 'v1.0.0';
    private $api_url = 'https://dev-api.zenki.fi';
    private $js_url = 'https://dev-resources.zenki.fi/zenkipay/script/v2/zenkipay.js';

    public function __construct()
    {
        $this->name = 'zenkipay';
        $this->tab = 'payments_gateways';
        $this->version = '1.6.0';
        $this->author = 'PayByWallet, Inc';
        $this->webhook_signing_secret = Configuration::get('ZENKIPAY_WEBHOOK_SIGNING_SECRET');

        parent::__construct();

        $this->displayName = 'Zenkipay';
        $this->description = $this->l('Accept cryptos from any wallet, any coin at your check out page');
        $this->confirmUninstall = $this->l('Are you sure you want uninstall this module?');
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
            Configuration::updateValue('ZENKIPAY_API_KEY', '') &&
            Configuration::updateValue('ZENKIPAY_SECRET_KEY', '') &&
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
        return parent::uninstall() &&
            Configuration::deleteByName('ZENKIPAY_API_KEY') &&
            Configuration::deleteByName('ZENKIPAY_SECRET_KEY') &&
            Configuration::deleteByName('ZENKIPAY_WEBHOOK_SIGNING_SECRET');
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
                $data = [
                    'courier_type' => 'EXTERNAL',
                    'tracking_id' => $order->shipping_number,
                ];
                $this->handleTrackingNumber($payment[0]->transaction_id, $data);
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
        return Configuration::get('ZENKIPAY_API_KEY') != '' && Configuration::get('ZENKIPAY_SECRET_KEY') != '';
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

        $tests['php71'] = [
            'name' => $this->l('Your server must have PHP 7.1 or later.'),
            'result' => version_compare(PHP_VERSION, '7.1.0', '>='),
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
                'ZENKIPAY_SECRET_KEY' => trim(Tools::getValue('zenkipay_secret_key')),
                'ZENKIPAY_API_KEY' => trim(Tools::getValue('zenkipay_api_key')),
                'ZENKIPAY_WEBHOOK_SIGNING_SECRET' => trim(Tools::getValue('zenkipay_webhook_signing_secret')),
            ];

            foreach ($configuration_values as $configuration_key => $configuration_value) {
                Configuration::updateValue($configuration_key, $configuration_value);
            }

            if (!$this->validateZenkipayKey()) {
                $errors[] = $this->l('Credentials are incorrect.');
                Configuration::deleteByName('ZENKIPAY_API_KEY');
                Configuration::deleteByName('ZENKIPAY_SECRET_KEY');
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
            'zenkipay_configuration' => Configuration::getMultiple(['ZENKIPAY_MODE', 'ZENKIPAY_SECRET_KEY', 'ZENKIPAY_API_KEY', 'ZENKIPAY_WEBHOOK_SIGNING_SECRET']),
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
     * Return payment options available for PS 1.7+
     *
     * @param array $params Hook parameters
     * @return array|null
     * @throws Exception
     * @throws SmartyException
     */
    public function hookPaymentOptions($params)
    {
        if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            return;
        }

        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        /** @var Cart $cart */
        $cart = $params['cart'];

        try {
            $externalOption = new PaymentOption();
            $externalOption
                ->setCallToActionText($this->l('Zenkipay'))
                ->setForm($this->generateForm($cart))
                ->setModuleName($this->name)
                ->setLogo($this->_path . 'views/img/logo.png');
            // ->setAdditionalInformation($this->context->smarty->fetch('module:zenkipay/views/templates/front/payment_infos.tpl'));

            return [$externalOption];
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::addLog('Zenkipay - hookPaymentOptions MSG ' . $e->getMessage(), 3, null, null, null, true);
                Logger::addLog('Zenkipay - hookPaymentOptions FILE ' . $e->getFile() . ', LINE' . $e->getLine(), 3, null, null, null, true);
            }

            $this->context->cookie->__set('zenkipay_error', $e->getMessage());
        }
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
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
                'reason' => 'Refund request originated by PrestaShop.',
            ];

            $this->createRefund($payment[0]->transaction_id, $data);
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - hookActionOrderStatusPostUpdate getMessage ' . $e->getMessage(), 3, $e->getCode(), null, null, true);
            Logger::addLog('Zenkipay - hookActionOrderStatusPostUpdate getLine ' . $e->getLine(), 3, $e->getCode(), null, null, true);
        }
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

        Logger::addLog('Zenkipay - processPayment - zenkipay_trx_id: ' . $zenkipay_trx_id, 1, null, 'Cart', (int) $this->context->cart->id, true);

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

        Logger::addLog('Zenkipay - params ' . $order->id_cart, 1, null, null, null, true);

        $cart = new Cart((int) $order->id_cart);
        $currency = Currency::getIsoCodeById((int) $cart->id_currency);

        $country = $cart->getTaxCountry();
        $products = $cart->getProducts();
        $formatted_products = [];
        $shopperEmail = '';
        $summary = $cart->getSummaryDetails();
        $items_types = [];

        if (property_exists($cart, 'id_customer')) {
            $customer = new Customer((int) $cart->id_customer);
            $shopperEmail = $customer->email;
        }

        foreach ($products as $product) {
            $item_type = $product['is_virtual'] ? 'WITHOUT_CARRIER' : 'WITH_CARRIER';
            array_push($items_types, $item_type);

            array_push($formatted_products, [
                'external_id' => $product['id_product'],
                'name' => $product['name'],
                'description' => strip_tags($product['description_short']),
                'quantity' => $product['cart_quantity'],
                'price' => $this->formatNumber($product['price']), // without taxes
                'metadata' => [
                    'price_wt' => $this->formatNumber($product['price_wt']),
                    'reduction_applies' => $product['reduction_applies'] === true ? 'true' : 'false',
                    'reduction_type' => (string) $product['reduction_type'],
                    'reduction' => $this->formatNumber($product['reduction']),
                    'reduction_without_tax' => $this->formatNumber($product['reduction_without_tax']),
                ],
                'type' => $item_type,
            ]);
        }

        Logger::addLog('Zenkipay - $order->id ' . $order->id, 1, null, null, null, true);

        $purchase_data = [
            'version' => $this->purchase_data_version,
            'order_id' => $order->id,
            'cart_id' => $cart->id,
            'type' => $this->getOrderType($items_types),
            'country_code_iso2' => $country->iso_code,
            'shopper' => [
                'email' => $shopperEmail,
            ],
            'breakdown' => [
                'currency_code_iso3' => $currency,
                'total_items_amount' => $this->formatNumber($summary['total_products']), // without taxes
                'shipment_amount' => $this->formatNumber($summary['total_shipping_tax_exc']), // without taxes
                'subtotal_amount' => $this->formatNumber($summary['total_price_without_tax']), // without taxes
                'taxes_amount' => $this->formatNumber($summary['total_tax']),
                'local_taxes_amount' => 0,
                'import_costs' => 0,
                'discount_amount' => $this->formatNumber($summary['total_discounts']),
                'grand_total_amount' => $this->formatNumber($cart->getOrderTotal()),
            ],
            'items' => $formatted_products,
        ];

        $zenkipay_order = json_decode($this->createOrder($purchase_data));

        Logger::addLog('Zenkipay - createdOrder Response: ' . $zenkipay_order->zenki_order_id, 1, null, null, null, true);

        $data = [
            'js_dir' => _PS_JS_DIR_,
            'zenki_order_id' => $zenkipay_order->zenki_order_id,
            'payment_signature' => $zenkipay_order->payment_signature,
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
        $client_id = Configuration::get('ZENKIPAY_API_KEY');
        $client_secret = Configuration::get('ZENKIPAY_SECRET_KEY');

        Logger::addLog('client_id' . $client_id, 1, null, null, null, true);
        Logger::addLog('client_secret' . $client_secret, 1, null, null, null, true);

        $url = $this->api_url . '/v1/oauth/tokens';
        $data = http_build_query([
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'client_credentials',
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true, // return the transfer as a string of the return value
            CURLOPT_TIMEOUT => 0, // The maximum number of seconds to allow cURL functions to execute.
            CURLOPT_POST => true, // This line must place before CURLOPT_POSTFIELDS
            CURLOPT_POSTFIELDS => $data, // Data that will send
        ]);

        $result = curl_exec($ch);
        if ($result === false || !$result) {
            Logger::addLog('Curl error ' . curl_errno($ch) . ': ' . curl_error($ch), 1, null, null, null, true);
            return [];
        }

        curl_close($ch);
        return json_decode($result, true);
    }

    protected function handleTrackingNumber($orderId, $data)
    {
        try {
            $url = $this->api_url . '/v1/pay/orders/' . $orderId . '/tracking';
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

    protected function createRefund($orderId, $data)
    {
        try {
            $url = $this->api_url . '/v1/pay/orders/' . $orderId . '/refunds';
            $method = 'POST';

            $result = $this->customRequest($url, $method, $data);

            Logger::addLog('Zenkipay - createRefund ' . $result, 1, null, null, null, true);

            return true;
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - createRefund: ' . $e->getMessage(), 1, null, 'Cart', (int) $this->context->cart->id, true);
            Logger::addLog('Zenkipay - createRefund: ' . $e->getTraceAsString(), 4, $e->getCode(), 'Cart', (int) $this->context->cart->id, true);
            return false;
        }
    }

    protected function createOrder($data)
    {
        try {
            $url = $this->api_url . '/v1/pay/orders';
            $method = 'POST';

            Logger::addLog('Zenkipay - createOrder - url ' . $url, 1, null, null, null, true);

            $result = $this->customRequest($url, $method, $data);

            Logger::addLog('Zenkipay - createOrder ' . $result, 1, null, null, null, true);

            return $result;
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - createOrder ERROR: ' . $e->getMessage(), 1, null, 'Order', (int) $this->context->cart->id, true);
            Logger::addLog('Zenkipay - createOrder ERROR: ' . $e->getTraceAsString(), 4, $e->getCode(), 'Order', (int) $this->context->cart->id, true);
            return null;
        }
    }

    protected function customRequest($url, $method, $data)
    {
        Logger::addLog('Zenkipay - customRequest - AccessToken: ' . $url, 1, null, null, null, true);

        $token_result = $this->getAccessToken();

        if (!array_key_exists('access_token', $token_result)) {
            Logger::addLog('Zenkipay - customRequest: Error al obtener access_token ', 3, null, null, null, true);
            throw new PrestaShopException('Invalid access token');
        }

        Logger::addLog('Zenkipay - AccessToken: ' . $token_result['access_token'], 1, null, null, null, true);
        $headers = ['Accept: */*', 'Content-Type: application/json', 'Authorization: Bearer ' . $token_result['access_token']];

        Logger::addLog('Zenkipay - data to create order: ' . json_encode($data), 1, null, null, null, true);

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
     * Get order type
     *
     * @param array $items_types
     *
     * @return string
     */
    protected function getOrderType($items_types)
    {
        $needles = ['WITH_CARRIER', 'WITHOUT_CARRIER'];
        if (empty(array_diff($needles, $items_types))) {
            return 'MIXED';
        } elseif (in_array('WITH_CARRIER', $items_types)) {
            return 'WITH_CARRIER';
        } else {
            return 'WITHOUT_CARRIER';
        }
    }
}
