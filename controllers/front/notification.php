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

use Svix\Webhook;

require_once dirname(__FILE__) . '/../../lib/svix/init.php';
require_once dirname(__FILE__) . '/../../zenkipay.php';

class ZenkipayNotificationModuleFrontController extends ModuleFrontController
{
    private $zenkipay;

    public function __construct()
    {
        $this->auth = false;
        parent::__construct();
        $this->context = Context::getContext();
        $this->zenkipay = new Zenkipay();
    }

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $payload = Tools::file_get_contents('php://input');
        // $json = json_decode($payload);

        $headers = apache_request_headers();
        $svix_headers = [];
        foreach ($headers as $key => $value) {
            $header = strtolower($key);
            $svix_headers[$header] = $value;
        }

        Logger::addLog('Webhook payload: ' . $payload, 1, null, null, null, true);

        try {
            $secret = $this->zenkipay->getWebhookSigningSecret();
            $wh = new Webhook($secret);
            $json = $wh->verify($payload, $svix_headers);

            Logger::addLog('Zenkipay - SVIX => ' . json_encode($json), 1, null, null, null, true);

            if ($json->transactionStatus != 'COMPLETED') {
                return;
            }

            if (!empty($json->merchantOrderId)) {
                $order = new Order((int) $json->merchantOrderId);
                $order_state = $order->getCurrentOrderState();

                if ($order_state && !$order_state->paid) {
                    $payments = $order->getOrderPaymentCollection();
                    foreach ($payments as $key => $payment) {
                        Logger::addLog('Webhook payment (' . $key . '): ' . json_encode($payment), 1, null, null, null, true);
                    }
                    if (isset($payments[0])) {
                        Logger::addLog('Webhook orderId => ' . $json->orderId, 1, null, null, null, true);
                        $payments[0]->transaction_id = pSQL($json->orderId);
                        $payments[0]->save();
                    }

                    $order->setCurrentState((int) Configuration::get('PS_OS_PAYMENT'));
                }
            } else {
                Logger::addLog('#Webhook NO ORDER', 1, null, null, null, true);
            }
        } catch (Exception $e) {
            http_response_code(400);
            if (class_exists('Logger')) {
                Logger::addLog('Webhook Notification - BAD REQUEST 400 - Request Body: ' . $e->getMessage(), 3, null, null, null, true);
                Logger::addLog('Webhook Notification - BAD REQUEST 400 - Request Body: ' . $e->getLine(), 3, null, null, null, true);
            }
            exit();
        }

        // header('HTTP/1.1 200 OK');
        http_response_code(200);
        exit();
    }
}
