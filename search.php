<?php
require_once 'config.php';
require_once 'validate.php';
require_once 'pagination.php';

class SearchManager {
    private $db;
    private $validator;
    
    public function __construct($database) {
        $this->db = $database;
        $this->validator = new Validator();
    }
    
    public function searchPosts($query, $filters = [], $page = 1, $per_page = 10) {
        // Validate and sanitize search query
        $query = $this->validator->sanitizeString($query);
        
        if (strlen($query) < 2) {
            return ['results' => [], 'total' => 0, 'pagination' => null];
        }
        
        // Build search query
        $sql = "SELECT p.*, u.username, u.email 
                FROM posts p 
                LEFT JOIN users u ON p.user_id = u.id 
                WHERE 1=1";
        
        $params = [];
        
        // Add search conditions
        if (!empty($query)) {
            $sql .= " AND (p.title LIKE ? OR p.content LIKE ? OR u.username LIKE ?)";
            $search_term = '%' . $query . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // Add filters
        if (!empty($filters['category'])) {
            $sql .= " AND p.category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND p.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND p.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND p.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND p.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        // Get total count for pagination
        $count_sql = str_replace("SELECT p.*, u.username, u.email", "SELECT COUNT(*)", $sql);
        
        try {
            $count_stmt = $this->db->prepare($count_sql);
            $count_stmt->execute($params);
            $total_records = $count_stmt->fetchColumn();
            
            // Add ordering and pagination
            $sql .= " ORDER BY p.created_at DESC";
            
            $pagination = new Pagination($total_records, $per_page, $page);
            $sql .= " LIMIT " . $pagination->getLimit() . " OFFSET " . $pagination->getOffset();
            
            // Execute main query
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'results' => $results,
                'total' => $total_records,
                'pagination' => $pagination,
                'query' => $query,
                'filters' => $filters
            ];
            
        } catch (PDOException $e) {
            error_log("Search error: " . $e->getMessage());
            return ['results' => [], 'total' => 0, 'pagination' => null];
        }
    }
    
    public function searchUsers($query, $page = 1, $per_page = 10) {
        $query = $this->validator->sanitizeString($query);
        
        if (strlen($query) < 2) {
            return ['results' => [], 'total' => 0, 'pagination' => null];
        }
        
        $sql = "SELECT id, username, email, role, created_at, last_login 
                FROM users 
                WHERE username LIKE ? OR email LIKE ?";
        
        $search_term = '%' . $query . '%';
        $params = [$search_term, $search_term];
        
        try {
            // Get total count
            $count_sql = str_replace("SELECT id, username, email, role, created_at, last_login", "SELECT COUNT(*)", $sql);
            $count_stmt = $this->db->prepare($count_sql);
            $count_stmt->execute($params);
            $total_records = $count_stmt->fetchColumn();
            
            // Add pagination
            $pagination = new Pagination($total_records, $per_page, $page);
            $sql .= " ORDER BY username ASC LIMIT " . $pagination->getLimit() . " OFFSET " . $pagination->getOffset();
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'results' => $results,
                'total' => $total_records,
                'pagination' => $pagination,
                'query' => $query
            ];
            
        } catch (PDOException $e) {
            error_log("User search error: " . $e->getMessage());
            return ['results' => [], 'total' => 0, 'pagination' => null];
        }
    }
    
    public function getSearchSuggestions($query, $limit = 5) {
        $query = $this->validator->sanitizeString($query);
        
        if (strlen($query) < 2) {
            return [];
        }
        
        try {
            $sql = "SELECT DISTINCT title 
                    FROM posts 
                    WHERE title LIKE ? 
                    ORDER BY created_at DESC 
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['%' . $query . '%', $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } catch (PDOException $e) {
            error_log("Search suggestions error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getPopularSearches($limit = 10) {
        try {
            $sql = "SELECT search_term, COUNT(*) as count 
                    FROM search_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY search_term 
                    ORDER BY count DESC 
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Popular searches error: " . $e->getMessage());
            return [];
        }
    }
    
    public function logSearch($query, $user_id = null, $results_count = 0) {
        try {
            $sql = "INSERT INTO search_logs (search_term, user_id, results_count, created_at) 
                    VALUES (?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$query, $user_id, $results_count]);
            
        } catch (PDOException $e) {
            error_log("Search logging error: " . $e->getMessage());
        }
    }
    
    public function buildSearchForm($current_query = '', $current_filters = []) {
        $categories = $this->getCategories();
        
        $html = '<form method="GET" class="search-form">';
        $html .= '<div class="row">';
        
        // Search input
        $html .= '<div class="col-md-6">';
        $html .= '<input type="text" name="q" class="form-control" placeholder="Search posts..." value="' . htmlspecialchars($current_query) . '">';
        $html .= '</div>';
        
        // Category filter
        $html .= '<div class="col-md-3">';
        $html .= '<select name="category" class="form-control">';
        $html .= '<option value="">All Categories</option>';
        foreach ($categories as $category) {
            $selected = (isset($current_filters['category']) && $current_filters['category'] == $category) ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($category) . '" ' . $selected . '>' . htmlspecialchars($category) . '</option>';
        }
        $html .= '</select>';
        $html .= '</div>';
        
        // Submit button
        $html .= '<div class="col-md-3">';
        $html .= '<button type="submit" class="btn btn-primary">Search</button>';
        $html .= '<a href="?" class="btn btn-secondary ml-2">Clear</a>';
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</form>';
        
        return $html;
    }
    
    private function getCategories() {
        try {
            $stmt = $this->db->query("SELECT DISTINCT category FROM posts WHERE category IS NOT NULL ORDER BY category");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }
}

// AJAX endpoint for search suggestions
if (isset($_GET['ajax']) && $_GET['ajax'] === 'suggestions') {
    header('Content-Type: application/json');
    
    $query = $_GET['q'] ?? '';
    $searchManager = new SearchManager($db);
    $suggestions = $searchManager->getSearchSuggestions($query);
    
    echo json_encode($suggestions);
    exit;
}
?>
