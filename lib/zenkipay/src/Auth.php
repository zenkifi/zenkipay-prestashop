<?php

declare(strict_types=1);

namespace Zenkipay;

use Exception;

final class Auth
{
    public const API_URL = 'https://dev-auth.zenki.fi';

    /**
     * Get Zenkipay's access token
     *
     * @return array
     */
    public static function getAccessToken(string $client_id, string $client_secret): array
    {
        $url = self::API_URL . '/auth/realms/pbw-local/protocol/openid-connect/token';
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
        if ($result === false) {
            throw new Exception('Cannot get the access token');
        }

        curl_close($ch);
        return json_decode($result, true);
    }
}
