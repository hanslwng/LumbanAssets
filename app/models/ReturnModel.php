<?php
/**
 * Return Model
 * 
 * Handles item returns with condition assessment
 */

class ReturnModel {
    private $db;
    
    public function __construct() {
        $this->db = getDBConnection();
    }
    
    /**
     * Create new return
     * 
     * @param array $data
     * @return int Last insert ID
     */
    public function createReturn($data) {
        $itemModel = new Item();
        $issuanceModel = new Issuance();
        
        // Get issuance details
        $issuance = $issuanceModel->getIssuanceById($data['issuance_id']);
        
        if (!$issuance) {
            throw new Exception("Issuance not found");
        }
        
        // Validate: Cannot transfer unserviceable items
        if ($data['action_taken'] === 'Transfer to Another Office' && $data['condition_status'] === 'Damaged') {
            throw new Exception("Unserviceable/Damaged items cannot be transferred");
        }
        
        // Create return record
        $stmt = $this->db->prepare("
            INSERT INTO returns (
                issuance_id, item_id, quantity, return_date, condition_status,
                action_taken, transfer_office_id, remarks, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['issuance_id'],
            $issuance['item_id'],
            $data['quantity'],
            $data['return_date'],
            $data['condition_status'],
            $data['action_taken'],
            $data['transfer_office_id'] ?? null,
            $data['remarks'] ?? null,
            $data['created_by']
        ]);
        
        $returnId = $this->db->lastInsertId();
        
        // Process based on action taken
        switch ($data['action_taken']) {
            case 'Return to Storage':
                // Add quantity back to available
                $itemModel->updateQuantity($issuance['item_id'], $data['quantity'], 'add');
                
                // Get updated balance
                $item = $itemModel->getItemById($issuance['item_id']);
                
                // Add property card entry (IN)
                $itemModel->addPropertyCardEntry(
                    $issuance['item_id'],
                    'IN',
                    $data['quantity'],
                    $item['available_quantity'],
                    "RETURN-" . $returnId,
                    $data['return_date'],
                    "Returned from: {$issuance['issued_to']} - Condition: {$data['condition_status']}"
                );
                break;
                
            case 'Transfer to Another Office':
                // Transfer logic: Create new issuance to another office
                // For now, just add back to available quantity
                $itemModel->updateQuantity($issuance['item_id'], $data['quantity'], 'add');
                
                $item = $itemModel->getItemById($issuance['item_id']);
                
                $itemModel->addPropertyCardEntry(
                    $issuance['item_id'],
                    'IN',
                    $data['quantity'],
                    $item['available_quantity'],
                    "TRANSFER-" . $returnId,
                    $data['return_date'],
                    "Transferred to another office"
                );
                break;
                
            case 'Mark as Unserviceable':
                // Mark item as unserviceable
                $itemModel->updateStatus($issuance['item_id'], 'Unserviceable');
                
                // Create unserviceable record
                $unserviceableStmt = $this->db->prepare("
                    INSERT INTO unserviceable_items (item_id, quantity, reason, date_marked, marked_by, remarks)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $unserviceableStmt->execute([
                    $issuance['item_id'],
                    $data['quantity'],
                    "Returned in damaged/unserviceable condition",
                    $data['return_date'],
                    $data['created_by'],
                    $data['remarks']
                ]);
                
                // Get current item
                $item = $itemModel->getItemById($issuance['item_id']);
                
                // Add property card entry
                $itemModel->addPropertyCardEntry(
                    $issuance['item_id'],
                    'IN',
                    $data['quantity'],
                    $item['available_quantity'],
                    "UNSERV-" . $returnId,
                    $data['return_date'],
                    "Marked as unserviceable - Condition: {$data['condition_status']}"
                );
                break;
        }
        
        // Update issuance status
        // Check if all items returned
        $totalReturned = $this->getTotalReturnedQuantity($data['issuance_id']);
        if ($totalReturned >= $issuance['quantity']) {
            $issuanceModel->updateStatus($data['issuance_id'], 'Returned');
        } else {
            $issuanceModel->updateStatus($data['issuance_id'], 'Partial');
        }
        
        return $returnId;
    }
    
    /**
     * Get total returned quantity for an issuance
     * 
     * @param int $issuanceId
     * @return int
     */
    private function getTotalReturnedQuantity($issuanceId) {
        $stmt = $this->db->prepare("SELECT SUM(quantity) as total FROM returns WHERE issuance_id = ?");
        $stmt->execute([$issuanceId]);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
    
    /**
     * Get all returns
     * 
     * @param array $filters
     * @return array
     */
    public function getAllReturns($filters = []) {
        $sql = "
            SELECT r.*, i.item_name, i.property_number,
                   iss.issued_to, iss.form_number,
                   o.office_name as transfer_office,
                   u.name as created_by_name
            FROM returns r
            LEFT JOIN items i ON r.item_id = i.id
            LEFT JOIN issuances iss ON r.issuance_id = iss.id
            LEFT JOIN offices o ON r.transfer_office_id = o.id
            LEFT JOIN users u ON r.created_by = u.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['item_id'])) {
            $sql .= " AND r.item_id = ?";
            $params[] = $filters['item_id'];
        }
        
        if (!empty($filters['condition_status'])) {
            $sql .= " AND r.condition_status = ?";
            $params[] = $filters['condition_status'];
        }
        
        $sql .= " ORDER BY r.return_date DESC, r.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get return by ID
     * 
     * @param int $id
     * @return array|false
     */
    public function getReturnById($id) {
        $stmt = $this->db->prepare("
            SELECT r.*, i.item_name, i.property_number,
                   iss.issued_to, iss.form_number,
                   o.office_name as transfer_office,
                   u.name as created_by_name
            FROM returns r
            LEFT JOIN items i ON r.item_id = i.id
            LEFT JOIN issuances iss ON r.issuance_id = iss.id
            LEFT JOIN offices o ON r.transfer_office_id = o.id
            LEFT JOIN users u ON r.created_by = u.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get returns for an issuance
     * 
     * @param int $issuanceId
     * @return array
     */
    public function getReturnsByIssuance($issuanceId) {
        $stmt = $this->db->prepare("
            SELECT r.*, u.name as created_by_name
            FROM returns r
            LEFT JOIN users u ON r.created_by = u.id
            WHERE r.issuance_id = ?
            ORDER BY r.return_date DESC
        ");
        $stmt->execute([$issuanceId]);
        return $stmt->fetchAll();
    }
}
