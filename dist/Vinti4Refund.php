<?php


/**
 * Classe responsável por gerir operações de ESTORNO (Refund / Reversal)
 * de transações realizadas via gateway Vinti4Net.
 *
 * @package Erilshk\Vinti4Pay
 * @version 1.0.0
 * @license MIT
 */
class Vinti4Refund
{
    /** @var string Identificador do terminal POS fornecido pelo SISP */
    private string $posID;

    /** @var string Código de autenticação POS */
    private string $posAuthCode;

    /** @var string URL base do ambiente Vinti4Net */
    private string $baseUrl;

    /** @var string URL padrão de produção */
    public const DEFAULT_BASE_URL = "https://mc.vinti4net.cv/BizMPIOnUs/CardPayment";

    /** @var string Código da moeda Cabo-verdiana (CVE) */
    public const CURRENCY_CVE = '132';

    /**
     * Construtor principal.
     *
     * @param string $posID Identificador do terminal (POS ID)
     * @param string $posAuthCode Código de autenticação POS
     * @param string|null $endpoint Endpoint alternativo (opcional)
     */
    public function __construct(string $posID, string $posAuthCode, ?string $endpoint = null)
    {
        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
        $this->baseUrl = $endpoint ?? self::DEFAULT_BASE_URL;
    }

    // ------------------------------------------------------
    // 🔐 Geração de Fingerprints (Privado)
    // ------------------------------------------------------

    /**
     * Gera o fingerprint (versão 6) utilizado em pedidos de estorno (reversal).
     *
     * @param array $data Dados a incluir no cálculo.
     * @return string Fingerprint em Base64.
     */
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

    /**
     * Gera o fingerprint (versão 7) utilizado na resposta de estorno.
     *
     * @param array $data Dados recebidos na resposta.
     * @return string Fingerprint em Base64.
     */
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
    // 💳 Criação do Formulário de Estorno
    // ------------------------------------------------------

    /**
     * Cria o formulário HTML para iniciar um pedido de estorno (Refund/Reversal).
     *
     * @param string $merchantRef Identificador da transação original.
     * @param string $merchantSession Sessão original da transação.
     * @param int|string $amount Valor do estorno.
     * @param string $clearingPeriod Período de liquidação original.
     * @param string $transactionID Identificador da transação original (TID).
     * @param string $responseUrl URL para receber a resposta do estorno.
     * @param string $language Código do idioma (ex: "pt", "en").
     *
     * @return string HTML do formulário pronto para envio automático.
     *
     * @throws \InvalidArgumentException Caso algum campo essencial esteja ausente.
     */
    public function criarFormularioEstorno(
        string $merchantRef,
        string $merchantSession,
        int|string $amount,
        string $clearingPeriod,
        string $transactionID,
        string $responseUrl,
        string $language = 'pt'
    ): string {
        // 🔍 Validação de parâmetros obrigatórios
        foreach (
            [
                'merchantRef' => $merchantRef,
                'merchantSession' => $merchantSession,
                'clearingPeriod' => $clearingPeriod,
                'transactionID' => $transactionID,
                'responseUrl' => $responseUrl,
            ] as $field => $value
        ) {
            if (empty($value)) {
                throw new \InvalidArgumentException("Campo obrigatório ausente: {$field}");
            }
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException("O valor do estorno deve ser maior que zero.");
        }

        // 🧩 Montagem dos campos do estorno
        $fields = [
            'transactionCode'      => '4', // Código de operação: Reversal
            'posID'                => $this->posID,
            'merchantRef'          => $merchantRef,
            'merchantSession'      => $merchantSession,
            'amount'               => (int)$amount,
            'currency'             => self::CURRENCY_CVE,
            'clearingPeriod'       => $clearingPeriod,
            'transactionID'        => $transactionID,
            'reversal'             => 'R',
            'urlMerchantResponse'  => $responseUrl,
            'languageMessages'     => $language,
            'fingerPrintVersion'   => '1',
            'timeStamp'            => date('Y-m-d H:i:s'),
        ];

        // 🔐 Fingerprint 6
        $fields['fingerPrint6'] = $this->generateReversalFingerprint($fields);

        // 📨 URL de envio
        $postUrl = $this->baseUrl . '?' . http_build_query([
            'FingerPrint'        => $fields['fingerPrint6'],
            'TimeStamp'          => $fields['timeStamp'],
            'FingerPrintVersion' => $fields['fingerPrintVersion'],
        ]);

        // 💾 Geração do HTML
        $inputs = '';
        foreach ($fields as $key => $value) {
            $inputs .= "<input type='hidden' name='" . htmlspecialchars($key) . "' value='" . htmlspecialchars($value) . "'>\n";
        }

        return "
        <html>
            <head><title>Estorno Vinti4Net</title></head>
            <body onload='document.forms[0].submit()' style='font-family:Arial,sans-serif;text-align:center;padding:30px;'>
                <h3>Processando o pedido de estorno...</h3>
                <p>Por favor, aguarde um momento.</p>
                <form action='{$postUrl}' method='post'>{$inputs}</form>
            </body>
        </html>";
    }

    // ------------------------------------------------------
    // 📩 Processamento da Resposta de Estorno
    // ------------------------------------------------------

    /**
     * Processa e valida a resposta de um pedido de estorno (Refund/Reversal).
     *
     * @param array $postData Dados recebidos no callback do SISP (geralmente $_POST).
     * @return array{
     *     status: string,
     *     message: string,
     *     success: bool,
     *     data: array,
     *     detail?: string,
     *     debug?: array
     * }
     */
    public function processarRespostaEstorno(array $postData): array
    {
        $result = [
            'status'  => 'ERROR',
            'message' => 'Erro desconhecido na resposta de estorno.',
            'success' => false,
            'data'    => $postData,
        ];

        $messageType = $postData['messageType'] ?? null;

        // ⚠️ Nenhuma resposta recebida
        if ($messageType === null) {
            $result['message'] = 'Nenhuma resposta recebida (timeout ou falha de rede).';
            return $result;
        }

        // ❌ Estorno falhou (messageType 6)
        if ($messageType === '6') {
            $result['status']  = 'ERROR';
            $result['message'] = $postData['merchantRespErrorDescription'] ?? 'Erro desconhecido no estorno.';
            $result['detail']  = $postData['merchantRespErrorDetail'] ?? '';
            return $result;
        }

        // ✅ Estorno bem-sucedido (messageType 10)
        if ($messageType === '10') {
            $calcFingerprint = $this->generateRefundResponseFingerprint($postData);
            $receivedFingerprint = $postData['resultFingerPrint7'] ?? '';

            if ($receivedFingerprint === $calcFingerprint) {
                $result['status']  = 'SUCCESS';
                $result['message'] = 'Estorno processado com sucesso e fingerprint válido.';
                $result['success'] = true;
            } else {
                $result['status']  = 'INVALID_FINGERPRINT';
                $result['message'] = 'Estorno processado, mas fingerprint inválido.';
                $result['success'] = true;
                $result['debug'] = [
                    'received'   => $receivedFingerprint,
                    'calculated' => $calcFingerprint,
                ];
            }
            return $result;
        }

        // 🟡 Resposta inesperada
        $result['message'] = $postData['merchantRespErrorDescription'] ?? 'Tipo de resposta inesperado no estorno.';
        $result['detail']  = $postData['merchantRespErrorDetail'] ?? '';

        return $result;
    }
}
