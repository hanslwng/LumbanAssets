<?php
/**
 * Report Controller
 * 
 * Handles report generation
 */

class ReportController {
    
    private function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }
    
    /**
     * Show reports page
     */
    public function index() {
        $this->checkAuth();
        
        $itemModel = new Item();
        $officeModel = new Office();
        
        $items = $itemModel->getAllItems();
        $offices = $officeModel->getAllOffices();
        
        require VIEW_PATH . '/reports/index.php';
    }
    
    /**
     * Generate inventory report
     */
    public function inventory() {
        $this->checkAuth();
        
        $itemModel = new Item();
        $officeModel = new Office();
        
        $filters = [];
        if (!empty($_GET['office_id'])) {
            $filters['office_id'] = $_GET['office_id'];
        }
        if (!empty($_GET['classification'])) {
            $filters['classification'] = $_GET['classification'];
        }
        
        $items = $itemModel->getAllItems($filters);
        $offices = $officeModel->getAllOffices();
        
        require VIEW_PATH . '/reports/inventory.php';
    }
    
    /**
     * Generate unserviceable items report
     */
    public function unserviceable() {
        $this->checkAuth();
        
        $itemModel = new Item();
        $items = $itemModel->getAllItems(['status' => 'Unserviceable']);
        
        require VIEW_PATH . '/reports/unserviceable.php';
    }
}
