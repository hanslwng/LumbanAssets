<?php
/**
 * Dashboard Controller
 * 
 * Displays main dashboard with statistics and recent transactions
 */

class DashboardController {
    
    /**
     * Show dashboard
     */
    public function index() {
        $this->checkAuth();
        
        $itemModel = new Item();
        
        // Get dashboard statistics
        $stats = $itemModel->getDashboardStats();
        
        // Get recent transactions
        $recentTransactions = $itemModel->getRecentTransactions(10);
        
        require VIEW_PATH . '/dashboard/index.php';
    }
    
    /**
     * Check if user is authenticated
     */
    private function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }
}
