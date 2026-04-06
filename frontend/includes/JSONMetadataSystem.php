<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

class JSONMetadataSystem
{
    private $tenantId;
    
    public function __construct($tenantId = null)
    {
        $this->tenantId = $tenantId ?? ($_SESSION['tenant_id'] ?? 1);
    }
    
    /**
     * Store metadata for any entity
     */
    public function storeMetadata($entityType, $entityId, $metadata)
    {
        try {
            $existing = $this->getMetadata($entityType, $entityId);
            
            $metadataArray = is_array($metadata) ? $metadata : json_decode($metadata, true);
            $metadataJson = json_encode($metadataArray);
            
            if ($existing) {
                // Update existing metadata
                db()->update('json_metadata', [
                    'metadata' => $metadataJson,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'entity_type = ? AND entity_id = ? AND tenant_id = ?', 
                [$entityType, $entityId, $this->tenantId]);
            } else {
                // Insert new metadata
                db()->insert('json_metadata', [
                    'tenant_id' => $this->tenantId,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'metadata' => $metadataJson,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("JSONMetadataSystem::storeMetadata error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get metadata for an entity
     */
    public function getMetadata($entityType, $entityId)
    {
        try {
            $result = db()->fetchOne("
                SELECT metadata, created_at, updated_at 
                FROM json_metadata 
                WHERE entity_type = ? AND entity_id = ? AND tenant_id = ?
            ", [$entityType, $entityId, $this->tenantId]);
            
            if ($result) {
                return [
                    'metadata' => json_decode($result['metadata'], true),
                    'created_at' => $result['created_at'],
                    'updated_at' => $result['updated_at']
                ];
            }
            
            return null;
        } catch (Exception $e) {
            error_log("JSONMetadataSystem::getMetadata error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update specific metadata fields
     */
    public function updateMetadataField($entityType, $entityId, $field, $value)
    {
        try {
            $existing = $this->getMetadata($entityType, $entityId);
            
            if ($existing) {
                $metadata = $existing['metadata'];
                $metadata[$field] = $value;
                
                return $this->storeMetadata($entityType, $entityId, $metadata);
            }
            
            return false;
        } catch (Exception $e) {
            error_log("JSONMetadataSystem::updateMetadataField error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log user activity
     */
    public function logActivity($userId, $action, $details = [])
    {
        try {
            $metadata = [
                'user_id' => $userId,
                'last_login' => date('Y-m-d H:i:s'),
                'recent_actions' => []
            ];
            
            $existing = $this->getMetadata('user', $userId);
            if ($existing) {
                $metadata = $existing['metadata'];
                $metadata['last_login'] = date('Y-m-d H:i:s');
                
                // Add to recent actions (keep last 10)
                $recentActions = $metadata['recent_actions'] ?? [];
                array_unshift($recentActions, [
                    'action' => $action,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'details' => $details
                ]);
                $metadata['recent_actions'] = array_slice($recentActions, 0, 10);
            }
            
            return $this->storeMetadata('user', $userId, $metadata);
        } catch (Exception $e) {
            error_log("JSONMetadataSystem::logActivity error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get quick lookup data for dashboards
     */
    public function getQuickLookup($entityType, $entityId)
    {
        try {
            $metadata = $this->getMetadata($entityType, $entityId);
            
            if ($metadata) {
                return [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'last_updated' => $metadata['updated_at'],
                    'key_data' => $this->extractKeyData($metadata['metadata'])
                ];
            }
            
            return null;
        } catch (Exception $e) {
            error_log("JSONMetadataSystem::getQuickLookup error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extract key data for quick lookups
     */
    private function extractKeyData($metadata)
    {
        $keyData = [];
        
        // Common important fields
        $importantFields = [
            'last_login', 'role', 'status', 'grade_level', 
            'attendance_rate', 'performance_score', 'assigned_classes'
        ];
        
        foreach ($importantFields as $field) {
            if (isset($metadata[$field])) {
                $keyData[$field] = $metadata[$field];
            }
        }
        
        return $keyData;
    }
    
    /**
     * Search metadata
     */
    public function searchMetadata($entityType, $searchTerm, $limit = 20)
    {
        try {
            $results = db()->fetchAll("
                SELECT entity_id, metadata, updated_at 
                FROM json_metadata 
                WHERE entity_type = ? AND tenant_id = ? 
                AND LOWER(metadata) LIKE LOWER(?)
                ORDER BY updated_at DESC 
                LIMIT ?
            ", [$entityType, $this->tenantId, "%{$searchTerm}%", $limit]);
            
            $searchResults = [];
            foreach ($results as $result) {
                $searchResults[] = [
                    'entity_id' => $result['entity_id'],
                    'metadata' => json_decode($result['metadata'], true),
                    'updated_at' => $result['updated_at'],
                    'relevance_score' => $this->calculateRelevance($searchTerm, $result['metadata'])
                ];
            }
            
            // Sort by relevance
            usort($searchResults, function($a, $b) {
                return $b['relevance_score'] <=> $a['relevance_score'];
            });
            
            return $searchResults;
        } catch (Exception $e) {
            error_log("JSONMetadataSystem::searchMetadata error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate relevance score for search results
     */
    private function calculateRelevance($searchTerm, $metadataJson)
    {
        $metadata = json_decode($metadataJson, true);
        $searchLower = strtolower($searchTerm);
        $relevance = 0;
        
        if (is_array($metadata)) {
            foreach ($metadata as $key => $value) {
                if (is_string($value) && strpos(strtolower($value), $searchLower) !== false) {
                    $relevance += 1;
                }
                if (strpos(strtolower($key), $searchLower) !== false) {
                    $relevance += 0.5;
                }
            }
        }
        
        return $relevance;
    }
}
