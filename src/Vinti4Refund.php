<?php

namespace Erilshk\Vinti4Pay;

/**
 * Class Vinti4Refund
 *
 * Handles refund (reversal) operations for Vinti4Net transactions.
 * This class is separated from Vinti4Pay for clarity and SRP compliance.
 *
 * @package Erilshk\Vinti4Pay
 * @version 2.0.0
 * @license MIT
 * @link https://www.vinti4.cv/documentation.aspx?id=585
 */
class Vinti4Refund
{
    private string $posID;
    private string $posAuthCode;
    private string $baseUrl;

    const DEFAULT_BASE_URL = "https://mc.vinti4net.cv/BizMPIOnUs/CardPayment";
    const CURRENCY_CVE = '132';

    public function __construct(string $posID, string $posAuthCode, ?string $endpoint = null)
    {
        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
        $this->baseUrl = $endpoint ?? self::DEFAULT_BASE_URL;
    }

    // ------------------------------------------------------
    // 🔐 Fingerprint Generation (Private)
    // ------------------------------------------------------

    private function generateReversalFingerprint(array $data): string
    {
        $toHash = base64_encode(hash('sha512', $this->posAuthCode, true)) .
            $data['transactionCode'] .
            $data['posID'] .
            $data['merchantRef'] .
            $data['merchantSession'] .
            $data['amount'] .
            $data['currency'] .
            $data['clearingPeriod'] .
            $data['transactionID'] .
            $data['reversal'] .
            $data['urlMerchantResponse'] .
            $data['languageMessages'] .
            $data['fingerPrintVersion'] .
            $data['timeStamp'];

        return base64_encode(hash('sha512', $toHash, true));
    }

    private function generateRefundResponseFingerprint(array $data): string
    {
        $toHash = base64_encode(hash('sha512', $this->posAuthCode, true)) .
            $data['merchantRespMerchantRef'] .
            $data['merchantRespMerchantSession'] .
            ($data['merchantRespErrorCode'] ?? '') .
            ($data['merchantRespErrorDescription'] ?? '') .
            ($data['merchantRespErrorDetail'] ?? '') .
            ($data['merchantRespAdditionalErrorMessage'] ?? '') .
            ($data['merchantRespCP'] ?? '') .
            ($data['merchantRespTid'] ?? '') .
            ($data['merchantRespMessageID'] ?? '') .
            ($data['languageMessages'] ?? '') .
            ($data['messageType'] ?? '') .
            ($data['merchantRespTimeStamp'] ?? '') .
            ($data['resultFingerPrintVersion'] ?? '');

        return base64_encode(hash('sha512', $toHash, true));
    }

    // ------------------------------------------------------
    // 🧾 Prepare Refund
    // ------------------------------------------------------

    /**
     * Prepare a refund (reversal) request.
     *
     * @param string $merchantRef Original payment reference
     * @param string $merchantSession Original session ID
     * @param int|string $amount Original transaction amount (CVE)
     * @param string $clearingPeriod Original transaction clearing period (merchantRespCP)
     * @param string $transactionID Original transaction ID (merchantRespTid)
     * @param string $responseUrl Callback URL for refund response
     * @param string $language ISO 639-1 language code (default 'en')
     * @return array{postUrl:string, fields:array}
     */
    public function prepareRefund(
        string $merchantRef,
        string $merchantSession,
        int|string $amount,
        string $clearingPeriod,
        string $transactionID,
        string $responseUrl,
        string $language = 'en'
    ): array {
        $fields = [
            'transactionCode' => '4',
            'posID' => $this->posID,
            'merchantRef' => $merchantRef,
            'merchantSession' => $merchantSession,
            'amount' => (int)$amount,
            'currency' => self::CURRENCY_CVE,
            'clearingPeriod' => $clearingPeriod,
            'transactionID' => $transactionID,
            'reversal' => 'R',
            'urlMerchantResponse' => $responseUrl,
            'languageMessages' => $language,
            'fingerPrintVersion' => '1',
            'timeStamp' => date('Y-m-d H:i:s')
        ];

        $fields['fingerPrint6'] = $this->generateReversalFingerprint($fields);

        $postUrl = $this->baseUrl .
            "?FingerPrint=" . urlencode($fields["fingerPrint6"]) .
            "&TimeStamp=" . urlencode($fields["timeStamp"]) .
            "&FingerPrintVersion=" . urlencode($fields["fingerPrintVersion"]);

        return [
            'postUrl' => $postUrl,
            'fields' => $fields
        ];
    }

    // ------------------------------------------------------
    // 🧾 Process Refund Response
    // ------------------------------------------------------

    /**
     * Process and validate refund (reversal) response from Vinti4Net.
     *
     * @param array $postData Normally $_POST from Vinti4Net callback.
     * @return array{
     *     status: string,
     *     message: string,
     *     success: bool,
     *     data: array,
     *     debug?: array,
     *     detail?: string
     * }
     */
    public function processRefundResponse(array $postData): array
    {
        $result = [
            'status' => 'ERROR',
            'message' => 'Unknown refund error.',
            'success' => false,
            'data' => $postData
        ];

        $messageType = $postData['messageType'] ?? null;

        if ($messageType === null) {
            $result['message'] = 'No response from SISP (timeout or network issue).';
            return $result;
        }

        if ($messageType === '6') {
            $result['status'] = 'ERROR';
            $result['message'] = $postData['merchantRespErrorDescription'] ?? 'Unknown error';
            $result['detail'] = $postData['merchantRespErrorDetail'] ?? '';
            return $result;
        }

        if ($messageType === '10') {
            $calcFingerprint = $this->generateRefundResponseFingerprint($postData);
            $receivedFingerprint = $postData['resultFingerPrint7'] ?? '';

            if ($receivedFingerprint === $calcFingerprint) {
                $result['status'] = 'SUCCESS';
                $result['message'] = 'Refund processed successfully and fingerprint valid.';
                $result['success'] = true;
            } else {
                $result['status'] = 'INVALID_FINGERPRINT';
                $result['message'] = 'Refund processed but fingerprint invalid.';
                $result['success'] = true;
                $result['debug'] = [
                    'received' => $receivedFingerprint,
                    'calculated' => $calcFingerprint
                ];
            }
            return $result;
        }

        $result['message'] = $postData['merchantRespErrorDescription'] ?? 'Unexpected messageType in refund response.';
        $result['detail'] = $postData['merchantRespErrorDetail'] ?? '';

        return $result;
    }
}
