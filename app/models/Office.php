<?php
/**
 * Office Model
 * 
 * Handles office data management
 */

class Office {
    private $db;
    
    public function __construct() {
        $this->db = getDBConnection();
    }
    
    /**
     * Get all offices
     * 
     * @return array
     */
    public function getAllOffices() {
        $stmt = $this->db->query("SELECT * FROM offices ORDER BY office_name");
        return $stmt->fetchAll();
    }
    
    /**
     * Get office by ID
     * 
     * @param int $id
     * @return array|false
     */
    public function getOfficeById($id) {
        $stmt = $this->db->prepare("SELECT * FROM offices WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Create new office
     * 
     * @param array $data
     * @return int Last insert ID
     */
    public function createOffice($data) {
        $stmt = $this->db->prepare("
            INSERT INTO offices (office_name, office_code, head_name, contact_number) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['office_name'],
            $data['office_code'],
            $data['head_name'] ?? null,
            $data['contact_number'] ?? null
        ]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Update office
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateOffice($id, $data) {
        $stmt = $this->db->prepare("
            UPDATE offices 
            SET office_name = ?, office_code = ?, head_name = ?, contact_number = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['office_name'],
            $data['office_code'],
            $data['head_name'] ?? null,
            $data['contact_number'] ?? null,
            $id
        ]);
    }
    
    /**
     * Get office item count
     * 
     * @param int $officeId
     * @return int
     */
    public function getOfficeItemCount($officeId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM items WHERE office_id = ?");
        $stmt->execute([$officeId]);
        $result = $stmt->fetch();
        return $result['count'];
    }
}
