<?php
/**
 * Teste direto e simples da API do WHMCS
 */

// Carrega configurações
require_once 'config.php';

// Defina diretamente as variáveis (sem usar variáveis do formulário)
$api_url = $config['whmcs_url'];
$api_identifier = $config['identifier'];
$api_secret = $config['secret'];

// Dados para a requisição com a ação correta (respeitando maiúsculas/minúsculas)
$postdata = [
    'identifier' => $api_identifier,
    'secret' => $api_secret,
    'action' => 'GetClientsProducts',  // Exemplo de ação válida, sensível a maiúsculas/minúsculas
    'responsetype' => 'json'
];

// Faz a chamada direta à API 
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

echo "<h2>Testando API do WHMCS</h2>";
echo "<p>URL da API: {$api_url}</p>";
echo "<p>Ação: {$postdata['action']}</p>";

echo "<h3>Dados que estamos enviando:</h3>";
echo "<pre>";
print_r($postdata);
echo "</pre>";

echo "<h3>Resposta da API:</h3>";

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "<p>Erro cURL: " . curl_error($ch) . "</p>";
} else {
    echo "<pre>";
    $decoded = json_decode($response, true);
    if ($decoded) {
        print_r($decoded);
    } else {
        echo htmlspecialchars($response);
    }
    echo "</pre>";
}

curl_close($ch);
?>
