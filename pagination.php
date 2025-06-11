<?php
class Pagination {
    private $total_records;
    private $records_per_page;
    private $current_page;
    private $total_pages;
    
    public function __construct($total_records, $records_per_page = 10, $current_page = 1) {
        $this->total_records = (int)$total_records;
        $this->records_per_page = (int)$records_per_page;
        $this->current_page = max(1, (int)$current_page);
        $this->total_pages = ceil($this->total_records / $this->records_per_page);
        
        // Ensure current page doesn't exceed total pages
        if ($this->current_page > $this->total_pages && $this->total_pages > 0) {
            $this->current_page = $this->total_pages;
        }
    }
    
    public function getOffset() {
        return ($this->current_page - 1) * $this->records_per_page;
    }
    
    public function getLimit() {
        return $this->records_per_page;
    }
    
    public function getCurrentPage() {
        return $this->current_page;
    }
    
    public function getTotalPages() {
        return $this->total_pages;
    }
    
    public function getTotalRecords() {
        return $this->total_records;
    }
    
    public function hasNext() {
        return $this->current_page < $this->total_pages;
    }
    
    public function hasPrevious() {
        return $this->current_page > 1;
    }
    
    public function getNextPage() {
        return $this->hasNext() ? $this->current_page + 1 : $this->current_page;
    }
    
    public function getPreviousPage() {
        return $this->hasPrevious() ? $this->current_page - 1 : $this->current_page;
    }
    
    public function generatePaginationHTML($base_url = '', $query_params = []) {
        if ($this->total_pages <= 1) {
            return '';
        }
        
        $html = '<nav aria-label="Pagination"><ul class="pagination">';
        
        // Previous button
        if ($this->hasPrevious()) {
            $prev_url = $this->buildUrl($base_url, array_merge($query_params, ['page' => $this->getPreviousPage()]));
            $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($prev_url) . '">Previous</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
        }
        
        // Page numbers
        $start = max(1, $this->current_page - 2);
        $end = min($this->total_pages, $this->current_page + 2);
        
        if ($start > 1) {
            $first_url = $this->buildUrl($base_url, array_merge($query_params, ['page' => 1]));
            $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($first_url) . '">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $this->current_page) {
                $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $page_url = $this->buildUrl($base_url, array_merge($query_params, ['page' => $i]));
                $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($page_url) . '">' . $i . '</a></li>';
            }
        }
        
        if ($end < $this->total_pages) {
            if ($end < $this->total_pages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $last_url = $this->buildUrl($base_url, array_merge($query_params, ['page' => $this->total_pages]));
            $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($last_url) . '">' . $this->total_pages . '</a></li>';
        }
        
        // Next button
        if ($this->hasNext()) {
            $next_url = $this->buildUrl($base_url, array_merge($query_params, ['page' => $this->getNextPage()]));
            $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($next_url) . '">Next</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
        }
        
        $html .= '</ul></nav>';
        
        return $html;
    }
    
    private function buildUrl($base_url, $params) {
        if (empty($base_url)) {
            $base_url = $_SERVER['PHP_SELF'];
        }
        
        if (!empty($params)) {
            $base_url .= '?' . http_build_query($params);
        }
        
        return $base_url;
    }
    
    public function getInfo() {
        $start = $this->getOffset() + 1;
        $end = min($start + $this->records_per_page - 1, $this->total_records);
        
        return [
            'start' => $start,
            'end' => $end,
            'total' => $this->total_records,
            'current_page' => $this->current_page,
            'total_pages' => $this->total_pages
        ];
    }
}
?>
