<?php
session_start();
require_once __DIR__ . '/../connect.php';
if (!isset($_SESSION['admin_id'])) header("Location: ../login.php");

// Load helper functions
require_once __DIR__ . '/sheets_helper.php';

$rowNumber = isset($_GET['row']) ? intval($_GET['row']) : (isset($_POST['row_number']) ? intval($_POST['row_number']) : 0);
if ($rowNumber < 2) {
    header("Location: ../dashboard.php");
    exit();
}

// Configuration
$credentialsPath = __DIR__ . '/../credentials/alumni-service.json';
$spreadsheetId = '1y2BTTfKBrokY4syfNL9qHbX0gTIsnopAznnanpRlPr4';
$sheetName = 'Form responses 1';

$error = '';
$success = '';
$rowData = [];
$headers = [];

// Load Google Sheets API
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    $error = "Composer dependencies not installed.";
} else {
    require_once $vendorAutoload;
    
    try {
        $client = new Google_Client();
        $client->setApplicationName('TechVyom Alumni Management');
        $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
        $client->setAuthConfig($credentialsPath);
        $client->setAccessType('offline');
        
        $service = new Google_Service_Sheets($client);
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_sheet'])) {
            $updatedValues = [];
            $columnLetters = [];
            
            // Get headers first - use larger range to get all columns
            $headerRange = "'Form responses 1'!A1:AZ1";
            $headerResponse = $service->spreadsheets_values->get($spreadsheetId, $headerRange);
            $headers = array_map('trim', $headerResponse->getValues()[0]);
            
            // Prepare updates for each column
            foreach ($headers as $colIndex => $header) {
                if (!empty(trim($header))) {
                    $fieldName = 'field_' . $colIndex;
                    $newValue = isset($_POST[$fieldName]) ? trim($_POST[$fieldName]) : '';
                    
                    // Convert column index to letter using helper function
                    $columnLetter = columnIndexToLetter($colIndex);
                    
                    $updateRange = "'Form responses 1'!" . $columnLetter . $rowNumber;
                    $updateValues = [[$newValue]];
                    $body = new Google_Service_Sheets_ValueRange(['values' => $updateValues]);
                    $params = ['valueInputOption' => 'RAW'];
                    $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);
                }
            }
            
            $success = "Entry updated successfully in Google Sheet!";
        }
        
        // Read current row data - use larger range to get all columns
        $range = "'Form responses 1'!A1:AZ1000";
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();
        
        if (!empty($values) && isset($values[$rowNumber - 1])) {
            $headers = array_map('trim', $values[0]);
            $rowData = $values[$rowNumber - 1];
            
            // Ensure row has same length as headers
            while (count($rowData) < count($headers)) {
                $rowData[] = '';
            }
        } else {
            $error = "Row not found in Google Sheet.";
        }
    } catch (Exception $e) {
        $error = 'Error: ' . htmlspecialchars($e->getMessage());
    }
}

// Find name for display
$name = 'N/A';
foreach ($headers as $idx => $header) {
    $headerLower = strtolower(trim($header));
    if (in_array($headerLower, ['name', 'full name', 'fullname']) && isset($rowData[$idx])) {
        $name = htmlspecialchars($rowData[$idx]);
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Google Sheets Entry | TechVyom Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f6ff;
            color: #333;
            line-height: 1.6;
        }
        .admin-header {
            background: linear-gradient(135deg, #2d0a5e 0%, #5a1a9e 35%, #6a2aae 50%, #5a1a9e 65%, #2d0a5e 100%);
            color: white;
            padding: 8px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .header-title h1 { font-size: 18px; font-weight: 600; }
        .header-btn {
            padding: 6px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
        }
        .header-btn:hover { background: rgba(255, 255, 255, 0.2); }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-input, .form-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s ease;
        }
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: #9333ea;
            box-shadow: 0 0 0 4px rgba(147, 51, 234, 0.1);
        }
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e5e7eb;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #9333ea, #7c3aed);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(147, 51, 234, 0.3);
        }
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        .btn-secondary:hover {
            background: #d1d5db;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
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
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="header-content">
            <div class="header-title">
                <h1><i class="fas fa-edit"></i> Edit Google Sheets Entry</h1>
            </div>
            <div class="header-actions">
                <a href="../dashboard.php" class="header-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($error) && !empty($headers)): ?>
            <div class="form-card">
                <h2 style="margin-bottom: 30px; color: #111827;">Editing: <?php echo $name; ?></h2>
                
                <form method="POST">
                    <input type="hidden" name="row_number" value="<?php echo $rowNumber; ?>">
                    <input type="hidden" name="update_sheet" value="1">
                    
                    <div class="form-grid">
                        <?php foreach ($headers as $index => $header): ?>
                            <?php if (!empty(trim($header))): ?>
                                <div class="form-group">
                                    <label class="form-label"><?php echo htmlspecialchars($header); ?></label>
                                    <?php 
                                    $value = isset($rowData[$index]) ? htmlspecialchars($rowData[$index]) : '';
                                    // Use textarea for longer fields
                                    $isLongField = in_array(strtolower($header), ['message', 'achievements', 'past experience', 'career help', 'advice']);
                                    ?>
                                    <?php if ($isLongField): ?>
                                        <textarea 
                                            name="field_<?php echo $index; ?>" 
                                            class="form-textarea"
                                            rows="4"
                                        ><?php echo $value; ?></textarea>
                                    <?php else: ?>
                                        <input 
                                            type="text" 
                                            name="field_<?php echo $index; ?>" 
                                            class="form-input"
                                            value="<?php echo $value; ?>"
                                        >
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="../dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

