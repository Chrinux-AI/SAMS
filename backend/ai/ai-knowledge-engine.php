<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

class AI_KnowledgeEngine
{
    private $tenantId;
    
    public function __construct($tenantId = null)
    {
        $this->tenantId = $tenantId ?? ($_SESSION['tenant_id'] ?? 1);
    }
    
    /**
     * Index admin notes for AI search
     */
    public function indexAdminNotes($content, $title, $category, $authorId)
    {
        try {
            $keywords = $this->extractKeywords($content . ' ' . $title);
            
            db()->insert('ai_knowledge_base', [
                'tenant_id' => $this->tenantId,
                'content' => $content,
                'title' => $title,
                'category' => $category,
                'author_id' => $authorId,
                'keywords' => json_encode($keywords),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("AI_KnowledgeEngine::indexAdminNotes error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Search indexed knowledge base
     */
    public function search($query, $limit = 10)
    {
        try {
            $keywords = $this->extractKeywords($query);
            $searchTerms = [];
            
            foreach ($keywords as $keyword) {
                $searchTerms[] = "LOWER(content) LIKE LOWER(?) OR LOWER(title) LIKE LOWER(?)";
                $params[] = "%{$keyword}%";
                $params[] = "%{$keyword}%";
            }
            
            $sql = "SELECT * FROM ai_knowledge_base 
                   WHERE tenant_id = ? AND (" . implode(' OR ', $searchTerms) . ")
                   ORDER BY created_at DESC LIMIT ?";
            
            array_unshift($params, $this->tenantId);
            $params[] = $limit;
            
            return db()->fetchAll($sql, $params);
        } catch (Exception $e) {
            error_log("AI_KnowledgeEngine::search error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Extract keywords from text
     */
    private function extractKeywords($text)
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = array_filter(explode(' ', $text), function($word) {
            return strlen($word) > 3;
        });
        
        // Remove common words
        $stopWords = ['this', 'that', 'with', 'from', 'they', 'have', 'been', 'said', 'each', 'which', 'their', 'time', 'will', 'about', 'would', 'there', 'could', 'other', 'were', 'than', 'some', 'only', 'after', 'being', 'before', 'when', 'make', 'like', 'how', 'also', 'what', 'know', 'first', 'back', 'through', 'even', 'just', 'where', 'much', 'well', 'get', 'good', 'new', 'want', 'because', 'any', 'these', 'give', 'most', 'us', 'is', 'was', 'are', 'been', 'be', 'have', 'had', 'do', 'does', 'did', 'will', 'would', 'should', 'could', 'may', 'might', 'must', 'can', 'shall', 'not', 'no', 'yes', 'but', 'or', 'and', 'if', 'so', 'too', 'very'];
        
        $keywords = array_diff($words, $stopWords);
        
        // Count frequency and return top keywords
        $wordCounts = array_count_values($keywords);
        arsort($wordCounts);
        
        return array_keys(array_slice($wordCounts, 0, 10, true));
    }
    
    /**
     * Get knowledge base statistics
     */
    public function getStatistics()
    {
        try {
            $stats = db()->fetchOne("
                SELECT 
                    COUNT(*) as total_entries,
                    COUNT(DISTINCT category) as categories,
                    COUNT(DISTINCT author_id) as contributors,
                    MAX(created_at) as last_updated
                FROM ai_knowledge_base 
                WHERE tenant_id = ?
            ", [$this->tenantId]);
            
            return $stats;
        } catch (Exception $e) {
            error_log("AI_KnowledgeEngine::getStatistics error: " . $e->getMessage());
            return null;
        }
    }
}
