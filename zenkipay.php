<?php
/**
 * 2007-2023 PrestaShop
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
 *  @copyright 2007-2023 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Zenkipay extends PaymentModule
{
    protected $purchase_data_version = 'v1.0.0';
    protected $api_url = 'https://api.zenki.fi';
    protected $js_url = 'https://resources.zenki.fi/zenkipay/script/v2/zenkipay.js';

    public function __construct()
    {
        $this->name = 'zenkipay';
        $this->tab = 'payments_gateways';
        $this->version = '2.1.2';
        $this->author = 'PayByWallet, Inc';
        $this->module_key = 'df41f70b22ceb53e7a971bf132e0cbc1';

        parent::__construct();

        $this->displayName = 'Zenkipay';
        $this->description = $this->l('Accept cryptos from any wallet, any coin at your check out page');
        $this->confirmUninstall = $this->l('Are you sure you want uninstall this module?');
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
    }

    /**
     * Zenkipay's module installation
     *
     * @return bool Install result
     */
    public function install()
    {
        $ret =
            parent::install() &&
            $this->createPendingState() &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('header') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('actionOrderStatusPostUpdate') &&
            $this->registerHook('actionAdminOrdersTrackingNumberUpdate') &&
            Configuration::updateValue('ZENKIPAY_MODE', true) &&
            Configuration::updateValue('ZENKIPAY_SYNC_CODE', '') &&
            Configuration::updateValue('ZENKIPAY_API_KEY_LIVE', '') &&
            Configuration::updateValue('ZENKIPAY_SECRET_KEY_LIVE', '') &&
            Configuration::updateValue('ZENKIPAY_WHSEC_LIVE', '') &&
            Configuration::updateValue('ZENKIPAY_API_KEY_TEST', '') &&
            Configuration::updateValue('ZENKIPAY_SECRET_KEY_TEST', '') &&
            Configuration::updateValue('ZENKIPAY_WHSEC_TEST', '');

        return $ret;
    }

    /**
     * Zenkipay's module uninstallation (Configuration values, database tables...)
     *
     * @return bool Uninstall result
     */
    public function uninstall()
    {
        $order_status = (int) Configuration::get('PS_OS_ZENKIPAY_PAYMENT');
        $order_state = new OrderState($order_status);
        $order_state->delete();

        return parent::uninstall() &&
            Configuration::deleteByName('ZENKIPAY_MODE') &&
            Configuration::deleteByName('ZENKIPAY_SYNC_CODE') &&
            Configuration::deleteByName('ZENKIPAY_API_KEY_LIVE') &&
            Configuration::deleteByName('ZENKIPAY_SECRET_KEY_LIVE') &&
            Configuration::deleteByName('ZENKIPAY_WHSEC_LIVE') &&
            Configuration::deleteByName('ZENKIPAY_API_KEY_TEST') &&
            Configuration::deleteByName('ZENKIPAY_SECRET_KEY_TEST') &&
            Configuration::deleteByName('ZENKIPAY_WHSEC_TEST') &&
            Configuration::deleteByName('PS_OS_ZENKIPAY_PAYMENT');
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
                    'courierType' => 'EXTERNAL',
                    'trackingId' => $order->shipping_number,
                ];
                $this->handleTrackingNumber($payment[0]->transaction_id, $data);
            }
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - TrackingNumberUpdate getMessage ' . $e->getMessage(), 3, $e->getCode(), null, null, true);
        }
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
        $state->logable = false;
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
        $this->context->controller->addJS('https://unpkg.com/imask');
        $sync_successfully = null;
        $sync_message = '';

        if (Tools::isSubmit('SubmitZenkipay')) {
            $sync_code = trim(Tools::getValue('zenkipay_sync_code'));
            Configuration::updateValue('ZENKIPAY_MODE', Tools::getValue('zenkipay_mode') == 'on');

            if (!$this->validateZenkipayKey($sync_code)) {
                $sync_successfully = false;
                $sync_message = $this->l('An error occurred while syncing the account');

                Configuration::updateValue('ZENKIPAY_SYNC_CODE', '');
                Configuration::updateValue('ZENKIPAY_API_KEY_LIVE', '');
                Configuration::updateValue('ZENKIPAY_SECRET_KEY_LIVE', '');
                Configuration::updateValue('ZENKIPAY_WHSEC_LIVE', '');
                Configuration::updateValue('ZENKIPAY_API_KEY_TEST', '');
                Configuration::updateValue('ZENKIPAY_SECRET_KEY_TEST', '');
                Configuration::updateValue('ZENKIPAY_WHSEC_TEST', '');
            } else {
                $sync_successfully = true;
                $sync_message = $this->l('Synchronization completed successfully');

                Configuration::updateValue('ZENKIPAY_SYNC_CODE', $sync_code);
            }
        }

        $this->context->smarty->assign([
            'zenkipay_form_link' => $_SERVER['REQUEST_URI'],
            'zenkipay_configuration' => Configuration::getMultiple(['ZENKIPAY_MODE', 'ZENKIPAY_SYNC_CODE']),
            'zenkipay_env' => Configuration::get('ZENKIPAY_MODE') ? 'Test mode' : 'Live mode',
            'sync_successfully' => $sync_successfully,
            'sync_message' => $sync_message,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
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
            $this->context->controller->registerJavascript('remote-jquery', 'https://code.jquery.com/jquery-3.6.1.min.js', ['position' => 'head', 'server' => 'remote']);
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

        $cart = $params['cart'];
        if (!$this->checkCurrency($cart)) {
            return;
        }

        try {
            $externalOption = new PaymentOption();
            $externalOption
                ->setCallToActionText($this->l('Zenkipay'))
                ->setForm($this->generateForm($cart))
                ->setModuleName($this->name)
                ->setLogo($this->_path . 'views/img/logo.png');

            return [$externalOption];
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - hookPaymentOptions MSG ' . $e->getMessage(), 3, null, null, null, true);
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
     * Makes the refund
     *
     * @param array $params
     *
     * @return bool
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $order_id = $params['id_order'];
        $new_order_state = $params['newOrderStatus'];

        try {
            $order = new Order((int) $order_id);
            if ($order->payment == 'Zenkipay' && $new_order_state->id == Configuration::get('PS_OS_REFUND')) {
                $payment = $order->getOrderPayments();
                $data = [
                    'reason' => 'Refund request originated by PrestaShop.',
                ];

                $this->createRefund($payment[0]->transaction_id, $data);
            }
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - hookActionOrderStatusPostUpdate getMessage ' . $e->getMessage(), 3, $e->getCode(), null, null, true);
        }
    }

    private function generateForm($cart)
    {
        if (!empty($this->context->cookie->zenkipay_error)) {
            $this->context->smarty->assign('zenkipay_error', $this->context->cookie->zenkipay_error);
            $this->context->cookie->__set('zenkipay_error', null);
        }

        try {
            $data = [
                'module_dir' => $this->_path,
                'action' => $this->context->link->getModuleLink($this->name, 'validation', [], Tools::usingSecureMode()),
                'create_order_ajax' => Tools::getHttpHost(true) . __PS_BASE_URI__ . 'module/zenkipay/order',
                'cart_id' => $cart->id,
                'crypto_btn' => $this->context->language->iso_code == 'es' ? $this->_path . 'views/img/crypto-btn-es.png' : $this->_path . 'views/img/crypto-btn.png',
            ];

            $this->context->smarty->assign($data);

            return $this->context->smarty->fetch('module:zenkipay/views/templates/front/form.tpl');
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - generateForm' . $e->getMessage(), 3, $e->getCode(), null, null, true);
            return false;
        }
    }

    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Checks if the Zenkipay key is valid
     *
     * @return bool
     */
    private function validateZenkipayKey($sync_code)
    {
        if (!$this->setCredentials($sync_code)) {
            return false;
        }

        $result = $this->getAccessToken();

        if (!array_key_exists('accessToken', $result)) {
            return false;
        }

        return true;
    }

    /**
     * Get Zenkipay access token
     *
     * @return array
     */
    private function getAccessToken()
    {
        $api_key = Configuration::get('ZENKIPAY_MODE') ? Configuration::get('ZENKIPAY_API_KEY_TEST') : Configuration::get('ZENKIPAY_API_KEY_LIVE');
        $secret_key = Configuration::get('ZENKIPAY_MODE') ? Configuration::get('ZENKIPAY_SECRET_KEY_TEST') : Configuration::get('ZENKIPAY_SECRET_KEY_LIVE');

        $url = $this->api_url . '/v1/oauth/tokens';
        $credentials = ['clientId' => $api_key, 'clientSecret' => $secret_key, 'grantType' => 'client_credentials'];
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        $agent = 'Zenkipay-PHP/1.0';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true, // return the transfer as a string of the return value
            CURLOPT_TIMEOUT => 30, // The maximum number of seconds to allow cURL functions to execute.
            CURLOPT_USERAGENT => $agent,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($credentials), // Data that will send
        ]);

        $result = curl_exec($ch);
        if ($result === false || !$result) {
            Logger::addLog('Curl error ' . curl_errno($ch) . ': ' . curl_error($ch), 1, null, null, null, true);
            return [];
        }

        curl_close($ch);
        return json_decode($result, true);
    }

    private function handleTrackingNumber($orderId, $data)
    {
        try {
            $url = $this->api_url . '/v1/pay/orders/' . $orderId . '/tracking';
            $method = 'POST';

            $result = $this->customRequest($url, $method, $data);

            Logger::addLog('Zenkipay - handleTrackingNumber ' . $result, 1, null, null, null, true);

            return true;
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - handleTrackingNumber: ' . $e->getMessage(), 1, null, null, null, true);
            return false;
        }
    }

    private function createRefund($orderId, $data)
    {
        try {
            $url = $this->api_url . '/v1/pay/orders/' . $orderId . '/refunds';
            $method = 'POST';

            $result = $this->customRequest($url, $method, $data);

            Logger::addLog('Zenkipay - createRefund ' . $result, 1, null, null, null, true);

            return true;
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - createRefund: ' . $e->getMessage(), 3, null, null, null, true);
            return false;
        }
    }

    public function getMerchant()
    {
        try {
            $url = $this->api_url . '/v1/pay/me';
            $method = 'GET';

            $result = $this->customRequest($url, $method, null);

            Logger::addLog('Zenkipay - getMerchant ' . $result, 1, null, null, null, true);

            return json_decode($result);
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - getMerchant ERROR: ' . $e->getMessage(), 3, null, null, null, true);
            return false;
        }
    }

    public function getZenkiOrder($order_id)
    {
        try {
            $url = $this->api_url . '/v1/pay/orders/' . $order_id;
            $method = 'GET';

            $result = $this->customRequest($url, $method, null);

            Logger::addLog('Zenkipay - getZenkiOrder ' . $result, 1, null, null, null, true);

            return json_decode($result);
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - getZenkiOrder ERROR: ' . $e->getMessage(), 3, null, null, null, true);
            return false;
        }
    }

    public function updateZenkiOrder($order_id, $data)
    {
        try {
            $url = $this->api_url . '/v1/pay/orders/' . $order_id;
            $method = 'PATCH';

            $result = $this->customRequest($url, $method, $data);

            Logger::addLog('Zenkipay - updateZenkiOrder ' . $result, 1, null, null, null, true);

            return json_decode($result);
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - updateZenkiOrder ERROR: ' . $e->getMessage(), 3, null, null, null, true);
            return false;
        }
    }

    private function customRequest($url, $method, $data)
    {
        $token_result = $this->getAccessToken();

        if (!array_key_exists('accessToken', $token_result)) {
            Logger::addLog('Zenkipay - customRequest: Error al obtener accessToken ', 3, null, null, null, true);
            throw new PrestaShopException('Invalid access token');
        }

        $agent = 'Zenkipay-SDK/1.0';
        $headers = ['Accept: */*', 'Content-Type: application/json', 'Authorization: Bearer ' . $token_result['accessToken']];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
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
        return Configuration::get('ZENKIPAY_MODE') ? Configuration::get('ZENKIPAY_WHSEC_TEST') : Configuration::get('ZENKIPAY_WHSEC_LIVE');
    }

    private function formatNumber($value, $decimals = 2)
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
    private function getOrderType($items_types)
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

    public function createOrder()
    {
        $url = $this->api_url . '/v1/pay/orders';
        $method = 'POST';
        $cart = new Cart((int) $this->context->cart->id);
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
                'unitPrice' => $this->formatNumber($product['price']), // without taxes
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

        $purchase_data = [
            'version' => $this->purchase_data_version,
            'cartId' => $cart->id,
            'type' => $this->getOrderType($items_types),
            'countryCodeIso2' => $country->iso_code,
            'shopper' => [
                'email' => $shopperEmail,
            ],
            'breakdown' => [
                'currencyCodeIso3' => $currency,
                'totalItemsAmount' => $this->formatNumber($summary['total_products']), // without taxes
                'shipmentAmount' => $this->formatNumber($summary['total_shipping_tax_exc']), // without taxes
                'subtotalAmount' => $this->formatNumber($summary['total_price_without_tax']), // without taxes
                'taxesAmount' => $this->formatNumber($summary['total_tax']),
                'localTaxesAmount' => 0,
                'importCosts' => 0,
                'discountAmount' => $this->formatNumber($summary['total_discounts']),
                'grandTotalAmount' => $this->formatNumber($cart->getOrderTotal()),
            ],
            'items' => $formatted_products,
        ];

        Logger::addLog('Zenkipay - $purchase_data => ' . json_encode($purchase_data), 1);

        $result = $this->customRequest($url, $method, $purchase_data);
        $zenkipay_order = json_decode($result);

        return [
            'zenki_order_id' => $zenkipay_order->zenkiOrderId,
            'payment_signature' => $zenkipay_order->paymentSignature,
        ];
    }

    private function setCredentials($sync_code)
    {
        try {
            if (!$this->hasSyncedAccount() || Configuration::get('ZENKIPAY_SYNC_CODE') != $sync_code) {
                $credentials = $this->sync($sync_code);

                if (isset($credentials['errorCode'])) {
                    throw new PrestaShopException($credentials['humanMessage']);
                }

                // Se valida que la sincronización haya sido exitosa
                if (isset($credentials['status']) && $credentials['status'] != 'SYNCHRONIZED') {
                    throw new PrestaShopException('Sync status ' . $credentials['status'] . ' is different from SYNCHRONIZED.');
                }

                // TEST
                Configuration::updateValue('ZENKIPAY_API_KEY_TEST', $credentials['synchronizationAccessData']['sandboxApiAccessData']['apiAccessData']['apiKey']);
                Configuration::updateValue('ZENKIPAY_SECRET_KEY_TEST', $credentials['synchronizationAccessData']['sandboxApiAccessData']['apiAccessData']['secretKey']);
                Configuration::updateValue('ZENKIPAY_WHSEC_TEST', $credentials['synchronizationAccessData']['sandboxApiAccessData']['webhookAccessData']['signingSecret']);

                // PROD
                Configuration::updateValue('ZENKIPAY_API_KEY_LIVE', $credentials['synchronizationAccessData']['liveApiAccessData']['apiAccessData']['apiKey']);
                Configuration::updateValue('ZENKIPAY_SECRET_KEY_LIVE', $credentials['synchronizationAccessData']['liveApiAccessData']['apiAccessData']['secretKey']);
                Configuration::updateValue('ZENKIPAY_WHSEC_LIVE', $credentials['synchronizationAccessData']['liveApiAccessData']['webhookAccessData']['signingSecret']);

                // Test mode
                Configuration::updateValue('ZENKIPAY_MODE', $credentials['testMode']);
            }

            return true;
        } catch (Exception $e) {
            Logger::addLog('Zenkipay - setCredentials ERROR: ' . $e->getTraceAsString(), 3, $e->getCode(), null, null, true);
            return false;
        }
    }

    private function sync($sync_code)
    {
        $synchronizationCode = trim(str_replace('-', '', $sync_code));
        $url = $this->api_url . '/public/v1/pay/plugins/synchronize';
        $urlStore = rtrim(Tools::getHttpHost(true) . __PS_BASE_URI__, '/');
        $method = 'POST';
        $data = ['pluginUrl' => $urlStore, 'pluginVersion' => 'v1.0.0', 'synchronizationCode' => $synchronizationCode];
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        $agent = 'Zenkipay-PHP/1.0';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true, // return the transfer as a string of the return value
            CURLOPT_TIMEOUT => 30, // The maximum number of seconds to allow cURL functions to execute.
            CURLOPT_USERAGENT => $agent,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($data), // Data that will send
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            throw new PrestaShopException('Error with the ' . $method . ' request ' . $url);
        }

        curl_close($ch);
        return json_decode($result, true);
    }

    private function hasSyncedAccount()
    {
        $api_key = Configuration::get('ZENKIPAY_MODE') ? Configuration::get('ZENKIPAY_API_KEY_TEST') : Configuration::get('ZENKIPAY_API_KEY_LIVE');
        $secret_key = Configuration::get('ZENKIPAY_MODE') ? Configuration::get('ZENKIPAY_SECRET_KEY_TEST') : Configuration::get('ZENKIPAY_SECRET_KEY_LIVE');
        $whsec = Configuration::get('ZENKIPAY_MODE') ? Configuration::get('ZENKIPAY_WHSEC_TEST') : Configuration::get('ZENKIPAY_WHSEC_LIVE');

        if (empty($api_key) || empty($secret_key) || empty($whsec)) {
            return false;
        }

        return true;
    }
}
