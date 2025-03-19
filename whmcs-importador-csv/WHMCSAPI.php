<?php
/**
 * Classe para interagir com a API do WHMCS
 */
class WHMCSAPI {
    private $url;
    private $identifier;
    private $secret;
    private $debug;
    private $ignore_ssl;
    private $lastError;
    private $lastResponse;

    /**
     * Construtor
     * 
     * @param string $url URL da API (opcional)
     * @param string $identifier Identificador da API (opcional)
     * @param string $secret Chave secreta da API (opcional)
     */
    public function __construct($url = null, $identifier = null, $secret = null) {
        global $config;
        
        // Use os parâmetros fornecidos ou as configurações do arquivo config.php
        $this->url = $url ?? $config['whmcs_url'];
        $this->identifier = $identifier ?? $config['identifier'] ?? $config['whmcs_identifier'];
        $this->secret = $secret ?? $config['secret'] ?? $config['whmcs_secret'];
        $this->debug = $config['debug'] ?? false;
        $this->ignore_ssl = $config['ignore_ssl'] ?? false;
        $this->lastError = null;
        $this->lastResponse = null;
    }

    /**
     * Chamada à API do WHMCS
     * 
     * @param array $params Parâmetros da API
     * @return array|false Resposta da API ou false em caso de erro
     */
    public function call($params) {
        // Adiciona as credenciais aos parâmetros
        $postfields = array_merge([
            'identifier' => $this->identifier,
            'secret' => $this->secret,
            'responsetype' => 'json',
        ], $params);

        // Log de DEBUG para diagnóstico
        if ($this->debug) {
            $this->logDebug("Iniciando chamada API WHMCS com parâmetros: " . json_encode(array_diff_key($postfields, ['secret' => ''])));
        }

        // EXATAMENTE como está implementado na landing page (que funciona)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        // Executa a requisição
        $response = curl_exec($ch);
        
        // Log de diagnóstico
        if ($this->debug) {
            $this->logDebug("Resposta da API WHMCS: " . $response);
        }
        
        // Verifica erro de cURL
        if (curl_errno($ch)) {
            $this->lastError = 'Erro cURL: ' . curl_error($ch);
            
            if ($this->debug) {
                $this->logDebug("Erro na requisição: " . $this->lastError);
            }
            
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        
        // Processa a resposta JSON
        $result = json_decode($response, true);
        if ($result === null) {
            $this->lastError = 'Erro ao decodificar resposta JSON: ' . $response;
            return false;
        }
        
        $this->lastResponse = $result;
        
        // Verifica se houve erro na API
        if (isset($result['result']) && $result['result'] == 'error') {
            $this->lastError = $result['message'] ?? 'Erro desconhecido na API';
            return false;
        }
        
        return $result;
    }

    /**
     * Obtém o último erro ocorrido
     */
    public function getLastError() {
        return $this->lastError;
    }

    /**
     * Obtém a última resposta
     */
    public function getLastResponse() {
        return $this->lastResponse;
    }

    /**
     * Verifica se um cliente existe pelo e-mail
     */
    public function clientExists($email) {
        $result = $this->call([
            'action' => 'GetClients',
            'search' => $email,
            'limitnum' => 1,
        ]);

        if (!$result) {
            return false;
        }

        return (isset($result['clients']['client']) && count($result['clients']['client']) > 0);
    }

    /**
     * Cria um novo cliente
     */
    public function createClient($clientData) {
        // Preservar o valor original de customfield1 se existir
        $customfield1 = $clientData['customfield1'] ?? null;
        
        // Prepara os parâmetros
        $params = [
            'action' => 'AddClient',
        ];

        // Mapeamento de campos CSV para campos da API WHMCS
        $fieldMapping = [
            'firstname' => 'firstname',
            'lastname' => 'lastname',
            'email' => 'email',
            'company' => 'companyname',
            'address1' => 'address1',
            'address2' => 'address2',
            'city' => 'city',
            'state' => 'state',
            'postcode' => 'postcode',
            'country' => 'country',
            'phonenumber' => 'phonenumber',
            'password' => 'password2', // WHMCS usa 'password2' para o campo de senha
            'notes' => 'notes',
            'customfield1' => 'customfield1' // Mapeia explicitamente o customfield1
        ];

        // Adiciona dados do cliente com o mapeamento correto
        foreach ($clientData as $key => $value) {
            if (isset($fieldMapping[$key])) {
                $params[$fieldMapping[$key]] = $value;
            } else {
                // Não adicione o campo CNPJ diretamente, será tratado separadamente
                if ($key !== 'cnpj') {
                    $params[$key] = $value;
                }
            }
        }

        // Garante que customfield1 seja preservado se já existir
        if ($customfield1 !== null) {
            $params['customfield1'] = $customfield1;
        } 
        // Caso contrário, processa o campo CNPJ/CPF normalmente
        else {
            $documentoValido = null;
            
            // Verifica se existe campo CNPJ no CSV
            if (isset($clientData['cnpj'])) {
                $documentoValido = $this->formatarDocumento($clientData['cnpj']);
            } else if (isset($clientData['documento']) || isset($clientData['cpfcnpj']) || isset($clientData['document'])) {
                // Tenta encontrar o documento em outros campos possíveis
                $documentField = $clientData['documento'] ?? $clientData['cpfcnpj'] ?? $clientData['document'] ?? '';
                $documentoValido = $this->formatarDocumento($documentField);
            }
            
            // Tratamento SIMPLIFICADO do campo customizado do CNPJ
            // Usando a mesma abordagem que funcionou na landing page
            if ($documentoValido !== null) {
                // APENAS UM formato - o que funciona na landing page
                $params['customfield1'] = $documentoValido;
                
                // Log para debug do documento
                if ($this->debug) {
                    $this->logDebug([
                        'action' => 'CNPJ/CPF - Formato Simplificado',
                        'documento_original' => $clientData['cnpj'] ?? $clientData['documento'] ?? $clientData['cpfcnpj'] ?? $clientData['document'] ?? '',
                        'documento_processado' => $documentoValido,
                        'params' => $params
                    ]);
                }
            }
        }
        
        // Garante que a senha esteja presente
        if (!isset($params['password2']) || empty($params['password2'])) {
            $params['password2'] = $this->generateRandomPassword();
        }

        // Debug - Pode remover depois
        if ($this->debug) {
            $this->logDebug([
                'action' => 'AddClient',
                'clientData' => $clientData,
                'mappedParams' => $params
            ]);
        }

        // Executa a requisição
        $result = $this->call($params);

        if (!$result) {
            return false;
        }

        return $result;
    }

    /**
     * Gera uma senha aleatória
     */
    private function generateRandomPassword($length = 10) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }

    /**
     * Valida um CPF
     * 
     * @param string $cpf CPF para validar
     * @return bool True se válido, False caso contrário
     */
    private function validaCPF($cpf) {
        // Remove formatação
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        // Verifica se tem 11 dígitos
        if (strlen($cpf) != 11) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/^(\d)\1+$/', $cpf)) {
            return false;
        }
        
        // Calcula o primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += $cpf[$i] * (10 - $i);
        }
        $resto = $soma % 11;
        $dv1 = ($resto < 2) ? 0 : 11 - $resto;
        
        // Calcula o segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += $cpf[$i] * (11 - $i);
        }
        $soma += $dv1 * 2;
        $resto = $soma % 11;
        $dv2 = ($resto < 2) ? 0 : 11 - $resto;
        
        // Verifica se os dígitos verificadores estão corretos
        return ($cpf[9] == $dv1 && $cpf[10] == $dv2);
    }
    
    /**
     * Valida um CNPJ
     * 
     * @param string $cnpj CNPJ para validar
     * @return bool True se válido, False caso contrário
     */
    private function validaCNPJ($cnpj) {
        // Remove formatação
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        // Verifica se tem 14 dígitos
        if (strlen($cnpj) != 14) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/^(\d)\1+$/', $cnpj)) {
            return false;
        }
        
        // Calcula o primeiro dígito verificador
        $soma = 0;
        $multiplicador = 5;
        for ($i = 0; $i < 12; $i++) {
            $soma += $cnpj[$i] * $multiplicador;
            $multiplicador = ($multiplicador == 2) ? 9 : $multiplicador - 1;
        }
        $resto = $soma % 11;
        $dv1 = ($resto < 2) ? 0 : 11 - $resto;
        
        // Calcula o segundo dígito verificador
        $soma = 0;
        $multiplicador = 6;
        for ($i = 0; $i < 12; $i++) {
            $soma += $cnpj[$i] * $multiplicador;
            $multiplicador = ($multiplicador == 2) ? 9 : $multiplicador - 1;
        }
        $soma += $dv1 * 2;
        $resto = $soma % 11;
        $dv2 = ($resto < 2) ? 0 : 11 - $resto;
        
        // Verifica se os dígitos verificadores estão corretos
        return ($cnpj[12] == $dv1 && $cnpj[13] == $dv2);
    }
    
    /**
     * Formata o documento para envio adequado ao WHMCS
     * 
     * @param string $document Documento (CPF ou CNPJ)
     * @return string|null Documento formatado ou null se inválido
     */
    private function formatarDocumento($document) {
        // Remove formatação
        $document = preg_replace('/[^0-9]/', '', $document);
        
        // Verifica se é CPF ou CNPJ
        if (strlen($document) == 11) {
            // É um CPF
            if ($this->validaCPF($document)) {
                return $document; // Retorna sem formatação, apenas números
            }
        } else if (strlen($document) == 14) {
            // É um CNPJ
            if ($this->validaCNPJ($document)) {
                return $document; // Retorna sem formatação, apenas números
            }
        }
        
        // Se chegou aqui, o documento é inválido
        return null;
    }

    /**
     * Log de debug
     */
    private function logDebug($data) {
        global $config;
        
        $logDir = $config['log_dir'];
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/api_' . date('Ymd') . '.log';
        $logData = date('Y-m-d H:i:s') . ' - ' . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
        
        file_put_contents($logFile, $logData, FILE_APPEND);
    }
}
