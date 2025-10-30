<?php

namespace Erilshk\Vinti4Net;

/**
 * Class Vinti4Net
 *
 * SDK PHP para integração com o sistema de pagamentos Vinti4Net (SISP Cabo Verde, Serviço MOP021).
 * Permite criar, enviar e validar transações online, incluindo suporte a 3DS e DCC.
 *
 * Funcionalidades:
 * - Preparação de pagamento (purchaseRequest)
 * - Geração de fingerprint para segurança
 * - Geração de formulário HTML auto-submissível
 * - Validação de resposta (callback) da SISP
 *
 * @author Eril TS Carvalho
 * @version 0.1.0
 * @license MIT
 */
class Vinti4Net
{
    const DEFAULT_BASE_URL = "https://mc.vinti4net.cv/BizMPIOnUs/CardPayment";
    const TRANSACTION_TYPE_COMPRA = '1';
    const TRANSACTION_TYPE_PAGAMENTO_SERVICO = '2';
    const TRANSACTION_TYPE_RECARGA = '3';
    const CURRENCY_CVE = '132';
    const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];

    private $posID;
    private $posAutCode;
    private $baseUrl;

    /**
     * Construtor da classe.
     *
     * @param string $posID Código do Ponto de Venda
     * @param string $posAutCode Código de Autorização Secreto
     * @param string|null $endpoint URL do endpoint de pagamento (default: produção)
     */
    public function __construct(string $posID, string $posAutCode, ?string $endpoint = null)
    {
        $this->posID = $posID;
        $this->posAutCode = $posAutCode;
        $this->baseUrl = $endpoint ?? self::DEFAULT_BASE_URL;
    }

    /**
     * Prepara os dados de pagamento, gera purchaseRequest e fingerprint.
     *
     * @param float|string $amount Valor da transação
     * @param string $responseUrl URL de callback para resposta
     * @param array{
     *  transactionCode?:string,merchantRef?:string,merchantSession?:string,languageMessages?:string,entityCode?:int,referenceNumber?:int,
     *  billing?:array{billAddrCountry:string,billAddrCity:string,billAddrLine1:string,billAddrPostCode:string,email:string}
     * } $aditionals Dados adicionais, ex.: billing, transactionCode
     * @return array{postUrl:string, fields:array} URL de POST e campos do formulário
     * @throws \InvalidArgumentException Se dados de billing obrigatórios estiverem ausentes
     */
    public function prepararPagamento(float|string $amount, string $responseUrl, array $aditionals = []): array
    {
        $billingData = $aditionals['billing'] ?? $aditionals['3DS'] ?? [];
        $dateTime = date('Y-m-d H:i:s');

        $fields = [
            'transactionCode' => $aditionals['transactionCode'] ?? self::TRANSACTION_TYPE_COMPRA,
            'posID' => $this->posID,
            'merchantRef' => $aditionals['merchantRef'] ?? 'R' . date('YmdHis'),
            'merchantSession' => $aditionals['merchantSession'] ?? 'S' . date('YmdHis'),
            'amount' => (int)(float)$amount,
            'currency' => self::CURRENCY_CVE,
            'is3DSec' => '1',
            'urlMerchantResponse' => $responseUrl,
            'languageMessages' => $aditionals['languageMessages'] ?? 'pt',
            'timeStamp' => $aditionals['timeStamp'] ?? $dateTime,
            'fingerprintversion' => '1',
            'entityCode' => $aditionals['entityCode'] ?? '',
            'referenceNumber' => $aditionals['referenceNumber'] ?? '',
        ];

        if ($fields['transactionCode'] === self::TRANSACTION_TYPE_COMPRA && !empty($billingData)) {
            $requiredKeys = ['billAddrCountry','billAddrCity','billAddrLine1','billAddrPostCode','email'];
            $missingKeys = array_diff_key(array_flip($requiredKeys), $billingData);

            if (!empty($missingKeys)) {
                throw new \InvalidArgumentException("Campos obrigatórios de cobrança ausentes: " . implode(', ', array_keys($missingKeys)));
            }

            $requiredData = array_intersect_key($billingData, array_flip($requiredKeys));
            $emptyFields = array_filter($requiredData, fn($v) => empty($v));

            if (!empty($emptyFields)) {
                throw new \InvalidArgumentException("Campos obrigatórios de cobrança vazios: " . implode(', ', array_keys($emptyFields)));
            }

            $additionalData = array_diff_key($billingData, array_flip($requiredKeys));
            $fields['purchaseRequest'] = $this->GerarPurchaseRequest(
                $requiredData['billAddrCountry'],
                $requiredData['billAddrCity'],
                $requiredData['billAddrLine1'],
                $requiredData['billAddrPostCode'],
                $requiredData['email'],
                $additionalData
            );
        }

        $fields['fingerprint'] = $this->GerarFingerPrintEnvio($fields);

        $postUrl = $this->baseUrl
            . "?FingerPrint=" . urlencode($fields["fingerprint"])
            . "&TimeStamp=" . urlencode($fields["timeStamp"])
            . "&FingerPrintVersion=" . urlencode($fields["fingerprintversion"]);

        return ['postUrl' => $postUrl, 'fields' => $fields];
    }

    /**
     * Gera formulário HTML auto-submissível para envio de pagamento.
     *
     * @param array $paymentData Array retornado por prepararPagamento()
     * @return string Código HTML do formulário
     */
    public function gerarFormularioHtml(array $paymentData): string
    {
        $htmlFields = '';
        foreach ($paymentData['fields'] as $key => $value) {
            $htmlFields .= "<input type='hidden' name='" . htmlspecialchars($key) . "' value='" . htmlspecialchars($value) . "'>";
        }

        return "
        <html>
            <head><title>Pagamento Vinti4Net</title></head>
            <body onload='document.forms[0].submit()'>
                <form action='{$paymentData['postUrl']}' method='post'>
                    {$htmlFields}
                </form>
            </body>
        </html>";
    }

    /**
     * Valida a resposta da transação (callback).
     *
     * @param array $postData Normalmente $_POST
     * @return array{
     *     status: string,
     *     message: string,
     *     success: bool,
     *     data: array,
     *     debug?: array,
     *     detail?: string
     * }
     */
    public function validarResposta(array $postData): array
    {
        $dccData = [];
        if (!empty($postData['merchantRespDCCData'])) {
            $decoded = json_decode($postData['merchantRespDCCData'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $dccData = $decoded;
            }
        }
        $postData['dcc'] = $dccData;

        $result = ['status' => 'ERROR', 'message' => 'Erro desconhecido na transação.', 'success' => false, 'data' => $postData];

        if (!empty($postData["UserCancelled"]) && $postData["UserCancelled"] === "true") {
            return ['status' => 'CANCELLED', 'message' => 'Transação cancelada pelo usuário.', 'success' => false, 'data' => $postData];
        }

        if (!empty($postData["messageType"]) && in_array($postData["messageType"], self::SUCCESS_MESSAGE_TYPES)) {
            $fingerPrintCalculado = $this->GerarFingerPrintRespostaBemSucedida($postData);

            if (($postData["resultFingerPrint"] ?? '') === $fingerPrintCalculado) {
                return ['status' => 'SUCCESS', 'message' => 'Transação bem-sucedida.', 'success' => true, 'data' => $postData];
            }

            return [
                'status' => 'INVALID_FINGERPRINT',
                'message' => 'Fingerprint inválido.',
                'success' => true,
                'data' => $postData,
                'debug' => ['recebido' => $postData["resultFingerPrint"] ?? 'N/A','calculado' => $fingerPrintCalculado]
            ];
        }

        $result['message'] = $postData["merchantRespErrorDescription"] ?? 'Erro desconhecido.';
        $result['detail'] = $postData["merchantRespErrorDetail"] ?? '';
        return $result;
    }

    // ---------------------- PRIVATE ----------------------

    private function GerarFingerPrintEnvio(array $data): string
    {
        $entityCode = !empty($data['entityCode']) ? (int)$data['entityCode'] : '';
        $referenceNumber = !empty($data['referenceNumber']) ? (int)$data['referenceNumber'] : '';
        $amountInMille = (int)((float)$data['amount'] * 1000);

        $toHash = base64_encode(hash('sha512', $this->posAutCode, true))
            . $data['timeStamp']
            . $amountInMille
            . $data['merchantRef']
            . $data['merchantSession']
            . $data['posID']
            . $data['currency']
            . $data['transactionCode']
            . $entityCode
            . $referenceNumber;

        return base64_encode(hash('sha512', $toHash, true));
    }

    private function GerarFingerPrintRespostaBemSucedida(array $data): string
    {
        $reference = !empty($data['merchantRespReferenceNumber']) ? (int)$data['merchantRespReferenceNumber'] : '';
        $entity = !empty($data['merchantRespEntityCode']) ? (int)$data['merchantRespEntityCode'] : '';
        $reloadCode = $data['merchantRespReloadCode'] ?? '';
        $additionalErrorMessage = trim($data['merchantRespAdditionalErrorMessage'] ?? '');

        $toHash = base64_encode(hash('sha512', $this->posAutCode, true))
            . $data["messageType"]
            . $data["merchantRespCP"]
            . $data["merchantRespTid"]
            . $data["merchantRespMerchantRef"]
            . $data["merchantRespMerchantSession"]
            . ((int)((float)$data["merchantRespPurchaseAmount"] * 1000))
            . $data["merchantRespMessageID"]
            . $data["merchantRespPan"]
            . $data["merchantResp"]
            . $data["merchantRespTimeStamp"]
            . $reference
            . $entity
            . $data["merchantRespClientReceipt"]
            . $additionalErrorMessage
            . $reloadCode;

        return base64_encode(hash('sha512', $toHash, true));
    }

    private function GerarPurchaseRequest(
        string $billAddrCountry,
        string $billAddrCity,
        string $billAddrLine1,
        string $billAddrPostCode,
        string $email,
        array $additionalData = []
    ): string {
        $payload = array_merge([
            'billAddrCountry' => $billAddrCountry,
            'billAddrCity' => $billAddrCity,
            'billAddrLine1' => $billAddrLine1,
            'billAddrPostCode' => $billAddrPostCode,
            'email' => $email,
        ], $additionalData);

        if (($payload['addrMatch'] ?? 'N') === 'Y') {
            $payload['shipAddrCountry'] = $payload['billAddrCountry'];
            $payload['shipAddrCity'] = $payload['billAddrCity'];
            $payload['shipAddrLine1'] = $payload['billAddrLine1'];
            foreach (['Line2','Line3','PostCode','State'] as $field) {
                if (isset($payload["billAddr{$field}"])) {
                    $payload["shipAddr{$field}"] = $payload["billAddr{$field}"];
                }
            }
        }

        $cleanPayload = array_filter($payload, fn($v) => !empty($v) || is_numeric($v));
        $json = json_encode($cleanPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new \Exception("Erro ao codificar PurchaseRequest");

        return base64_encode($json);
    }
}
