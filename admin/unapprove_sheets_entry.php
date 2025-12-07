<?php
session_start();
require_once __DIR__ . '/../connect.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Load helper functions
require_once __DIR__ . '/sheets_helper.php';

$rowNumber = isset($_POST['row_index']) ? intval($_POST['row_index']) : 0;
if ($rowNumber < 2) {
    $_SESSION['approve_error'] = "Invalid row number.";
    header("Location: ../dashboard.php");
    exit();
}

// Configuration
$credentialsPath = __DIR__ . '/../credentials/alumni-service.json';
$spreadsheetId = '1y2BTTfKBrokY4syfNL9qHbX0gTIsnopAznnanpRlPr4';
$sheetName = 'Form responses 1';

// Load Google Sheets API
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    $_SESSION['approve_error'] = "Composer dependencies not installed.";
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
    
    // Get headers to find status column
    $headerRange = "'$sheetName'!A1:AZ1";
    $headerResponse = $service->spreadsheets_values->get($spreadsheetId, $headerRange);
    $headers = array_map('trim', $headerResponse->getValues()[0]);
    
    // Find status column index using helper function
    $statusColIndex = getColumnIndex($headers, ['Status', 'status']);
    
    // If no status column, create one (add it as last column)
    if ($statusColIndex === false) {
        $lastColIndex = count($headers);
        $columnLetter = columnIndexToLetter($lastColIndex);
        
        // Add header
        $headerRange = "'$sheetName'!" . $columnLetter . "1";
        $updateValues = [['Status']];
        $body = new Google_Service_Sheets_ValueRange(['values' => $updateValues]);
        $params = ['valueInputOption' => 'RAW'];
        $service->spreadsheets_values->update($spreadsheetId, $headerRange, $body, $params);
        
        $statusColIndex = $lastColIndex;
    }
    
    // Get the row data to find email for database update
    $rowRange = "'$sheetName'!A{$rowNumber}:AZ{$rowNumber}";
    $rowResponse = $service->spreadsheets_values->get($spreadsheetId, $rowRange);
    $rowData = isset($rowResponse->getValues()[0]) ? $rowResponse->getValues()[0] : [];
    
    // Ensure row data has same length as headers
    while (count($rowData) < count($headers)) {
        $rowData[] = '';
    }
    
    // Get email to find database entry
    $email = mapValue($headers, $rowData, ['Email Address', 'email address', 'email']);
    
    // Update status to empty (null) so it shows in pending
    $columnLetter = columnIndexToLetter($statusColIndex);
    $updateRange = "'$sheetName'!" . $columnLetter . $rowNumber;
    $updateValues = [['']]; // Empty string to clear status
    $body = new Google_Service_Sheets_ValueRange(['values' => $updateValues]);
    $params = ['valueInputOption' => 'RAW'];
    $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);
    
    // Also update database if entry exists - set verified = 0
    if (!empty($email)) {
        $email = trim($email);
        $stmt = $conn->prepare("UPDATE alumni_basic SET verified = 0 WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->close();
    }
    
    $_SESSION['approve_success'] = "Entry moved back to pending successfully. The entry will now appear in the pending verification section.";
    
    // Clear cached sheet data to force fresh reload
    unset($_SESSION['sheets_rows'], $_SESSION['sheets_headers']);
    
} catch (Exception $e) {
    $_SESSION['approve_error'] = "Error unapproving entry: " . htmlspecialchars($e->getMessage());
    error_log("Unapprove error: " . $e->getMessage());
}

header("Location: ../dashboard.php");
exit();
?>

