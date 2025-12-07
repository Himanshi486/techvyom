<?php
session_start();
require_once __DIR__ . '/../connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Load helper functions
require_once __DIR__ . '/sheets_helper.php';

// Load Google API client
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    $_SESSION['approve_error'] = "Composer dependencies not installed. Please run 'composer install' in the project root.";
    header("Location: ../dashboard.php");
    exit();
}
require_once $vendorAutoload;

// Configuration
$credentialsPath = __DIR__ . '/../credentials/alumni-service.json';
$spreadsheetId = '1y2BTTfKBrokY4syfNL9qHbX0gTIsnopAznnanpRlPr4';
$sheetName = 'Form responses 1';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['row_index']) && isset($_POST['row_data']) && isset($_POST['headers'])) {
    try {
        // Get row data and headers
        $rowIndex = intval($_POST['row_index']);
        $rowData = json_decode(base64_decode($_POST['row_data']), true);
        $headers = json_decode(base64_decode($_POST['headers']), true);

        if (empty($rowData) || empty($headers) || $rowIndex < 2) {
            throw new Exception("Invalid row data or headers provided.");
        }

        // Ensure rowData array matches headers array length (pad with empty strings if needed)
        while (count($rowData) < count($headers)) {
            $rowData[] = '';
        }
        
        // Helper function to find field value by searching all headers systematically
        $findFieldByKeywords = function($keywords, $headers, $rowData) {
            $bestMatch = null;
            $bestPriority = 0;
            
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                $headerTrimmed = trim($header);
                $priority = 0;
                $matched = false;
                
                // Check each keyword
                foreach ($keywords as $keyword) {
                    $keywordLower = strtolower(trim($keyword));
                    
                    // Exact match gets highest priority
                    if ($headerTrimmed === $keyword || $headerLower === $keywordLower) {
                        $priority = 100;
                        $matched = true;
                        break;
                    }
                    // Contains all words from keyword (for multi-word keywords)
                    elseif (stripos($headerLower, $keywordLower) !== false) {
                        $priority = max($priority, 50);
                        $matched = true;
                    }
                }
                
                // If we found a match
                if ($matched && $priority > $bestPriority) {
                    // Ensure rowData is long enough
                    while (count($rowData) <= $idx) {
                        $rowData[] = '';
                    }
                    $value = isset($rowData[$idx]) ? trim((string)$rowData[$idx]) : '';
                    $bestMatch = $value;
                    $bestPriority = $priority;
                }
            }
            
            return $bestMatch !== null ? $bestMatch : '';
        };

        // Initialize Google Sheets API
        $client = new Google_Client();
        $client->setApplicationName('TechVyom Alumni Management');
        $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
        $client->setAuthConfig($credentialsPath);
        $client->setAccessType('offline');

        $service = new Google_Service_Sheets($client);

        // Helper function to normalize header for matching
        $normalizeHeader = function($header) {
            // Convert to lowercase, normalize whitespace
            $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $header)));
            
            // Only remove file upload instructions for document fields, preserve everything else
            // Remove file upload instruction suffixes (but keep the core field name)
            $normalized = preg_replace('/\s+accepted formats:.*$/i', '', $normalized);
            $normalized = preg_replace('/\s+maximum file size:.*$/i', '', $normalized);
            
            // For document fields, remove the long description but keep core keywords
            if (stripos($normalized, 'attach') !== false) {
                $normalized = preg_replace('/\s*\([^)]*\)\s*/', ' ', $normalized); // Remove parentheses content
                $normalized = preg_replace('/\s+(document|file|image|pdf|photo|id card|certificate|degree|awards?|qualifications?|exam).*$/i', '', $normalized);
            }
            
            // For question fields, remove question mark but keep the content
            $normalized = preg_replace('/\s*\?$/', '', $normalized);
            
            // For fields with colons, keep everything before colon
            if (stripos($normalized, ':') !== false) {
                $parts = explode(':', $normalized);
                $normalized = trim($parts[0]);
            }
            
            $normalized = trim(preg_replace('/\s+/', ' ', $normalized));
            return $normalized;
        };
        
        // Map row data to column names with multiple key formats for flexible matching
        $columnData = [];
        $normalizedColumnData = [];
        foreach ($headers as $index => $header) {
            $value = isset($rowData[$index]) ? trim($rowData[$index]) : '';
            $headerLower = strtolower(trim($header));
            $headerNormalized = $normalizeHeader($header);
            
            // Store with lowercase key (exact match)
            $columnData[$headerLower] = $value;
            
            // Store with normalized key (flexible match)
            if ($headerNormalized !== $headerLower) {
                $normalizedColumnData[$headerNormalized] = $value;
            }
        }
        unset($columnData['_row_index']); // Remove internal field
        
        // Extract data based on column names (flexible matching with direct header search)
        $getValue = function($keys) use ($headers, $rowData, $columnData, $normalizedColumnData, $normalizeHeader) {
            // First try exact lowercase match in columnData (even if empty)
            foreach ($keys as $key) {
                $keyLower = strtolower(trim($key));
                if (isset($columnData[$keyLower])) {
                    return $columnData[$keyLower]; // Return even if empty - let caller handle it
                }
            }
            
            // Then search directly in headers with flexible matching
            foreach ($keys as $key) {
                $keyLower = strtolower(trim($key));
                $keyNormalized = $normalizeHeader($key);
                
                // Search through all headers directly
                foreach ($headers as $idx => $header) {
                    $headerLower = strtolower(trim($header));
                    $headerNormalized = $normalizeHeader($header);
                    $value = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    
                    // Don't skip empty values - we want to return the first match even if empty
                    // The calling code will handle empty values appropriately
                    
                    // Exact match (after normalization)
                    if ($keyNormalized === $headerNormalized) {
                        return $value; // Return even if empty - let caller decide
                    }
                    
                    // Check if key is contained in header or vice versa
                    if (stripos($headerLower, $keyLower) !== false || stripos($keyLower, $headerLower) !== false) {
                        return $value;
                    }
                    
                    // Check normalized versions
                    if (stripos($headerNormalized, $keyNormalized) !== false || stripos($keyNormalized, $headerNormalized) !== false) {
                        return $value;
                    }
                    
                    // Partial word matching - check if all important words from key are in header
                    $keyWords = array_filter(explode(' ', $keyNormalized), function($w) {
                        $w = trim($w);
                        return strlen($w) > 2 && !in_array(strtolower($w), ['the', 'and', 'or', 'for', 'at', 'in', 'of', 'from', 'to']);
                    });
                    
                    if (!empty($keyWords)) {
                        $allWordsMatch = true;
                        foreach ($keyWords as $word) {
                            if (stripos($headerNormalized, $word) === false && stripos($headerLower, $word) === false) {
                                $allWordsMatch = false;
                                break;
                            }
                        }
                        if ($allWordsMatch) {
                            return $value;
                        }
                    }
                }
            }
            
            // Fallback: try normalized column data
            foreach ($keys as $key) {
                $normalizedKey = $normalizeHeader($key);
                if (isset($normalizedColumnData[$normalizedKey]) && !empty($normalizedColumnData[$normalizedKey])) {
                    return $normalizedColumnData[$normalizedKey];
                }
                
                foreach ($normalizedColumnData as $normHeader => $value) {
                    if (!empty($value) && ($normalizedKey === $normHeader || stripos($normHeader, $normalizedKey) !== false || stripos($normalizedKey, $normHeader) !== false)) {
                        return $value;
                    }
                }
            }
            
            return '';
        };

        // Map columns to database fields using helper function
        $timestampRaw = mapValue($headers, $rowData, ['Timestamp', 'timestamp']);
        
        // Convert timestamp format from Google Sheets to MySQL format using helper
        $timestamp = parseDateToMySQL($timestampRaw);
        if (empty($timestamp)) {
            $timestamp = date('Y-m-d H:i:s'); // Default to current date/time if parsing fails
        }
        
        // Legacy code kept for reference but timestamp now uses helper function
        if (false && !empty($timestampRaw)) {
            $timestampRaw = trim($timestampRaw);
            
            // Try multiple parsing strategies
            $parsed = false;
            
            // Strategy 1: Try DateTime class (handles most formats automatically)
            try {
                $dateTime = new DateTime($timestampRaw);
                $timestamp = $dateTime->format('Y-m-d H:i:s');
                $parsed = true;
            } catch (Exception $e) {
                // Continue to manual parsing
            }
            
            // Strategy 2: Manual parsing for MM/DD/YYYY or DD/MM/YYYY formats
            if (!$parsed && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})(\s+(\d{1,2}):(\d{1,2}):(\d{1,2}))?/', $timestampRaw, $matches)) {
                $first = intval($matches[1]);
                $second = intval($matches[2]);
                $year = intval($matches[3]);
                
                // Determine format: if first > 12, it must be DD/MM, otherwise try MM/DD
                $month = $first;
                $day = $second;
                
                if ($first > 12) {
                    // DD/MM/YYYY format
                    $day = $first;
                    $month = $second;
                } elseif ($second > 12) {
                    // MM/DD/YYYY format (already set)
                    // month = first, day = second
                } else {
                    // Ambiguous - try both and validate
                    // Try MM/DD first
                    if (checkdate($first, $second, $year)) {
                        $month = $first;
                        $day = $second;
                    } elseif (checkdate($second, $first, $year)) {
                        // Try DD/MM
                        $month = $second;
                        $day = $first;
                    } else {
                        // Default to MM/DD
                        $month = $first;
                        $day = $second;
                    }
                }
                
                // Ensure valid date
                if (!checkdate($month, $day, $year)) {
                    // If still invalid, use current date
                    $timestamp = date('Y-m-d H:i:s');
                } else {
                    // Format properly
                    $month = str_pad($month, 2, '0', STR_PAD_LEFT);
                    $day = str_pad($day, 2, '0', STR_PAD_LEFT);
                    $year = str_pad($year, 4, '0', STR_PAD_LEFT);
                    
                    // Extract time if present
                    if (isset($matches[4]) && !empty(trim($matches[4])) && isset($matches[5])) {
                        $hour = str_pad(intval($matches[5]), 2, '0', STR_PAD_LEFT);
                        $minute = str_pad(intval($matches[6]), 2, '0', STR_PAD_LEFT);
                        $second = str_pad(intval($matches[7]), 2, '0', STR_PAD_LEFT);
                        $timestamp = "$year-$month-$day $hour:$minute:$second";
                    } else {
                        $timestamp = "$year-$month-$day 00:00:00";
                    }
                }
                $parsed = true;
            }
            
            // Strategy 3: Try YYYY-MM-DD format (already MySQL format)
            if (!$parsed && preg_match('/^(\d{4})-(\d{2})-(\d{2})(\s+(\d{2}):(\d{2}):(\d{2}))?/', $timestampRaw, $matches)) {
                $year = intval($matches[1]);
                $month = intval($matches[2]);
                $day = intval($matches[3]);
                
                // Validate date
                if (checkdate($month, $day, $year)) {
                    if (isset($matches[4]) && !empty(trim($matches[4])) && isset($matches[5])) {
                        $timestamp = $timestampRaw; // Use as-is
                    } else {
                        $timestamp = $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' 00:00:00';
                    }
                    $parsed = true;
                }
            }
            
            // Strategy 4: Try strtotime as last resort
            if (!$parsed && ($parsedTime = strtotime($timestampRaw))) {
                $timestamp = date('Y-m-d H:i:s', $parsedTime);
                $parsed = true;
            }
            
            // Final validation - ensure timestamp is valid MySQL format
            if ($parsed && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $timestamp)) {
                // If still invalid, use current date
                $timestamp = date('Y-m-d H:i:s');
            }
        }
        // Map using exact Google Sheets column names (with comprehensive matching)
        $email = $getValue(['Email Address', 'email address', 'email', 'Email']);
        
        // Direct search for email if not found
        if (empty($email)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (stripos($headerLower, 'email') !== false) {
                    $email = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($email)) break;
                }
            }
        }
        
        $name = $getValue(['Full Name', 'full name', 'name', 'Name']);
        
        // Direct search for name if not found
        if (empty($name)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if ((stripos($headerLower, 'full name') !== false) ||
                    (stripos($headerLower, 'name') !== false && stripos($headerLower, 'full') !== false)) {
                    $name = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($name)) break;
                }
            }
        }
        
        $enroll = $getValue(['University Enrollment no.', 'university enrollment no.', 'University Enrollment no', 'enrollment', 'Enrollment', 'enrollment no']);
        
        // Direct search for enroll if not found
        if (empty($enroll)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if ((stripos($headerLower, 'university enrollment') !== false) ||
                    (stripos($headerLower, 'enrollment') !== false && stripos($headerLower, 'no') !== false)) {
                    $enroll = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($enroll)) break;
                }
            }
        }
        
        // Course - comprehensive search through ALL headers
        $course = '';
        $courseMatchPriority = 0; // Higher = better match
        
        // Search through ALL headers systematically
        foreach ($headers as $idx => $header) {
            $headerTrimmed = trim($header);
            $headerLower = strtolower($headerTrimmed);
            $currentPriority = 0;
            
            // Priority 3: Exact match "Course of Study at SPM college"
            if (stripos($headerTrimmed, 'Course of Study at SPM college') !== false ||
                stripos($headerLower, 'course of study at spm college') !== false) {
                $currentPriority = 3;
            }
            // Priority 2: Contains "course" and "study" and "spm"
            elseif (stripos($headerLower, 'course') !== false && 
                    stripos($headerLower, 'study') !== false && 
                    stripos($headerLower, 'spm') !== false) {
                $currentPriority = 2;
            }
            // Priority 1: Contains "course" and "study"
            elseif (stripos($headerLower, 'course') !== false && 
                    stripos($headerLower, 'study') !== false) {
                $currentPriority = 1;
            }
            // Priority 0.5: Just contains "course" (but not "curriculum" or other unrelated fields)
            elseif (stripos($headerLower, 'course') !== false && 
                    stripos($headerLower, 'curriculum') === false) {
                $currentPriority = 0.5;
            }
            
            // If we found a match and it's better than previous, use it
            if ($currentPriority > $courseMatchPriority) {
                // Ensure rowData array is long enough
                while (count($rowData) <= $idx) {
                    $rowData[] = '';
                }
                $courseValue = isset($rowData[$idx]) ? trim((string)$rowData[$idx]) : '';
                
                // Always store if better priority, but prefer non-empty values
                if ($courseValue !== '' || $course === '') {
                    $course = $courseValue;
                    $courseMatchPriority = $currentPriority;
                }
            }
            // If same priority but current has value and stored one is empty, prefer the one with value
            elseif ($currentPriority === $courseMatchPriority && $currentPriority > 0) {
                while (count($rowData) <= $idx) {
                    $rowData[] = '';
                }
                $courseValue = isset($rowData[$idx]) ? trim((string)$rowData[$idx]) : '';
                if ($courseValue !== '' && $course === '') {
                    $course = $courseValue;
                }
            }
        }
        
        // Fallback: try getValue function if still empty
        if (empty($course)) {
            $course = $getValue([
                'Course of Study at SPM college', 
                'course of study at spm college',
                'Course of Study',
                'course of study',
                'course',
                'Course'
            ]);
        }
        
        // Additional fallback: use helper function to search by keywords
        if (empty($course)) {
            $course = mapValue($headers, $rowData, [
                'Course of Study at SPM college',
                'course of study at spm college',
                'Course of Study',
                'course of study',
                'course'
            ]);
        }
        
        // Department - comprehensive search through ALL headers
        $dept = '';
        $deptMatchPriority = 0; // Higher = better match
        
        // Search through ALL headers systematically
        foreach ($headers as $idx => $header) {
            $headerTrimmed = trim($header);
            $headerLower = strtolower($headerTrimmed);
            $currentPriority = 0;
            
            // Priority 3: Exact match "Name of Department"
            if (stripos($headerTrimmed, 'Name of Department') !== false ||
                stripos($headerLower, 'name of department') !== false) {
                $currentPriority = 3;
            }
            // Priority 2: Contains "department" and "name"
            elseif (stripos($headerLower, 'department') !== false && 
                    stripos($headerLower, 'name') !== false) {
                $currentPriority = 2;
            }
            // Priority 1: Just contains "department"
            elseif (stripos($headerLower, 'department') !== false) {
                $currentPriority = 1;
            }
            
            // If we found a match and it's better than previous, use it
            if ($currentPriority > $deptMatchPriority) {
                // Ensure rowData array is long enough
                while (count($rowData) <= $idx) {
                    $rowData[] = '';
                }
                $deptValue = isset($rowData[$idx]) ? trim((string)$rowData[$idx]) : '';
                
                // Always store if better priority, but prefer non-empty values
                if ($deptValue !== '' || $dept === '') {
                    $dept = $deptValue;
                    $deptMatchPriority = $currentPriority;
                }
            }
            // If same priority but current has value and stored one is empty, prefer the one with value
            elseif ($currentPriority === $deptMatchPriority && $currentPriority > 0) {
                while (count($rowData) <= $idx) {
                    $rowData[] = '';
                }
                $deptValue = isset($rowData[$idx]) ? trim((string)$rowData[$idx]) : '';
                if ($deptValue !== '' && $dept === '') {
                    $dept = $deptValue;
                }
            }
        }
        
        // Fallback: try getValue function if still empty
        if (empty($dept)) {
            $dept = $getValue([
                'Name of Department',
                'name of department',
                'department',
                'Department'
            ]);
        }
        
        // Additional fallback: use helper function to search by keywords
        if (empty($dept)) {
            $dept = mapValue($headers, $rowData, [
                'Name of Department',
                'name of department',
                'department'
            ]);
        }
        
        // Year of admission - try multiple variations
        $year_in = $getValue([
            'Year of admission in SPM college',
            'year of admission in spm college',
            'Year of admission in SPM college ',
            'year of admission',
            'Year of admission',
            'Year Admission',
            'admission year',
            'admission'
        ]);
        
        // Direct search for year of admission if not found
        if (empty($year_in)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (stripos($headerLower, 'year') !== false && stripos($headerLower, 'admission') !== false && stripos($headerLower, 'spm') !== false) {
                    $year_in = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($year_in)) break;
                }
            }
        }
        
        // Year of passing - try multiple variations
        $year_out = $getValue([
            'Year of passing from SPM college',
            'year of passing from spm college',
            'Year of passing from SPM college ',
            'year of passing',
            'Year of passing',
            'Year Passing',
            'passing year',
            'passing',
            'batch',
            'Batch'
        ]);
        
        // Direct search for year of passing if not found
        if (empty($year_out)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if ((stripos($headerLower, 'year') !== false && stripos($headerLower, 'passing') !== false && stripos($headerLower, 'spm') !== false) ||
                    (stripos($headerLower, 'year') !== false && stripos($headerLower, 'passing') !== false)) {
                    $year_out = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($year_out)) break;
                }
            }
        }
        $phone = $getValue(['Contact Number', 'contact number', 'contact', 'Contact', 'phone', 'Phone']);
        
        // Direct search for phone if not found
        if (empty($phone)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if ((stripos($headerLower, 'contact number') !== false) ||
                    (stripos($headerLower, 'contact') !== false && stripos($headerLower, 'number') !== false) ||
                    (stripos($headerLower, 'phone') !== false)) {
                    $phone = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($phone)) break;
                }
            }
        }
        
        $linkedin = $getValue(['LinkedIn Profile', 'linkedin profile', 'linkedin', 'LinkedIn']);
        
        // Direct search for linkedin if not found
        if (empty($linkedin)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if ((stripos($headerLower, 'linkedin profile') !== false) ||
                    (stripos($headerLower, 'linkedin') !== false)) {
                    $linkedin = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($linkedin)) break;
                }
            }
        }
        // College doc is the first "Attach Supporting Document" (before education section)
        $college_doc = '';
        foreach ($headers as $idx => $header) {
            $headerLower = strtolower(trim($header));
            if (stripos($headerLower, 'attach supporting document') !== false && 
                (stripos($headerLower, 'college') !== false || $idx < 13)) {
                $college_doc = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                break;
            }
        }
        if (empty($college_doc)) {
            $college_doc = $getValue(['attach supporting document (college id/degree)', 'college doc']);
        }

        // Education fields
        $has_higher = $getValue(['Have you completed or are you currently pursuing any higher education?', 'have you completed or are you currently pursuing any higher education', 'higher education', 'has higher edu']);
        
        // Direct search for has_higher if not found
        if (empty($has_higher)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (stripos($headerLower, 'higher education') !== false || 
                    (stripos($headerLower, 'completed') !== false && stripos($headerLower, 'pursuing') !== false)) {
                    $has_higher = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($has_higher)) break;
                }
            }
        }
        
        $degree_name = $getValue(['Name of degree (Pursuing/Completed)', 'name of degree', 'degree', 'Degree', 'degree name']);
        
        // Direct search for degree_name if not found
        if (empty($degree_name)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (stripos($headerLower, 'name of degree') !== false || 
                    (stripos($headerLower, 'degree') !== false && stripos($headerLower, 'name') !== false)) {
                    $degree_name = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($degree_name)) break;
                }
            }
        }
        
        $edu_year = $getValue(['Year of Admission', 'year of admission', 'education year', 'higher education year']);
        
        // Direct search for edu_year if not found (but different from year_in - this is for higher education)
        if (empty($edu_year)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (stripos($headerLower, 'year of admission') !== false && stripos($headerLower, 'spm') === false) {
                    $edu_year = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($edu_year)) break;
                }
            }
        }
        
        $inst_name = $getValue(['Institution Name', 'institution name', 'institution', 'Institution']);
        
        // Direct search for inst_name if not found
        if (empty($inst_name)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (stripos($headerLower, 'institution name') !== false || 
                    (stripos($headerLower, 'institution') !== false && stripos($headerLower, 'name') !== false)) {
                    $inst_name = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($inst_name)) break;
                }
            }
        }
        
        $uni_name = $getValue(['University Name', 'university name', 'university', 'University']);
        
        // Direct search for uni_name if not found
        if (empty($uni_name)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (stripos($headerLower, 'university name') !== false || 
                    (stripos($headerLower, 'university') !== false && stripos($headerLower, 'name') !== false)) {
                    $uni_name = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($uni_name)) break;
                }
            }
        }
        // Education doc is the second "Attach Supporting Document" (in education section, after university name)
        $edu_doc = '';
        $foundCollegeDoc = false;
        foreach ($headers as $idx => $header) {
            $headerLower = strtolower(trim($header));
            if (stripos($headerLower, 'attach supporting document') !== false) {
                if ($foundCollegeDoc) {
                    // This is the education doc (second occurrence)
                    $edu_doc = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    break;
                } else {
                    $foundCollegeDoc = true; // First occurrence is college doc
                }
            }
        }
        if (empty($edu_doc)) {
            $edu_doc = $getValue(['education document', 'edu doc']);
        }

        // Employment fields
        $emp_status = $getValue(['Current Employment Status:', 'current employment status', 'Employment Status', 'employment', 'Employment']);
        
        // Direct search for emp_status if not found
        if (empty($emp_status)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (stripos($headerLower, 'employment status') !== false || 
                    (stripos($headerLower, 'current') !== false && stripos($headerLower, 'employment') !== false)) {
                    $emp_status = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($emp_status)) break;
                }
            }
        }
        
        $org = $getValue(['Currently working with Organisation/Company', 'currently working with organisation company', 'organisation', 'Organization', 'company', 'Company']);
        
        // Direct search for org if not found
        if (empty($org)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if ((stripos($headerLower, 'currently working') !== false && stripos($headerLower, 'organisation') !== false) ||
                    (stripos($headerLower, 'currently working') !== false && stripos($headerLower, 'company') !== false) ||
                    (stripos($headerLower, 'organisation') !== false && stripos($headerLower, 'company') !== false)) {
                    $org = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($org)) break;
                }
            }
        }
        
        $role = $getValue(['Current Job Title/ Designation', 'current job title designation', 'designation', 'Designation', 'role', 'Role', 'job title']);
        
        // Direct search for role if not found
        if (empty($role)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if ((stripos($headerLower, 'current job title') !== false) ||
                    (stripos($headerLower, 'job title') !== false && stripos($headerLower, 'designation') !== false) ||
                    (stripos($headerLower, 'designation') !== false)) {
                    $role = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($role)) break;
                }
            }
        }
        
        $job_loc = $getValue(['Location of Current Job', 'location of current job', 'location', 'Location', 'job location']);
        
        // Direct search for job_loc if not found
        if (empty($job_loc)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if ((stripos($headerLower, 'location of current job') !== false) ||
                    (stripos($headerLower, 'location') !== false && stripos($headerLower, 'job') !== false)) {
                    $job_loc = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($job_loc)) break;
                }
            }
        }
        
        $exp_years = $getValue(['Work Experience (years)', 'work experience years', 'experience', 'Experience', 'experience years']);
        
        // Direct search for exp_years if not found
        if (empty($exp_years)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if ((stripos($headerLower, 'work experience') !== false && stripos($headerLower, 'years') !== false) ||
                    (stripos($headerLower, 'experience') !== false && stripos($headerLower, 'years') !== false)) {
                    $exp_years = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($exp_years)) break;
                }
            }
        }
        
        $package = $getValue(['Current Annual Package', 'current annual package', 'package', 'Package', 'annual package', 'salary', 'Salary']);
        
        // Direct search for package if not found
        if (empty($package)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if ((stripos($headerLower, 'current annual package') !== false) ||
                    (stripos($headerLower, 'annual package') !== false) ||
                    (stripos($headerLower, 'package') !== false && stripos($headerLower, 'current') !== false)) {
                    $package = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($package)) break;
                }
            }
        }
        // Employment doc is the third "Attach Supporting Document" (employee id card or salary slip)
        $emp_doc = '';
        $docCount = 0;
        foreach ($headers as $idx => $header) {
            $headerLower = strtolower(trim($header));
            if (stripos($headerLower, 'attach supporting document') !== false) {
                $docCount++;
                if ($docCount == 3 || stripos($headerLower, 'employee id card') !== false || stripos($headerLower, 'salary slip') !== false) {
                    $emp_doc = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    break;
                }
            }
        }
        if (empty($emp_doc)) {
            $emp_doc = $getValue(['employee id card', 'salary slip', 'employment document', 'emp doc']);
        }
        $placed = $getValue(['Were you placed through SPM?', 'were you placed through spm', 'Placed Through SPM', 'placed']);
        
        // Direct search for placed if not found
        if (empty($placed)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (stripos($headerLower, 'placed through spm') !== false ||
                    (stripos($headerLower, 'placed') !== false && stripos($headerLower, 'spm') !== false)) {
                    $placed = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($placed)) break;
                }
            }
        }
        
        $placement_company = $getValue(['placement_company', 'Placement Company']);
        // Note: This field might not exist in Google Sheets, it's usually empty
        
        $placement_role = $getValue(['Role/Job profile', 'role job profile', 'Placement Role', 'placement role']);
        
        // Direct search for placement_role if not found
        if (empty($placement_role)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (stripos($headerLower, 'role') !== false && stripos($headerLower, 'job profile') !== false ||
                    (stripos($headerLower, 'role') !== false && stripos($headerLower, 'job') !== false && stripos($headerLower, 'profile') !== false)) {
                    $placement_role = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($placement_role)) break;
                }
            }
        }
        
        $placement_salary = $getValue(['Salary Offered', 'salary offered', 'Placement Salary', 'placement salary']);
        
        // Direct search for placement_salary if not found
        if (empty($placement_salary)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (stripos($headerLower, 'salary offered') !== false ||
                    (stripos($headerLower, 'salary') !== false && stripos($headerLower, 'offered') !== false)) {
                    $placement_salary = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($placement_salary)) break;
                }
            }
        }
        
        $past_exp = $getValue(['How many Organisations / Companies have you worked with', 'how many organisations companies have you worked with', 'Past Experience', 'past experience']);
        
        // Direct search for past_exp if not found
        if (empty($past_exp)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if ((stripos($headerLower, 'how many') !== false && stripos($headerLower, 'organisations') !== false && stripos($headerLower, 'worked') !== false) ||
                    (stripos($headerLower, 'how many') !== false && stripos($headerLower, 'companies') !== false && stripos($headerLower, 'worked') !== false)) {
                    $past_exp = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($past_exp)) break;
                }
            }
        }
        
        $past_doc = $getValue(['Attach Supporting Documents', 'attach supporting documents', 'past experience doc']);
        
        // Direct search for past_doc if not found (fourth "Attach Supporting Document")
        if (empty($past_doc)) {
            $docCount = 0;
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (stripos($headerLower, 'attach supporting document') !== false) {
                    $docCount++;
                    if ($docCount == 4) {
                        $past_doc = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                        break;
                    }
                }
            }
        }

        // Extras fields
        $exam = $getValue(['Competitive Exam cleared (After Graduation)', 'competitive exam cleared after graduation', 'Competitive Exam', 'exam']);
        
        // Direct search for exam if not found
        if (empty($exam)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (stripos($headerLower, 'competitive exam') !== false && stripos($headerLower, 'cleared') !== false ||
                    (stripos($headerLower, 'competitive exam') !== false && stripos($headerLower, 'graduation') !== false)) {
                    $exam = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($exam)) break;
                }
            }
        }
        
        $exam_doc = $getValue(['Attach Certificates for Qualified Exams', 'attach certificates for qualified exams', 'exam document', 'exam doc']);
        
        // Direct search for exam_doc if not found
        if (empty($exam_doc)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (stripos($headerLower, 'attach certificates') !== false && stripos($headerLower, 'qualified exams') !== false ||
                    (stripos($headerLower, 'certificates') !== false && stripos($headerLower, 'exam') !== false)) {
                    $exam_doc = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($exam_doc)) break;
                }
            }
        }
        
        $achievements = $getValue(['Other significant Achievements/ Awards (if applicable)', 'other significant achievements awards', 'Achievements', 'achievements']);
        
        // Direct search for achievements if not found
        if (empty($achievements)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if ((stripos($headerLower, 'other significant achievements') !== false) ||
                    (stripos($headerLower, 'achievements') !== false && stripos($headerLower, 'awards') !== false)) {
                    $achievements = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($achievements)) break;
                }
            }
        }
        
        $achieve_doc = $getValue(['Attach Certificates for Achievements / Awards', 'attach certificates for achievements awards', 'achievement doc']);
        
        // Direct search for achieve_doc if not found
        if (empty($achieve_doc)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (stripos($headerLower, 'attach certificates') !== false && stripos($headerLower, 'achievements') !== false ||
                    (stripos($headerLower, 'certificates') !== false && stripos($headerLower, 'achievements') !== false && stripos($headerLower, 'awards') !== false)) {
                    $achieve_doc = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($achieve_doc)) break;
                }
            }
        }
        
        $career_help = $getValue(['How has your college education helped you in your career?', 'how has your college education helped you in your career', 'career help', 'Career Help']);
        
        // Direct search for career_help if not found
        if (empty($career_help)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if ((stripos($headerLower, 'how has your college education') !== false && stripos($headerLower, 'career') !== false) ||
                    (stripos($headerLower, 'college education') !== false && stripos($headerLower, 'helped') !== false && stripos($headerLower, 'career') !== false)) {
                    $career_help = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($career_help)) break;
                }
            }
        }
        
        $message = $getValue(['Any advice or message for current students?', 'any advice or message for current students', 'message', 'Message', 'message to students']);
        
        // Direct search for message if not found
        if (empty($message)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if ((stripos($headerLower, 'any advice') !== false && stripos($headerLower, 'message') !== false && stripos($headerLower, 'students') !== false) ||
                    (stripos($headerLower, 'advice') !== false && stripos($headerLower, 'current students') !== false)) {
                    $message = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($message)) break;
                }
            }
        }
        
        $mentor = $getValue(['Are you willing to mentor or help current students?', 'are you willing to mentor or help current students', 'Willing to Mentor', 'willing to mentor', 'mentor']);
        
        // Direct search for mentor if not found
        if (empty($mentor)) {
            foreach ($headers as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if ((stripos($headerLower, 'willing to mentor') !== false) ||
                    (stripos($headerLower, 'mentor') !== false && stripos($headerLower, 'help') !== false && stripos($headerLower, 'students') !== false)) {
                    $mentor = isset($rowData[$idx]) ? trim($rowData[$idx]) : '';
                    if (!empty($mentor)) break;
                }
            }
        }

        // Validate required fields
        if (empty($email)) {
            throw new Exception("Email is required but not found in the row data.");
        }

        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM alumni_basic WHERE email = ?");
        $check->bind_param('s', $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $check->close();
            throw new Exception("An alumni with email '{$email}' already exists in the database.");
        }
        $check->close();

        // Begin transaction
        $conn->begin_transaction();

        try {
            // Handle empty year fields - convert to NULL for nullable fields
            $year_in_val = (!empty($year_in) && is_numeric($year_in)) ? intval($year_in) : NULL;
            $year_out_val = (!empty($year_out) && is_numeric($year_out)) ? intval($year_out) : NULL;
            $edu_year_val = (!empty($edu_year) && is_numeric($edu_year)) ? intval($edu_year) : NULL;

            // Download and store documents locally from Google Drive
            // This makes them accessible to anyone with admin access, regardless of Drive permissions
            if (!empty($college_doc) && (stripos($college_doc, 'drive.google.com') !== false || stripos($college_doc, 'docs.google.com') !== false)) {
                $localPath = downloadDriveDocument($college_doc, 'uploads/documents', 'college_' . preg_replace('/[^a-zA-Z0-9]/', '_', $email), $credentialsPath);
                if (!empty($localPath)) {
                    $college_doc = $localPath;
                } else {
                    // Fallback to cleaned Drive link if download fails
                    $college_doc = cleanDriveLink($college_doc);
                }
            } else {
                $college_doc = cleanDriveLink($college_doc);
            }
            
            if (!empty($edu_doc) && (stripos($edu_doc, 'drive.google.com') !== false || stripos($edu_doc, 'docs.google.com') !== false)) {
                $localPath = downloadDriveDocument($edu_doc, 'uploads/documents', 'education_' . preg_replace('/[^a-zA-Z0-9]/', '_', $email), $credentialsPath);
                if (!empty($localPath)) {
                    $edu_doc = $localPath;
                } else {
                    $edu_doc = cleanDriveLink($edu_doc);
                }
            } else {
                $edu_doc = cleanDriveLink($edu_doc);
            }
            
            if (!empty($emp_doc) && (stripos($emp_doc, 'drive.google.com') !== false || stripos($emp_doc, 'docs.google.com') !== false)) {
                $localPath = downloadDriveDocument($emp_doc, 'uploads/documents', 'employment_' . preg_replace('/[^a-zA-Z0-9]/', '_', $email), $credentialsPath);
                if (!empty($localPath)) {
                    $emp_doc = $localPath;
                } else {
                    $emp_doc = cleanDriveLink($emp_doc);
                }
            } else {
                $emp_doc = cleanDriveLink($emp_doc);
            }
            
            if (!empty($past_doc) && (stripos($past_doc, 'drive.google.com') !== false || stripos($past_doc, 'docs.google.com') !== false)) {
                $localPath = downloadDriveDocument($past_doc, 'uploads/documents', 'past_exp_' . preg_replace('/[^a-zA-Z0-9]/', '_', $email), $credentialsPath);
                if (!empty($localPath)) {
                    $past_doc = $localPath;
                } else {
                    $past_doc = cleanDriveLink($past_doc);
                }
            } else {
                $past_doc = cleanDriveLink($past_doc);
            }
            
            if (!empty($exam_doc) && (stripos($exam_doc, 'drive.google.com') !== false || stripos($exam_doc, 'docs.google.com') !== false)) {
                $localPath = downloadDriveDocument($exam_doc, 'uploads/documents', 'exam_' . preg_replace('/[^a-zA-Z0-9]/', '_', $email), $credentialsPath);
                if (!empty($localPath)) {
                    $exam_doc = $localPath;
                } else {
                    $exam_doc = cleanDriveLink($exam_doc);
                }
            } else {
                $exam_doc = cleanDriveLink($exam_doc);
            }
            
            if (!empty($achieve_doc) && (stripos($achieve_doc, 'drive.google.com') !== false || stripos($achieve_doc, 'docs.google.com') !== false)) {
                $localPath = downloadDriveDocument($achieve_doc, 'uploads/documents', 'achievement_' . preg_replace('/[^a-zA-Z0-9]/', '_', $email), $credentialsPath);
                if (!empty($localPath)) {
                    $achieve_doc = $localPath;
                } else {
                    $achieve_doc = cleanDriveLink($achieve_doc);
                }
            } else {
                $achieve_doc = cleanDriveLink($achieve_doc);
            }
            
            // Convert empty strings to NULL for nullable fields in alumni_basic
            // BUT first, double-check course and department were extracted correctly
            // Final comprehensive search for course - go through ALL headers
            if (empty($course)) {
                foreach ($headers as $idx => $header) {
                    $headerCheck = strtolower(trim($header));
                    // Check for ANY mention of course (be very permissive)
                    if (stripos($headerCheck, 'course') !== false) {
                        // Ensure rowData is long enough
                        while (count($rowData) <= $idx) {
                            $rowData[] = '';
                        }
                        $courseValue = isset($rowData[$idx]) ? trim((string)$rowData[$idx]) : '';
                        // Take the first match we find, even if it seems empty
                        if ($course === '') {
                            $course = $courseValue;
                        }
                        // If we found a non-empty value, use it
                        if (!empty($courseValue)) {
                            $course = $courseValue;
                            break;
                        }
                    }
                }
            }
            
            // Final comprehensive search for department - go through ALL headers
            if (empty($dept)) {
                foreach ($headers as $idx => $header) {
                    $headerCheck = strtolower(trim($header));
                    // Check for ANY mention of department (be very permissive)
                    if (stripos($headerCheck, 'department') !== false) {
                        // Ensure rowData is long enough
                        while (count($rowData) <= $idx) {
                            $rowData[] = '';
                        }
                        $deptValue = isset($rowData[$idx]) ? trim((string)$rowData[$idx]) : '';
                        // Take the first match we find, even if it seems empty
                        if ($dept === '') {
                            $dept = $deptValue;
                        }
                        // If we found a non-empty value, use it
                        if (!empty($deptValue)) {
                            $dept = $deptValue;
                            break;
                        }
                    }
                }
            }
            
            // Documents already downloaded and stored locally above (or cleaned if download failed)
            
            // Convert to NULL only if truly empty, otherwise preserve the value
            $enroll = (trim($enroll) !== '') ? trim($enroll) : NULL;
            $course = (trim($course) !== '') ? trim($course) : NULL;
            $dept = (trim($dept) !== '') ? trim($dept) : NULL;
            $phone = (trim($phone) !== '') ? trim($phone) : NULL;
            $linkedin = (trim($linkedin) !== '') ? trim($linkedin) : NULL;
            $college_doc = (trim($college_doc) !== '') ? trim($college_doc) : NULL;

            // Insert into alumni_basic
            $basic_stmt = $conn->prepare("
                INSERT INTO alumni_basic 
                (timestamp, email, full_name, enrollment_no, course, department, year_admission, year_passing, contact_number, linkedin_profile, college_doc_path, verified)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            // 11 parameters: timestamp(s), email(s), name(s), enroll(s), course(s), dept(s), year_in(i), year_out(i), phone(s), linkedin(s), college_doc(s)
            $basic_stmt->bind_param("ssssssiiss",
                $timestamp, $email, $name, $enroll, $course, $dept,
                $year_in_val, $year_out_val, $phone, $linkedin, $college_doc
            );
            
            if (!$basic_stmt->execute()) {
                throw new Exception("Error inserting into alumni_basic: " . $basic_stmt->error);
            }
            $alumni_id = $conn->insert_id;
            $basic_stmt->close();

            // Insert into alumni_education
            // NOTE: institution_name, university_name, edu_doc_path are NOT NULL in database
            // So we need to ensure they're at least empty strings, not NULL
            $has_higher_val = (strtolower(trim($has_higher)) == 'yes' || trim($has_higher) == '1') ? 1 : 0;
            $degree_name = (trim($degree_name) !== '') ? trim($degree_name) : NULL;
            $inst_name = (trim($inst_name) !== '') ? trim($inst_name) : '';
            $uni_name = (trim($uni_name) !== '') ? trim($uni_name) : '';
            $edu_doc = (trim($edu_doc) !== '') ? trim($edu_doc) : '';
            
            $education_stmt = $conn->prepare("
                INSERT INTO alumni_education 
                (alumni_id, has_higher_edu, degree_name, year_admission, institution_name, university_name, edu_doc_path)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $education_stmt->bind_param("iisisss", 
                $alumni_id, $has_higher_val, $degree_name, $edu_year_val, $inst_name, $uni_name, $edu_doc
            );
            
            if (!$education_stmt->execute()) {
                throw new Exception("Error inserting into alumni_education: " . $education_stmt->error);
            }
            $education_stmt->close();

            // Insert into alumni_employment
            // Default employment status if not provided (NOT NULL enum field)
            if (empty($emp_status) || trim($emp_status) === '') {
                $emp_status = 'Other';
            }
            $placed_val = (strtolower(trim($placed)) == 'yes' || trim($placed) == '1') ? 1 : 0;
            $exp_years_val = (!empty($exp_years) && is_numeric($exp_years)) ? floatval($exp_years) : NULL;
            
            // Convert empty strings to NULL for nullable fields
            $org = (trim($org) !== '') ? trim($org) : NULL;
            $role = (trim($role) !== '') ? trim($role) : NULL;
            $job_loc = (trim($job_loc) !== '') ? trim($job_loc) : NULL;
            $package = (trim($package) !== '') ? trim($package) : NULL;
            $emp_doc = (trim($emp_doc) !== '') ? trim($emp_doc) : NULL;
            $placement_company = (trim($placement_company) !== '') ? trim($placement_company) : NULL;
            $placement_role = (trim($placement_role) !== '') ? trim($placement_role) : NULL;
            $placement_salary = (trim($placement_salary) !== '') ? trim($placement_salary) : NULL;
            $past_exp = (trim($past_exp) !== '') ? trim($past_exp) : NULL;
            $past_doc = (trim($past_doc) !== '') ? trim($past_doc) : NULL;
            
            $employment_stmt = $conn->prepare("
                INSERT INTO alumni_employment 
                (alumni_id, employment_status, organisation, designation, location, experience_years, annual_package, emp_doc_path, placed_through_spm, placement_company, placement_role, placement_salary, past_experience, past_exp_doc_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $employment_stmt->bind_param("issssdsissssss",
                $alumni_id, $emp_status, $org, $role, $job_loc, $exp_years_val, $package,
                $emp_doc, $placed_val, $placement_company, $placement_role, $placement_salary,
                $past_exp, $past_doc
            );
            
            if (!$employment_stmt->execute()) {
                throw new Exception("Error inserting into alumni_employment: " . $employment_stmt->error);
            }
            $employment_stmt->close();

            // Insert into alumni_extras
            // NOTE: ALL fields in alumni_extras are NOT NULL, so ensure they're at least empty strings
            $mentor_val = (strtolower(trim($mentor)) == 'yes' || trim($mentor) == '1') ? 1 : 0;
            $exam = (trim($exam) !== '') ? trim($exam) : '';
            $exam_doc = (trim($exam_doc) !== '') ? trim($exam_doc) : '';
            $achievements = (trim($achievements) !== '') ? trim($achievements) : '';
            $achieve_doc = (trim($achieve_doc) !== '') ? trim($achieve_doc) : '';
            $career_help = (trim($career_help) !== '') ? trim($career_help) : '';
            $message = (trim($message) !== '') ? trim($message) : '';
            
            $extras_stmt = $conn->prepare("
                INSERT INTO alumni_extras 
                (alumni_id, competitive_exam, exam_doc_path, achievements, achievement_doc_path, career_help_text, message_to_students, willing_to_mentor)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $extras_stmt->bind_param("issssssi", 
                $alumni_id, $exam, $exam_doc, $achievements, $achieve_doc, $career_help, $message, $mentor_val
            );
            
            if (!$extras_stmt->execute()) {
                throw new Exception("Error inserting into alumni_extras: " . $extras_stmt->error);
            }
            $extras_stmt->close();

            // Commit transaction
            $conn->commit();

            // Update status in Google Sheets to 'approved' - MUST happen after database commit
            try {
                // Get fresh headers from the sheet
                $headerRange = "'Form responses 1'!A1:Z1";
                $headerResponse = $service->spreadsheets_values->get($spreadsheetId, $headerRange);
                $currentHeaders = array_map('trim', $headerResponse->getValues()[0]);
                
                // Find status column index using helper function
                $statusColIndex = getColumnIndex($currentHeaders, ['Status', 'status']);
                
                // If no status column exists, create it (add as last column)
                if ($statusColIndex === false) {
                    $lastColIndex = count($currentHeaders);
                    $columnLetter = columnIndexToLetter($lastColIndex);
                    
                    // Add status header
                    $headerRange = "'Form responses 1'!" . $columnLetter . "1";
                    $updateValues = [['Status']];
                    $body = new Google_Service_Sheets_ValueRange(['values' => $updateValues]);
                    $params = ['valueInputOption' => 'RAW'];
                    $service->spreadsheets_values->update($spreadsheetId, $headerRange, $body, $params);
                    
                    $statusColIndex = $lastColIndex;
                }
                
                // Update the status to 'approved' using helper function
                $columnLetter = columnIndexToLetter($statusColIndex);
                $updateRange = "'Form responses 1'!" . $columnLetter . $rowIndex;
                $updateValues = [['approved']];
                $body = new Google_Service_Sheets_ValueRange(['values' => $updateValues]);
                $params = ['valueInputOption' => 'RAW'];
                $result = $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);
                
            } catch (Exception $statusError) {
                // Log error but don't fail the approval if status update fails
                error_log("Failed to update status in Google Sheet for row $rowIndex: " . $statusError->getMessage());
                // Still mark as success since database insert worked
            }

            $success = true;
            $_SESSION['approve_success'] = "Alumni entry approved and inserted into database successfully! Entry will no longer appear in pending list.";
            
            // Clear all cached data so dashboard refreshes
            unset($_SESSION['sheets_rows']);
            unset($_SESSION['sheets_headers']);

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
        $_SESSION['approve_error'] = $error;
    }
}

// Redirect back to dashboard - force fresh data load
if ($success) {
    // Clear all session cache
    unset($_SESSION['sheets_rows']);
    unset($_SESSION['sheets_headers']);
    // Redirect with timestamp to force refresh
    header("Location: ../dashboard.php?approved=" . time());
    exit();
} else {
    header("Location: ../dashboard.php");
    exit();
}
?>
