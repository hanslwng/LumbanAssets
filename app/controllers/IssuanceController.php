<?php
/**
 * Issuance Controller
 * 
 * Handles item issuance (PAR/ICS)
 */

class IssuanceController {
    
    private function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }
    
    /**
     * List all issuances
     */
    public function index() {
        $this->checkAuth();
        
        $issuanceModel = new Issuance();
        $officeModel = new Office();
        
        // Get filters
        $filters = [];
        if (!empty($_GET['office_id'])) {
            $filters['office_id'] = $_GET['office_id'];
        }
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        if (!empty($_GET['form_type'])) {
            $filters['form_type'] = $_GET['form_type'];
        }
        
        $issuances = $issuanceModel->getAllIssuances($filters);
        $offices = $officeModel->getAllOffices();
        
        require VIEW_PATH . '/issuances/index.php';
    }
    
    /**
     * Show create issuance form
     */
    public function create() {
        $this->checkAuth();
        
        $itemModel = new Item();
        $officeModel = new Office();
        
        // Get only serviceable items with available quantity
        $items = $itemModel->getAllItems(['status' => 'Serviceable']);
        $offices = $officeModel->getAllOffices();
        
        require VIEW_PATH . '/issuances/create.php';
    }
    
    /**
     * Store new issuance
     */
    public function store() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /issuances');
            exit;
        }
        
        try {
            $data = [
                'item_id' => filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT),
                'quantity' => filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT),
                'issued_to' => filter_input(INPUT_POST, 'issued_to', FILTER_SANITIZE_STRING),
                'office_id' => filter_input(INPUT_POST, 'office_id', FILTER_VALIDATE_INT),
                'issuance_date' => filter_input(INPUT_POST, 'issuance_date', FILTER_SANITIZE_STRING),
                'form_number' => filter_input(INPUT_POST, 'form_number', FILTER_SANITIZE_STRING),
                'ris_number' => filter_input(INPUT_POST, 'ris_number', FILTER_SANITIZE_STRING),
                'purpose' => filter_input(INPUT_POST, 'purpose', FILTER_SANITIZE_STRING),
                'created_by' => $_SESSION['user_id']
            ];
            
            // Validation
            if (empty($data['item_id']) || empty($data['quantity']) || empty($data['issued_to']) || 
                empty($data['office_id']) || empty($data['issuance_date']) || empty($data['form_number'])) {
                throw new Exception("All required fields must be filled");
            }
            
            $issuanceModel = new Issuance();
            $issuanceId = $issuanceModel->createIssuance($data);
            
            $_SESSION['success'] = 'Issuance created successfully';
            header('Location: /issuances/view/' . $issuanceId);
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /issuances/create');
            exit;
        }
    }
    
    /**
     * View issuance details
     */
    public function view($id) {
        $this->checkAuth();
        
        $issuanceModel = new Issuance();
        $issuance = $issuanceModel->getIssuanceById($id);
        
        if (!$issuance) {
            $_SESSION['error'] = 'Issuance not found';
            header('Location: /issuances');
            exit;
        }
        
        // Get returns for this issuance
        $returnModel = new ReturnModel();
        $returns = $returnModel->getReturnsByIssuance($id);
        
        require VIEW_PATH . '/issuances/view.php';
    }
    
    /**
     * Print PAR/ICS form
     */
    public function printForm($id) {
        $this->checkAuth();
        
        $issuanceModel = new Issuance();
        $issuance = $issuanceModel->getIssuanceById($id);
        
        if (!$issuance) {
            $_SESSION['error'] = 'Issuance not found';
            header('Location: /issuances');
            exit;
        }
        
        if ($issuance['form_type'] === 'PAR') {
            require VIEW_PATH . '/issuances/print_par.php';
        } else {
            require VIEW_PATH . '/issuances/print_ics.php';
        }
    }
}
