<?php
/**
 * Formatting Helper Functions
 * Ensures consistent formatting of all table entries across all pages
 */

if (!function_exists('formatStringLabel')) {
    /**
     * Format a string label with proper capitalization
     */
    function formatStringLabel($value) {
        if (is_null($value) || $value === '') {
            return '';
        }
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        // Remove extra whitespace
        $value = preg_replace('/\s+/', ' ', $value);
        // Proper capitalization
        return ucwords(strtolower($value));
    }
}

if (!function_exists('formatName')) {
    /**
     * Format a person's name properly
     */
    function formatName($name) {
        if (empty($name)) {
            return '';
        }
        $name = trim((string)$name);
        if ($name === '') {
            return '';
        }
        // Remove extra whitespace
        $name = preg_replace('/\s+/', ' ', $name);
        // Capitalize each word
        return ucwords(strtolower($name));
    }
}

if (!function_exists('formatCompanyName')) {
    /**
     * Format company/organization names consistently
     */
    function formatCompanyName($name) {
        if (empty($name)) {
            return '';
        }
        $name = trim((string)$name);
        if ($name === '') {
            return '';
        }
        // Remove extra whitespace
        $name = preg_replace('/\s+/', ' ', $name);
        // Preserve acronyms and common company name patterns
        $name = formatStringLabel($name);
        return $name;
    }
}

if (!function_exists('formatInstitutionName')) {
    /**
     * Format institution/university names consistently
     */
    function formatInstitutionName($name) {
        if (empty($name)) {
            return '';
        }
        $name = trim((string)$name);
        if ($name === '') {
            return '';
        }
        // Remove extra whitespace and trailing periods
        $name = preg_replace('/\s+/', ' ', $name);
        $name = rtrim($name, '.');
        // Capitalize properly
        return ucwords(strtolower($name));
    }
}

if (!function_exists('formatLocation')) {
    /**
     * Format location strings consistently
     */
    function formatLocation($location) {
        if (empty($location)) {
            return '';
        }
        $location = trim((string)$location);
        if ($location === '') {
            return '';
        }
        // Remove extra whitespace and trailing punctuation
        $location = preg_replace('/\s+/', ' ', $location);
        $location = rtrim($location, '.,;');
        // Capitalize properly
        return ucwords(strtolower($location));
    }
}

if (!function_exists('formatDegree')) {
    /**
     * Format degree/program names consistently
     */
    function formatDegree($degree) {
        if (empty($degree)) {
            return '';
        }
        $degree = trim((string)$degree);
        if ($degree === '') {
            return '';
        }
        // Filter out URLs/links
        if (filter_var($degree, FILTER_VALIDATE_URL) !== false ||
            stripos($degree, 'drive.google.com') !== false ||
            stripos($degree, 'http://') !== false ||
            stripos($degree, 'https://') !== false) {
            return '';
        }
        // Remove extra whitespace
        $degree = preg_replace('/\s+/', ' ', $degree);
        // Capitalize properly
        return ucwords(strtolower($degree));
    }
}

if (!function_exists('formatRole')) {
    /**
     * Format job role/designation consistently
     */
    function formatRole($role) {
        if (empty($role)) {
            return '';
        }
        $role = trim((string)$role);
        if ($role === '') {
            return '';
        }
        // Remove extra whitespace
        $role = preg_replace('/\s+/', ' ', $role);
        // Capitalize properly
        return ucwords(strtolower($role));
    }
}

if (!function_exists('formatLinkedInUrl')) {
    /**
     * Clean and validate LinkedIn URL
     */
    function formatLinkedInUrl($url) {
        if (empty($url)) {
            return '';
        }
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }
        // Remove extra whitespace
        $url = trim($url);
        // Ensure it starts with http:// or https://
        if (!preg_match('/^https?:\/\//i', $url)) {
            // If it's a LinkedIn profile URL without protocol, add https://
            if (stripos($url, 'linkedin.com') !== false || stripos($url, 'linked.in') !== false) {
                $url = 'https://' . ltrim($url, '/');
            }
        }
        return $url;
    }
}

if (!function_exists('formatBatch')) {
    /**
     * Format batch year display consistently
     */
    function formatBatch($admissionYear, $passingYear) {
        $parts = [];
        if (!empty($admissionYear) && is_numeric($admissionYear)) {
            $parts[] = (int)$admissionYear;
        }
        if (!empty($passingYear) && is_numeric($passingYear)) {
            $parts[] = (int)$passingYear;
        }
        if (count($parts) === 0) {
            return '';
        }
        return implode(' - ', $parts);
    }
}

if (!function_exists('formatDisplayValue')) {
    /**
     * Format any value for display in tables (handles empty values consistently)
     */
    function formatDisplayValue($value, $emptyDisplay = '—') {
        if (is_null($value) || $value === '' || (is_string($value) && trim($value) === '')) {
            return $emptyDisplay;
        }
        
        // Value is already formatted, just check if empty
        $trimmed = trim((string)$value);
        return $trimmed === '' ? $emptyDisplay : $trimmed;
    }
}

if (!function_exists('formatProgramName')) {
    /**
     * Format program/course names with mappings
     */
    function formatProgramName($name) {
        if (empty($name)) {
            return '';
        }
        
        $formatted = formatStringLabel($name);
        if ($formatted === '') {
            return '';
        }

        $normalized = strtolower(preg_replace('/[^a-z0-9\s]/i', ' ', $name));
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        $programMappings = [
            'computer science' => 'BSc Computer Science (Hons)',
            'bsc computer science h' => 'BSc Computer Science (Hons)',
            'bsc cs hons' => 'BSc Computer Science (Hons)',
            'bsc h computer science' => 'BSc Computer Science (Hons)',
            'bsc honours computer science' => 'BSc Computer Science (Hons)',
            'bsc computer science honours' => 'BSc Computer Science (Hons)',
            'bsc hons computer science' => 'BSc Computer Science (Hons)',
            'bsc computer science' => 'BSc Computer Science',
            'bsc pol science' => 'BSc Political Science',
            'political science' => 'BSc Political Science',
            'bsc pol science h' => 'BSc Political Science (Hons)',
        ];

        foreach ($programMappings as $key => $canonical) {
            if (strpos($normalized, $key) !== false) {
                return $canonical;
            }
        }

        return $formatted;
    }
}

