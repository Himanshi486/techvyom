<?php
session_start();
require_once __DIR__ . '/../connect.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get row number (Google Sheets) or ID (database) from URL
$rowNumber = isset($_GET['row']) ? intval($_GET['row']) : 0;
$alumniId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$fromDatabase = ($alumniId > 0);

if (!$fromDatabase && $rowNumber < 2) {
    header("Location: ../dashboard.php");
    exit();
}

if ($fromDatabase && $alumniId <= 0) {
    header("Location: ../dashboard.php");
    exit();
}

$error = '';
$allFields = [];
$name = 'N/A';
$email = 'N/A';

// Handle database entries (using ID)
if ($fromDatabase) {
    // Fetch from database - same logic as view_alumni.php
    $stmt = $conn->prepare("SELECT * FROM alumni_basic WHERE id = ?");
    $stmt->bind_param('i', $alumniId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error = "Alumni entry not found.";
    } else {
        $alumni = $result->fetch_assoc();
        
        // Fetch employment data if exists
        $stmt2 = $conn->prepare("SELECT * FROM alumni_employment WHERE alumni_id = ?");
        $stmt2->bind_param("i", $alumniId);
        $stmt2->execute();
        $employment = $stmt2->get_result()->fetch_assoc();
        
        // Fetch education data if exists
        $stmt3 = $conn->prepare("SELECT * FROM alumni_education WHERE alumni_id = ?");
        $stmt3->bind_param("i", $alumniId);
        $stmt3->execute();
        $education = $stmt3->get_result()->fetch_assoc();
        
        // Fetch extras data if exists
        $stmt4 = $conn->prepare("SELECT * FROM alumni_extras WHERE alumni_id = ?");
        $stmt4->bind_param("i", $alumniId);
        $stmt4->execute();
        $extras = $stmt4->get_result()->fetch_assoc();
        
        // Combine all data into a single array with readable field names - same as view_alumni.php
        // Basic Information fields
        $allFields['Timestamp'] = $alumni['timestamp'] ?? '';
        $allFields['Email'] = $alumni['email'] ?? '';
        $allFields['Full Name'] = $alumni['full_name'] ?? '';
        $allFields['Enrollment Number'] = $alumni['enrollment_no'] ?? '';
        $allFields['Course'] = $alumni['course'] ?? '';
        $allFields['Department'] = $alumni['department'] ?? '';
        $allFields['Year of Admission'] = $alumni['year_admission'] ?? '';
        $allFields['Year of Passing / Batch'] = $alumni['year_passing'] ?? '';
        $allFields['Contact Number'] = $alumni['contact_number'] ?? '';
        $allFields['LinkedIn Profile'] = $alumni['linkedin_profile'] ?? '';
        $allFields['College Document'] = $alumni['college_doc_path'] ?? '';
        
        // Education fields
        if ($education) {
            $allFields['Has Higher Education'] = isset($education['has_higher_edu']) && $education['has_higher_edu'] ? 'Yes' : 'No';
            $allFields['Degree Name'] = $education['degree_name'] ?? '';
            $allFields['Education Admission Year'] = $education['year_admission'] ?? '';
            $allFields['Institution Name'] = $education['institution_name'] ?? '';
            $allFields['University Name'] = $education['university_name'] ?? '';
            $allFields['Education Document'] = $education['edu_doc_path'] ?? '';
        }
        
        // Employment fields
        if ($employment) {
            $allFields['Employment Status'] = $employment['employment_status'] ?? '';
            $allFields['Organisation / Company'] = $employment['organisation'] ?? '';
            $allFields['Designation / Role'] = $employment['designation'] ?? '';
            $allFields['Job Location'] = $employment['location'] ?? '';
            $allFields['Experience (Years)'] = $employment['experience_years'] ?? '';
            $allFields['Annual Package'] = $employment['annual_package'] ?? '';
            $allFields['Employment Document'] = $employment['emp_doc_path'] ?? '';
            $allFields['Placed Through College'] = isset($employment['placed_through_spm']) && $employment['placed_through_spm'] ? 'Yes' : 'No';
            $allFields['Placement Company'] = $employment['placement_company'] ?? '';
            $allFields['Placement Role'] = $employment['placement_role'] ?? '';
            $allFields['Placement Salary'] = $employment['placement_salary'] ?? '';
            $allFields['Past Experience'] = $employment['past_experience'] ?? '';
            $allFields['Past Experience Document'] = $employment['past_exp_doc_path'] ?? '';
        }
        
        // Extras fields
        if ($extras) {
            $allFields['Competitive Exam'] = $extras['competitive_exam'] ?? '';
            $allFields['Exam Document'] = $extras['exam_doc_path'] ?? '';
            $allFields['Achievements'] = $extras['achievements'] ?? '';
            $allFields['Achievement Document'] = $extras['achievement_doc_path'] ?? '';
            $allFields['Career Help Text'] = $extras['career_help_text'] ?? '';
            $allFields['Message to Students'] = $extras['message_to_students'] ?? '';
            $allFields['Willing to Mentor'] = isset($extras['willing_to_mentor']) && $extras['willing_to_mentor'] ? 'Yes' : 'No';
        }
        
        $name = $allFields['Full Name'] ?: 'N/A';
        $email = $allFields['Email'] ?: 'N/A';
    }
} else {
    // Handle Google Sheets entries (using row number)
    $credentialsPath = __DIR__ . '/../credentials/alumni-service.json';
    $spreadsheetId = '1y2BTTfKBrokY4syfNL9qHbX0gTIsnopAznnanpRlPr4';
    
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
            
            // Read data from Google Sheets
            $range = "'Form responses 1'!A1:AZ100000";
            $response = $service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues();
            
            if (!empty($values) && isset($values[$rowNumber - 1])) {
                $headers = array_map('trim', $values[0]);
                $rowData = $values[$rowNumber - 1];
                
                // Ensure row has same length as headers
                while (count($rowData) < count($headers)) {
                    $rowData[] = '';
                }
                
                // Convert to allFields array format
                foreach ($headers as $idx => $header) {
                    if (!empty(trim($header))) {
                        $value = isset($rowData[$idx]) ? $rowData[$idx] : '';
                        $allFields[$header] = $value;
                    }
                }
                
                // Find name and email for display
                foreach ($headers as $idx => $header) {
                    $headerLower = strtolower(trim($header));
                    if (in_array($headerLower, ['name', 'full name', 'fullname']) && isset($rowData[$idx])) {
                        $name = htmlspecialchars($rowData[$idx]);
                    }
                    if (in_array($headerLower, ['email', 'email address']) && isset($rowData[$idx])) {
                        $email = htmlspecialchars($rowData[$idx]);
                    }
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Verified Alumni Details | TechVyom Admin</title>
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
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="header-content">
            <div class="header-title">
                <h1><i class="fas fa-eye"></i> View Verified Alumni Details</h1>
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
                    <h1 class="alumni-name"><?php echo htmlspecialchars($name); ?></h1>
                    <div style="color: #6b7280;">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($email); ?>
                    </div>
                </div>

                <div class="detail-grid">
                    <?php foreach ($allFields as $fieldName => $fieldValue): ?>
                        <?php if (!empty(trim($fieldName))): ?>
                            <div class="detail-item">
                                <div class="detail-label"><?php echo htmlspecialchars($fieldName); ?></div>
                                <div class="detail-value <?php echo ($fieldValue === null || $fieldValue === '') ? 'empty' : ''; ?>">
                                    <?php 
                                    $value = $fieldValue;
                                    // Check if value is truly empty (null, empty string, but allow 0 and '0')
                                    if ($value === null || $value === '') {
                                        echo 'Not provided';
                                    } else {
                                        // Check if it's a URL or Google Drive link
                                        $isUrl = is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
                                        $isDriveLink = is_string($value) && (stripos($value, 'drive.google.com') !== false || 
                                                                            stripos($value, 'docs.google.com') !== false);
                                        
                                        if ($isDriveLink) {
                                            // Serve document directly using service account - documents accessible to anyone with sheet access
                                            $fieldId = 'doc-' . md5($fieldName . $value);
                                            $isDocumentField = stripos(strtolower($fieldName), 'document') !== false || 
                                                               stripos(strtolower($fieldName), 'attach') !== false ||
                                                               stripos(strtolower($fieldName), 'file') !== false;
                                            ?>
                                            <div class="document-viewer-wrapper">
                                                <?php if (!$isDocumentField): ?>
                                                    <div style="margin-bottom: 8px; color: #6b7280; font-size: 13px;">
                                                        <?php echo htmlspecialchars($value); ?>
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
                                        } elseif ($isUrl) {
                                            echo '<a href="' . htmlspecialchars($value) . '" target="_blank" style="color: #9333ea; text-decoration: underline;">' . htmlspecialchars($value) . ' <i class="fas fa-external-link-alt"></i></a>';
                                        } else {
                                            // Display the value - handle both strings and numbers
                                            $displayValue = (string)$value;
                                            echo nl2br(htmlspecialchars($displayValue));
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
