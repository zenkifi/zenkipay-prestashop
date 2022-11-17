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

namespace Svix;

final class TestPayload
{
    private const DEFAULT_MSG_ID = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
    private const DEFAULT_PAYLOAD = '{"test": 2432232315}';
    private const DEFAULT_SECRET = 'MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';

    public $id;
    public $timestamp;
    public $payload;
    public $secret;
    public $header;

    public function __construct(int $timestamp)
    {
        $this->id = self::DEFAULT_MSG_ID;
        $this->timestamp = strval($timestamp);

        $this->payload = self::DEFAULT_PAYLOAD;
        $this->secret = self::DEFAULT_SECRET;

        $toSign = "{$this->id}.{$this->timestamp}.{$this->payload}";
        $signature = base64_encode(pack('H*', hash_hmac('sha256', $toSign, base64_decode($this->secret))));

        $this->header = [
            'svix-id' => $this->id,
            'svix-signature' => "v1,{$signature}",
            'svix-timestamp' => $this->timestamp,
        ];
    }
}
