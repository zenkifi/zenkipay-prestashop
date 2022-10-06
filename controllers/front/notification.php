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
            // $secret = $this->zenkipay->getWebhookSigningSecret();
            // $wh = new Webhook($secret);
            // $json = $wh->verify($payload, $svix_headers);
            $json = json_decode($payload);

            Logger::addLog('Zenkipay - SVIX => ' . json_encode($json), 1, null, null, null, true);

            if ($json->transactionStatus != 'COMPLETED') {
                return;
            }

            if (!empty($json->merchantOrderId)) {
                $discount = 10;
                $order = new Order((int) $json->merchantOrderId);
                $order_state = $order->getCurrentOrderState();
                $cart = new Cart((int) $order->id_cart);

                $cart_rule = $this->createCartRule($cart, $discount);
                $this->addDiscount($order, $cart, $cart_rule);

                // if ($order_state && !$order_state->paid) {
                //     $payments = $order->getOrderPaymentCollection();
                //     foreach ($payments as $key => $payment) {
                //         Logger::addLog('Webhook payment (' . $key . '): ' . json_encode($payment), 1, null, null, null, true);
                //     }
                //     if (isset($payments[0])) {
                //         Logger::addLog('Webhook orderId => ' . $json->orderId, 1, null, null, null, true);
                //         $payments[0]->transaction_id = pSQL($json->orderId);
                //         $payments[0]->save();
                //     }

                //     $order->setCurrentState((int) Configuration::get('PS_OS_PAYMENT'));
                // }
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

    protected function createCartRule($cart, $discount)
    {
        $cart_rule = new CartRule();
        $language_ids = LanguageCore::getIDs(false);

        foreach ($language_ids as $language_id) {
            $cart_rule->name[$language_id] = $this->l('Zenkipay rule');
            $cart_rule->description = $this->trans('Rule created automatically by Zenkipay');
        }

        $now = time();
        $cart_rule->code = 'ZENKI' . $now;
        $cart_rule->date_from = date('Y-m-d H:i:s', $now);
        $cart_rule->date_to = date('Y-m-d H:i:s', strtotime('+1 minute'));
        $cart_rule->reduction_amount = $discount;
        $cart_rule->reduction_tax = true;
        $cart_rule->reduction_currency = $cart->id_currency;
        $cart_rule->add();

        return $cart_rule;
    }

    protected function addDiscount($order, $cart, $cart_rule)
    {
        $cart->addCartRule($cart_rule->id);

        $total_discounts = $cart_rule->reduction_amount;
        $total_discounts_tax_incl = $cart_rule->reduction_amount;
        $total_discounts_tax_excl = $cart_rule->reduction_amount;
        $total_paid = $order->total_paid - $total_discounts;
        $total_paid_tax_excl = $order->total_paid_tax_excl - $total_discounts_tax_excl;

        Db::getInstance()->Execute(
            'UPDATE ' .
                _DB_PREFIX_ .
                'orders  SET total_discounts = ' .
                $total_discounts .
                ', total_discounts_tax_incl = ' .
                $total_discounts_tax_incl .
                ', total_discounts_tax_excl = ' .
                $total_discounts_tax_excl .
                ', total_paid = ' .
                $total_paid .
                ', total_paid_tax_incl = ' .
                $total_paid .
                ', total_paid_tax_excl = ' .
                $total_paid_tax_excl .
                ', total_paid_real = ' .
                $total_paid .
                ' WHERE id_order = ' .
                $order->id
        );

        Db::getInstance()->Execute(
            'INSERT INTO ' .
                _DB_PREFIX_ .
                'order_cart_rule (`id_order`, `id_cart_rule`, `id_order_invoice`, `name`, `value`, `value_tax_excl`, `free_shipping`, `deleted`) VALUES (' .
                $order->id .
                ', ' .
                $cart_rule->id .
                ', "0", "Crypto Love", ' .
                $total_discounts .
                ', ' .
                $total_discounts_tax_excl .
                ', 0, 0)'
        );
    }
}
