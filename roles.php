<?php
require_once 'config.php';

class RoleManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // Define role constants
    const ROLE_ADMIN = 'admin';
    const ROLE_MODERATOR = 'moderator';
    const ROLE_USER = 'user';
    const ROLE_GUEST = 'guest';
    
    // Role hierarchy (higher number = more permissions)
    private $role_hierarchy = [
        'guest' => 1,
        'user' => 2,
        'moderator' => 3,
        'admin' => 4
    ];
    
    // Permission definitions
    private $permissions = [
        'admin' => ['*'], // All permissions
        'moderator' => [
            'read_all_posts',
            'edit_any_post',
            'delete_any_post',
            'manage_users',
            'view_reports'
        ],
        'user' => [
            'create_post',
            'edit_own_post',
            'delete_own_post',
            'comment',
            'view_posts'
        ],
        'guest' => [
            'view_posts'
        ]
    ];
    
    public function getUserRole($user_id) {
        try {
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['role'] : self::ROLE_GUEST;
        } catch (PDOException $e) {
            error_log("Error getting user role: " . $e->getMessage());
            return self::ROLE_GUEST;
        }
    }
    
    public function setUserRole($user_id, $role) {
        // Validate role
        if (!$this->isValidRole($role)) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET role = ? WHERE id = ?");
            return $stmt->execute([$role, $user_id]);
        } catch (PDOException $e) {
            error_log("Error setting user role: " . $e->getMessage());
            return false;
        }
    }
    
    public function hasPermission($user_id, $permission) {
        $role = $this->getUserRole($user_id);
        return $this->roleHasPermission($role, $permission);
    }
    
    public function roleHasPermission($role, $permission) {
        if (!isset($this->permissions[$role])) {
            return false;
        }
        
        $role_permissions = $this->permissions[$role];
        
        // Admin has all permissions
        if (in_array('*', $role_permissions)) {
            return true;
        }
        
        return in_array($permission, $role_permissions);
    }
    
    public function requirePermission($user_id, $permission) {
        if (!$this->hasPermission($user_id, $permission)) {
            http_response_code(403);
            die(json_encode(['error' => 'Access denied. Insufficient permissions.']));
        }
    }
    
    public function requireRole($user_id, $required_role) {
        $user_role = $this->getUserRole($user_id);
        
        if (!$this->roleHasLevel($user_role, $required_role)) {
            http_response_code(403);
            die(json_encode(['error' => 'Access denied. Insufficient role level.']));
        }
    }
    
    public function roleHasLevel($user_role, $required_role) {
        if (!isset($this->role_hierarchy[$user_role]) || !isset($this->role_hierarchy[$required_role])) {
            return false;
        }
        
        return $this->role_hierarchy[$user_role] >= $this->role_hierarchy[$required_role];
    }
    
    public function isValidRole($role) {
        return in_array($role, [self::ROLE_ADMIN, self::ROLE_MODERATOR, self::ROLE_USER, self::ROLE_GUEST]);
    }
    
    public function getAllRoles() {
        return array_keys($this->role_hierarchy);
    }
    
    public function getRolePermissions($role) {
        return isset($this->permissions[$role]) ? $this->permissions[$role] : [];
    }
    
    public function canManageUser($manager_id, $target_user_id) {
        $manager_role = $this->getUserRole($manager_id);
        $target_role = $this->getUserRole($target_user_id);
        
        // Can't manage yourself or someone with equal/higher role
        return $manager_id != $target_user_id && 
               $this->role_hierarchy[$manager_role] > $this->role_hierarchy[$target_role];
    }
    
    public function getCurrentUserRole() {
        if (!isset($_SESSION['user_id'])) {
            return self::ROLE_GUEST;
        }
        
        return $this->getUserRole($_SESSION['user_id']);
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            header('Location: login.php');
            exit();
        }
    }
}

// Middleware function for route protection
function requireRole($required_role) {
    global $db;
    $roleManager = new RoleManager($db);
    
    $roleManager->requireLogin();
    $roleManager->requireRole($_SESSION['user_id'], $required_role);
}

function requirePermission($permission) {
    global $db;
    $roleManager = new RoleManager($db);
    
    $roleManager->requireLogin();
    $roleManager->requirePermission($_SESSION['user_id'], $permission);
}
?>
