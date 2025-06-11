<?php
class Validator {
    private $errors = [];
    
    public function __construct() {
        $this->errors = [];
    }
    
    public function validate($data, $rules) {
        $this->errors = [];
        
        foreach ($rules as $field => $rule_string) {
            $rules_array = explode('|', $rule_string);
            $value = isset($data[$field]) ? $data[$field] : null;
            
            foreach ($rules_array as $rule) {
                $rule_parts = explode(':', $rule);
                $rule_name = $rule_parts[0];
                $rule_params = isset($rule_parts[1]) ? explode(',', $rule_parts[1]) : [];
                
                if (!$this->applyRule($field, $value, $rule_name, $rule_params)) {
                    break; // Stop validating this field if a rule fails
                }
            }
        }
        
        return empty($this->errors);
    }
    
    private function applyRule($field, $value, $rule, $params = []) {
        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, 'The ' . $field . ' field is required.');
                    return false;
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'The ' . $field . ' must be a valid email address.');
                    return false;
                }
                break;
                
            case 'min':
                $min_length = isset($params[0]) ? (int)$params[0] : 0;
                if (!empty($value) && strlen($value) < $min_length) {
                    $this->addError($field, 'The ' . $field . ' must be at least ' . $min_length . ' characters.');
                    return false;
                }
                break;
                
            case 'max':
                $max_length = isset($params[0]) ? (int)$params[0] : 255;
                if (!empty($value) && strlen($value) > $max_length) {
                    $this->addError($field, 'The ' . $field . ' may not be greater than ' . $max_length . ' characters.');
                    return false;
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, 'The ' . $field . ' must be a number.');
                    return false;
                }
                break;
                
            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, 'The ' . $field . ' must be an integer.');
                    return false;
                }
                break;
                
            case 'alpha':
                if (!empty($value) && !ctype_alpha($value)) {
                    $this->addError($field, 'The ' . $field . ' may only contain letters.');
                    return false;
                }
                break;
                
            case 'alpha_num':
                if (!empty($value) && !ctype_alnum($value)) {
                    $this->addError($field, 'The ' . $field . ' may only contain letters and numbers.');
                    return false;
                }
                break;
                
            case 'alpha_dash':
                if (!empty($value) && !preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
                    $this->addError($field, 'The ' . $field . ' may only contain letters, numbers, dashes, and underscores.');
                    return false;
                }
                break;
                
            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, 'The ' . $field . ' must be a valid URL.');
                    return false;
                }
                break;
                
            case 'confirmed':
                $confirmation_field = $field . '_confirmation';
                if (!empty($value) && (!isset($_POST[$confirmation_field]) || $value !== $_POST[$confirmation_field])) {
                    $this->addError($field, 'The ' . $field . ' confirmation does not match.');
                    return false;
                }
                break;
                
            case 'unique':
                if (!empty($value) && isset($params[0])) {
                    $table = $params[0];
                    $column = isset($params[1]) ? $params[1] : $field;
                    if ($this->checkUnique($table, $column, $value)) {
                        $this->addError($field, 'The ' . $field . ' has already been taken.');
                        return false;
                    }
                }
                break;
                
            case 'exists':
                if (!empty($value) && isset($params[0])) {
                    $table = $params[0];
                    $column = isset($params[1]) ? $params[1] : $field;
                    if (!$this->checkExists($table, $column, $value)) {
                        $this->addError($field, 'The selected ' . $field . ' is invalid.');
                        return false;
                    }
                }
                break;
                
            case 'in':
                if (!empty($value) && !in_array($value, $params)) {
                    $this->addError($field, 'The selected ' . $field . ' is invalid.');
                    return false;
                }
                break;
                
            case 'regex':
                if (!empty($value) && isset($params[0]) && !preg_match($params[0], $value)) {
                    $this->addError($field, 'The ' . $field . ' format is invalid.');
                    return false;
                }
                break;
                
            case 'password':
                if (!empty($value)) {
                    $errors = $this->validatePassword($value);
                    if (!empty($errors)) {
                        foreach ($errors as $error) {
                            $this->addError($field, $error);
                        }
                        return false;
                    }
                }
                break;
        }
        
        return true;
    }
    
    private function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }
        
        return $errors;
    }
    
    private function checkUnique($table, $column, $value) {
        global $db;
        
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
            $stmt->execute([$value]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    private function checkExists($table, $column, $value) {
        global $db;
        
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
            $stmt->execute([$value]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getFirstError($field = null) {
        if ($field) {
            return isset($this->errors[$field]) ? $this->errors[$field][0] : null;
        }
        
        foreach ($this->errors as $field_errors) {
            return $field_errors[0];
        }
        
        return null;
    }
    
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    // Sanitization methods
    public function sanitizeString($string) {
        return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
    }
    
    public function sanitizeEmail($email) {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
    
    public function sanitizeInt($int) {
        return filter_var($int, FILTER_SANITIZE_NUMBER_INT);
    }
    
    public function sanitizeFloat($float) {
        return filter_var($float, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
    
    public function sanitizeUrl($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
    
    // CSRF Token methods
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public function getCSRFInput() {
        return '<input type="hidden" name="csrf_token" value="' . $this->generateCSRFToken() . '">';
    }
    
    // File upload validation
    public function validateFile($file, $rules = []) {
        $errors = [];
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'No file was uploaded.';
            return $errors;
        }
        
        // Check file size
        if (isset($rules['max_size'])) {
            $max_size = $rules['max_size'] * 1024 * 1024; // Convert MB to bytes
            if ($file['size'] > $max_size) {
                $errors[] = 'File size must not exceed ' . $rules['max_size'] . ' MB.';
            }
        }
        
        // Check file type
        if (isset($rules['allowed_types'])) {
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_extension, $rules['allowed_types'])) {
                $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $rules['allowed_types']);
            }
        }
        
        // Check MIME type
        if (isset($rules['allowed_mimes'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $rules['allowed_mimes'])) {
                $errors[] = 'Invalid file format.';
            }
        }
        
        return $errors;
    }
}

// Helper function for quick validation
function validate($data, $rules) {
    $validator = new Validator();
    $is_valid = $validator->validate($data, $rules);
    
    return [
        'valid' => $is_valid,
        'errors' => $validator->getErrors()
    ];
}
?>
