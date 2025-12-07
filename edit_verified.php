<?php
session_start();
require_once __DIR__ . '/connect.php';
if (!isset($_SESSION['admin_id'])) header("Location: login.php");

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: dashboard.php");
    exit();
}

function fetchAlumniData(mysqli $conn, int $id): array {
    $data = [
        'basic' => null,
        'education' => null,
        'employment' => null,
        'extras' => null
    ];

    // Fetch basic data
    $stmt = $conn->prepare("SELECT * FROM alumni_basic WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        return $data;
    }
    $data['basic'] = $result->fetch_assoc();

    // Fetch education data
    $stmt = $conn->prepare("SELECT * FROM alumni_education WHERE alumni_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $eduResult = $stmt->get_result();
    if ($eduResult->num_rows > 0) {
        $data['education'] = $eduResult->fetch_assoc();
    }

    // Fetch employment data
    $stmt = $conn->prepare("SELECT * FROM alumni_employment WHERE alumni_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $empResult = $stmt->get_result();
    if ($empResult->num_rows > 0) {
        $data['employment'] = $empResult->fetch_assoc();
    }

    // Fetch extras data
    $stmt = $conn->prepare("SELECT * FROM alumni_extras WHERE alumni_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $extrasResult = $stmt->get_result();
    if ($extrasResult->num_rows > 0) {
        $data['extras'] = $extrasResult->fetch_assoc();
    }

    return $data;
}

try {
    $alumniData = fetchAlumniData($conn, $id);
    if (!$alumniData['basic']) {
        header("Location: dashboard.php");
        exit();
    }
} catch (Exception $e) {
    header("Location: dashboard.php");
    exit();
}

$basic = $alumniData['basic'];
$education = $alumniData['education'] ?? null;
$employment = $alumniData['employment'] ?? null;
$extras = $alumniData['extras'] ?? null;

$error = '';
$success = '';

// Build all fields array with readable names
$allFields = [];

// Basic fields
$allFields['Timestamp'] = $basic['timestamp'] ?? '';
$allFields['Email'] = $basic['email'] ?? '';
$allFields['Full Name'] = $basic['full_name'] ?? '';
$allFields['Enrollment Number'] = $basic['enrollment_no'] ?? '';
$allFields['Course'] = $basic['course'] ?? '';
$allFields['Department'] = $basic['department'] ?? '';
$allFields['Year of Admission'] = $basic['year_admission'] ?? '';
$allFields['Year of Passing / Batch'] = $basic['year_passing'] ?? '';
$allFields['Contact Number'] = $basic['contact_number'] ?? '';
$allFields['LinkedIn Profile'] = $basic['linkedin_profile'] ?? '';
$allFields['College Document'] = $basic['college_doc_path'] ?? '';

// Education fields
if ($education) {
    $allFields['Has Higher Education'] = isset($education['has_higher_edu']) ? ($education['has_higher_edu'] ? 'Yes' : 'No') : '';
    $allFields['Degree Name'] = $education['degree_name'] ?? '';
    $allFields['Education Admission Year'] = $education['year_admission'] ?? '';
    $allFields['Institution Name'] = $education['institution_name'] ?? '';
    $allFields['University Name'] = $education['university_name'] ?? '';
    $allFields['Education Document'] = $education['edu_doc_path'] ?? '';
} else {
    $allFields['Has Higher Education'] = '';
    $allFields['Degree Name'] = '';
    $allFields['Education Admission Year'] = '';
    $allFields['Institution Name'] = '';
    $allFields['University Name'] = '';
    $allFields['Education Document'] = '';
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
    $allFields['Placed Through College'] = isset($employment['placed_through_spm']) ? ($employment['placed_through_spm'] ? 'Yes' : 'No') : '';
    $allFields['Placement Company'] = $employment['placement_company'] ?? '';
    $allFields['Placement Role'] = $employment['placement_role'] ?? '';
    $allFields['Placement Salary'] = $employment['placement_salary'] ?? '';
    $allFields['Past Experience'] = $employment['past_experience'] ?? '';
    $allFields['Past Experience Document'] = $employment['past_exp_doc_path'] ?? '';
} else {
    $allFields['Employment Status'] = '';
    $allFields['Organisation / Company'] = '';
    $allFields['Designation / Role'] = '';
    $allFields['Job Location'] = '';
    $allFields['Experience (Years)'] = '';
    $allFields['Annual Package'] = '';
    $allFields['Employment Document'] = '';
    $allFields['Placed Through College'] = '';
    $allFields['Placement Company'] = '';
    $allFields['Placement Role'] = '';
    $allFields['Placement Salary'] = '';
    $allFields['Past Experience'] = '';
    $allFields['Past Experience Document'] = '';
}

// Extras fields
if ($extras) {
    $allFields['Competitive Exam'] = $extras['competitive_exam'] ?? '';
    $allFields['Exam Document'] = $extras['exam_doc_path'] ?? '';
    $allFields['Achievements'] = $extras['achievements'] ?? '';
    $allFields['Achievement Document'] = $extras['achievement_doc_path'] ?? '';
    $allFields['Career Help Text'] = $extras['career_help_text'] ?? '';
    $allFields['Message to Students'] = $extras['message_to_students'] ?? '';
    $allFields['Willing to Mentor'] = isset($extras['willing_to_mentor']) ? ($extras['willing_to_mentor'] ? 'Yes' : 'No') : '';
} else {
    $allFields['Competitive Exam'] = '';
    $allFields['Exam Document'] = '';
    $allFields['Achievements'] = '';
    $allFields['Achievement Document'] = '';
    $allFields['Career Help Text'] = '';
    $allFields['Message to Students'] = '';
    $allFields['Willing to Mentor'] = '';
}

// Get name for display
$name = $allFields['Full Name'] ?: 'N/A';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_alumni'])) {
    try {
        $conn->begin_transaction();

        // Basic fields
        $fullName = trim($_POST['Full Name'] ?? '');
        $email = trim($_POST['Email'] ?? '');
        $enrollmentNo = trim($_POST['Enrollment Number'] ?? '');
        $course = trim($_POST['Course'] ?? '');
        $department = trim($_POST['Department'] ?? '');
        $yearAdmission = !empty($_POST['Year of Admission']) ? intval($_POST['Year of Admission']) : null;
        $yearPassing = !empty($_POST['Year of Passing / Batch']) ? intval($_POST['Year of Passing / Batch']) : null;
        $contactNumber = trim($_POST['Contact Number'] ?? '');
        $linkedin = trim($_POST['LinkedIn Profile'] ?? '');

        $basicStmt = $conn->prepare("UPDATE alumni_basic SET full_name=?, email=?, enrollment_no=?, course=?, department=?, year_admission=?, year_passing=?, contact_number=?, linkedin_profile=? WHERE id=?");
        $basicStmt->bind_param('sssssiissi', $fullName, $email, $enrollmentNo, $course, $department, $yearAdmission, $yearPassing, $contactNumber, $linkedin, $id);
        $basicStmt->execute();

        // Education fields
        $hasHigher = (trim($_POST['Has Higher Education'] ?? '') === 'Yes') ? 1 : 0;
        $degreeName = trim($_POST['Degree Name'] ?? '');
        $eduYearAdmission = !empty($_POST['Education Admission Year']) ? intval($_POST['Education Admission Year']) : null;
        $instName = trim($_POST['Institution Name'] ?? '');
        $uniName = trim($_POST['University Name'] ?? '');

        // Check if education record exists
        $checkEdu = $conn->prepare("SELECT edu_id FROM alumni_education WHERE alumni_id = ?");
        $checkEdu->bind_param('i', $id);
        $checkEdu->execute();
        $eduExists = $checkEdu->get_result()->num_rows > 0;

        if ($eduExists) {
            $eduStmt = $conn->prepare("UPDATE alumni_education SET has_higher_edu=?, degree_name=?, year_admission=?, institution_name=?, university_name=? WHERE alumni_id=?");
            $eduStmt->bind_param('isissi', $hasHigher, $degreeName, $eduYearAdmission, $instName, $uniName, $id);
            $eduStmt->execute();
        } else {
            $eduStmt = $conn->prepare("INSERT INTO alumni_education (alumni_id, has_higher_edu, degree_name, year_admission, institution_name, university_name, edu_doc_path) VALUES (?, ?, ?, ?, ?, ?, '')");
            $eduStmt->bind_param('iisiss', $id, $hasHigher, $degreeName, $eduYearAdmission, $instName, $uniName);
            $eduStmt->execute();
        }

        // Employment fields
        $empStatus = trim($_POST['Employment Status'] ?? '');
        $org = trim($_POST['Organisation / Company'] ?? '');
        $role = trim($_POST['Designation / Role'] ?? '');
        $jobLoc = trim($_POST['Job Location'] ?? '');
        $expYears = !empty($_POST['Experience (Years)']) ? floatval($_POST['Experience (Years)']) : null;
        $package = trim($_POST['Annual Package'] ?? '');
        $placedThrough = (trim($_POST['Placed Through College'] ?? '') === 'Yes') ? 1 : 0;
        $placementCompany = trim($_POST['Placement Company'] ?? '');
        $placementRole = trim($_POST['Placement Role'] ?? '');
        $placementSalary = trim($_POST['Placement Salary'] ?? '');
        $pastExp = trim($_POST['Past Experience'] ?? '');

        // Check if employment record exists
        $checkEmp = $conn->prepare("SELECT emp_id FROM alumni_employment WHERE alumni_id = ?");
        $checkEmp->bind_param('i', $id);
        $checkEmp->execute();
        $empExists = $checkEmp->get_result()->num_rows > 0;

        if ($empExists) {
            $empStmt = $conn->prepare("UPDATE alumni_employment SET employment_status=?, organisation=?, designation=?, location=?, experience_years=?, annual_package=?, placed_through_spm=?, placement_company=?, placement_role=?, placement_salary=?, past_experience=? WHERE alumni_id=?");
            $empStmt->bind_param('ssssdsissssi', $empStatus, $org, $role, $jobLoc, $expYears, $package, $placedThrough, $placementCompany, $placementRole, $placementSalary, $pastExp, $id);
            $empStmt->execute();
        } else {
            $empStmt = $conn->prepare("INSERT INTO alumni_employment (alumni_id, employment_status, organisation, designation, location, experience_years, annual_package, emp_doc_path, placed_through_spm, placement_company, placement_role, placement_salary, past_experience, past_exp_doc_path) VALUES (?, ?, ?, ?, ?, ?, ?, '', ?, ?, ?, ?, ?, '')");
            $empStmt->bind_param('issssdsisssss', $id, $empStatus, $org, $role, $jobLoc, $expYears, $package, $placedThrough, $placementCompany, $placementRole, $placementSalary, $pastExp);
            $empStmt->execute();
        }

        // Extras fields
        $competitiveExam = trim($_POST['Competitive Exam'] ?? '');
        $achievements = trim($_POST['Achievements'] ?? '');
        $careerHelp = trim($_POST['Career Help Text'] ?? '');
        $message = trim($_POST['Message to Students'] ?? '');
        $willingToMentor = (trim($_POST['Willing to Mentor'] ?? '') === 'Yes') ? 1 : 0;

        // Check if extras record exists
        $checkExtras = $conn->prepare("SELECT extra_id FROM alumni_extras WHERE alumni_id = ?");
        $checkExtras->bind_param('i', $id);
        $checkExtras->execute();
        $extrasExists = $checkExtras->get_result()->num_rows > 0;

        if ($extrasExists) {
            $extrasStmt = $conn->prepare("UPDATE alumni_extras SET competitive_exam=?, achievements=?, career_help_text=?, message_to_students=?, willing_to_mentor=? WHERE alumni_id=?");
            $extrasStmt->bind_param('sssssi', $competitiveExam, $achievements, $careerHelp, $message, $willingToMentor, $id);
            $extrasStmt->execute();
        } else {
            $extrasStmt = $conn->prepare("INSERT INTO alumni_extras (alumni_id, competitive_exam, exam_doc_path, achievements, achievement_doc_path, career_help_text, message_to_students, willing_to_mentor) VALUES (?, ?, '', ?, '', ?, ?, ?)");
            $extrasStmt->bind_param('issssi', $id, $competitiveExam, $achievements, $careerHelp, $message, $willingToMentor);
            $extrasStmt->execute();
        }

        $conn->commit();
        $success = "Alumni record updated successfully!";
        
        // Refresh data
        $alumniData = fetchAlumniData($conn, $id);
        $basic = $alumniData['basic'];
        $education = $alumniData['education'] ?? null;
        $employment = $alumniData['employment'] ?? null;
        $extras = $alumniData['extras'] ?? null;
        
        // Rebuild fields array
        $allFields = [];
        $allFields['Timestamp'] = $basic['timestamp'] ?? '';
        $allFields['Email'] = $basic['email'] ?? '';
        $allFields['Full Name'] = $basic['full_name'] ?? '';
        $allFields['Enrollment Number'] = $basic['enrollment_no'] ?? '';
        $allFields['Course'] = $basic['course'] ?? '';
        $allFields['Department'] = $basic['department'] ?? '';
        $allFields['Year of Admission'] = $basic['year_admission'] ?? '';
        $allFields['Year of Passing / Batch'] = $basic['year_passing'] ?? '';
        $allFields['Contact Number'] = $basic['contact_number'] ?? '';
        $allFields['LinkedIn Profile'] = $basic['linkedin_profile'] ?? '';
        $allFields['College Document'] = $basic['college_doc_path'] ?? '';
        
        if ($education) {
            $allFields['Has Higher Education'] = isset($education['has_higher_edu']) ? ($education['has_higher_edu'] ? 'Yes' : 'No') : '';
            $allFields['Degree Name'] = $education['degree_name'] ?? '';
            $allFields['Education Admission Year'] = $education['year_admission'] ?? '';
            $allFields['Institution Name'] = $education['institution_name'] ?? '';
            $allFields['University Name'] = $education['university_name'] ?? '';
            $allFields['Education Document'] = $education['edu_doc_path'] ?? '';
        } else {
            $allFields['Has Higher Education'] = '';
            $allFields['Degree Name'] = '';
            $allFields['Education Admission Year'] = '';
            $allFields['Institution Name'] = '';
            $allFields['University Name'] = '';
            $allFields['Education Document'] = '';
        }
        
        if ($employment) {
            $allFields['Employment Status'] = $employment['employment_status'] ?? '';
            $allFields['Organisation / Company'] = $employment['organisation'] ?? '';
            $allFields['Designation / Role'] = $employment['designation'] ?? '';
            $allFields['Job Location'] = $employment['location'] ?? '';
            $allFields['Experience (Years)'] = $employment['experience_years'] ?? '';
            $allFields['Annual Package'] = $employment['annual_package'] ?? '';
            $allFields['Employment Document'] = $employment['emp_doc_path'] ?? '';
            $allFields['Placed Through College'] = isset($employment['placed_through_spm']) ? ($employment['placed_through_spm'] ? 'Yes' : 'No') : '';
            $allFields['Placement Company'] = $employment['placement_company'] ?? '';
            $allFields['Placement Role'] = $employment['placement_role'] ?? '';
            $allFields['Placement Salary'] = $employment['placement_salary'] ?? '';
            $allFields['Past Experience'] = $employment['past_experience'] ?? '';
            $allFields['Past Experience Document'] = $employment['past_exp_doc_path'] ?? '';
        } else {
            $allFields['Employment Status'] = '';
            $allFields['Organisation / Company'] = '';
            $allFields['Designation / Role'] = '';
            $allFields['Job Location'] = '';
            $allFields['Experience (Years)'] = '';
            $allFields['Annual Package'] = '';
            $allFields['Employment Document'] = '';
            $allFields['Placed Through College'] = '';
            $allFields['Placement Company'] = '';
            $allFields['Placement Role'] = '';
            $allFields['Placement Salary'] = '';
            $allFields['Past Experience'] = '';
            $allFields['Past Experience Document'] = '';
        }
        
        if ($extras) {
            $allFields['Competitive Exam'] = $extras['competitive_exam'] ?? '';
            $allFields['Exam Document'] = $extras['exam_doc_path'] ?? '';
            $allFields['Achievements'] = $extras['achievements'] ?? '';
            $allFields['Achievement Document'] = $extras['achievement_doc_path'] ?? '';
            $allFields['Career Help Text'] = $extras['career_help_text'] ?? '';
            $allFields['Message to Students'] = $extras['message_to_students'] ?? '';
            $allFields['Willing to Mentor'] = isset($extras['willing_to_mentor']) ? ($extras['willing_to_mentor'] ? 'Yes' : 'No') : '';
        } else {
            $allFields['Competitive Exam'] = '';
            $allFields['Exam Document'] = '';
            $allFields['Achievements'] = '';
            $allFields['Achievement Document'] = '';
            $allFields['Career Help Text'] = '';
            $allFields['Message to Students'] = '';
            $allFields['Willing to Mentor'] = '';
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = 'Error: ' . htmlspecialchars($e->getMessage());
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
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s ease;
        }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
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
                <h1><i class="fas fa-edit"></i> Edit Verified Alumni Record</h1>
            </div>
            <div class="header-actions">
                <a href="view_verified.php?id=<?= $id ?>" class="header-btn">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="dashboard.php" class="header-btn">
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

        <?php if (!empty($allFields)): ?>
            <div class="form-card">
                <h2 style="margin-bottom: 30px; color: #111827;">Editing: <?php echo htmlspecialchars($name); ?></h2>
                
                <form method="POST">
                    <input type="hidden" name="update_alumni" value="1">
                    
                    <div class="form-grid">
                        <?php foreach ($allFields as $fieldName => $fieldValue): ?>
                            <div class="form-group">
                                <label class="form-label"><?php echo htmlspecialchars($fieldName); ?></label>
                                <?php 
                                $value = htmlspecialchars($fieldValue);
                                // Use textarea for longer fields
                                $isLongField = in_array(strtolower($fieldName), ['message to students', 'achievements', 'past experience', 'career help text', 'advice']);
                                // Use select for yes/no fields
                                $isYesNoField = in_array(strtolower($fieldName), ['has higher education', 'placed through college', 'willing to mentor']);
                                ?>
                                <?php if ($isYesNoField): ?>
                                    <select name="<?php echo htmlspecialchars($fieldName); ?>" class="form-select">
                                        <option value="">Select...</option>
                                        <option value="Yes" <?php echo ($value === 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                        <option value="No" <?php echo ($value === 'No' || empty($value)) ? 'selected' : ''; ?>>No</option>
                                    </select>
                                <?php elseif ($isLongField): ?>
                                    <textarea 
                                        name="<?php echo htmlspecialchars($fieldName); ?>" 
                                        class="form-textarea"
                                        rows="4"
                                    ><?php echo $value; ?></textarea>
                                <?php else: ?>
                                    <input 
                                        type="text" 
                                        name="<?php echo htmlspecialchars($fieldName); ?>" 
                                        class="form-input"
                                        value="<?php echo $value; ?>"
                                    >
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

