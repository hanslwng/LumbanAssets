<?php
/**
 * Item Controller
 * 
 * Handles inventory item management
 */

class ItemController {
    
    /**
     * Check authentication
     */
    private function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }
    
    /**
     * List all items
     */
    public function index() {
        $this->checkAuth();
        
        $itemModel = new Item();
        $officeModel = new Office();
        
        // Get filters
        $filters = [];
        if (!empty($_GET['office_id'])) {
            $filters['office_id'] = $_GET['office_id'];
        }
        if (!empty($_GET['classification'])) {
            $filters['classification'] = $_GET['classification'];
        }
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        
        $items = $itemModel->getAllItems($filters);
        $offices = $officeModel->getAllOffices();
        
        require VIEW_PATH . '/items/index.php';
    }
    
    /**
     * Show create item form
     */
    public function create() {
        $this->checkAuth();
        
        $officeModel = new Office();
        $offices = $officeModel->getAllOffices();
        
        require VIEW_PATH . '/items/create.php';
    }
    
    /**
     * Store new item
     */
    public function store() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /items');
            exit;
        }
        
        try {
            $data = [
                'item_name' => filter_input(INPUT_POST, 'item_name', FILTER_SANITIZE_STRING),
                'description' => filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING),
                'unit_value' => filter_input(INPUT_POST, 'unit_value', FILTER_VALIDATE_FLOAT),
                'quantity' => filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT),
                'office_id' => filter_input(INPUT_POST, 'office_id', FILTER_VALIDATE_INT),
                'article' => filter_input(INPUT_POST, 'article', FILTER_SANITIZE_STRING),
                'po_number' => filter_input(INPUT_POST, 'po_number', FILTER_SANITIZE_STRING),
                'year_acquired' => filter_input(INPUT_POST, 'year_acquired', FILTER_VALIDATE_INT)
            ];
            
            // Validation
            if (empty($data['item_name']) || empty($data['unit_value']) || empty($data['quantity']) || 
                empty($data['office_id']) || empty($data['year_acquired'])) {
                throw new Exception("All required fields must be filled");
            }
            
            $itemModel = new Item();
            $itemId = $itemModel->createItem($data);
            
            $_SESSION['success'] = 'Item created successfully with auto-generated property number';
            header('Location: /items/view/' . $itemId);
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /items/create');
            exit;
        }
    }
    
    /**
     * View item details and property card
     */
    public function view($id) {
        $this->checkAuth();
        
        $itemModel = new Item();
        $item = $itemModel->getItemById($id);
        
        if (!$item) {
            $_SESSION['error'] = 'Item not found';
            header('Location: /items');
            exit;
        }
        
        // Get property card
        $propertyCard = $itemModel->getPropertyCard($id);
        
        // Get active issuances
        $issuanceModel = new Issuance();
        $activeIssuances = $issuanceModel->getActiveIssuancesByItem($id);
        
        require VIEW_PATH . '/items/view.php';
    }
    
    /**
     * Print property card
     */
    public function printPropertyCard($id) {
        $this->checkAuth();
        
        $itemModel = new Item();
        $item = $itemModel->getItemById($id);
        
        if (!$item) {
            $_SESSION['error'] = 'Item not found';
            header('Location: /items');
            exit;
        }
        
        $propertyCard = $itemModel->getPropertyCard($id);
        
        require VIEW_PATH . '/items/print_property_card.php';
    }
}
