<?php
/**
 * Issuance Model
 * 
 * Handles item issuance (PAR for PPE, ICS for Semi-Expendable)
 */

class Issuance {
    private $db;
    
    public function __construct() {
        $this->db = getDBConnection();
    }
    
    /**
     * Create new issuance
     * PPE → PAR, Semi-Expendable → ICS
     * 
     * @param array $data
     * @return int Last insert ID
     */
    public function createIssuance($data) {
        // Get item details to determine form type
        $itemModel = new Item();
        $item = $itemModel->getItemById($data['item_id']);
        
        if (!$item) {
            throw new Exception("Item not found");
        }
        
        // Check if sufficient quantity available
        if ($item['available_quantity'] < $data['quantity']) {
            throw new Exception("Insufficient quantity available");
        }
        
        // Determine form type based on classification
        $formType = ($item['classification'] === 'PPE') ? 'PAR' : 'ICS';
        
        // Create issuance record
        $stmt = $this->db->prepare("
            INSERT INTO issuances (
                item_id, quantity, issued_to, office_id, issuance_date, 
                form_type, form_number, ris_number, purpose, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)
        ");
        
        $stmt->execute([
            $data['item_id'],
            $data['quantity'],
            $data['issued_to'],
            $data['office_id'],
            $data['issuance_date'],
            $formType,
            $data['form_number'],
            $data['ris_number'] ?? null,
            $data['purpose'] ?? null,
            $data['created_by']
        ]);
        
        $issuanceId = $this->db->lastInsertId();
        
        // Update item available quantity
        $itemModel->updateQuantity($data['item_id'], $data['quantity'], 'subtract');
        
        // Get current balance
        $updatedItem = $itemModel->getItemById($data['item_id']);
        
        // Add property card entry (OUT)
        $itemModel->addPropertyCardEntry(
            $data['item_id'],
            'OUT',
            $data['quantity'],
            $updatedItem['available_quantity'],
            $data['form_number'],
            $data['issuance_date'],
            "Issued to: {$data['issued_to']}"
        );
        
        return $issuanceId;
    }
    
    /**
     * Get all issuances
     * 
     * @param array $filters
     * @return array
     */
    public function getAllIssuances($filters = []) {
        $sql = "
            SELECT iss.*, i.item_name, i.property_number, i.classification,
                   o.office_name, u.name as created_by_name
            FROM issuances iss
            LEFT JOIN items i ON iss.item_id = i.id
            LEFT JOIN offices o ON iss.office_id = o.id
            LEFT JOIN users u ON iss.created_by = u.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['office_id'])) {
            $sql .= " AND iss.office_id = ?";
            $params[] = $filters['office_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND iss.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['form_type'])) {
            $sql .= " AND iss.form_type = ?";
            $params[] = $filters['form_type'];
        }
        
        $sql .= " ORDER BY iss.issuance_date DESC, iss.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get issuance by ID
     * 
     * @param int $id
     * @return array|false
     */
    public function getIssuanceById($id) {
        $stmt = $this->db->prepare("
            SELECT iss.*, i.item_name, i.property_number, i.classification,
                   o.office_name, u.name as created_by_name
            FROM issuances iss
            LEFT JOIN items i ON iss.item_id = i.id
            LEFT JOIN offices o ON iss.office_id = o.id
            LEFT JOIN users u ON iss.created_by = u.id
            WHERE iss.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Update issuance status
     * 
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus($id, $status) {
        $stmt = $this->db->prepare("UPDATE issuances SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
    
    /**
     * Get active issuances for an item
     * 
     * @param int $itemId
     * @return array
     */
    public function getActiveIssuancesByItem($itemId) {
        $stmt = $this->db->prepare("
            SELECT iss.*, o.office_name
            FROM issuances iss
            LEFT JOIN offices o ON iss.office_id = o.id
            WHERE iss.item_id = ? AND iss.status = 'Active'
            ORDER BY iss.issuance_date DESC
        ");
        $stmt->execute([$itemId]);
        return $stmt->fetchAll();
    }
}
