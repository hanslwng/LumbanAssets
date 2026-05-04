<?php
/**
 * Item Model
 * 
 * Handles inventory item management with COA-compliant logic
 */

class Item {
    private $db;
    
    public function __construct() {
        $this->db = getDBConnection();
    }
    
    /**
     * Generate property number
     * Format: YYYY-PPE-000001 or YYYY-SE-000001
     * 
     * @param string $classification PPE or Semi-Expendable
     * @param int $year
     * @return string
     */
    public function generatePropertyNumber($classification, $year) {
        $prefix = ($classification === 'PPE') ? 'PPE' : 'SE';
        
        // Get the last property number for this year and classification
        $stmt = $this->db->prepare("
            SELECT property_number 
            FROM items 
            WHERE property_number LIKE ? 
            ORDER BY property_number DESC 
            LIMIT 1
        ");
        $stmt->execute(["$year-$prefix-%"]);
        $lastNumber = $stmt->fetch();
        
        if ($lastNumber) {
            // Extract the sequence number and increment
            $parts = explode('-', $lastNumber['property_number']);
            $sequence = intval($parts[2]) + 1;
        } else {
            $sequence = 1;
        }
        
        return sprintf("%d-%s-%06d", $year, $prefix, $sequence);
    }
    
    /**
     * Auto-classify item based on unit value
     * PPE if >= 50,000, Semi-Expendable if < 50,000
     * 
     * @param float $unitValue
     * @return string
     */
    public function autoClassify($unitValue) {
        return ($unitValue >= 50000) ? 'PPE' : 'Semi-Expendable';
    }
    
    /**
     * Create new item
     * 
     * @param array $data
     * @return int Last insert ID
     */
    public function createItem($data) {
        // Auto-classify based on unit value
        $classification = $this->autoClassify($data['unit_value']);
        
        // Generate property number
        $propertyNumber = $this->generatePropertyNumber($classification, $data['year_acquired']);
        
        $stmt = $this->db->prepare("
            INSERT INTO items (
                property_number, item_name, description, unit_value, quantity, 
                available_quantity, office_id, classification, article, 
                po_number, year_acquired, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $propertyNumber,
            $data['item_name'],
            $data['description'] ?? null,
            $data['unit_value'],
            $data['quantity'],
            $data['quantity'], // Initially, available = total quantity
            $data['office_id'],
            $classification,
            $data['article'] ?? null,
            $data['po_number'] ?? null,
            $data['year_acquired'],
            'Serviceable'
        ]);
        
        $itemId = $this->db->lastInsertId();
        
        // Auto-create initial property card entry (IN)
        $this->createPropertyCardEntry($itemId, 'IN', $data['quantity'], $data['quantity'], $propertyNumber, date('Y-m-d'), 'Initial inventory');
        
        return $itemId;
    }
    
    /**
     * Create property card entry
     * 
     * @param int $itemId
     * @param string $type IN or OUT
     * @param int $quantity
     * @param int $balance
     * @param string $reference
     * @param string $date
     * @param string $remarks
     * @return int
     */
    private function createPropertyCardEntry($itemId, $type, $quantity, $balance, $reference, $date, $remarks = null) {
        $stmt = $this->db->prepare("
            INSERT INTO property_cards (item_id, transaction_type, quantity, balance, reference_number, transaction_date, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$itemId, $type, $quantity, $balance, $reference, $date, $remarks]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Get all items
     * 
     * @param array $filters
     * @return array
     */
    public function getAllItems($filters = []) {
        $sql = "
            SELECT i.*, o.office_name, o.office_code 
            FROM items i
            LEFT JOIN offices o ON i.office_id = o.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['office_id'])) {
            $sql .= " AND i.office_id = ?";
            $params[] = $filters['office_id'];
        }
        
        if (!empty($filters['classification'])) {
            $sql .= " AND i.classification = ?";
            $params[] = $filters['classification'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND i.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY i.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get item by ID
     * 
     * @param int $id
     * @return array|false
     */
    public function getItemById($id) {
        $stmt = $this->db->prepare("
            SELECT i.*, o.office_name, o.office_code 
            FROM items i
            LEFT JOIN offices o ON i.office_id = o.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Update item quantity
     * 
     * @param int $itemId
     * @param int $quantity
     * @param string $type 'add' or 'subtract'
     * @return bool
     */
    public function updateQuantity($itemId, $quantity, $type = 'subtract') {
        if ($type === 'add') {
            $stmt = $this->db->prepare("UPDATE items SET available_quantity = available_quantity + ? WHERE id = ?");
        } else {
            $stmt = $this->db->prepare("UPDATE items SET available_quantity = available_quantity - ? WHERE id = ?");
        }
        return $stmt->execute([$quantity, $itemId]);
    }
    
    /**
     * Update item status
     * 
     * @param int $itemId
     * @param string $status
     * @return bool
     */
    public function updateStatus($itemId, $status) {
        $stmt = $this->db->prepare("UPDATE items SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $itemId]);
    }
    
    /**
     * Get property card for item
     * 
     * @param int $itemId
     * @return array
     */
    public function getPropertyCard($itemId) {
        $stmt = $this->db->prepare("
            SELECT * FROM property_cards 
            WHERE item_id = ? 
            ORDER BY transaction_date ASC, created_at ASC
        ");
        $stmt->execute([$itemId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Add property card entry (public method for issuance/return)
     * 
     * @param int $itemId
     * @param string $type
     * @param int $quantity
     * @param int $balance
     * @param string $reference
     * @param string $date
     * @param string $remarks
     * @return int
     */
    public function addPropertyCardEntry($itemId, $type, $quantity, $balance, $reference, $date, $remarks = null) {
        return $this->createPropertyCardEntry($itemId, $type, $quantity, $balance, $reference, $date, $remarks);
    }
    
    /**
     * Get dashboard statistics
     * 
     * @return array
     */
    public function getDashboardStats() {
        $stats = [];
        
        // Total PPE
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM items WHERE classification = 'PPE' AND status = 'Serviceable'");
        $stats['total_ppe'] = $stmt->fetch()['count'];
        
        // Total Semi-Expendable
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM items WHERE classification = 'Semi-Expendable' AND status = 'Serviceable'");
        $stats['total_semi'] = $stmt->fetch()['count'];
        
        // Total Issued Items
        $stmt = $this->db->query("SELECT SUM(quantity) as count FROM issuances WHERE status = 'Active'");
        $result = $stmt->fetch();
        $stats['total_issued'] = $result['count'] ?? 0;
        
        // Total Unserviceable
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM items WHERE status = 'Unserviceable'");
        $stats['total_unserviceable'] = $stmt->fetch()['count'];
        
        return $stats;
    }
    
    /**
     * Get recent transactions
     * 
     * @param int $limit
     * @return array
     */
    public function getRecentTransactions($limit = 10) {
        $stmt = $this->db->prepare("
            SELECT pc.*, i.item_name, i.property_number
            FROM property_cards pc
            LEFT JOIN items i ON pc.item_id = i.id
            ORDER BY pc.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
