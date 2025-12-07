<?php
session_start();
require_once __DIR__ . '/../connect.php';
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get row number (Google Sheets) or ID (database) from URL
$rowNumber = isset($_GET['row']) ? intval($_GET['row']) : (isset($_POST['row_number']) ? intval($_POST['row_number']) : 0);
$alumniId = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['alumni_id']) ? intval($_POST['alumni_id']) : 0);
$fromDatabase = ($alumniId > 0);

if (!$fromDatabase && $rowNumber < 2) {
    header("Location: ../dashboard.php");
    exit();
}

if ($fromDatabase && $alumniId <= 0) {
    header("Location: ../dashboard.php");
    exit();
}

// If editing database entry, redirect to edit_alumni.php which already handles database edits
if ($fromDatabase) {
    header("Location: ../edit_alumni.php?id=" . $alumniId);
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

// Helper: Get value from row by header keywords
function getValueByHeader($headers, $row, $keywords) {
    foreach ($headers as $idx => $header) {
        $headerNorm = strtolower(trim(preg_replace('/\s+/', ' ', $header)));
        foreach ($keywords as $kw) {
            $kwNorm = strtolower(trim($kw));
            if ($headerNorm === $kwNorm || strpos($headerNorm, $kwNorm) !== false || strpos($kwNorm, $headerNorm) !== false) {
                return isset($row[$idx]) ? trim((string)$row[$idx]) : null;
            }
        }
    }
    return null;
}

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
        
        // Handle form submission - Update BOTH Google Sheet AND Database
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_verified'])) {
            // Get current row data to get email (needed for database lookup)
            $range = "'Form responses 1'!A1:AZ100000";
            $response = $service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues();
            $currentRowData = isset($values[$rowNumber - 1]) ? $values[$rowNumber - 1] : [];
            $currentHeaders = array_map('trim', $values[0]);
            while (count($currentRowData) < count($currentHeaders)) {
                $currentRowData[] = '';
            }
            
            // Get email from current row (before update) for database lookup
            $email = getValueByHeader($currentHeaders, $currentRowData, ['email', 'email address']);
            
            if (empty($email)) {
                $error = "Email not found. Cannot update database.";
            } else {
                // Start transaction for database updates
                $conn->begin_transaction();
                $dbError = '';
                
                try {
                    // 1. Update Google Sheet first
                    foreach ($currentHeaders as $colIndex => $header) {
                        if (!empty(trim($header))) {
                            $fieldName = 'field_' . $colIndex;
                            $newValue = isset($_POST[$fieldName]) ? trim($_POST[$fieldName]) : '';
                            
                            $columnLetter = columnIndexToLetter($colIndex);
                            $updateRange = "'Form responses 1'!" . $columnLetter . $rowNumber;
                            $body = new Google_Service_Sheets_ValueRange(['values' => [[$newValue]]]);
                            $params = ['valueInputOption' => 'RAW'];
                            $service->spreadsheets_values->update($spreadsheetId, $updateRange, $body, $params);
                        }
                    }
                    
                    // 2. Update Database - find entry by email and update all 4 tables
                    $checkStmt = $conn->prepare("SELECT id FROM alumni_basic WHERE email = ?");
                    $checkStmt->bind_param('s', $email);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    $dbAlumniId = null;
                    
                    if ($checkResult->num_rows > 0) {
                        $dbAlumniId = $checkResult->fetch_assoc()['id'];
                        $checkStmt->close();
                        
                        // Get updated values from form (get from POST with header names)
                        $updatedHeaders = array_map('trim', $values[0]);
                        
                        // Helper function to get form value by header
                        function getFormValueByHeader($headers, $postData, $keywords) {
                            foreach ($headers as $idx => $header) {
                                $headerNorm = strtolower(trim(preg_replace('/\s+/', ' ', $header)));
                                foreach ($keywords as $kw) {
                                    $kwNorm = strtolower(trim($kw));
                                    if ($headerNorm === $kwNorm || strpos($headerNorm, $kwNorm) !== false || strpos($kwNorm, $headerNorm) !== false) {
                                        $fieldName = 'field_' . $idx;
                                        return isset($postData[$fieldName]) ? trim($postData[$fieldName]) : null;
                                    }
                                }
                            }
                            return null;
                        }
                        
                        // Map Google Sheets fields to database fields
                        $fullName = getFormValueByHeader($updatedHeaders, $_POST, ['full name', 'name']);
                        $enroll = getFormValueByHeader($updatedHeaders, $_POST, ['university enrollment', 'enrollment no', 'enrollment']);
                        $course = getFormValueByHeader($updatedHeaders, $_POST, ['course of study', 'course']);
                        $dept = getFormValueByHeader($updatedHeaders, $_POST, ['name of department', 'department']);
                        $yearIn = getFormValueByHeader($updatedHeaders, $_POST, ['year of admission', 'admission year']);
                        $yearOut = getFormValueByHeader($updatedHeaders, $_POST, ['year of passing', 'passing year', 'batch']);
                        $phone = getFormValueByHeader($updatedHeaders, $_POST, ['contact number', 'phone']);
                        $linkedin = getFormValueByHeader($updatedHeaders, $_POST, ['linkedin profile', 'linkedin']);
                        $collegeDoc = getFormValueByHeader($updatedHeaders, $_POST, ['attach supporting document', 'college id', 'college document']);
                        
                        // Update alumni_basic
                        $yearInVal = !empty($yearIn) && is_numeric($yearIn) ? intval($yearIn) : null;
                        $yearOutVal = !empty($yearOut) && is_numeric($yearOut) ? intval($yearOut) : null;
                        $enroll = empty($enroll) ? null : $enroll;
                        $course = empty($course) ? null : $course;
                        $dept = empty($dept) ? null : $dept;
                        $phone = empty($phone) ? null : $phone;
                        $linkedin = empty($linkedin) ? null : $linkedin;
                        $collegeDoc = empty($collegeDoc) ? null : $collegeDoc;
                        
                        $basicStmt = $conn->prepare("UPDATE alumni_basic SET full_name=?, enrollment_no=?, course=?, department=?, year_admission=?, year_passing=?, contact_number=?, linkedin_profile=?, college_doc_path=? WHERE id=?");
                        $basicStmt->bind_param('sssssiissi', $fullName, $enroll, $course, $dept, $yearInVal, $yearOutVal, $phone, $linkedin, $collegeDoc, $dbAlumniId);
                        if (!$basicStmt->execute()) {
                            throw new Exception("Update alumni_basic failed: " . $basicStmt->error);
                        }
                        $basicStmt->close();
                        
                        // Update alumni_education if exists
                        $hasHigher = getFormValueByHeader($updatedHeaders, $_POST, ['have you completed', 'higher education']);
                        $degreeName = getFormValueByHeader($updatedHeaders, $_POST, ['name of degree', 'degree name']);
                        $eduYear = getFormValueByHeader($updatedHeaders, $_POST, ['year of admission', 'education admission year']);
                        $instName = getFormValueByHeader($updatedHeaders, $_POST, ['institution name', 'institution']);
                        $uniName = getFormValueByHeader($updatedHeaders, $_POST, ['university name', 'university']);
                        
                        $hasHigherVal = (!empty($hasHigher) && (strtolower($hasHigher) === 'yes' || $hasHigher === '1')) ? 1 : 0;
                        $eduYearVal = !empty($eduYear) && is_numeric($eduYear) ? intval($eduYear) : null;
                        $degreeName = empty($degreeName) ? null : $degreeName;
                        $instName = empty($instName) ? null : $instName;
                        $uniName = empty($uniName) ? null : $uniName;
                        
                        $checkEdu = $conn->prepare("SELECT edu_id FROM alumni_education WHERE alumni_id = ?");
                        $checkEdu->bind_param('i', $dbAlumniId);
                        $checkEdu->execute();
                        $eduExists = $checkEdu->get_result()->num_rows > 0;
                        $checkEdu->close();
                        
                        if ($eduExists) {
                            $eduStmt = $conn->prepare("UPDATE alumni_education SET has_higher_edu=?, degree_name=?, year_admission=?, institution_name=?, university_name=? WHERE alumni_id=?");
                            $eduStmt->bind_param('iisissi', $hasHigherVal, $degreeName, $eduYearVal, $instName, $uniName, $dbAlumniId);
                        } else {
                            $eduStmt = $conn->prepare("INSERT INTO alumni_education (alumni_id, has_higher_edu, degree_name, year_admission, institution_name, university_name) VALUES (?, ?, ?, ?, ?, ?)");
                            $eduStmt->bind_param('iiisiss', $dbAlumniId, $hasHigherVal, $degreeName, $eduYearVal, $instName, $uniName);
                        }
                        if (!$eduStmt->execute()) {
                            throw new Exception("Update/Insert alumni_education failed: " . $eduStmt->error);
                        }
                        $eduStmt->close();
                        
                        // Update alumni_employment if exists
                        $empStatus = getFormValueByHeader($updatedHeaders, $_POST, ['are you currently working', 'current employment status', 'employment status']);
                        $org = getFormValueByHeader($updatedHeaders, $_POST, ['currently working with organisation', 'organisation', 'company']);
                        $role = getFormValueByHeader($updatedHeaders, $_POST, ['current job title', 'designation', 'role']);
                        $jobLoc = getFormValueByHeader($updatedHeaders, $_POST, ['location of current job', 'location']);
                        $expYears = getFormValueByHeader($updatedHeaders, $_POST, ['work experience', 'experience years']);
                        $package = getFormValueByHeader($updatedHeaders, $_POST, ['current annual package', 'annual package', 'salary']);
                        $placed = getFormValueByHeader($updatedHeaders, $_POST, ['were you placed', 'placed through spm']);
                        $placementCompany = getFormValueByHeader($updatedHeaders, $_POST, ['placement company']);
                        $placementRole = getFormValueByHeader($updatedHeaders, $_POST, ['role/job profile', 'placement role']);
                        $placementSalary = getFormValueByHeader($updatedHeaders, $_POST, ['salary offered', 'placement salary']);
                        $pastExp = getFormValueByHeader($updatedHeaders, $_POST, ['how many organisations', 'past experience']);
                        
                        $expYearsVal = !empty($expYears) && is_numeric($expYears) ? floatval($expYears) : null;
                        $placedVal = (!empty($placed) && (strtolower($placed) === 'yes' || $placed === '1')) ? 1 : 0;
                        if (empty($empStatus)) $empStatus = null;
                        $org = empty($org) ? null : $org;
                        $role = empty($role) ? null : $role;
                        $jobLoc = empty($jobLoc) ? null : $jobLoc;
                        $package = empty($package) ? null : $package;
                        $placementCompany = empty($placementCompany) ? null : $placementCompany;
                        $placementRole = empty($placementRole) ? null : $placementRole;
                        $placementSalary = empty($placementSalary) ? null : $placementSalary;
                        $pastExp = empty($pastExp) ? null : $pastExp;
                        
                        $checkEmp = $conn->prepare("SELECT emp_id FROM alumni_employment WHERE alumni_id = ?");
                        $checkEmp->bind_param('i', $dbAlumniId);
                        $checkEmp->execute();
                        $empExists = $checkEmp->get_result()->num_rows > 0;
                        $checkEmp->close();
                        
                        if ($empExists) {
                            $empStmt = $conn->prepare("UPDATE alumni_employment SET employment_status=?, organisation=?, designation=?, location=?, experience_years=?, annual_package=?, placed_through_spm=?, placement_company=?, placement_role=?, placement_salary=?, past_experience=? WHERE alumni_id=?");
                            $empStmt->bind_param('ssssdsissssi', $empStatus, $org, $role, $jobLoc, $expYearsVal, $package, $placedVal, $placementCompany, $placementRole, $placementSalary, $pastExp, $dbAlumniId);
                        } else {
                            $empStmt = $conn->prepare("INSERT INTO alumni_employment (alumni_id, employment_status, organisation, designation, location, experience_years, annual_package, placed_through_spm, placement_company, placement_role, placement_salary, past_experience) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $empStmt->bind_param('issssdsisssss', $dbAlumniId, $empStatus, $org, $role, $jobLoc, $expYearsVal, $package, $placedVal, $placementCompany, $placementRole, $placementSalary, $pastExp);
                        }
                        if (!$empStmt->execute()) {
                            throw new Exception("Update/Insert alumni_employment failed: " . $empStmt->error);
                        }
                        $empStmt->close();
                        
                        // Update alumni_extras if exists
                        $exam = getFormValueByHeader($updatedHeaders, $_POST, ['competitive exam', 'competitive exam cleared']);
                        $achievements = getFormValueByHeader($updatedHeaders, $_POST, ['other significant achievements', 'achievements']);
                        $careerHelp = getFormValueByHeader($updatedHeaders, $_POST, ['how has your college education helped', 'career help']);
                        $message = getFormValueByHeader($updatedHeaders, $_POST, ['any advice', 'message to students']);
                        $mentor = getFormValueByHeader($updatedHeaders, $_POST, ['are you willing to mentor', 'willing to mentor']);
                        
                        $mentorVal = (!empty($mentor) && (strtolower($mentor) === 'yes' || $mentor === '1')) ? 1 : 0;
                        $exam = empty($exam) ? null : $exam;
                        $achievements = empty($achievements) ? null : $achievements;
                        $careerHelp = empty($careerHelp) ? null : $careerHelp;
                        $message = empty($message) ? null : $message;
                        
                        $checkExtras = $conn->prepare("SELECT extra_id FROM alumni_extras WHERE alumni_id = ?");
                        $checkExtras->bind_param('i', $dbAlumniId);
                        $checkExtras->execute();
                        $extrasExists = $checkExtras->get_result()->num_rows > 0;
                        $checkExtras->close();
                        
                        if ($extrasExists) {
                            $extrasStmt = $conn->prepare("UPDATE alumni_extras SET competitive_exam=?, achievements=?, career_help_text=?, message_to_students=?, willing_to_mentor=? WHERE alumni_id=?");
                            $extrasStmt->bind_param('ssssisi', $exam, $achievements, $careerHelp, $message, $mentorVal, $dbAlumniId);
                        } else {
                            $extrasStmt = $conn->prepare("INSERT INTO alumni_extras (alumni_id, competitive_exam, achievements, career_help_text, message_to_students, willing_to_mentor) VALUES (?, ?, ?, ?, ?, ?)");
                            $extrasStmt->bind_param('isssisi', $dbAlumniId, $exam, $achievements, $careerHelp, $message, $mentorVal);
                        }
                        if (!$extrasStmt->execute()) {
                            throw new Exception("Update/Insert alumni_extras failed: " . $extrasStmt->error);
                        }
                        $extrasStmt->close();
                        
                        // Commit database transaction
                        $conn->commit();
                        $success = "Entry updated successfully in both Google Sheet and database!";
                        
                    } else {
                        // Database entry doesn't exist - only update Google Sheet
                        $checkStmt->close();
                        $conn->rollback();
                        $success = "Entry updated in Google Sheet. Database entry not found (entry may not be approved yet).";
                    }
                    
                } catch (Exception $dbEx) {
                    $conn->rollback();
                    $error = "Database update failed: " . htmlspecialchars($dbEx->getMessage());
                    // Google Sheet was already updated, so show partial success
                    if (empty($success)) {
                        $success = "Entry updated in Google Sheet, but database update failed.";
                    }
                }
            }
        }
        
        // Read current row data from Google Sheets
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
    <title>Edit Verified Alumni | TechVyom Admin</title>
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
                <h1><i class="fas fa-edit"></i> Edit Verified Alumni</h1>
            </div>
            <div class="header-actions">
                <a href="view_verified.php?row=<?php echo $rowNumber; ?>" class="header-btn">
                    <i class="fas fa-eye"></i> View
                </a>
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
                <p style="color: #6b7280; margin-bottom: 20px;">Changes will be saved to both Google Sheet and database.</p>
                
                <form method="POST">
                    <input type="hidden" name="row_number" value="<?php echo $rowNumber; ?>">
                    <input type="hidden" name="update_verified" value="1">
                    
                    <div class="form-grid">
                        <?php foreach ($headers as $index => $header): ?>
                            <?php if (!empty(trim($header))): ?>
                                <div class="form-group">
                                    <label class="form-label"><?php echo htmlspecialchars($header); ?></label>
                                    <?php 
                                    $value = isset($rowData[$index]) ? htmlspecialchars($rowData[$index]) : '';
                                    // Use textarea for longer fields
                                    $isLongField = in_array(strtolower($header), ['message', 'achievements', 'past experience', 'career help', 'advice', 'how has your college education helped']);
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
                            <i class="fas fa-save"></i> Save Changes (Google Sheet + Database)
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
