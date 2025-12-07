<?php
session_start();
require_once __DIR__ . '/../connect.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Configuration
$credentialsPath = __DIR__ . '/../credentials/alumni-service.json';
$spreadsheetId = '1y2BTTfKBrokY4syfNL9qHbX0gTIsnopAznnanpRlPr4';
$sheetName = 'Form responses 1';

$synced = 0;
$errors = [];
$notFound = [];

// Load Google Sheets API
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    $_SESSION['sync_error'] = "Composer dependencies not installed.";
    header("Location: ../dashboard.php");
    exit();
}

require_once $vendorAutoload;

try {
    $client = new Google_Client();
    $client->setApplicationName('TechVyom Alumni Management');
    $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
    $client->setAuthConfig($credentialsPath);
    $client->setAccessType('offline');
    
    $service = new Google_Service_Sheets($client);
    
    // Get all verified entries from database
    $dbVerifiedQuery = $conn->query("SELECT id, email, full_name FROM alumni_basic WHERE verified = 1");
    
    if ($dbVerifiedQuery && $dbVerifiedQuery->num_rows > 0) {
        // Read all Google Sheets data once
        $range = "'Form responses 1'!A1:AZ100000";
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $allValues = $response->getValues();
        
        if (!empty($allValues) && count($allValues) > 1) {
            $allHeaders = array_map('trim', $allValues[0]);
            
            // Find email and status column indices
            $emailColIndex = false;
            $statusColIndex = false;
            
            foreach ($allHeaders as $idx => $header) {
                $headerLower = strtolower(trim($header));
                if (in_array($headerLower, ['email', 'email address'])) {
                    $emailColIndex = $idx;
                }
                $headerNorm = strtolower(trim(preg_replace('/\s+/', ' ', $header)));
                if ($headerNorm === 'status' || strpos($headerNorm, 'status') !== false) {
                    $statusColIndex = $idx;
                }
            }
            
            // If status column doesn't exist, create it
            if ($statusColIndex === false) {
                $lastColIndex = count($allHeaders);
                $colNum = $lastColIndex + 1;
                $columnLetter = '';
                while ($colNum > 0) {
                    $colNum--;
                    $columnLetter = chr(65 + ($colNum % 26)) . $columnLetter;
                    $colNum = intval($colNum / 26);
                }
                
                // Add status header
                $headerRange = "'Form responses 1'!" . $columnLetter . "1";
                $updateValues = [['Status']];
                $body = new Google_Service_Sheets_ValueRange(['values' => $updateValues]);
                $params = ['valueInputOption' => 'RAW'];
                $service->spreadsheets_values->update($spreadsheetId, $headerRange, $body, $params);
                
                $statusColIndex = $lastColIndex;
                // Re-read to get updated headers
                $response = $service->spreadsheets_values->get($spreadsheetId, $range);
                $allValues = $response->getValues();
                $allHeaders = array_map('trim', $allValues[0]);
            }
            
            // Helper: Convert column index to letter
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
            
            // Process each database entry
            while ($dbRow = $dbVerifiedQuery->fetch_assoc()) {
                $dbEmail = strtolower(trim($dbRow['email']));
                $foundRowIndex = null;
                
                // Find matching row in Google Sheets by email
                for ($i = 1; $i < count($allValues); $i++) {
                    $row = $allValues[$i];
                    while (count($row) < count($allHeaders)) {
                        $row[] = '';
                    }
                    
                    if ($emailColIndex !== false && isset($row[$emailColIndex])) {
                        $sheetEmail = strtolower(trim((string)$row[$emailColIndex]));
                        if ($sheetEmail === $dbEmail) {
                            $foundRowIndex = $i + 1; // 1-based row number
                            break;
                        }
                    }
                }
                
                if ($foundRowIndex) {
                    // Check current status
                    $currentRow = $allValues[$foundRowIndex - 1];
                    while (count($currentRow) < count($allHeaders)) {
                        $currentRow[] = '';
                    }
                    
                    $currentStatus = '';
                    if ($statusColIndex !== false && isset($currentRow[$statusColIndex])) {
                        $currentStatus = trim(strtolower((string)$currentRow[$statusColIndex]));
                    }
                    
                    // Update status to "approved" if not already approved
                    if (!in_array($currentStatus, ['approved', 'approve'])) {
                        $columnLetter = columnIndexToLetter($statusColIndex);
                        $updateRange = "'Form responses 1'!" . $columnLetter . $foundRowIndex;
                        $updateValues = [['approved']];
                        $body = new Google_Service_Sheets_ValueRange(['values' => $updateValues]);
                        $params = ['valueInputOption' => 'RAW'];
                        
                        try {
                            $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);
                            $synced++;
                        } catch (Exception $updateError) {
                            $errors[] = "Failed to update row {$foundRowIndex} for {$dbRow['email']}: " . $updateError->getMessage();
                        }
                    }
                } else {
                    // Entry not found in Google Sheets
                    $notFound[] = $dbRow['email'] . ' (' . $dbRow['full_name'] . ')';
                }
            }
        }
    }
    
    // Set success message
    $message = "Sync completed. ";
    if ($synced > 0) {
        $message .= "Updated {$synced} entry/entries to 'approved' status in Google Sheets. ";
    }
    if (!empty($notFound)) {
        $message .= count($notFound) . " database entry/entries not found in Google Sheets: " . implode(', ', array_slice($notFound, 0, 5));
        if (count($notFound) > 5) {
            $message .= " and " . (count($notFound) - 5) . " more.";
        }
    }
    if (!empty($errors)) {
        $message .= " Errors: " . implode('; ', array_slice($errors, 0, 3));
    }
    
    $_SESSION['sync_success'] = $message;
    
} catch (Exception $e) {
    $_SESSION['sync_error'] = "Sync failed: " . htmlspecialchars($e->getMessage());
}

// Clear sync issues from session
unset($_SESSION['status_sync_issues']);

header("Location: ../dashboard.php");
exit();

