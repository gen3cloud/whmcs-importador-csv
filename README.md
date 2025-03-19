# Importador CSV para WHMCS

Este sistema permite importar clientes em massa para o WHMCS através de um arquivo CSV.

## Funcionalidades

- Upload de arquivos CSV com dados de clientes
- Validação dos dados antes da importação
- Importação de clientes via API do WHMCS
- Relatório detalhado dos resultados da importação

## Requisitos

- PHP 7.4 ou superior
- Extensão cURL do PHP habilitada
- Credenciais de API válidas do WHMCS
- Servidor web (Apache, Nginx, etc.)

## Como usar

1. Prepare seu arquivo CSV seguindo o modelo fornecido (`modelo_clientes.csv`)
2. Acesse a interface web em seu navegador
3. Faça upload do arquivo CSV
4. Revise os dados e confirme a importação
5. Verifique o relatório de resultados

## Estrutura do arquivo CSV

O arquivo CSV deve conter os seguintes campos (cabeçalhos):

- firstname - Nome do cliente
- lastname - Sobrenome do cliente
- email - Email do cliente (obrigatório)
- company - Nome da empresa
- address1 - Endereço linha 1
- address2 - Endereço linha 2
- city - Cidade
- state - Estado
- postcode - CEP
- country - País (código de 2 letras, ex: BR)
- phonenumber - Telefone
- password - Senha (opcional)
- notes - Notas adicionais

## Configuração

As credenciais da API estão configuradas no arquivo `config.php`.
Também precisa ser alteradas nos arquivos: `batch_import.php` e `teste_api.php`

Certifique-se de que estão corretas antes de usar o sistema.

## Suporte

Para suporte ou dúvidas, entre em contato com meajuda@gen3cloud.com.br
