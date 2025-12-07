<?php
session_start();
include 'connect.php';
if (!isset($_SESSION['admin_id'])) header("Location: login.php");

// Get alumni ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    header("Location: dashboard.php");
    exit();
}

// Fetch alumni basic data
$stmt = $conn->prepare("SELECT * FROM alumni_basic WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: dashboard.php?error=alumni_not_found");
    exit();
}

$alumni = $result->fetch_assoc();

// Fetch employment data if exists
$stmt2 = $conn->prepare("SELECT * FROM alumni_employment WHERE alumni_id = ?");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$employment = $stmt2->get_result()->fetch_assoc();

// Fetch education data if exists
$stmt3 = $conn->prepare("SELECT * FROM alumni_education WHERE alumni_id = ?");
$stmt3->bind_param("i", $id);
$stmt3->execute();
$education = $stmt3->get_result()->fetch_assoc();

// Fetch extras data if exists
$stmt4 = $conn->prepare("SELECT * FROM alumni_extras WHERE alumni_id = ?");
$stmt4->bind_param("i", $id);
$stmt4->execute();
$extras = $stmt4->get_result()->fetch_assoc();

// Combine all data
$data = array_merge($alumni, $employment ?: [], $education ?: [], $extras ?: []);

// Combine all data into a single array with readable field names
$allFields = [];

// Basic Information fields - preserve actual values
$allFields['Timestamp'] = isset($alumni['timestamp']) ? $alumni['timestamp'] : '';
$allFields['Email'] = isset($alumni['email']) ? $alumni['email'] : '';
$allFields['Full Name'] = isset($alumni['full_name']) ? $alumni['full_name'] : '';
$allFields['Enrollment Number'] = isset($alumni['enrollment_no']) ? $alumni['enrollment_no'] : '';
$allFields['Course'] = isset($alumni['course']) ? $alumni['course'] : '';
$allFields['Department'] = isset($alumni['department']) ? $alumni['department'] : '';
$allFields['Year of Admission'] = isset($alumni['year_admission']) && $alumni['year_admission'] !== null ? (string)$alumni['year_admission'] : '';
$allFields['Year of Passing / Batch'] = isset($alumni['year_passing']) && $alumni['year_passing'] !== null ? (string)$alumni['year_passing'] : '';
$allFields['Contact Number'] = isset($alumni['contact_number']) ? $alumni['contact_number'] : '';
$allFields['LinkedIn Profile'] = isset($alumni['linkedin_profile']) ? $alumni['linkedin_profile'] : '';
$allFields['College Document'] = isset($alumni['college_doc_path']) ? $alumni['college_doc_path'] : '';

// Education fields
if ($education) {
    $allFields['Has Higher Education'] = isset($education['has_higher_edu']) ? ($education['has_higher_edu'] ? 'Yes' : 'No') : '';
    $allFields['Degree Name'] = isset($education['degree_name']) ? $education['degree_name'] : '';
    $allFields['Education Admission Year'] = isset($education['year_admission']) && $education['year_admission'] !== null ? (string)$education['year_admission'] : '';
    $allFields['Institution Name'] = isset($education['institution_name']) ? $education['institution_name'] : '';
    $allFields['University Name'] = isset($education['university_name']) ? $education['university_name'] : '';
    $allFields['Education Document'] = isset($education['edu_doc_path']) ? $education['edu_doc_path'] : '';
}

// Employment fields
if ($employment) {
    $allFields['Employment Status'] = isset($employment['employment_status']) ? $employment['employment_status'] : '';
    $allFields['Organisation / Company'] = isset($employment['organisation']) ? $employment['organisation'] : '';
    $allFields['Designation / Role'] = isset($employment['designation']) ? $employment['designation'] : '';
    $allFields['Job Location'] = isset($employment['location']) ? $employment['location'] : '';
    $allFields['Experience (Years)'] = isset($employment['experience_years']) && $employment['experience_years'] !== null ? (string)$employment['experience_years'] : '';
    $allFields['Annual Package'] = isset($employment['annual_package']) ? $employment['annual_package'] : '';
    $allFields['Employment Document'] = isset($employment['emp_doc_path']) ? $employment['emp_doc_path'] : '';
    $allFields['Placed Through College'] = isset($employment['placed_through_spm']) ? ($employment['placed_through_spm'] ? 'Yes' : 'No') : '';
    $allFields['Placement Company'] = isset($employment['placement_company']) ? $employment['placement_company'] : '';
    $allFields['Placement Role'] = isset($employment['placement_role']) ? $employment['placement_role'] : '';
    $allFields['Placement Salary'] = isset($employment['placement_salary']) ? $employment['placement_salary'] : '';
    $allFields['Past Experience'] = isset($employment['past_experience']) ? $employment['past_experience'] : '';
    $allFields['Past Experience Document'] = isset($employment['past_exp_doc_path']) ? $employment['past_exp_doc_path'] : '';
}

// Extras fields
if ($extras) {
    $allFields['Competitive Exam'] = isset($extras['competitive_exam']) ? $extras['competitive_exam'] : '';
    $allFields['Exam Document'] = isset($extras['exam_doc_path']) ? $extras['exam_doc_path'] : '';
    $allFields['Achievements'] = isset($extras['achievements']) ? $extras['achievements'] : '';
    $allFields['Achievement Document'] = isset($extras['achievement_doc_path']) ? $extras['achievement_doc_path'] : '';
    $allFields['Career Help Text'] = isset($extras['career_help_text']) ? $extras['career_help_text'] : '';
    $allFields['Message to Students'] = isset($extras['message_to_students']) ? $extras['message_to_students'] : '';
    $allFields['Willing to Mentor'] = isset($extras['willing_to_mentor']) ? ($extras['willing_to_mentor'] ? 'Yes' : 'No') : '';
}

// Get name and email for display
$name = $allFields['Full Name'] ?: 'N/A';
$email = $allFields['Email'] ?: 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Verified Alumni Details | TechVyom</title>
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
            padding: 10px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
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

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .header-btn {
            padding: 6px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }

        /* Alumni Card */
        .alumni-detail-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            background: linear-gradient(135deg, #9333ea, #7c3aed);
            color: white;
            padding: 30px;
        }

        .alumni-name {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .alumni-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 16px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 15px;
        }

        .badge-verified {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .card-body {
            padding: 30px;
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

        /* Action Section */
        .action-section {
            background: #f3f4f6;
            padding: 25px 30px;
            border-top: 1px solid #e5e7eb;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }

        .btn-edit {
            background: #9333ea;
            color: white;
        }

        .btn-edit:hover {
            background: #7c3aed;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(147, 51, 234, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .alumni-name {
                font-size: 26px;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <div class="header-content">
            <div class="header-title">
                <h1><i class="fas fa-user-graduate"></i> Verified Alumni Details</h1>
            </div>
            <div class="header-actions">
                <a href="dashboard.php" class="header-btn btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <!-- Alumni Detail Card -->
        <div class="alumni-detail-card">
            <!-- Card Header -->
            <div class="card-header">
                <h1 class="alumni-name"><?= htmlspecialchars($name) ?></h1>
                <div class="alumni-meta">
                    <div class="meta-item">
                        <i class="fas fa-envelope"></i>
                        <?= htmlspecialchars($email) ?>
                    </div>
                    <?php if (!empty($allFields['Course'])): ?>
                    <div class="meta-item">
                        <i class="fas fa-graduation-cap"></i>
                        <?= htmlspecialchars($allFields['Course']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($allFields['Year of Passing / Batch'])): ?>
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        Class of <?= htmlspecialchars($allFields['Year of Passing / Batch']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <span class="status-badge badge-verified">
                    <i class="fas fa-check-circle"></i>
                    Verified
                </span>
            </div>

            <!-- Card Body -->
            <div class="card-body">
                <div class="detail-grid">
                    <?php foreach ($allFields as $fieldName => $fieldValue): ?>
                        <div class="detail-item">
                            <div class="detail-label"><?= htmlspecialchars($fieldName) ?></div>
                            <div class="detail-value <?= (empty($fieldValue) && $fieldValue !== 0 && $fieldValue !== '0') ? 'empty' : '' ?>">
                                <?php 
                                // Don't treat 0 as empty for display purposes - show actual value
                                $isEmpty = ($fieldValue === null || $fieldValue === '' || (is_string($fieldValue) && trim($fieldValue) === ''));
                                if ($isEmpty) {
                                    echo 'Not provided';
                                } else {
                                    // Check if it's a URL
                                    if (filter_var($fieldValue, FILTER_VALIDATE_URL)) {
                                        echo '<a href="' . htmlspecialchars($fieldValue) . '" target="_blank" style="color: #9333ea;">' . htmlspecialchars($fieldValue) . ' <i class="fas fa-external-link-alt"></i></a>';
                                    } else {
                                        // Check if it's a document path
                                        if (strpos($fieldValue, '.pdf') !== false || strpos($fieldValue, '.doc') !== false || strpos($fieldValue, '.jpg') !== false || strpos($fieldValue, '.png') !== false) {
                                            echo htmlspecialchars($fieldValue);
                                        } else {
                                            // For text fields, preserve line breaks
                                            echo nl2br(htmlspecialchars($fieldValue));
                                        }
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Action Section -->
            <div class="action-section">
                <div class="action-buttons">
                    <a href="edit_verified.php?id=<?= $id ?>" class="action-btn btn-edit">
                        <i class="fas fa-edit"></i>
                        Edit Alumni
                    </a>
                    <a href="dashboard.php" class="action-btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
