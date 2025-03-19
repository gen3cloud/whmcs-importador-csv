<?php
/**
 * Script de diagnóstico para testar diferentes métodos de envio do campo CNPJ para o WHMCS
 * 
 * Este script tenta várias abordagens diferentes de enviar o mesmo CNPJ para a API do WHMCS
 * e registra todas as respostas para que possamos identificar qual método funciona
 */

require_once 'config.php';

// CNPJ para testar (com formatação)
$cnpj_formatado = '61.553.148/0001-07';
$cnpj_limpo = preg_replace('/[^0-9]/', '', $cnpj_formatado);

echo "=== DIAGNÓSTICO DE CAMPO CNPJ NO WHMCS ===\n\n";
echo "CNPJ formatado: $cnpj_formatado\n";
echo "CNPJ limpo: $cnpj_limpo\n\n";

// Dados básicos para criar um cliente de teste
$clienteBase = [
    'firstname' => 'Teste',
    'lastname' => 'Diagnóstico',
    'email' => 'teste' . time() . '@diagnóstico.com',
    'address1' => 'Rua de Teste',
    'city' => 'São Paulo',
    'state' => 'SP',
    'postcode' => '01234-567',
    'country' => 'BR',
    'phonenumber' => '11912345678',
    'password' => 'Senha@123',
];

// Função para chamar a API do WHMCS
function chamarAPI($params) {
    global $config;
    
    // Parâmetros base da API
    $postfields = [
        'identifier' => $config['identifier'],
        'secret' => $config['secret'],
        'action' => 'AddClient',
        'responsetype' => 'json',
    ];
    
    // Adiciona os parâmetros específicos
    $postfields = array_merge($postfields, $params);
    
    // Faz a requisição para a API do WHMCS
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['url']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        return "Erro cURL: " . curl_error($ch);
    }
    
    curl_close($ch);
    
    return $response;
}

// Função para testar uma abordagem específica
function testarAbordagem($nome, $params) {
    global $clienteBase;
    
    // Gera um email único para cada teste
    $params['email'] = 'teste.' . md5(time() . rand(1000, 9999)) . '@diagnóstico.com';
    
    // Mescla com os dados base do cliente
    $params = array_merge($clienteBase, $params);
    
    echo "=== TENTATIVA: $nome ===\n";
    echo "Parâmetros enviados:\n";
    print_r($params);
    
    $response = chamarAPI($params);
    
    echo "\nResposta do WHMCS:\n";
    echo $response . "\n\n";
    
    // Verifica se foi bem sucedido
    $result = json_decode($response, true);
    if ($result && isset($result['result']) && $result['result'] === 'success') {
        echo "✅ SUCESSO! Esta abordagem funcionou!\n\n";
        return true;
    } else {
        echo "❌ FALHA: Esta abordagem não funcionou.\n\n";
        return false;
    }
}

// Array com todas as abordagens a serem testadas
$abordagens = [
    // Abordagem 1: Enviar como customfield1 formatado
    '1-customfield1-formatado' => [
        'customfield1' => $cnpj_formatado
    ],
    
    // Abordagem 2: Enviar como customfield1 limpo
    '2-customfield1-limpo' => [
        'customfield1' => $cnpj_limpo
    ],
    
    // Abordagem 3: Enviar como customfield[1] formatado
    '3-customfield-array-formatado' => [
        'customfield[1]' => $cnpj_formatado
    ],
    
    // Abordagem 4: Enviar como customfield[1] limpo
    '4-customfield-array-limpo' => [
        'customfield[1]' => $cnpj_limpo
    ],
    
    // Abordagem 5: Usando base64_encode e serialize
    '5-customfields-serializado-formatado' => [
        'customfields' => base64_encode(serialize(array("1" => $cnpj_formatado)))
    ],
    
    // Abordagem 6: Usando base64_encode e serialize com valor limpo
    '6-customfields-serializado-limpo' => [
        'customfields' => base64_encode(serialize(array("1" => $cnpj_limpo)))
    ],
    
    // Abordagem 7: Enviar diretamente como cnpj
    '7-campo-direto-cnpj-formatado' => [
        'cnpj' => $cnpj_formatado
    ],
    
    // Abordagem 8: Enviar diretamente como cnpj limpo
    '8-campo-direto-cnpj-limpo' => [
        'cnpj' => $cnpj_limpo
    ],
    
    // Abordagem 9: Enviar como cpf_cnpj
    '9-campo-cpf_cnpj-formatado' => [
        'cpf_cnpj' => $cnpj_formatado
    ],
    
    // Abordagem 10: Enviar como cpf_cnpj limpo
    '10-campo-cpf_cnpj-limpo' => [
        'cpf_cnpj' => $cnpj_limpo
    ]
];

// Testa cada abordagem
foreach ($abordagens as $nome => $params) {
    testarAbordagem($nome, $params);
}

echo "=== DIAGNÓSTICO COMPLETO ===\n";
echo "Veja acima qual(is) abordagem(ns) funcionou(aram) corretamente.\n";
?>
