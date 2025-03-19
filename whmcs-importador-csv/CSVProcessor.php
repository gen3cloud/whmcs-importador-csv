<?php
/**
 * Classe para processamento de arquivos CSV
 */
class CSVProcessor {
    private $filename;
    private $delimiter;
    private $enclosure;
    private $escape;
    private $headers;
    private $data;
    private $errors = [];
    private $position = 0; // Para controle de iteração
    
    /**
     * Construtor
     */
    public function __construct($filename, $delimiter = ',', $enclosure = '"', $escape = '\\') {
        $this->filename = $filename;
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escape = $escape;
        $this->headers = [];
        $this->data = [];
        $this->errors = [];
        $this->position = 0;
        
        // Processa o arquivo ao instanciar
        try {
            $this->process();
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
        }
    }
    
    /**
     * Processa o arquivo CSV e prepara os dados
     *
     * @return bool True se processado com sucesso, False caso contrário
     */
    public function process() {
        if (!file_exists($this->filename)) {
            throw new Exception("Arquivo não encontrado: {$this->filename}");
        }
        
        $handle = fopen($this->filename, 'r');
        if (!$handle) {
            throw new Exception("Não foi possível abrir o arquivo: {$this->filename}");
        }
        
        // Lê o cabeçalho
        $this->headers = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape);
        
        if (!$this->headers) {
            fclose($handle);
            throw new Exception("Falha ao ler o cabeçalho do CSV");
        }
        
        // Lê os dados
        $rowCount = 0;
        while (($row = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== false) {
            $rowCount++;
            
            // Verifica se o número de colunas coincide com o cabeçalho
            if (count($row) != count($this->headers)) {
                fclose($handle);
                throw new Exception("Erro na linha {$rowCount}: número de colunas não coincide com o cabeçalho");
            }
            
            // Combina o cabeçalho com os valores
            $this->data[] = array_combine($this->headers, $row);
        }
        
        fclose($handle);
        return true;
    }
    
    /**
     * Valida os dados do CSV
     *
     * @return bool True se válido, False caso contrário
     */
    public function validate() {
        $this->errors = []; // Limpa erros anteriores
        $requiredFields = ['firstname', 'lastname', 'email'];
        
        foreach ($this->data as $index => $row) {
            $rowNum = $index + 2; // +2 porque o índice começa em 0 e linha 1 é o cabeçalho
            
            // Verifica campos obrigatórios
            foreach ($requiredFields as $field) {
                if (!isset($row[$field]) || empty(trim($row[$field]))) {
                    $this->errors[] = "Linha {$rowNum}: Campo obrigatório '{$field}' está vazio";
                }
            }
            
            // Valida email
            if (isset($row['email']) && !empty($row['email']) && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Linha {$rowNum}: Email inválido '{$row['email']}'";
            }
            
            // Valida país (deve ser um código de 2 letras)
            if (isset($row['country']) && !empty($row['country']) && strlen($row['country']) != 2) {
                $this->errors[] = "Linha {$rowNum}: Código de país inválido '{$row['country']}' (deve ter 2 letras)";
            }
        }
        
        return empty($this->errors); // Retorna true se não houver erros
    }
    
    /**
     * Retorna os erros de validação
     *
     * @return array Lista de erros encontrados
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Retorna os dados processados
     *
     * @return array Dados do CSV
     */
    public function getData() {
        return $this->data;
    }
    
    /**
     * Retorna os cabeçalhos do CSV
     *
     * @return array Cabeçalhos
     */
    public function getHeaders() {
        return $this->headers;
    }
    
    /**
     * Retorna o número de registros
     *
     * @return int Número de registros
     */
    public function count() {
        return count($this->data);
    }
    
    /**
     * Para compatibilidade anterior
     */
    public function getRecordCount() {
        return $this->count();
    }
    
    /**
     * Obtém o próximo registro do CSV (iterador)
     * 
     * @return array|false O próximo registro ou false se não houver mais
     */
    public function getNext() {
        if ($this->position >= count($this->data)) {
            return false;
        }
        
        return $this->data[$this->position++];
    }
    
    /**
     * Reseta o iterador interno
     */
    public function reset() {
        $this->position = 0;
        return true;
    }
}
