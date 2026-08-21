<?php
namespace App\Controller;

class UserController {
    
    /** @var array Parâmetros da rota injetados pelo Router */
    public array $_routeParams = [];
    
    public function index(): void {
        $users = \Database::fetchAll(
            'SELECT id, name, email, role, active, created_at, last_login FROM users ORDER BY created_at DESC'
        );
        
        require __DIR__ . '/../../views/users/index.php';
    }
    
    public function create(): void {
        require __DIR__ . '/../../views/users/create.php';
    }
    
    public function store(): void {
        $name = trim($_POST['name'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        
        if (!$name || !$email || !$password) {
            $error = 'Preencha todos os campos obrigatórios';
            require __DIR__ . '/../../views/users/create.php';
            return;
        }
        
        // Verificar se email já existe
        $existing = \Database::fetch('SELECT id FROM users WHERE email = ?', [$email]);
        if ($existing) {
            $error = 'Este email já está cadastrado';
            require __DIR__ . '/../../views/users/create.php';
            return;
        }
        
        \Database::insert('users', [
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'active' => true,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        
        header('Location: /users?success=created');
        exit;
    }
    
    public function edit(): void {
        $id = $this->getRouteParam('id');
        
        $user = \Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$user) {
            header('Location: /users');
            exit;
        }
        
        require __DIR__ . '/../../views/users/edit.php';
    }
    
    public function update(): void {
        $id = $this->getRouteParam('id');
        $name = trim($_POST['name'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $role = $_POST['role'] ?? 'user';
        $active = isset($_POST['active']);
        $password = $_POST['password'] ?? '';
        
        if (!$name || !$email) {
            $error = 'Nome e email são obrigatórios';
            $user = \Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
            require __DIR__ . '/../../views/users/edit.php';
            return;
        }
        
        $data = [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'active' => $active,
        ];
        
        if ($password) {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        
        \Database::update('users', $data, 'id = ?', [$id]);
        
        header('Location: /users?success=updated');
        exit;
    }
    
    public function delete(): void {
        $id = $this->getRouteParam('id');
        
        // Não permitir deletar a si mesmo
        if ($id == $_SESSION['user_id']) {
            header('Location: /users?error=self_delete');
            exit;
        }
        
        \Database::delete('users', 'id = ?', [$id]);
        header('Location: /users?success=deleted');
        exit;
    }
    
    public function profile(): void {
        $user = \Database::fetch(
            'SELECT * FROM users WHERE id = ?',
            [$_SESSION['user_id']]
        );
        
        require __DIR__ . '/../../views/users/profile.php';
    }
    
    public function updateProfile(): void {
        $id = $_SESSION['user_id'];
        $name = trim($_POST['name'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        
        if (!$name) {
            $error = 'Nome é obrigatório';
            $user = \Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
            require __DIR__ . '/../../views/users/profile.php';
            return;
        }
        
        // Se quer trocar senha, verificar senha atual
        if ($new_password) {
            $user = \Database::fetch('SELECT password FROM users WHERE id = ?', [$id]);
            if (!password_verify($current_password, $user['password'])) {
                $error = 'Senha atual incorreta';
                $user = \Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
                require __DIR__ . '/../../views/users/profile.php';
                return;
            }
            
            \Database::update('users', [
                'name' => $name,
                'password' => password_hash($new_password, PASSWORD_DEFAULT),
            ], 'id = ?', [$id]);
        } else {
            \Database::update('users', ['name' => $name], 'id = ?', [$id]);
        }
        
        $_SESSION['user_name'] = $name;
        
        header('Location: /profile?success=updated');
        exit;
    }
    
    private function getRouteParam(string $key): string {
        if (!empty($this->_routeParams[$key])) {
            return $this->_routeParams[$key];
        }
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($uri, '/'));
        $segments = array_values($segments);
        $last = end($segments);
        if (is_numeric($last) && ($key === 'id')) {
            return $last;
        }
        return '';
    }
}
