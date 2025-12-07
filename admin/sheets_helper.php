<?php
/**
 * Google Sheets Helper Functions
 * Bulletproof mapping and utility functions for Google Sheets integration
 */

/**
 * Convert Google Drive share links to direct download URLs
 */
function cleanDriveLink($url) {
    if (empty($url)) {
        return '';
    }
    
    $url = trim($url);
    
    // If already a direct download link, return as is
    if (strpos($url, 'uc?id=') !== false || strpos($url, 'uc?export=download') !== false) {
        return $url;
    }
    
    // Extract file ID from various Google Drive link formats
    $fileId = '';
    
    // Format: https://drive.google.com/file/d/FILE_ID/view?usp=sharing
    if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $fileId = $matches[1];
    }
    // Format: https://drive.google.com/open?id=FILE_ID
    elseif (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $fileId = $matches[1];
    }
    // Format: https://docs.google.com/document/d/FILE_ID/edit
    elseif (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $fileId = $matches[1];
    }
    
    if (!empty($fileId)) {
        return 'https://drive.google.com/uc?export=download&id=' . $fileId;
    }
    
    // If we can't parse it, return original
    return $url;
}

/**
 * Get Google Drive embed/preview URL for inline document viewing
 * Returns a URL that can be embedded in an iframe for direct viewing
 */
function getDriveEmbedUrl($url) {
    if (empty($url)) {
        return '';
    }
    
    $url = trim($url);
    $fileId = '';
    $isGoogleDoc = false;
    $docType = '';
    
    // Check if it's a Google Docs/Sheets/Slides link
    if (preg_match('/docs\.google\.com\/(document|spreadsheets|presentation)\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $fileId = $matches[2];
        $isGoogleDoc = true;
        $docType = $matches[1];
    }
    // Format: https://drive.google.com/file/d/FILE_ID/view?usp=sharing
    elseif (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $fileId = $matches[1];
    }
    // Format: https://drive.google.com/open?id=FILE_ID
    elseif (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $fileId = $matches[1];
    }
    // Format: download link
    elseif (preg_match('/uc\?(?:.*&)?id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $fileId = $matches[1];
    }
    
    if (!empty($fileId)) {
        if ($isGoogleDoc) {
            // Google Docs/Sheets/Slides preview URL
            return 'https://docs.google.com/' . $docType . '/d/' . $fileId . '/preview';
        } else {
            // Regular Google Drive file preview (works for PDFs, images, etc.)
            return 'https://drive.google.com/file/d/' . $fileId . '/preview';
        }
    }
    
    return '';
}

/**
 * Download document from Google Drive and save it locally
 * Returns the local file path if successful, empty string if failed
 * 
 * @param string $driveUrl - Google Drive share link
 * @param string $destinationDir - Directory to save the file (relative to project root)
 * @param string $filenamePrefix - Prefix for the filename
 * @param string $credentialsPath - Path to Google service account credentials
 * @return string - Local file path relative to project root, or empty string on failure
 */
function downloadDriveDocument($driveUrl, $destinationDir, $filenamePrefix, $credentialsPath) {
    if (empty($driveUrl) || empty($destinationDir)) {
        return '';
    }
    
    $driveUrl = trim($driveUrl);
    
    // Extract file ID from Google Drive URL
    $fileId = '';
    
    // Format: https://drive.google.com/file/d/FILE_ID/view?usp=sharing
    if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $driveUrl, $matches)) {
        $fileId = $matches[1];
    }
    // Format: https://drive.google.com/open?id=FILE_ID
    elseif (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $driveUrl, $matches)) {
        $fileId = $matches[1];
    }
    // Format: https://docs.google.com/document/d/FILE_ID/edit
    elseif (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $driveUrl, $matches)) {
        $fileId = $matches[1];
    }
    
    if (empty($fileId)) {
        return '';
    }
    
    try {
        // Load Google API client
        $vendorAutoload = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($vendorAutoload)) {
            return '';
        }
        require_once $vendorAutoload;
        
        // Initialize Google Drive API client
        $client = new Google_Client();
        $client->setApplicationName('TechVyom Alumni Management');
        $client->setScopes([
            Google_Service_Sheets::SPREADSHEETS,
            'https://www.googleapis.com/auth/drive.readonly'
        ]);
        $client->setAuthConfig($credentialsPath);
        $client->setAccessType('offline');
        
        // Create Drive service
        $driveService = new Google_Service_Drive($client);
        
        // Get file metadata to determine file extension
        $file = $driveService->files->get($fileId, ['fields' => 'name,mimeType']);
        $fileName = $file->getName();
        $mimeType = $file->getMimeType();
        
        // Determine file extension from MIME type or filename
        $extension = '';
        if (preg_match('/\.([a-zA-Z0-9]+)$/', $fileName, $extMatches)) {
            $extension = '.' . strtolower($extMatches[1]);
        } else {
            // Map MIME types to extensions
            $mimeToExt = [
                'application/pdf' => '.pdf',
                'image/jpeg' => '.jpg',
                'image/png' => '.png',
                'image/gif' => '.gif',
                'application/vnd.google-apps.document' => '.pdf', // Google Docs -> PDF
                'application/vnd.google-apps.spreadsheet' => '.pdf', // Google Sheets -> PDF
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
                'application/msword' => '.doc',
            ];
            $extension = isset($mimeToExt[$mimeType]) ? $mimeToExt[$mimeType] : '';
        }
        
        // Generate unique filename
        $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filenamePrefix);
        $uniqueFilename = $safeFilename . '_' . substr(md5($fileId . time()), 0, 8) . $extension;
        
        // Create destination directory if it doesn't exist
        $fullDestinationDir = __DIR__ . '/../' . trim($destinationDir, '/');
        if (!is_dir($fullDestinationDir)) {
            mkdir($fullDestinationDir, 0755, true);
        }
        
        $localFilePath = $fullDestinationDir . '/' . $uniqueFilename;
        
        // Download file
        if (strpos($mimeType, 'application/vnd.google-apps') === 0) {
            // Google Workspace files (Docs, Sheets, etc.) - export as PDF
            $response = $driveService->files->export($fileId, 'application/pdf', [
                'alt' => 'media'
            ]);
        } else {
            // Regular files - download directly
            $response = $driveService->files->get($fileId, [
                'alt' => 'media'
            ]);
        }
        
        // Save file to local directory
        file_put_contents($localFilePath, $response->getBody()->getContents());
        
        // Return relative path from project root
        $relativePath = trim($destinationDir, '/') . '/' . $uniqueFilename;
        return $relativePath;
        
    } catch (Exception $e) {
        error_log("Failed to download Drive document: " . $e->getMessage());
        return '';
    }
}

/**
 * Bulletproof mapping function to extract values from Google Sheets
 * 
 * @param array $headers - Array of header strings
 * @param array $row - Array of row values
 * @param array $keywordList - Array of keyword variations to search for
 * @param bool $allowUrls - Whether to allow URLs (default: false, filters them out)
 * @return string - The matched value or empty string
 */
function mapValue($headers, $row, $keywordList, $allowUrls = false) {
    if (empty($headers) || empty($row) || empty($keywordList)) {
        return '';
    }
    
    // Ensure row has same length as headers
    while (count($row) < count($headers)) {
        $row[] = '';
    }
    
    $bestMatch = '';
    $bestPriority = 0;
    
    foreach ($headers as $idx => $header) {
        $headerTrimmed = trim($header);
        $headerLower = strtolower(trim(preg_replace('/\s+/', ' ', $headerTrimmed)));
        
        // Normalize header - remove extra text in parentheses/brackets for file uploads
        $headerClean = preg_replace('/\([^)]*\)/i', '', $headerLower);
        $headerClean = preg_replace('/\[[^\]]*\]/i', '', $headerClean);
        $headerClean = preg_replace('/Accepted formats:.*/i', '', $headerClean);
        $headerClean = preg_replace('/Maximum file size:.*/i', '', $headerClean);
        $headerClean = trim($headerClean);
        
        $priority = 0;
        
        foreach ($keywordList as $keyword) {
            $keywordLower = strtolower(trim($keyword));
            $keywordClean = preg_replace('/\([^)]*\)/i', '', $keywordLower);
            $keywordClean = trim($keywordClean);
            
            // Priority 3: Exact match (case-insensitive, trimmed)
            if ($headerTrimmed === $keyword || $headerLower === $keywordLower) {
                $priority = 3;
                break;
            }
            
            // Priority 2: Clean header matches clean keyword exactly
            if ($headerClean === $keywordClean && !empty($keywordClean)) {
                $priority = max($priority, 2);
            }
            
            // Priority 1: Header contains keyword (or vice versa)
            if (stripos($headerClean, $keywordClean) !== false || 
                stripos($keywordClean, $headerClean) !== false) {
                $priority = max($priority, 1);
            }
        }
        
        // Skip file upload columns (unless explicitly allowed)
        if (!$allowUrls) {
            if (stripos($headerLower, 'attach') !== false ||
                stripos($headerLower, 'supporting document') !== false ||
                stripos($headerLower, 'document') !== false ||
                stripos($headerLower, 'pdf') !== false ||
                stripos($headerLower, 'jpg') !== false ||
                stripos($headerLower, 'png') !== false ||
                stripos($headerLower, 'accepted formats') !== false ||
                stripos($headerLower, 'file size') !== false) {
                continue; // Skip file upload columns
            }
        }
        
        // If we found a match with better priority
        if ($priority > $bestPriority) {
            $value = isset($row[$idx]) ? trim((string)$row[$idx]) : '';
            
            // Filter out URLs if not allowed
            if (!$allowUrls && !empty($value)) {
                if (filter_var($value, FILTER_VALIDATE_URL) !== false ||
                    stripos($value, 'drive.google.com') !== false ||
                    stripos($value, 'http://') === 0 ||
                    stripos($value, 'https://') === 0) {
                    continue; // Skip URLs
                }
            }
            
            $bestMatch = $value;
            $bestPriority = $priority;
            
            // If exact match found, we're done
            if ($priority >= 3) {
                break;
            }
        }
    }
    
    return $bestMatch;
}

/**
 * Get column index for a given keyword list
 */
function getColumnIndex($headers, $keywordList, $allowFileUploads = false) {
    $bestIndex = false;
    $bestPriority = 0;
    
    foreach ($headers as $idx => $header) {
        $headerTrimmed = trim($header);
        $headerLower = strtolower(trim(preg_replace('/\s+/', ' ', $headerTrimmed)));
        $headerClean = preg_replace('/\([^)]*\)/i', '', $headerLower);
        $headerClean = preg_replace('/\[[^\]]*\]/i', '', $headerClean);
        $headerClean = preg_replace('/Accepted formats:.*/i', '', $headerClean);
        $headerClean = trim($headerClean);
        
        $priority = 0;
        
        foreach ($keywordList as $keyword) {
            $keywordLower = strtolower(trim($keyword));
            $keywordClean = preg_replace('/\([^)]*\)/i', '', $keywordLower);
            $keywordClean = trim($keywordClean);
            
            if ($headerTrimmed === $keyword || $headerLower === $keywordLower) {
                $priority = 3;
                break;
            }
            
            if ($headerClean === $keywordClean && !empty($keywordClean)) {
                $priority = max($priority, 2);
            }
            
            if (stripos($headerClean, $keywordClean) !== false || 
                stripos($keywordClean, $headerClean) !== false) {
                $priority = max($priority, 1);
            }
        }
        
        // Skip file upload columns unless explicitly allowed
        if (!$allowFileUploads) {
            if (stripos($headerLower, 'attach') !== false ||
                stripos($headerLower, 'supporting document') !== false ||
                stripos($headerLower, 'document') !== false ||
                stripos($headerLower, 'accepted formats') !== false) {
                continue;
            }
        }
        
        if ($priority > $bestPriority) {
            $bestIndex = $idx;
            $bestPriority = $priority;
            
            if ($priority >= 3) {
                break;
            }
        }
    }
    
    return $bestIndex;
}

/**
 * Convert column index to letter (A, B, C, ..., Z, AA, AB, etc.)
 */
function columnIndexToLetter($index) {
    $colNum = $index + 1;
    $colLetter = '';
    while ($colNum > 0) {
        $colNum--;
        $colLetter = chr(65 + ($colNum % 26)) . $colLetter;
        $colNum = intval($colNum / 26);
    }
    return $colLetter;
}

/**
 * Parse date from various formats to MySQL datetime format
 */
function parseDateToMySQL($dateString) {
    if (empty($dateString)) {
        return null;
    }
    
    $dateString = trim($dateString);
    
    // Try multiple formats
    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d',
        'm/d/Y H:i:s',
        'm/d/Y',
        'd/m/Y H:i:s',
        'd/m/Y',
        'Y/m/d H:i:s',
        'Y/m/d'
    ];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateString);
        if ($date !== false) {
            return $date->format('Y-m-d H:i:s');
        }
    }
    
    // Try strtotime as last resort
    $timestamp = strtotime($dateString);
    if ($timestamp !== false) {
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    return null;
}

