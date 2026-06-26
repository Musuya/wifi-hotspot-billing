<?php
/**
 * mpesa/Mpesa.php
 *
 * Safaricom Daraja API wrapper for STK Push (Lipa Na M-Pesa Online).
 * Docs: https://developer.safaricom.co.ke/
 *
 * Settings (consumer key/secret, shortcode, passkey) are pulled from
 * the `settings` table so you can edit them from the admin dashboard
 * instead of hardcoding them in this file.
 */

class Mpesa
{
    private string $consumerKey;
    private string $consumerSecret;
    private string $shortcode;
    private string $passkey;
    private string $env; // 'sandbox' or 'production'
    private string $callbackUrl;

    public function __construct(array $settings, string $callbackUrl)
    {
        $this->consumerKey    = $settings['mpesa_consumer_key'] ?? '';
        $this->consumerSecret = $settings['mpesa_consumer_secret'] ?? '';
        $this->shortcode      = $settings['mpesa_shortcode'] ?? '';
        $this->passkey        = $settings['mpesa_passkey'] ?? '';
        $this->env            = $settings['mpesa_env'] ?? 'sandbox';
        $this->callbackUrl    = $callbackUrl;
    }

    private function baseUrl(): string
    {
        return $this->env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Get an OAuth access token (valid ~1 hour).
     */
    private function getAccessToken(): ?string
    {
        $url = $this->baseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Basic ' . $credentials],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('Mpesa token error: ' . $error);
            return null;
        }

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    /**
     * Initiate an STK Push (the "Enter M-Pesa PIN" prompt on the customer's phone).
     *
     * @param string $phone   Format: 2547XXXXXXXX (no +, no leading 0)
     * @param float  $amount  Amount in KES
     * @param string $accountRef  Shown on customer statement, e.g. package name
     * @param string $transactionDesc  Short description
     * @return array  ['success' => bool, 'checkout_request_id' => string|null, 'message' => string]
     */
    public function stkPush(string $phone, float $amount, string $accountRef, string $transactionDesc): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'checkout_request_id' => null, 'message' => 'Could not authenticate with M-Pesa'];
        }

        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => (int)$amount,
            'PartyA'            => $phone,
            'PartyB'            => $this->shortcode,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => $this->callbackUrl,
            'AccountReference'  => substr($accountRef, 0, 12),
            'TransactionDesc'   => substr($transactionDesc, 0, 13),
        ];

        $ch = curl_init($this->baseUrl() . '/mpesa/stkpush/v1/processrequest');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'checkout_request_id' => null, 'message' => $error];
        }

        $data = json_decode($response, true);

        if (isset($data['ResponseCode']) && $data['ResponseCode'] === '0') {
            return [
                'success' => true,
                'checkout_request_id' => $data['CheckoutRequestID'],
                'message' => $data['CustomerMessage'] ?? 'Request sent. Check your phone.',
            ];
        }

        return [
            'success' => false,
            'checkout_request_id' => null,
            'message' => $data['errorMessage'] ?? ($data['ResponseDescription'] ?? 'STK push failed'),
        ];
    }
}
