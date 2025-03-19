<?php
/**
 * Script de importação direta - baseado diretamente no código da landing page que funciona
 * Este script ignora a estrutura complexa do importador e usa diretamente o código que sabemos que funciona
 */

// Define o encoding
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Habilita logs de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/importacao-direta.log');

// Função para log
function log_mensagem($mensagem) {
    echo $mensagem . "\n";
    error_log($mensagem);
}

// Configurações da API WHMCS - EXATAMENTE igual ao da landing page
$whmcs_url = 'https://cliente.claveinternet.com.br/includes/api.php';
$identifier = '3UOVmg8BPQ1KdUfiEjp0b2OBB3Q3Pp6K';
$secret = 'QmRc0OKczFa0OuLfyRJXqo0gw0HqRsmD';

// Função para ler o CSV
function lerCSV($arquivo) {
    $linhas = [];
    if (($handle = fopen($arquivo, "r")) !== FALSE) {
        // Ler o cabeçalho
        $cabecalho = fgetcsv($handle, 1000, ",");
        
        // Ler as linhas de dados
        while (($dados = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($dados) == count($cabecalho)) {
                $linha = array_combine($cabecalho, $dados);
                $linhas[] = $linha;
            }
        }
        fclose($handle);
    }
    return $linhas;
}

// Função para criar cliente no WHMCS - CÓDIGO EXATO da landing page
function criarClienteWHMCS($cliente) {
    global $whmcs_url, $identifier, $secret;
    
    // Verifica se é cnpj ou cpf_cnpj no CSV (compatibilidade)
    $documento = isset($cliente['cpf_cnpj']) ? $cliente['cpf_cnpj'] : $cliente['cnpj'];
    
    // Remove máscara do documento
    $cpf_cnpj = preg_replace('/[^0-9]/', '', $documento);
    
    // Log do documento
    log_mensagem("CPF/CNPJ após limpeza: " . $cpf_cnpj);
    
    // Prepara os dados do cliente
    $postfields = array(
        'identifier' => $identifier,
        'secret' => $secret,
        'action' => 'AddClient',
        'firstname' => $cliente['firstname'],
        'lastname' => $cliente['lastname'],
        'email' => $cliente['email'],
        'address1' => $cliente['address1'] ?? 'Rua Test',
        'city' => $cliente['city'] ?? 'São Paulo',
        'state' => $cliente['state'] ?? 'SP',
        'postcode' => $cliente['postcode'] ?? '01000-000',
        'country' => $cliente['country'] ?? 'BR',
        'phonenumber' => $cliente['phonenumber'] ?? '11999999999',
        'password2' => $cliente['password'] ?? bin2hex(random_bytes(8)),
        'clientip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'responsetype' => 'json'
    );

    // Correto formato para campos customizados WHMCS - EXATAMENTE como na landing page
    $postfields['customfield1'] = $cpf_cnpj;

    // Alternativa - formato de array - EXATAMENTE como na landing page
    $customFieldValues = array();
    $customFieldValues['customfield'][1] = $cpf_cnpj;
    
    foreach ($customFieldValues as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $subkey => $subvalue) {
                $postfields[$key . '[' . $subkey . ']'] = $subvalue;
            }
        } else {
            $postfields[$key] = $value;
        }
    }

    // Log completo dos dados enviados
    log_mensagem("Dados completos enviados para WHMCS: " . print_r($postfields, true));
    
    // Faz a requisição para a API do WHMCS - EXATAMENTE como na landing page
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $whmcs_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    $api_response = curl_exec($ch);
    
    // Log da resposta
    log_mensagem("Resposta do WHMCS: " . $api_response);
    
    if (curl_errno($ch)) {
        throw new Exception('Erro na conexão: ' . curl_error($ch));
    }

    curl_close($ch);
    
    $result = json_decode($api_response, true);
    if ($result === null) {
        throw new Exception('Erro ao decodificar resposta do WHMCS: ' . $api_response);
    }

    return $result;
}

// INICIAR O PROCESSO DE IMPORTAÇÃO
log_mensagem("=== INICIANDO IMPORTAÇÃO DIRETA ===");

// Solicitar o arquivo CSV
if (!isset($argv[1])) {
    die("Uso: php importar-direto.php seu_arquivo.csv\n");
}

$arquivoCSV = $argv[1];
if (!file_exists($arquivoCSV)) {
    die("Arquivo $arquivoCSV não encontrado!\n");
}

log_mensagem("Importando do arquivo: $arquivoCSV");
$clientes = lerCSV($arquivoCSV);
log_mensagem("Total de clientes no CSV: " . count($clientes));

// Processar um cliente por vez
$sucessos = 0;
$falhas = 0;

foreach ($clientes as $indice => $cliente) {
    log_mensagem("\n--- Processando cliente #" . ($indice + 1) . " ---");
    
    // Verificar campos obrigatórios
    if (empty($cliente['firstname']) || empty($cliente['lastname']) || empty($cliente['email']) || (empty($cliente['cnpj']) && empty($cliente['cpf_cnpj']))) {
        log_mensagem("ERRO: Campos obrigatórios faltando (nome, sobrenome, email, cnpj)");
        $falhas++;
        continue;
    }
    
    try {
        // Tenta criar o cliente
        log_mensagem("Tentando criar cliente: {$cliente['firstname']} {$cliente['lastname']} ({$cliente['email']})");
        $resultado = criarClienteWHMCS($cliente);
        
        if ($resultado['result'] === 'success') {
            log_mensagem("SUCESSO: Cliente criado com ID: " . $resultado['clientid']);
            $sucessos++;
        } else {
            log_mensagem("FALHA: " . ($resultado['message'] ?? 'Erro desconhecido'));
            $falhas++;
        }
    } catch (Exception $e) {
        log_mensagem("EXCEÇÃO: " . $e->getMessage());
        $falhas++;
    }
    
    // Pequena pausa para não sobrecarregar o servidor
    sleep(1);
}

log_mensagem("\n=== IMPORTAÇÃO CONCLUÍDA ===");
log_mensagem("Total de clientes processados: " . count($clientes));
log_mensagem("Sucessos: $sucessos");
log_mensagem("Falhas: $falhas");
?>
