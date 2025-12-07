<?php
session_start();
include 'connect.php';
if (!isset($_SESSION['admin_id'])) header("Location: login.php");

// Load helper functions
require_once __DIR__ . '/admin/sheets_helper.php';

// Fetch Google Sheets data for both pending and verified
$sheetsError = '';
$sheetsRows = [];          // Pending entries (not approved/rejected)
$sheetsVerifiedRows = [];  // Verified entries (status = approved)
$sheetsHeaders = [];
$sheetsPendingCount = 0;
$sheetsVerifiedCount = 0;
$sheetsTotalRows = 0;

// Check for success/error messages from approval
$sheetsSuccessMessage = '';
if (isset($_SESSION['approve_success'])) {
    $sheetsSuccessMessage = $_SESSION['approve_success'];
    unset($_SESSION['approve_success']);
}
if (isset($_SESSION['approve_error'])) {
    $sheetsError = $_SESSION['approve_error'];
    unset($_SESSION['approve_error']);
}

// Check for sync messages
$syncSuccessMessage = '';
$syncErrorMessage = '';
if (isset($_SESSION['sync_success'])) {
    $syncSuccessMessage = $_SESSION['sync_success'];
    unset($_SESSION['sync_success']);
}
if (isset($_SESSION['sync_error'])) {
    $syncErrorMessage = $_SESSION['sync_error'];
    unset($_SESSION['sync_error']);
}

// Check for auto-sync message
$autoSyncMessage = '';
if (isset($_SESSION['auto_sync_count']) && $_SESSION['auto_sync_count'] > 0) {
    $count = $_SESSION['auto_sync_count'];
    $autoSyncMessage = "Automatically marked {$count} database entr" . ($count > 1 ? 'ies' : 'y') . " as approved in Google Sheets.";
    unset($_SESSION['auto_sync_count']);
}

// Load Google Sheets API
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
    
    // Configuration
    $credentialsPath = __DIR__ . '/credentials/alumni-service.json';
    $spreadsheetId = '1y2BTTfKBrokY4syfNL9qHbX0gTIsnopAznnanpRlPr4';
    $sheetName = 'Form responses 1';
    
    // Initialize Google Sheets API
    try {
        if (file_exists($credentialsPath)) {
            $client = new Google_Client();
            $client->setApplicationName('TechVyom Alumni Management');
            $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
            $client->setAuthConfig($credentialsPath);
            $client->setAccessType('offline');
            
            $service = new Google_Service_Sheets($client);
            
            // Read ALL data from Google Sheets - use large range to get all rows
            $range = "'Form responses 1'!A1:AZ100000";
            
            try {
                $response = $service->spreadsheets_values->get($spreadsheetId, $range);
                $values = $response->getValues();
            } catch (Exception $rangeError) {
                // Fallback to smaller range if needed
                $range = "'Form responses 1'!A:Z";
                $response = $service->spreadsheets_values->get($spreadsheetId, $range);
                $values = $response->getValues();
            }
            
            if (!empty($values) && count($values) > 1) {
                // First row contains headers
                $sheetsHeaders = array_map('trim', $values[0]);
                
                // Helper function to normalize header matching
                function normalizeHeaderMatch($header, $keywords) {
                    $headerNorm = strtolower(trim(preg_replace('/\s+/', ' ', $header)));
                    foreach ($keywords as $kw) {
                        $kwNorm = strtolower(trim($kw));
                        if ($headerNorm === $kwNorm || strpos($headerNorm, $kwNorm) !== false || strpos($kwNorm, $headerNorm) !== false) {
                            return true;
                        }
                    }
                    return false;
                }
                
                // Find status column index - flexible matching (case-insensitive)
                $statusColIndex = false;
                foreach ($sheetsHeaders as $idx => $header) {
                    $headerNorm = strtolower(trim(preg_replace('/\s+/', ' ', $header)));
                    if ($headerNorm === 'status' || strpos($headerNorm, 'status') !== false) {
                        $statusColIndex = $idx;
                        break;
                    }
                }
                
                $sheetsTotalRows = count($values) - 1;
                
                // Process ALL rows - separate into pending and verified
                // IMPORTANT: Show ALL rows except explicitly rejected ones
                for ($i = 1; $i < count($values); $i++) {
                    $row = $values[$i];
                    
                    // Ensure row has same length as headers first (before checking if empty)
                    while (count($row) < count($sheetsHeaders)) {
                        $row[] = '';
                    }
                    
                    // Check if row has ANY meaningful data (at least one non-empty cell)
                    $hasAnyData = false;
                    foreach ($row as $cell) {
                        if (trim((string)$cell) !== '') {
                            $hasAnyData = true;
                            break;
                        }
                    }
                    
                    // Skip only completely empty rows (all cells are empty)
                    if (!$hasAnyData) {
                        continue;
                    }
                    
                    // Check status: 
                    // - Pending = no status column OR null/empty status OR status not "approved"/"rejected"
                    // - Verified = status = "approved" 
                    // - Rejected = status = "rejected" (skip these)
                    $status = '';
                    $hasStatus = false;
                    $isApproved = false;
                    $isRejected = false;
                    
                    // Check if status column exists
                    if ($statusColIndex !== false) {
                        // Status column exists - check its value
                        if (isset($row[$statusColIndex])) {
                            $statusRaw = $row[$statusColIndex];
                            // Check if status cell has any value
                            if ($statusRaw !== null && $statusRaw !== '' && trim((string)$statusRaw) !== '') {
                                $hasStatus = true;
                                $statusRaw = (string)$statusRaw;
                                $status = trim(strtolower(preg_replace('/\s+/', ' ', $statusRaw))); // Normalize whitespace
                                
                                // Check for approved status (case-insensitive, handle variations)
                                if (preg_match('/^approved|^approve/i', $status)) {
                                    $isApproved = true;
                                } elseif (preg_match('/^rejected|^reject/i', $status)) {
                                    $isRejected = true;
                                }
                            }
                        }
                    }
                    // If status column doesn't exist, treat as pending (no status = pending)
                    
                    // Add row index for reference (1-based, row 1 is headers)
                    $row['_row_index'] = $i + 1;
                    
                    // Separate into pending and verified
                    // Key logic: 
                    // - If explicitly rejected → skip it
                    // - If approved → add to verified
                    // - Everything else (no status, empty status, other values) → pending
                    if ($isRejected) {
                        // Explicitly rejected - skip it
                        continue;
                    } elseif ($isApproved) {
                        // Status is "approved" - add to verified
                        $sheetsVerifiedRows[] = $row;
                    } else {
                        // No status OR empty status OR other status values = PENDING
                        // This ensures ALL rows are shown unless explicitly rejected or approved
                        $sheetsRows[] = $row;
                    }
                }
                
                $sheetsPendingCount = count($sheetsRows);
                $sheetsVerifiedCount = count($sheetsVerifiedRows);
                
                // Always clear session cache and use fresh data
                unset($_SESSION['sheets_rows'], $_SESSION['sheets_headers']);
            }
            
            // Status Sync Check: Verify all database entries (verified=1) have status="approved" in Google Sheets
            // Do this check after reading all sheet data to avoid re-reading
            $statusSyncIssues = [];
            if (isset($values) && !empty($values) && count($values) > 1) {
                try {
                    // Get all verified entries from database
                    $dbVerifiedQuery = $conn->query("SELECT id, email, full_name FROM alumni_basic WHERE verified = 1");
                    if ($dbVerifiedQuery && $dbVerifiedQuery->num_rows > 0) {
                        // Build email-to-row mapping from already fetched Google Sheets data
                        $emailToRowMap = [];
                        $emailColIndex = false;
                        $allHeadersForSync = array_map('trim', $values[0]);
                        
                        // Find email and status column indices from headers
                        $statusColIndexForSync = false;
                        foreach ($allHeadersForSync as $idx => $header) {
                            $headerLower = strtolower(trim($header));
                            if (in_array($headerLower, ['email', 'email address'])) {
                                $emailColIndex = $idx;
                            }
                            $headerNorm = strtolower(trim(preg_replace('/\s+/', ' ', $header)));
                            if ($headerNorm === 'status' || strpos($headerNorm, 'status') !== false) {
                                $statusColIndexForSync = $idx;
                            }
                        }
                        
                        // Map ALL rows from Google Sheets by email (for sync check)
                        for ($i = 1; $i < count($values); $i++) {
                            $row = $values[$i];
                            while (count($row) < count($allHeadersForSync)) {
                                $row[] = '';
                            }
                            
                            if ($emailColIndex !== false && isset($row[$emailColIndex])) {
                                $sheetEmail = strtolower(trim((string)$row[$emailColIndex]));
                                if (!empty($sheetEmail)) {
                                    $emailToRowMap[$sheetEmail] = [
                                        'row' => $row,
                                        'row_index' => $i + 1
                                    ];
                                }
                            }
                        }
                        
                        // Helper: Convert column index to letter
                        function columnIndexToLetterSync($index) {
                            $colNum = $index + 1;
                            $colLetter = '';
                            while ($colNum > 0) {
                                $colNum--;
                                $colLetter = chr(65 + ($colNum % 26)) . $colLetter;
                                $colNum = intval($colNum / 26);
                            }
                            return $colLetter;
                        }
                        
                        // Auto-sync: Mark database entries as approved in Google Sheets
                        $autoSynced = 0;
                        while ($dbRow = $dbVerifiedQuery->fetch_assoc()) {
                            $dbEmail = strtolower(trim($dbRow['email']));
                            $foundInSheets = isset($emailToRowMap[$dbEmail]);
                            $isApprovedInSheets = false;
                            
                            if ($foundInSheets) {
                                // Check if status is approved
                                $sheetRowData = $emailToRowMap[$dbEmail]['row'];
                                $rowIndex = $emailToRowMap[$dbEmail]['row_index'];
                                
                                if ($statusColIndexForSync !== false && isset($sheetRowData[$statusColIndexForSync])) {
                                    $rowStatusRaw = $sheetRowData[$statusColIndexForSync];
                                    if ($rowStatusRaw !== null && $rowStatusRaw !== '' && trim((string)$rowStatusRaw) !== '') {
                                        $rowStatus = trim(strtolower(preg_replace('/\s+/', ' ', (string)$rowStatusRaw)));
                                        if (preg_match('/^approved|^approve/i', $rowStatus)) {
                                            $isApprovedInSheets = true;
                                        }
                                    }
                                }
                                
                                // Auto-sync: If found but not approved, mark as approved automatically
                                if (!$isApprovedInSheets && $statusColIndexForSync !== false) {
                                    try {
                                        // Create status column if it doesn't exist (already handled above, but ensure it exists)
                                        $columnLetter = columnIndexToLetterSync($statusColIndexForSync);
                                        $updateRange = "'Form responses 1'!" . $columnLetter . $rowIndex;
                                        $updateValues = [['approved']];
                                        $body = new Google_Service_Sheets_ValueRange(['values' => $updateValues]);
                                        $params = ['valueInputOption' => 'RAW'];
                                        $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);
                                        $autoSynced++;
                                        
                                        // Immediately update local data and move to verified list
                                        $sheetRowData[$statusColIndexForSync] = 'approved';
                                        $sheetRowData['_row_index'] = $rowIndex;
                                        
                                        // Remove from pending if it's there
                                        foreach ($sheetsRows as $key => $pendingRow) {
                                            $pendingRowIndex = isset($pendingRow['_row_index']) ? $pendingRow['_row_index'] : 0;
                                            if ($pendingRowIndex == $rowIndex) {
                                                unset($sheetsRows[$key]);
                                                $sheetsPendingCount--;
                                                break;
                                            }
                                        }
                                        
                                        // Add to verified if not already there
                                        $alreadyInVerified = false;
                                        foreach ($sheetsVerifiedRows as $verifiedRow) {
                                            $verifiedRowIndex = isset($verifiedRow['_row_index']) ? $verifiedRow['_row_index'] : 0;
                                            if ($verifiedRowIndex == $rowIndex) {
                                                $alreadyInVerified = true;
                                                break;
                                            }
                                        }
                                        if (!$alreadyInVerified) {
                                            $sheetsVerifiedRows[] = $sheetRowData;
                                            $sheetsVerifiedCount++;
                                        }
                                        
                                        // Update the email map for future checks
                                        $emailToRowMap[$dbEmail]['row'] = $sheetRowData;
                                        
                                        // Also ensure it's in the main values array for consistency
                                        if (isset($values[$rowIndex - 1])) {
                                            $values[$rowIndex - 1][$statusColIndexForSync] = 'approved';
                                        }
                                    } catch (Exception $syncError) {
                                        // Error auto-syncing - track as issue
                                        $statusSyncIssues[] = [
                                            'id' => $dbRow['id'],
                                            'email' => $dbRow['email'],
                                            'name' => $dbRow['full_name'],
                                            'found' => true,
                                            'approved' => false,
                                            'row_index' => $rowIndex,
                                            'error' => $syncError->getMessage()
                                        ];
                                    }
                                }
                            } else {
                                // Not found in sheets - track as issue
                                $statusSyncIssues[] = [
                                    'id' => $dbRow['id'],
                                    'email' => $dbRow['email'],
                                    'name' => $dbRow['full_name'],
                                    'found' => false,
                                    'approved' => false,
                                    'row_index' => null
                                ];
                            }
                        }
                        
                        // Store auto-sync count in session for display
                        if ($autoSynced > 0) {
                            $_SESSION['auto_sync_count'] = $autoSynced;
                        }
                        
                        // Final pass: Ensure ALL database entries (verified=1) that exist in Google Sheets are in verified list
                        // Re-query to get fresh results
                        $dbVerifiedQuery2 = $conn->query("SELECT id, email, full_name FROM alumni_basic WHERE verified = 1");
                        if ($dbVerifiedQuery2 && $dbVerifiedQuery2->num_rows > 0) {
                            while ($dbRow = $dbVerifiedQuery2->fetch_assoc()) {
                            $dbEmail = strtolower(trim($dbRow['email']));
                            if (isset($emailToRowMap[$dbEmail])) {
                                $sheetRowData = $emailToRowMap[$dbEmail]['row'];
                                $rowIndex = $emailToRowMap[$dbEmail]['row_index'];
                                
                                // Check if already in verified list
                                $alreadyInVerified = false;
                                foreach ($sheetsVerifiedRows as $verifiedRow) {
                                    $verifiedRowIndex = isset($verifiedRow['_row_index']) ? $verifiedRow['_row_index'] : 0;
                                    if ($verifiedRowIndex == $rowIndex) {
                                        $alreadyInVerified = true;
                                        break;
                                    }
                                }
                                
                                // If not in verified, add it (database entry means it's verified)
                                if (!$alreadyInVerified) {
                                    // Ensure row has _row_index
                                    if (!isset($sheetRowData['_row_index'])) {
                                        $sheetRowData['_row_index'] = $rowIndex;
                                    }
                                    // Remove from pending if it's there
                                    foreach ($sheetsRows as $key => $pendingRow) {
                                        $pendingRowIndex = isset($pendingRow['_row_index']) ? $pendingRow['_row_index'] : 0;
                                        if ($pendingRowIndex == $rowIndex) {
                                            unset($sheetsRows[$key]);
                                            $sheetsPendingCount--;
                                            break;
                                        }
                                    }
                                    // Add to verified
                                    $sheetsVerifiedRows[] = $sheetRowData;
                                    $sheetsVerifiedCount++;
                                }
                            }
                            }
                        }
                        if ($dbVerifiedQuery2) {
                            $dbVerifiedQuery2->close();
                        }
                    }
                } catch (Exception $syncError) {
                    // Error checking sync - continue anyway
                }
            }
            
            // Store sync issues in session for display
            if (!empty($statusSyncIssues)) {
                $_SESSION['status_sync_issues'] = $statusSyncIssues;
            } else {
                unset($_SESSION['status_sync_issues']);
            }
        }
    } catch (Exception $e) {
        $sheetsError = 'Google Sheets: ' . htmlspecialchars($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | TechVyom</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f6ff;
            color: #333;
            line-height: 1.6;
        }

        /* Header Navigation */
        .admin-header {
            background: linear-gradient(135deg, #2d0a5e 0%, #5a1a9e 35%, #6a2aae 50%, #5a1a9e 65%, #2d0a5e 100%);
            color: white;
            padding: 8px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-title h1 {
            font-size: 18px;
            font-weight: 600;
        }

        .header-title .admin-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 500;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .header-btn {
            padding: 6px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: white;
            color: #7c3aed;
        }

        .btn-primary:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.9);
            color: white;
        }

        .btn-danger:hover {
            background: rgba(220, 38, 38, 0.9);
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        /* Statistics Cards */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #9333ea;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(147, 51, 234, 0.15);
        }

        .stat-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: linear-gradient(135deg, #9333ea, #7c3aed);
            color: white;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #111827;
        }

        .stat-label {
            font-size: 14px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Section Headers */
        .section-header {
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 28px;
            color: #111827;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: #9333ea;
        }

        .section-subtitle {
            color: #6b7280;
            font-size: 16px;
        }

        /* Table Section */
        .table-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #9333ea, #7c3aed);
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }

        tr:hover {
            background-color: #f9fafb;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Action Buttons in Table */
        .table-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            cursor: pointer;
        }

        .btn-approve {
            background: #dcfce7;
            color: #166534;
        }

        .btn-approve:hover {
            background: #bbf7d0;
            transform: scale(1.05);
        }

        .btn-reject {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-reject:hover {
            background: #fecaca;
            transform: scale(1.05);
        }

        .btn-unapprove {
            background: #fef3c7;
            color: #92400e;
        }

        .btn-unapprove:hover {
            background: #fde68a;
            transform: scale(1.05);
        }

        .btn-view {
            background: #dbeafe;
            color: #1e40af;
        }

        .btn-view:hover {
            background: #bfdbfe;
            transform: scale(1.05);
        }

        .btn-edit {
            background: #ede9fe;
            color: #4c1d95;
        }

        .btn-edit:hover {
            background: #ddd6fe;
            transform: scale(1.05);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 64px;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #374151;
        }

        .empty-state p {
            font-size: 16px;
        }

        /* Badge Styles */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-verified {
            background: #dcfce7;
            color: #166534;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .stats-section {
                grid-template-columns: 1fr;
            }

            .section-title {
                font-size: 22px;
            }

            .table-section {
                padding: 20px;
            }

            th, td {
                padding: 10px;
                font-size: 13px;
            }

            .action-btn {
                padding: 6px 12px;
                font-size: 12px;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(147, 51, 234, 0.3);
            border-radius: 50%;
            border-top-color: #9333ea;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #16a34a;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <div class="header-content">
            <div class="header-title">
                <h1><i class="fas fa-dashboard"></i> TechVyom Admin</h1>
                <span class="admin-badge">Control Panel</span>
            </div>
            <div class="header-actions">
                <button onclick="location.reload()" class="header-btn btn-primary" title="Refresh Google Sheets Data">
                    <i class="fas fa-sync-alt"></i>
                    Refresh Data
                </button>
                <a href="index.php" class="header-btn btn-secondary">
                    <i class="fas fa-home"></i>
                    View Website
                </a>
                <a href="logout.php" class="header-btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <!-- Statistics Section -->
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-label">Pending Approval</div>
                        <div class="stat-number"><?= $sheetsPendingCount ?></div>
                    </div>
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-label">Verified Alumni</div>
                        <div class="stat-number"><?= $sheetsVerifiedCount ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-label">Total Alumni</div>
                        <div class="stat-number"><?= $sheetsPendingCount + $sheetsVerifiedCount ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($sheetsSuccessMessage): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($sheetsSuccessMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($sheetsError): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($sheetsError); ?>
            </div>
        <?php endif; ?>

        <?php if ($syncSuccessMessage): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($syncSuccessMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($syncErrorMessage): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($syncErrorMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($autoSyncMessage): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <?php echo htmlspecialchars($autoSyncMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Status Sync Issues Alert -->
        <?php if (isset($_SESSION['status_sync_issues']) && !empty($_SESSION['status_sync_issues'])): ?>
            <div class="alert alert-info">
                <i class="fas fa-sync-alt"></i>
                <strong>Status Sync Alert:</strong> 
                Found <?php echo count($_SESSION['status_sync_issues']); ?> database entry/entries that are verified but not marked as "approved" in Google Sheets.
                <div style="margin-top: 10px;">
                    <a href="admin/sync_status.php" class="btn btn-primary" style="padding: 8px 16px; background: #9333ea; color: white; text-decoration: none; border-radius: 6px; display: inline-block; font-size: 14px;">
                        <i class="fas fa-sync"></i> Sync Status Now
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Pending Entries Section -->
        <?php if (!empty($sheetsHeaders) || $sheetsError): ?>
        <div class="table-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-clock"></i>
                    Pending Alumni Verification
                </h2>
                <p class="section-subtitle">Review and approve entries from Google Sheets (<?php echo $sheetsPendingCount; ?> pending)</p>
            </div>

            <?php if ($sheetsPendingCount > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($sheetsRows as $rowIndex => $rowData): 
                            // Use helper functions for better column matching
                            $name = mapValue($sheetsHeaders, $rowData, ['Full Name', 'full name', 'name']);
                            if (empty($name)) $name = 'N/A';
                            
                            $email = mapValue($sheetsHeaders, $rowData, ['Email Address', 'email address', 'email']);
                            if (empty($email)) $email = 'N/A';
                            
                            $rowNumber = isset($rowData['_row_index']) ? $rowData['_row_index'] : ($rowIndex + 2);
                        ?>
                            <tr>
                                <td><strong><?php echo $name; ?></strong></td>
                                <td><?php echo $email; ?></td>
                            <td>
                                <div class="table-actions">
                                        <a href="admin/view_sheets_entry.php?row=<?php echo $rowNumber; ?>" class="action-btn btn-view" title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                        <a href="admin/edit_sheets_entry.php?row=<?php echo $rowNumber; ?>" class="action-btn btn-edit" title="Edit Entry">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                        <form method="POST" action="admin/approve.php" style="display: inline;">
                                            <input type="hidden" name="row_index" value="<?php echo htmlspecialchars($rowNumber); ?>">
                                            <input type="hidden" name="row_data" value="<?php echo htmlspecialchars(base64_encode(json_encode($rowData))); ?>">
                                            <input type="hidden" name="headers" value="<?php echo htmlspecialchars(base64_encode(json_encode($sheetsHeaders))); ?>">
                                            <button type="submit" class="action-btn btn-approve" title="Approve Entry" onclick="return confirm('Are you sure you want to approve this entry?');">
                                        <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" action="admin/reject_sheets_entry.php" style="display: inline;">
                                            <input type="hidden" name="row_index" value="<?php echo htmlspecialchars($rowNumber); ?>">
                                            <button type="submit" class="action-btn btn-reject" title="Reject Entry" onclick="return confirm('Are you sure you want to reject this entry?');">
                                        <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>All Caught Up!</h3>
                <p>No entries pending approval at the moment.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Verified Alumni Section -->
        <div class="table-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-check-circle"></i>
                    Verified Alumni
                </h2>
                <p class="section-subtitle">All approved alumni profiles from Google Sheets</p>
            </div>

            <?php if ($sheetsVerifiedCount > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Show Google Sheets verified entries - use same view/edit pages as pending
                        foreach ($sheetsVerifiedRows as $rowData): 
                            // Use helper functions for better column matching
                            $name = mapValue($sheetsHeaders, $rowData, ['Full Name', 'full name', 'name']);
                            if (empty($name)) $name = 'N/A';
                            
                            $email = mapValue($sheetsHeaders, $rowData, ['Email Address', 'email address', 'email']);
                            if (empty($email)) $email = 'N/A';
                            
                            $course = mapValue($sheetsHeaders, $rowData, [
                                'Course of Study at SPM college',
                                'course of study at spm college',
                                'Course of Study',
                                'course of study',
                                'course'
                            ]);
                            if (empty($course)) $course = 'N/A';
                            
                            $rowNumber = isset($rowData['_row_index']) ? $rowData['_row_index'] : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo $name; ?></strong></td>
                            <td><?php echo $email; ?></td>
                            <td><?php echo $course; ?></td>
                            <td>
                                <span class="badge badge-verified">
                                    <i class="fas fa-check"></i> Verified
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="admin/view_sheets_entry.php?row=<?php echo $rowNumber; ?>" class="action-btn btn-view" title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="admin/edit_sheets_entry.php?row=<?php echo $rowNumber; ?>" class="action-btn btn-edit" title="Edit Entry">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" action="admin/unapprove_sheets_entry.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to move this entry back to pending? It will no longer appear in verified alumni.');">
                                        <input type="hidden" name="row_index" value="<?php echo $rowNumber; ?>">
                                        <button type="submit" class="action-btn btn-unapprove" title="Move to Pending">
                                            <i class="fas fa-undo"></i> Unapprove
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>No Verified Alumni Yet</h3>
                <p>Start approving submissions to see them here.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh if approved parameter is present
        if (window.location.search.includes('approved=')) {
            // Remove the parameter from URL after 2 seconds
            setTimeout(function() {
                window.history.replaceState({}, document.title, window.location.pathname);
            }, 2000);
        }
        
        // Add confirmation to reject actions
        document.querySelectorAll('.btn-reject').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to reject this alumni submission?')) {
                    e.preventDefault();
                }
            });
        });

        // Add confirmation to approve actions
        document.querySelectorAll('.btn-approve').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to approve this alumni submission?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
