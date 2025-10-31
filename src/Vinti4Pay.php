<?php

namespace Erilshk\Vinti4Pay;


// header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
// header("Cache-Control: post-check=0, pre-check=0", false);
// header("Pragma: no-cache");

/**
 * Class Vinti4Pay
 *
 * PHP SDK for integrating with the Vinti4Net payment system (SISP Cabo Verde, MOP021 service).
 * This class allows creating, sending, and validating online payment transactions, including 3DS support.
 *
 * Main features:
 * - Prepare a payment (preparePayment)
 * - Generate fingerprints for security
 * - Generate auto-submitting HTML forms
 * - Process callback responses from Vinti4Net
 *
 * @package App\Services
 * @version 1.0.0
 * @license MIT
 * @link https://www.vinti4.cv/documentation.aspx?id=585
 */
class Vinti4Pay
{
    // -------------------------
    // URLs and Constants
    // -------------------------

    const DEFAULT_BASE_URL = "https://mc.vinti4net.cv/BizMPIOnUs/CardPayment";

    // Transaction types
    const TRANSACTION_TYPE_PURCHASE = '1';
    const TRANSACTION_TYPE_SERVICE_PAYMENT = '2';
    const TRANSACTION_TYPE_RECHARGE = '3';

    // Currency codes
    const CURRENCY_CVE = '132'; // Cape Verde Escudo

    // Message types indicating success
    const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];

    // -------------------------
    // Credentials
    // -------------------------

    private string $posID;
    private string $posAuthCode;
    private string $baseUrl;

    /**
     * Vinti4Pay constructor.
     *
     * @param string $posID Point of Sale ID
     * @param string $posAuthCode Secret Authorization Code
     * @param string|null $endpoint Optional endpoint URL (defaults to production)
     */
    public function __construct(string $posID, string $posAuthCode, ?string $endpoint = null)
    {
        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
        $this->baseUrl = $endpoint ?? self::DEFAULT_BASE_URL;
    }

    // -------------------------
    // PRIVATE: Fingerprint Generation
    // -------------------------

    private function generateRequestFingerprint(array $data): string
    {
        $entityCode = !empty($data['entityCode']) ? (int)$data['entityCode'] : '';
        $referenceNumber = !empty($data['referenceNumber']) ? (int)$data['referenceNumber'] : '';
        $amountInMille = (int)((float)$data['amount'] * 1000);

        $toHash = base64_encode(hash('sha512', $this->posAuthCode, true)) .
            $data['timeStamp'] .
            $amountInMille .
            $data['merchantRef'] .
            $data['merchantSession'] .
            $data['posID'] .
            $data['currency'] .
            $data['transactionCode'] .
            $entityCode .
            $referenceNumber;

        return base64_encode(hash('sha512', $toHash, true));
    }

    private function generateResponseFingerprint(array $data): string
    {
        $reference = !empty($data['merchantRespReferenceNumber']) ? (int)$data['merchantRespReferenceNumber'] : '';
        $entity = !empty($data['merchantRespEntityCode']) ? (int)$data['merchantRespEntityCode'] : '';
        $reloadCode = $data['merchantRespReloadCode'] ?? '';
        $additionalErrorMessage = trim($data['merchantRespAdditionalErrorMessage'] ?? '');

        $toHash = base64_encode(hash('sha512', $this->posAuthCode, true)) .
            $data["messageType"] .
            $data["merchantRespCP"] .
            $data["merchantRespTid"] .
            $data["merchantRespMerchantRef"] .
            $data["merchantRespMerchantSession"] .
            ((int)((float)$data["merchantRespPurchaseAmount"] * 1000)) .
            $data["merchantRespMessageID"] .
            $data["merchantRespPan"] .
            $data["merchantResp"] .
            $data["merchantRespTimeStamp"] .
            $reference .
            $entity .
            $data["merchantRespClientReceipt"] .
            $additionalErrorMessage .
            $reloadCode;

        return base64_encode(hash('sha512', $toHash, true));
    }

    // -------------------------
    // PUBLIC: Payment Preparation
    // -------------------------

    /**
     * Prepare payment data for Vinti4Net
     *
     * Prepares the transaction data including 3DS purchaseRequest (if applicable) 
     * and fingerprint for security. Does not generate HTML form.
     *
     * @param float|string $amount Transaction amount
     * @param string $responseUrl Callback URL for the payment response
     * @param array $options Additional options, such as:
     *   - 'billing' => array of billing data (required for purchases)
     *   - 'transactionCode' => transaction type ('1'=Purchase, '2'=Service, '3'=Recharge)
     *   - 'merchantRef', 'merchantSession', 'languageMessages', 'entityCode', 'referenceNumber'
     * @return array{postUrl:string, fields:array} Contains the POST URL and fields to submit
     * @throws \InvalidArgumentException If required billing fields for purchase are missing
     */
    public function preparePayment(
        float|string $amount,
        string $responseUrl,
        array $options = []
    ): array {
        $billing = $options['billing'] ?? [];
        $dateTime = date('Y-m-d H:i:s');

        $fields = [
            'transactionCode' => $options['transactionCode'] ?? self::TRANSACTION_TYPE_PURCHASE,
            'posID' => $this->posID,
            'merchantRef' => $options['merchantRef'] ?? 'R' . date('YmdHis'),
            'merchantSession' => $options['merchantSession'] ?? 'S' . date('YmdHis'),
            'amount' => (float)$amount,
            'currency' => self::CURRENCY_CVE,
            'is3DSec' => '1',
            'urlMerchantResponse' => $responseUrl,
            'languageMessages' => $options['languageMessages'] ?? 'en',
            'timeStamp' => $options['timeStamp'] ?? $dateTime,
            'fingerprintversion' => '1',
            'entityCode' => $options['entityCode'] ?? '',
            'referenceNumber' => $options['referenceNumber'] ?? '',
        ];

        // 3DS purchase request
        if ($fields['transactionCode'] === self::TRANSACTION_TYPE_PURCHASE && !empty($billing)) {

            /* 
            $user = $billing['user'] ?? [];

            if ($user) {
                if (isset($user['email'])) $billing['email'] ??= $user['email'];
                if (isset($user['country'])) $billing['billAddrCountry'] ??= $user['country'];
                if (isset($user['city'])) $billing['billAddrCity'] ??= $user['city'];
                if (isset($user['address'])) $billing['billAddrLine1'] ??= $user['address'];
                if (isset($user['address1'])) $billing['billAddrLine1'] ??= $user['address1'];
                if (isset($user['address2'])) $billing['billAddrLine2'] ??= $user['address2'];
                if (isset($user['address3'])) $billing['billAddrLine3'] ??= $user['address3'];
                if (isset($user['postCode'])) $billing['billAddrPostCode'] ??= $user['postCode'];
                if (isset($user['phone'])) {
                    $billing['mobilePhone'] = [
                        'cc' => $billing['mobilePhone']['cc'] ?? '123',
                        'subscriber' =>  $billing['mobilePhone']['subscriber'] ??  $user['phone'],
                    ];
                }
            }
            */

            $required = ['billAddrCountry', 'billAddrCity', 'billAddrLine1', 'billAddrPostCode', 'email'];
            $missing = array_diff($required, array_keys($billing));
            if (!empty($missing)) {
                throw new \InvalidArgumentException("Missing billing fields: " . implode(', ', $missing));
            }

            $fields['purchaseRequest'] = $this->generatePurchaseRequest(
                $billing['billAddrCountry'],
                $billing['billAddrCity'],
                $billing['billAddrLine1'],
                $billing['billAddrPostCode'],
                $billing['email'],
                array_diff_key($billing, array_flip($required))
            );
        }

        $fields['fingerprint'] = $this->generateRequestFingerprint($fields);

        $postUrl = $this->baseUrl .
            "?FingerPrint=" . urlencode($fields["fingerprint"]) .
            "&TimeStamp=" . urlencode($fields["timeStamp"]) .
            "&FingerPrintVersion=" . urlencode($fields["fingerprintversion"]);

        return [
            'postUrl' => $postUrl,
            'fields' => $fields
        ];
    }

    /**
     * Generate 3DS purchaseRequest Base64 JSON
     */
    private function generatePurchaseRequest(
        string $country,
        string $city,
        string $line1,
        string $postcode,
        string $email,
        array $additional = []
    ): string {
        $payload = array_merge([
            'billAddrCountry' => $country,
            'billAddrCity' => $city,
            'billAddrLine1' => $line1,
            'billAddrPostCode' => $postcode,
            'email' => $email
        ], $additional);

        if (($payload['addrMatch'] ?? 'N') === 'Y') {
            $payload['shipAddrCountry'] = $payload['billAddrCountry'];
            $payload['shipAddrCity'] = $payload['billAddrCity'];
            $payload['shipAddrLine1'] = $payload['billAddrLine1'];
            if (isset($payload['billAddrLine2'])) $payload['shipAddrLine2'] = $payload['billAddrLine2'];
            if (isset($payload['billAddrPostCode'])) $payload['shipAddrPostCode'] = $payload['billAddrPostCode'];
        }

        $clean = array_filter($payload, fn($v) => !empty($v) || is_numeric($v));
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new \Exception("Failed to encode purchaseRequest to JSON.");
        }

        return base64_encode($json);
    }

    /**
     * Generate auto-submitting HTML form for a prepared payment
     *
     * @param array $paymentData Result from preparePayment()
     * @return string HTML form ready to submit to Vinti4Net
     */
    public function generateHtmlForm(array $paymentData): string
    {
        $htmlFields = '';
        foreach ($paymentData['fields'] as $key => $value) {
            $htmlFields .= "<input type='hidden' name='" . htmlspecialchars($key) . "' value='" . htmlspecialchars($value) . "'>";
        }

        return "
        <html>
            <head><title>Vinti4Pay Payment</title></head>
            <body onload='document.forms[0].submit()'>
                <div>
                    <h5>Processing payment... Please wait.</h5>
                    <form action='{$paymentData['postUrl']}' method='post'>
                        {$htmlFields}
                    </form>
                </div>
            </body>
        </html>
        ";
    }

    // -------------------------
    // PUBLIC: Payment Forms (Direct methods)
    // -------------------------

    /**
     * Create HTML form for a purchase transaction (TransactionCode=1)
     *
     * @param float|string $amount Transaction amount
     * @param string $responseUrl Callback URL
     * @param array $billing Billing data (required):
     *   - 'billAddrCountry', 'billAddrCity', 'billAddrLine1', 'billAddrPostCode', 'email'
     *   - optionally 'addrMatch', 'billAddrLine2', etc.
     * @param array $extras Additional optional parameters
     * @return string HTML form ready to submit
     * @throws \InvalidArgumentException If required billing fields are missing
     */
    public function createPurchaseForm(float|string $amount, string $responseUrl, array $billing, array $extras = []): string
    {
        return $this->generateHtmlForm(
            $this->preparePayment($amount, $responseUrl, array_merge($extras, [
                'billing' => $billing,
                'transactionCode' => self::TRANSACTION_TYPE_PURCHASE
            ]))
        );
    }

    /**
     * Create HTML form for a service payment (TransactionCode=2)
     *
     * @param float|string $amount Transaction amount
     * @param string $responseUrl Callback URL
     * @param string $entityCode Numeric entity code (required)
     * @param string $referenceNumber Numeric reference number (required)
     * @param array $extras Additional optional parameters
     * @return string HTML form ready to submit
     * @throws \InvalidArgumentException If entityCode or referenceNumber are not numeric
     */
    public function createServicePaymentForm(float|string $amount, string $responseUrl, string $entityCode, string $referenceNumber, array $extras = []): string
    {
        if (!ctype_digit($entityCode) || !ctype_digit($referenceNumber)) {
            throw new \InvalidArgumentException("entityCode and referenceNumber must be numeric.");
        }

        return $this->generateHtmlForm(
            $this->preparePayment($amount, $responseUrl, array_merge($extras, [
                'entityCode' => $entityCode,
                'referenceNumber' => $referenceNumber,
                'transactionCode' => self::TRANSACTION_TYPE_SERVICE_PAYMENT
            ]))
        );
    }

    /**
     * Create HTML form for a recharge transaction (TransactionCode=3)
     *
     * @param float|string $amount Transaction amount
     * @param string $responseUrl Callback URL
     * @param string $entityCode Numeric entity code (required)
     * @param string $referenceNumber Numeric reference number (required)
     * @param array $extras Additional optional parameters
     * @return string HTML form ready to submit
     * @throws \InvalidArgumentException If entityCode or referenceNumber are not numeric
     */
    public function createRechargeForm(float|string $amount, string $responseUrl, string $entityCode, string $referenceNumber, array $extras = []): string
    {
        if (!ctype_digit($entityCode) || !ctype_digit($referenceNumber)) {
            throw new \InvalidArgumentException("entityCode and referenceNumber must be numeric.");
        }

        return $this->generateHtmlForm(
            $this->preparePayment($amount, $responseUrl, array_merge($extras, [
                'entityCode' => $entityCode,
                'referenceNumber' => $referenceNumber,
                'transactionCode' => self::TRANSACTION_TYPE_RECHARGE
            ]))
        );
    }

    // -------------------------
    // PUBLIC: Response Processing
    // -------------------------

    /**
     * Process and validate callback response from Vinti4Net
     *
     * Checks for user cancellation, success status, fingerprint validation, 
     * and decodes DCC data if available.
     *
     * @param array $postData Normally $_POST from Vinti4Net callback
     * @return array{
     *     status: string, // 'SUCCESS', 'CANCELLED', 'INVALID_FINGERPRINT', 'ERROR'
     *     message: string, // Human-readable message
     *     success: bool, // true if transaction succeeded (fingerprint may be invalid)
     *     data: array, // Original POST data
     *     dcc?: array, // Decoded DCC data if present
     *     debug?: array, // Optional debug info for fingerprint mismatch
     *     detail?: string // Optional error detail
     * }
     */
    public function processResponse(array $postData): array
    {
        $result = [
            'status' => 'ERROR',
            'message' => 'Unknown transaction error.',
            'success' => false,
            'data' => $postData,
            'dcc' => []
        ];

        // User cancelled
        if (($postData['UserCancelled'] ?? '') === 'true') {
            $result['status'] = 'CANCELLED';
            $result['message'] = 'User cancelled the payment.';
            return $result;
        }

        // DCC parsing
        if (!empty($postData['merchantRespDCCData'])) {
            $decoded = json_decode($postData['merchantRespDCCData'], true);
            $result['dcc'] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
        }

        // Success
        if (isset($postData['messageType']) && in_array($postData['messageType'], self::SUCCESS_MESSAGE_TYPES)) {
            $calcFingerprint = $this->generateResponseFingerprint($postData);
            $receivedFingerprint = $postData['resultFingerPrint'] ?? '';

            if ($receivedFingerprint === $calcFingerprint) {
                $result['status'] = 'SUCCESS';
                $result['message'] = 'Transaction valid and fingerprint verified.';
                $result['success'] = true;
            } else {
                $result['status'] = 'INVALID_FINGERPRINT';
                $result['message'] = 'Transaction processed but fingerprint invalid.';
                $result['success'] = true;
                $result['debug'] = [
                    'received' => $receivedFingerprint,
                    'calculated' => $calcFingerprint
                ];
            }
        } else {
            $result['message'] = $postData['merchantRespErrorDescription'] ?? $result['message'];
            $result['detail'] = $postData['merchantRespErrorDetail'] ?? '';
        }

        return $result;
        // return Vinti4Result::fromArray($result);
    }
}
