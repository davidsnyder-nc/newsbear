<?php
class AuthManager {
    private $settingsFile = 'config/user_settings.json';
    
    public function __construct() {
        if (!isset($_SESSION)) {
            session_start();
        }
    }
    
    public function isAuthEnabled() {
        $settings = $this->loadSettings();
        return isset($settings['authEnabled']) && $settings['authEnabled'] === true;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }
    
    public function requireAuth() {
        if ($this->isAuthEnabled() && !$this->isLoggedIn()) {
            $this->redirectToLogin();
        }
    }
    
    public function authenticate($username, $password) {
        // Simple hardcoded credentials as requested
        if ($username === 'admin' && $password === 'mindless') {
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $username;
            return true;
        }
        return false;
    }
    
    public function logout() {
        unset($_SESSION['authenticated']);
        unset($_SESSION['username']);
        session_destroy();
    }
    
    public function redirectToLogin() {
        header('Location: login.php');
        exit;
    }
    
    public function getAuthStatus() {
        return [
            'enabled' => $this->isAuthEnabled(),
            'loggedIn' => $this->isLoggedIn(),
            'username' => isset($_SESSION['username']) ? $_SESSION['username'] : null
        ];
    }
    
    private function loadSettings() {
        if (file_exists($this->settingsFile)) {
            $settingsJson = file_get_contents($this->settingsFile);
            return json_decode($settingsJson, true) ?: [];
        }
        return [];
    }
}
?>