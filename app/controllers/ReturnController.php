<?php
/**
 * Return Controller
 * 
 * Handles item returns
 */

class ReturnController {
    
    private function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }
    
    /**
     * List all returns
     */
    public function index() {
        $this->checkAuth();
        
        $returnModel = new ReturnModel();
        $returns = $returnModel->getAllReturns();
        
        require VIEW_PATH . '/returns/index.php';
    }
    
    /**
     * Show create return form
     */
    public function create() {
        $this->checkAuth();
        
        $issuanceModel = new Issuance();
        $officeModel = new Office();
        
        // Get only active issuances
        $issuances = $issuanceModel->getAllIssuances(['status' => 'Active']);
        $offices = $officeModel->getAllOffices();
        
        require VIEW_PATH . '/returns/create.php';
    }
    
    /**
     * Store new return
     */
    public function store() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /returns');
            exit;
        }
        
        try {
            $data = [
                'issuance_id' => filter_input(INPUT_POST, 'issuance_id', FILTER_VALIDATE_INT),
                'quantity' => filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT),
                'return_date' => filter_input(INPUT_POST, 'return_date', FILTER_SANITIZE_STRING),
                'condition_status' => filter_input(INPUT_POST, 'condition_status', FILTER_SANITIZE_STRING),
                'action_taken' => filter_input(INPUT_POST, 'action_taken', FILTER_SANITIZE_STRING),
                'transfer_office_id' => filter_input(INPUT_POST, 'transfer_office_id', FILTER_VALIDATE_INT),
                'remarks' => filter_input(INPUT_POST, 'remarks', FILTER_SANITIZE_STRING),
                'created_by' => $_SESSION['user_id']
            ];
            
            // Validation
            if (empty($data['issuance_id']) || empty($data['quantity']) || empty($data['return_date']) || 
                empty($data['condition_status']) || empty($data['action_taken'])) {
                throw new Exception("All required fields must be filled");
            }
            
            $returnModel = new ReturnModel();
            $returnId = $returnModel->createReturn($data);
            
            $_SESSION['success'] = 'Return processed successfully';
            header('Location: /returns/view/' . $returnId);
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /returns/create');
            exit;
        }
    }
    
    /**
     * View return details
     */
    public function view($id) {
        $this->checkAuth();
        
        $returnModel = new ReturnModel();
        $return = $returnModel->getReturnById($id);
        
        if (!$return) {
            $_SESSION['error'] = 'Return not found';
            header('Location: /returns');
            exit;
        }
        
        require VIEW_PATH . '/returns/view.php';
    }
}
