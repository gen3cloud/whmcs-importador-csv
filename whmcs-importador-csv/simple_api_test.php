<?php
// Arquivo de teste simples para a API do WHMCS
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurações da API WHMCS
$whmcs_url = 'https://cliente.claveinternet.com.br/includes/api.php';
$identifier = '3UOVmg8BPQ1KdUfiEjp0b2OBB3Q3Pp6K';
$secret = 'QmRc0OKczFa0OuLfyRJXqo0gw0HqRsmD';

echo "=================================================\n";
echo "Teste Simples de API WHMCS\n";
echo "=================================================\n\n";

echo "URL: " . $whmcs_url . "\n";
echo "Identificador: " . $identifier . "\n";
echo "Secret: " . substr($secret, 0, 3) . '***' . substr($secret, -3) . "\n\n";

// Parâmetros da API para uma ação simples
$postfields = array(
    'identifier' => $identifier,
    'secret' => $secret,
    'action' => 'GetSystemURL', // Corrigido para usar a forma correta da ação
    'responsetype' => 'json',
);

// Inicializa cURL com configurações básicas
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $whmcs_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

// Desabilita verificação SSL (apenas para teste)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// Ativa modo verboso para debug
curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

// Define os campos POST
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));

// Adiciona headers úteis
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'User-Agent: WHMCS API Tester',
    'Content-Type: application/x-www-form-urlencoded'
));

echo "Enviando requisição...\n";
$response = curl_exec($ch);

echo "Código de resposta HTTP: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n\n";

// Verifica erros cURL
if (curl_errno($ch)) {
    echo "ERRO CURL: " . curl_error($ch) . "\n";
} else {
    echo "Resposta recebida!\n";
}

// Obtém informações detalhadas de debug
rewind($verbose);
$verboseLog = stream_get_contents($verbose);
echo "\nDETALHES DE CONEXÃO:\n" . $verboseLog . "\n";

// Fecha a conexão cURL
curl_close($ch);

// Mostra a resposta
echo "\n----- RESPOSTA COMPLETA -----\n";
echo $response . "\n";
echo "------------------------------\n\n";

// Tenta decodificar a resposta JSON
$jsonResponse = json_decode($response, true);
if ($jsonResponse) {
    echo "Resposta JSON decodificada:\n";
    print_r($jsonResponse);
} else {
    echo "Não foi possível decodificar a resposta como JSON.\n";
    echo "Possível problema com a resposta ou formato incorreto.\n";
}

echo "\n\n=================================================\n";
echo "SUGESTÕES:\n";
echo "1. Verifique se a API está ativada no WHMCS\n";
echo "2. Confirme que seu IP tem permissão para acessar a API\n";
echo "3. Verifique se as credenciais são para o sistema correto\n";
echo "4. Tente acessar manualmente a URL da API para ver se ela existe\n";
echo "=================================================\n";
