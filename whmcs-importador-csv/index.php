<?php
// Inicia a sessão
session_start();

// Carrega as configurações
require_once 'config.php';

// Define a página atual
$page = $_GET['page'] ?? 'home';

// Mensagens de erro
$errorMessage = $_SESSION['error_message'] ?? '';
$validationErrors = $_SESSION['validation_errors'] ?? [];

// Resultados da importação
$importResults = $_SESSION['import_results'] ?? null;

// Limpa as mensagens da sessão após ler
unset($_SESSION['error_message']);
unset($_SESSION['validation_errors']);
unset($_SESSION['import_results']);

// Verifica o tamanho máximo de upload
$maxUploadSize = ini_get('upload_max_filesize');
$maxPostSize = ini_get('post_max_size');
$uploadLimitWarning = false;

// Compara os limites de tamanho
if (convertToBytes($maxUploadSize) < 50 * 1024 * 1024 || convertToBytes($maxPostSize) < 50 * 1024 * 1024) {
    $uploadLimitWarning = true;
}

// Função para converter para bytes
function convertToBytes($value) {
    $value = trim($value);
    $lastChar = strtolower($value[strlen($value)-1]);
    $num = (int)$value;
    
    switch($lastChar) {
        case 'g': $num *= 1024;
        case 'm': $num *= 1024;
        case 'k': $num *= 1024;
    }
    
    return $num;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importador CSV para WHMCS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            padding-top: 20px;
            padding-bottom: 40px;
            background-color: #f7f7f7;
        }
        .container {
            max-width: 960px;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .header {
            padding-bottom: 20px;
            margin-bottom: 30px;
            border-bottom: 1px solid #e5e5e5;
        }
        .footer {
            padding-top: 20px;
            margin-top: 30px;
            border-top: 1px solid #e5e5e5;
            color: #777;
            text-align: center;
        }
        .alert {
            margin-top: 20px;
        }
        .result-box {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
        .navbar {
            margin-bottom: 20px;
        }
        .table-fixed {
            table-layout: fixed;
        }
        .table-fixed td {
            word-wrap: break-word;
        }
        .batch-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border-left: 4px solid #6c757d;
        }
        .badge-large {
            font-size: 85%;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <a class="navbar-brand" href="index.php">Importador CSV - WHMCS</a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item <?php echo $page == 'home' ? 'active' : ''; ?>">
                            <a class="nav-link" href="index.php">Início</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="modelo_clientes.csv" download>Download Modelo CSV</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="check_environment.php">Verificar Ambiente</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="test_api.php">Testar API</a>
                        </li>
                    </ul>
                </div>
            </nav>
        </div>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger" role="alert">
                <strong>Erro:</strong> <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($validationErrors)): ?>
            <div class="alert alert-warning" role="alert">
                <strong>Erros de validação no CSV:</strong>
                <ul>
                    <?php foreach ($validationErrors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($uploadLimitWarning): ?>
            <div class="alert alert-warning" role="alert">
                <strong>Atenção:</strong> Os limites de upload do PHP estão configurados como:
                <ul>
                    <li>upload_max_filesize: <?php echo $maxUploadSize; ?></li>
                    <li>post_max_size: <?php echo $maxPostSize; ?></li>
                </ul>
                <p>Estes valores podem não ser suficientes para arquivos grandes. Para arquivos acima de 50MB, use o processamento em lotes via linha de comando.</p>
            </div>
        <?php endif; ?>
        
        <?php if ($page == 'home'): ?>
            <div class="jumbotron">
                <h1 class="display-4">Importador CSV para WHMCS</h1>
                <p class="lead">Este sistema permite importar clientes em massa para o WHMCS através de um arquivo CSV.</p>
                <hr class="my-4">
                <p>Selecione um arquivo CSV contendo os dados dos clientes para iniciar a importação.</p>
                <a class="btn btn-info" href="modelo_clientes.csv" download>Download do modelo CSV</a>
            </div>
            
            <div class="card">
                <div class="card-header">
                    Upload do Arquivo CSV
                </div>
                <div class="card-body">
                    <form action="import.php" method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="csv_file">Arquivo CSV:</label>
                            <input type="file" class="form-control-file" id="csv_file" name="csv_file" accept=".csv" required>
                            <small class="form-text text-muted">Tamanho máximo: <?php echo $maxUploadSize; ?>. Formatos permitidos: CSV.</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Importar Clientes</button>
                    </form>
                </div>
            </div>
            
            <div class="batch-info">
                <h4><span class="badge badge-secondary badge-large">Novo!</span> Importação em Lotes para Grandes Volumes</h4>
                <p>Para arquivos muito grandes (com milhares de registros), recomendamos o processamento em lotes via linha de comando:</p>
                <div class="bg-dark text-light p-2 rounded">
                    <code>php batch_import.php caminho/para/arquivo.csv</code>
                </div>
                <p class="mt-2 mb-0">Isso processará 10000 registros por vez, evitando timeouts e problemas de memória. <a href="IMPLANTACAO-REMOTA.md">Saiba mais</a></p>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    Instruções
                </div>
                <div class="card-body">
                    <h5>Como usar o sistema:</h5>
                    <ol>
                        <li>Faça o download do arquivo modelo CSV</li>
                        <li>Preencha com os dados dos clientes que deseja importar</li>
                        <li>Faça upload do arquivo preenchido</li>
                        <li>Aguarde o processamento e verifique os resultados</li>
                    </ol>
                    
                    <h5>Estrutura do arquivo CSV:</h5>
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Campo</th>
                                <th>Descrição</th>
                                <th>Obrigatório</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>firstname</td>
                                <td>Nome do cliente</td>
                                <td>Sim</td>
                            </tr>
                            <tr>
                                <td>lastname</td>
                                <td>Sobrenome do cliente</td>
                                <td>Sim</td>
                            </tr>
                            <tr>
                                <td>email</td>
                                <td>E-mail do cliente</td>
                                <td>Sim</td>
                            </tr>
                            <tr>
                                <td>company</td>
                                <td>Nome da empresa</td>
                                <td>Não</td>
                            </tr>
                            <tr>
                                <td>cnpj</td>
                                <td>CNPJ da empresa (com ou sem formatação)</td>
                                <td>Sim</td>
                            </tr>
                            <tr>
                                <td>address1</td>
                                <td>Endereço linha 1</td>
                                <td>Não</td>
                            </tr>
                            <tr>
                                <td>address2</td>
                                <td>Endereço linha 2</td>
                                <td>Não</td>
                            </tr>
                            <tr>
                                <td>city</td>
                                <td>Cidade</td>
                                <td>Não</td>
                            </tr>
                            <tr>
                                <td>state</td>
                                <td>Estado</td>
                                <td>Não</td>
                            </tr>
                            <tr>
                                <td>postcode</td>
                                <td>CEP</td>
                                <td>Não</td>
                            </tr>
                            <tr>
                                <td>country</td>
                                <td>País (código de 2 letras, ex: BR)</td>
                                <td>Não</td>
                            </tr>
                            <tr>
                                <td>phonenumber</td>
                                <td>Telefone</td>
                                <td>Não</td>
                            </tr>
                            <tr>
                                <td>password</td>
                                <td>Senha (opcional)</td>
                                <td>Não</td>
                            </tr>
                            <tr>
                                <td>notes</td>
                                <td>Notas adicionais</td>
                                <td>Não</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($page == 'results' && $importResults): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    Resultados da Importação
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Total</h5>
                                    <p class="card-text display-4"><?php echo $importResults['total']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Sucesso</h5>
                                    <p class="card-text display-4"><?php echo $importResults['success']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Falha</h5>
                                    <p class="card-text display-4"><?php echo $importResults['failed']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Ignorados</h5>
                                    <p class="card-text display-4"><?php echo $importResults['skipped']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mt-4">Detalhes da Importação:</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-fixed">
                            <thead>
                                <tr>
                                    <th>Linha</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Mensagem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($importResults['details'] as $detail): ?>
                                    <tr class="<?php echo $detail['status'] == 'success' ? 'table-success' : ($detail['status'] == 'skipped' ? 'table-warning' : 'table-danger'); ?>">
                                        <td><?php echo $detail['line']; ?></td>
                                        <td><?php echo htmlspecialchars($detail['email']); ?></td>
                                        <td>
                                            <?php if ($detail['status'] == 'success'): ?>
                                                <span class="badge badge-success">Sucesso</span>
                                            <?php elseif ($detail['status'] == 'skipped'): ?>
                                                <span class="badge badge-warning">Ignorado</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Falha</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($detail['message']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <a href="index.php" class="btn btn-primary mt-3">Voltar ao Início</a>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Página não encontrada. <a href="index.php">Voltar ao início</a>.
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Clave Internet - Importador CSV para WHMCS</p>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
