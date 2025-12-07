<?php
session_start();
require_once __DIR__ . '/../connect.php';
if (!isset($_SESSION['admin_id'])) header("Location: ../login.php");

// Load helper functions
require_once __DIR__ . '/sheets_helper.php';

// Get row number from URL
$rowNumber = isset($_GET['row']) ? intval($_GET['row']) : 0;
if ($rowNumber < 2) {
    header("Location: ../dashboard.php");
    exit();
}

// Configuration
$credentialsPath = __DIR__ . '/../credentials/alumni-service.json';
$spreadsheetId = '1y2BTTfKBrokY4syfNL9qHbX0gTIsnopAznnanpRlPr4';
$sheetName = 'Form responses 1';

$error = '';
$rowData = [];
$headers = [];

// Load Google Sheets API
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
    
    try {
        $client = new Google_Client();
        $client->setApplicationName('TechVyom Alumni Management');
        $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
        $client->setAuthConfig($credentialsPath);
        $client->setAccessType('offline');
        
        $service = new Google_Service_Sheets($client);
        
        // Read data from Google Sheets - use larger range to get all columns
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
} else {
    $error = "Composer dependencies not installed.";
}

// Find name and email for display using helper function
$name = mapValue($headers, $rowData, ['Full Name', 'full name', 'name']);
if (empty($name)) $name = 'N/A';

$email = mapValue($headers, $rowData, ['Email Address', 'email address', 'email']);
if (empty($email)) $email = 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Google Sheets Entry | TechVyom Admin</title>
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

        .header-title h1 {
            font-size: 18px;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            gap: 10px;
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
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .header-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .detail-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .alumni-name {
            font-size: 28px;
            color: #111827;
            margin-bottom: 15px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .detail-label {
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 15px;
            color: #111827;
            word-break: break-word;
        }

        .detail-value.empty {
            color: #9ca3af;
            font-style: italic;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc2626;
        }

        .document-viewer-wrapper {
            margin-top: 10px;
        }

        .btn-view-doc {
            background: #9333ea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s ease;
        }

        .btn-view-doc:hover {
            background: #7c2ed8;
        }

        .document-viewer {
            margin-top: 15px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .document-viewer iframe {
            width: 100%;
            height: 600px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
        }

        .detail-item.full-width {
            grid-column: 1 / -1;
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="header-content">
            <div class="header-title">
                <h1><i class="fas fa-eye"></i> View Google Sheets Entry</h1>
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
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php else: ?>
            <div class="detail-card">
                <div class="card-header">
                    <h1 class="alumni-name"><?php echo $name; ?></h1>
                    <div style="color: #6b7280;">
                        <i class="fas fa-envelope"></i> <?php echo $email; ?>
                    </div>
                </div>

                <div class="detail-grid">
                    <?php foreach ($headers as $index => $header): ?>
                        <?php if (!empty(trim($header))): ?>
                            <div class="detail-item">
                                <div class="detail-label"><?php echo htmlspecialchars($header); ?></div>
                                <div class="detail-value <?php echo empty($rowData[$index]) ? 'empty' : ''; ?>">
                                    <?php 
                                    $value = isset($rowData[$index]) ? trim($rowData[$index]) : '';
                                    if (empty($value)) {
                                        echo 'Not provided';
                                    } else {
                                        $displayValue = htmlspecialchars($value);
                                        // Check if it's a Google Drive link
                                        $isDriveLink = stripos($value, 'drive.google.com') !== false || stripos($value, 'docs.google.com') !== false;
                                        
                                        if ($isDriveLink && filter_var($value, FILTER_VALIDATE_URL)) {
                                            // Serve document directly using service account - no Drive link needed
                                            $fieldId = 'doc-' . md5($header . $index . $rowNumber);
                                            // Check if header indicates it's a document field
                                            $isDocumentField = stripos(strtolower($header), 'document') !== false || 
                                                               stripos(strtolower($header), 'attach') !== false ||
                                                               stripos(strtolower($header), 'file') !== false;
                                            ?>
                                            <div class="document-viewer-wrapper">
                                                <?php if (!$isDocumentField): ?>
                                                    <div style="margin-bottom: 8px; color: #6b7280; font-size: 13px;">
                                                        <?php echo $displayValue; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <button type="button" class="btn-view-doc" onclick="toggleDocumentView('<?php echo $fieldId; ?>', this)">
                                                    <i class="fas fa-file-alt"></i> View Document
                                                </button>
                                                <div id="<?php echo $fieldId; ?>" class="document-viewer" style="display: none;">
                                                    <iframe src="serve_document.php?url=<?php echo urlencode($value); ?>" allow="autoplay" allowfullscreen></iframe>
                                                </div>
                                            </div>
                                            <?php
                                        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
                                            // Regular URL (not Google Drive)
                                            echo '<a href="' . htmlspecialchars($value) . '" target="_blank" style="color: #9333ea; word-break: break-all; text-decoration: underline;">' . $displayValue . ' <i class="fas fa-external-link-alt"></i></a>';
                                        } else {
                                            echo $displayValue;
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleDocumentView(fieldId, button) {
            const viewer = document.getElementById(fieldId);
            if (viewer.style.display === 'none') {
                viewer.style.display = 'block';
                button.innerHTML = '<i class="fas fa-times"></i> Hide Document';
                button.style.background = '#dc2626';
            } else {
                viewer.style.display = 'none';
                button.innerHTML = '<i class="fas fa-file-alt"></i> View Document';
                button.style.background = '#9333ea';
            }
        }
    </script>
</body>
</html>

