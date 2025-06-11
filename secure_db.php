<?php
class SecureDatabase {
    private $pdo;
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        $this->connect();
    }
    
    private function connect() {
        $dsn = "mysql:host={$this->config['host']};dbname={$this->config['database']};charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'"
        ];
        
        try {
            $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }
    
    public function query($sql) {
        return $this->pdo->query($sql);
    }
    
    public function exec($sql) {
        return $this->pdo->exec($sql);
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    // Secure query methods
    public function secureSelect($table, $conditions = [], $columns = '*', $options = []) {
        $table = $this->sanitizeTableName($table);
        
        if (is_array($columns)) {
            $columns = implode(', ', array_map([$this, 'sanitizeColumnName'], $columns));
        } elseif ($columns !== '*') {
            $columns = $this->sanitizeColumnName($columns);
        }
        
        $sql = "SELECT {$columns} FROM {$table}";
        $params = [];
        
        if (!empty($conditions)) {
            $where_clauses = [];
            foreach ($conditions as $column => $value) {
                $column = $this->sanitizeColumnName($column);
                $where_clauses[] = "{$column} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }
        
        // Add options
        if (!empty($options['order_by'])) {
            $order_column = $this->sanitizeColumnName($options['order_by']);
            $order_direction = (!empty($options['order_direction']) && strtoupper($options['order_direction']) === 'DESC') ? 'DESC' : 'ASC';
            $sql .= " ORDER BY {$order_column} {$order_direction}";
        }
        
        if (!empty($options['limit'])) {
            $sql .= " LIMIT " . (int)$options['limit'];
            if (!empty($options['offset'])) {
                $sql .= " OFFSET " . (int)$options['offset'];
            }
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt;
    }
    
    public function secureInsert($table, $data) {
        $table = $this->sanitizeTableName($table);
        
        $columns = array_map([$this, 'sanitizeColumnName'], array_keys($data));
        $placeholders = array_fill(0, count($data), '?');
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array_values($data));
    }
    
    public function secureUpdate($table, $data, $conditions) {
        $table = $this->sanitizeTableName($table);
        
        $set_clauses = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $column = $this->sanitizeColumnName($column);
            $set_clauses[] = "{$column} = ?";
            $params[] = $value;
        }
        
        $where_clauses = [];
        foreach ($conditions as $column => $value) {
            $column = $this->sanitizeColumnName($column);
            $where_clauses[] = "{$column} = ?";
            $params[] = $value;
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $set_clauses) . " WHERE " . implode(' AND ', $where_clauses);
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function secureDelete($table, $conditions) {
        $table = $this->sanitizeTableName($table);
        
        $where_clauses = [];
        $params = [];
        
        foreach ($conditions as $column => $value) {
            $column = $this->sanitizeColumnName($column);
            $where_clauses[] = "{$column} = ?";
            $params[] = $value;
        }
        
        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $where_clauses);
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    private function sanitizeTableName($table) {
        // Allow only alphanumeric characters and underscores
        return preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    }
    
    private function sanitizeColumnName($column) {
        // Allow only alphanumeric characters, underscores, and dots (for table.column)
        return preg_replace('/[^a-zA-Z0-9_.]/', '', $column);
    }
    
    // Transaction wrapper
    public function transaction($callback) {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    // Connection health check
    public function healthCheck() {
        try {
            $stmt = $this->pdo->query('SELECT 1');
            return $stmt !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Get database statistics
    public function getStats() {
        try {
            $stats = [];
            
            // Get table sizes
            $stmt = $this->pdo->query("
                SELECT table_name, table_rows 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            
            $stats['tables'] = $stmt->fetchAll();
            
            // Get connection info
            $stats['connection'] = [
                'host' => $this->config['host'],
                'database' => $this->config['database']
            ];
            
            return $stats;
        } catch (PDOException $e) {
            return ['error' => 'Unable to retrieve stats'];
        }
    }
}
?>
