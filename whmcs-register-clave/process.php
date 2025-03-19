<?php
// Evita acesso direto
if (!defined('ABSPATH')) exit;

header('Content-Type: application/json');

// Configurações da API WHMCS
$whmcs_url = 'https://cliente.claveinternet.com.br/includes/api.php';
$identifier = '3UOVmg8BPQ1KdUfiEjp0b2OBB3Q3Pp6K';
$secret = 'QmRc0OKczFa0OuLfyRJXqo0gw0HqRsmD';

// Função para gerar endereço aleatório
function generateRandomAddress() {
    $streets = ['Rua A', 'Rua B', 'Avenida C', 'Rua D'];
    $cities = ['São Paulo', 'Rio de Janeiro', 'Belo Horizonte', 'Curitiba'];
    $states = ['SP', 'RJ', 'MG', 'PR'];
    
    return [
        'address' => $streets[array_rand($streets)] . ', ' . rand(1, 999),
        'city' => $cities[array_rand($cities)],
        'state' => $states[array_rand($states)],
        'postcode' => sprintf('%08d', rand(0, 99999999))
    ];
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    // Remove máscara do CNPJ
    $cpf_cnpj = preg_replace('/[^0-9]/', '', $_POST['cpf_cnpj'] ?? '');
    if (empty($cpf_cnpj)) {
        $response['message'] = 'O campo CNPJ é obrigatório.';
    } else {
        // Gera endereço aleatório
        $randomAddress = generateRandomAddress();
        
        // Coleta os dados do formulário
        $postfields = array(
            'identifier' => $identifier,
            'secret' => $secret,
            'action' => 'AddClient',
            'firstname' => $_POST['firstname'] ?? '',
            'lastname' => $_POST['lastname'] ?? '',
            'email' => $_POST['email'] ?? '',
            'address1' => $randomAddress['address'],
            'city' => $randomAddress['city'],
            'state' => $randomAddress['state'],
            'postcode' => $randomAddress['postcode'],
            'country' => 'BR',
            'phonenumber' => $_POST['phone'] ?? '',
            'password2' => bin2hex(random_bytes(8)),
            'clientip' => $_SERVER['REMOTE_ADDR'],
            'responsetype' => 'json',
            'customfields' => $cpf_cnpj
        );

        // Log dos dados enviados
        error_log('Dados enviados para WHMCS: ' . print_r($postfields, true));

        // Faz a requisição para a API do WHMCS
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $whmcs_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        $api_response = curl_exec($ch);
        
        // Log da resposta
        error_log('Resposta do WHMCS: ' . $api_response);
        
        if (curl_errno($ch)) {
            $response['message'] = 'Erro na conexão: ' . curl_error($ch);
            error_log('Erro CURL: ' . curl_error($ch));
        } else {
            $result = json_decode($api_response, true);
            if ($result['result'] === 'success') {
                $response['success'] = true;
                $response['message'] = 'Cliente cadastrado com sucesso!';
            } else {
                $response['message'] = 'Erro: ' . ($result['message'] ?? 'Erro desconhecido');
                error_log('Erro WHMCS: ' . ($result['message'] ?? 'Erro desconhecido'));
            }
        }
        curl_close($ch);
    }
    
    echo json_encode($response);
    exit;
}
