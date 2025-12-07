<?php
session_start();
include 'connect.php';

// Load helper functions for better column mapping and formatting
require_once __DIR__ . '/admin/sheets_helper.php';
require_once __DIR__ . '/format_helpers.php';

$higherStudies = [];

// Fetch higher studies alumni data from Google Sheets - only approved entries with higher education
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
    
    $credentialsPath = __DIR__ . '/credentials/alumni-service.json';
    $spreadsheetId = '1y2BTTfKBrokY4syfNL9qHbX0gTIsnopAznnanpRlPr4';
    $sheetName = 'Form responses 1';
    
    try {
        if (file_exists($credentialsPath)) {
            $client = new Google_Client();
            $client->setApplicationName('TechVyom Alumni Management');
            $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
            $client->setAuthConfig($credentialsPath);
            $client->setAccessType('offline');
            
            $service = new Google_Service_Sheets($client);
            
            // Read ALL data from Google Sheets
            $range = "'Form responses 1'!A1:AZ100000";
            try {
                $response = $service->spreadsheets_values->get($spreadsheetId, $range);
                $values = $response->getValues();
            } catch (Exception $rangeError) {
                $range = "'Form responses 1'!A:Z";
                $response = $service->spreadsheets_values->get($spreadsheetId, $range);
                $values = $response->getValues();
            }
            
            if (!empty($values) && count($values) > 1) {
                $headers = array_map('trim', $values[0]);
                
                // Find column indices
                $statusColIndex = false;
                $higherEduColIndex = false;
                $nameColIndex = false;
                $emailColIndex = false;
                $degreeColIndex = false;
                $institutionColIndex = false;
                $universityColIndex = false;
                $linkedinColIndex = false;
                $yearAdmissionColIndex = false;
                $yearPassingColIndex = false;
                $eduYearAdmissionColIndex = false;
                
                foreach ($headers as $idx => $header) {
                    $headerLower = strtolower(trim($header));
                    $headerTrimmed = trim($header);
                    
                    if ($headerLower === 'status' || strpos($headerLower, 'status') !== false) {
                        $statusColIndex = $idx;
                    }
                    if (stripos($headerLower, 'have you completed or are you currently pursuing any higher education') !== false ||
                        stripos($headerLower, 'higher education') !== false ||
                        stripos($headerLower, 'pursuing any higher education') !== false) {
                        $higherEduColIndex = $idx;
                    }
                    if (stripos($headerLower, 'full name') !== false || $headerLower === 'name') {
                        $nameColIndex = $idx;
                    }
                    if (stripos($headerLower, 'email') !== false) {
                        $emailColIndex = $idx;
                    }
                    // Look specifically for "Name of degree" - be very precise to avoid file upload columns
                    if ($degreeColIndex === false) {
                        if (stripos($headerTrimmed, 'Name of degree') !== false ||
                            stripos($headerTrimmed, 'Name of degree (Pursuing/Completed)') !== false) {
                            // Make sure it's NOT a file upload column
                            if (stripos($headerLower, 'attach') === false &&
                                stripos($headerLower, 'supporting document') === false &&
                                stripos($headerLower, 'document') === false &&
                                stripos($headerLower, 'pdf') === false &&
                                stripos($headerLower, 'jpg') === false &&
                                stripos($headerLower, 'png') === false &&
                                stripos($headerLower, 'accepted formats') === false) {
                                $degreeColIndex = $idx;
                            }
                        }
                    }
                    if (stripos($headerLower, 'institution name') !== false) {
                        $institutionColIndex = $idx;
                    }
                    if (stripos($headerLower, 'university name') !== false) {
                        $universityColIndex = $idx;
                    }
                    if (stripos($headerLower, 'linkedin') !== false) {
                        $linkedinColIndex = $idx;
                    }
                    if (stripos($headerLower, 'year of admission') !== false && stripos($headerLower, 'spm') !== false) {
                        $yearAdmissionColIndex = $idx;
                    }
                    if (stripos($headerLower, 'year of passing') !== false && stripos($headerLower, 'spm') !== false) {
                        $yearPassingColIndex = $idx;
                    }
                    if (stripos($headerLower, 'year of admission') !== false && stripos($headerLower, 'spm') === false) {
                        $eduYearAdmissionColIndex = $idx;
                    }
                }
                
                // Process rows - only approved entries with higher education
                for ($i = 1; $i < count($values); $i++) {
                    $row = $values[$i];
                    
                    // Skip empty rows
                    if (empty(array_filter($row, function($cell) {
                        return trim((string)$cell) !== '';
                    }))) {
                        continue;
                    }
                    
                    // Ensure row has same length as headers
                    while (count($row) < count($headers)) {
                        $row[] = '';
                    }
                    
                    // Check if approved
                    $isApproved = false;
                    if ($statusColIndex !== false && isset($row[$statusColIndex])) {
                        $statusRaw = trim(strtolower((string)$row[$statusColIndex]));
                        if (preg_match('/^approved|^approve/i', $statusRaw)) {
                            $isApproved = true;
                        }
                    }
                    
                    if (!$isApproved) {
                        continue; // Skip non-approved entries
                    }
                    
                    // Check if has higher education
                    $hasHigherEdu = false;
                    if ($higherEduColIndex !== false && isset($row[$higherEduColIndex])) {
                        $higherEduValue = trim(strtolower((string)$row[$higherEduColIndex]));
                        if (stripos($higherEduValue, 'yes') !== false || $higherEduValue === '1') {
                            $hasHigherEdu = true;
                        }
                    }
                    
                    // Also check if they have degree/institution data (fallback)
                    if (!$hasHigherEdu) {
                        $degree = ($degreeColIndex !== false && isset($row[$degreeColIndex])) ? trim($row[$degreeColIndex]) : '';
                        $institution = ($institutionColIndex !== false && isset($row[$institutionColIndex])) ? trim($row[$institutionColIndex]) : '';
                        $university = ($universityColIndex !== false && isset($row[$universityColIndex])) ? trim($row[$universityColIndex]) : '';
                        
                        if (!empty($degree) || !empty($institution) || !empty($university)) {
                            $hasHigherEdu = true;
                        }
                    }
                    
                    if (!$hasHigherEdu) {
                        continue; // Skip entries without higher education
                    }
                    
                    // Extract data
                    $name = ($nameColIndex !== false && isset($row[$nameColIndex])) ? trim($row[$nameColIndex]) : '';
                    $email = ($emailColIndex !== false && isset($row[$emailColIndex])) ? trim($row[$emailColIndex]) : '';
                    $degree = ($degreeColIndex !== false && isset($row[$degreeColIndex])) ? trim($row[$degreeColIndex]) : '';
                    
                    // Filter out Google Drive links or URLs from degree - should only be text
                    if (!empty($degree)) {
                        // Check if it's a URL/link
                        if (filter_var($degree, FILTER_VALIDATE_URL) !== false ||
                            stripos($degree, 'drive.google.com') !== false ||
                            stripos($degree, 'http://') !== false ||
                            stripos($degree, 'https://') !== false) {
                            $degree = ''; // Clear if it's a link
                        }
                    }
                    
                    $institution = ($institutionColIndex !== false && isset($row[$institutionColIndex])) ? trim($row[$institutionColIndex]) : '';
                    $university = ($universityColIndex !== false && isset($row[$universityColIndex])) ? trim($row[$universityColIndex]) : '';
                    $linkedin = ($linkedinColIndex !== false && isset($row[$linkedinColIndex])) ? trim($row[$linkedinColIndex]) : '';
                    $yearAdmission = ($yearAdmissionColIndex !== false && isset($row[$yearAdmissionColIndex])) ? trim($row[$yearAdmissionColIndex]) : '';
                    $yearPassing = ($yearPassingColIndex !== false && isset($row[$yearPassingColIndex])) ? trim($row[$yearPassingColIndex]) : '';
                    $eduYearAdmission = ($eduYearAdmissionColIndex !== false && isset($row[$eduYearAdmissionColIndex])) ? trim($row[$eduYearAdmissionColIndex]) : '';
                    
                    // Only add if we have at least a name
                    if (!empty($name)) {
                        // For institution field, prioritize university name, fallback to institution name
                        $institutionDisplay = !empty($university) ? $university : $institution;
                        
                $higherStudies[] = [
                            'name' => $name,
                    'degree' => $degree,
                            'institution' => $institutionDisplay,
                    'university' => $university,
                            'location' => $institutionDisplay,
                            'admissionYear' => !empty($yearAdmission) && is_numeric($yearAdmission) ? (int)$yearAdmission : null,
                            'passingYear' => !empty($yearPassing) && is_numeric($yearPassing) ? (int)$yearPassing : null,
                            'eduAdmissionYear' => !empty($eduYearAdmission) && is_numeric($eduYearAdmission) ? (int)$eduYearAdmission : null,
                            'linkedin' => $linkedin
                        ];
                    }
                }
                
                // Sort by passing year descending, then name ascending
                usort($higherStudies, function($a, $b) {
                    if ($a['passingYear'] != $b['passingYear']) {
                        return ($b['passingYear'] ?? 0) - ($a['passingYear'] ?? 0);
                    }
                    return strcmp($a['name'], $b['name']);
                });
            }
        }
    } catch (Exception $e) {
        error_log('Error fetching higher studies data from Google Sheets: ' . $e->getMessage());
        $higherStudies = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Higher Studies | TechVyom Alumni</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-additional.css">
    <link rel="stylesheet" href="styles-map.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="min-h-screen" style="background-color: #f8f6ff;">
        <nav class="nav-container" id="navigation">
            <div class="nav-content">
                <div class="college-header">
                    <h2 class="college-title">SHYAMA PRASAD MUKHERJI COLLEGE FOR WOMEN</h2>
                    <p class="college-subtitle">UNIVERSITY OF DELHI</p>
                </div>
                <div class="desktop-nav">
                    <div class="nav-grid">
                        <a class="nav-item" href="index.php">
                            <i class="fas fa-home nav-icon"></i>
                            <span class="nav-label">Home</span>
                        </a>
                        <a class="nav-item" href="placements.php">
                            <i class="fas fa-briefcase nav-icon"></i>
                            <span class="nav-label">Placements</span>
                        </a>
                        <a class="nav-item active" href="higher-studies.php">
                            <i class="fas fa-graduation-cap nav-icon"></i>
                            <span class="nav-label">Higher Studies</span>
                        </a>
                        <a class="nav-item" href="about-us.php">
                            <i class="fas fa-info-circle nav-icon"></i>
                            <span class="nav-label">About Us</span>
                        </a>
                        <?php if (!isset($_SESSION['admin_id'])): ?>
                        <a href="login.php" class="nav-item">
                            <i class="fas fa-sign-in-alt nav-icon"></i>
                            <span class="nav-label">Admin Login</span>
                        </a>
                        <?php else: ?>
                        <a href="dashboard.php" class="nav-item">
                            <i class="fas fa-tachometer-alt nav-icon"></i>
                            <span class="nav-label">Admin Panel</span>
                        </a>
                        <a href="logout.php" class="nav-item">
                            <i class="fas fa-sign-out-alt nav-icon"></i>
                            <span class="nav-label">Logout</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Mobile Menu Button -->
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <!-- Mobile Menu -->
            <div class="mobile-menu" id="mobileMenu">
                <div class="mobile-menu-header">
                    <h2 class="mobile-menu-title">TechVyom</h2>
                    <button class="mobile-menu-close" id="mobileMenuClose">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mobile-menu-content">
                    <p class="mobile-college-name">SHYAMA PRASAD MUKHERJI COLLEGE FOR WOMEN</p>
                    <p class="mobile-college-university">UNIVERSITY OF DELHI</p>
                </div>
                <div class="mobile-nav-items">
                    <a class="mobile-nav-item" href="index.php">
                        <i class="fas fa-home"></i>
                        Home
                    </a>
                    <a class="mobile-nav-item" href="placements.php">
                        <i class="fas fa-briefcase"></i>
                        Placements
                    </a>
                    <a class="mobile-nav-item" href="higher-studies.php">
                        <i class="fas fa-graduation-cap"></i>
                        Higher Studies
                    </a>
                    <a class="mobile-nav-item" href="about-us.php">
                        <i class="fas fa-info-circle"></i>
                        About Us
                    </a>
                    <?php if (!isset($_SESSION['admin_id'])): ?>
                    <a href="login.php" class="mobile-nav-item">
                        <i class="fas fa-sign-in-alt"></i>
                        Admin Login
                    </a>
                    <?php else: ?>
                    <a href="dashboard.php" class="mobile-nav-item">
                        <i class="fas fa-tachometer-alt"></i>
                        Admin Panel
                    </a>
                    <a href="logout.php" class="mobile-nav-item">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                    <?php endif; ?>
                </div>
                <div class="mobile-menu-footer">
                    <p class="mobile-menu-description">Computer Science Department Alumni Network</p>
                    <button class="mobile-menu-cta" data-section="info">Learn About Program</button>
                </div>
            </div>
        </nav>

        <main style="padding-top: 160px; padding-bottom: 40px;">
            <section class="alumni-table-section">
                <div class="table-card">
                    <div class="table-header">
                        <div class="table-header-content">
                            <h3><i class="fas fa-university"></i> Alumni Pursuing Higher Studies</h3>
                            <p>Alumni expanding their academic horizons around the world.</p>
                        </div>
                        <div class="table-actions">
                            <button class="view-toggle-btn" type="button" data-view-target="higher" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="view-toggle-menu" data-view-menu="higher">
                                <button class="view-option active" type="button" data-view="table">
                                    <i class="fas fa-table"></i>
                                    Table View
                                </button>
                                <button class="view-option" type="button" data-view="cards">
                                    <i class="fas fa-th-large"></i>
                                    Card View
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive is-visible" data-view-target="higher" data-view="table">
                        <table class="alumni-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Degree / Program</th>
                                    <th>Institution</th>
                                    <th>Admission Year</th>
                                    <th>LinkedIn</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($higherStudies) === 0): ?>
                                    <tr>
                                        <td colspan="6" class="table-empty">No higher studies records available yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($higherStudies as $alumni): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(formatName($alumni['name'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars(formatDisplayValue(formatDegree($alumni['degree'] ?? ''))); ?></td>
                                            <td><?php echo htmlspecialchars(formatDisplayValue(formatInstitutionName($alumni['institution'] ?? ''))); ?></td>
                                            <td>
                                                <?php
                                                    $admissionYear = $alumni['eduAdmissionYear'] ?? null;
                                                    echo formatDisplayValue($admissionYear ? (string)$admissionYear : '');
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $linkedin = formatLinkedInUrl($alumni['linkedin'] ?? '');
                                                    if (!empty($linkedin)): 
                                                ?>
                                                    <a href="<?php echo htmlspecialchars($linkedin); ?>" target="_blank" rel="noopener noreferrer" class="table-link" data-linkedin="<?php echo htmlspecialchars($linkedin); ?>">
                                                        View Profile
                                                    </a>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alumni-card-list" data-view-target="higher" data-view="cards" hidden>
                        <?php if (count($higherStudies) === 0): ?>
                            <div class="alumni-card-empty">No higher studies records available yet.</div>
                        <?php else: ?>
                            <?php foreach ($higherStudies as $alumni): ?>
                                <article class="alumni-card-entry">
                                    <div class="alumni-card-entry__header">
                                        <span class="alumni-card-entry__title"><?php echo htmlspecialchars(formatName($alumni['name'] ?? '')); ?></span>
                                        <?php if (!empty($alumni['year'])): ?>
                                            <span class="alumni-card-entry__badge">Batch <?php echo htmlspecialchars($alumni['year']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php 
                                        $degree = formatDegree($alumni['degree'] ?? '');
                                        if (!empty($degree)): 
                                    ?>
                                        <div class="alumni-card-entry__text">
                                            <i class="fas fa-book"></i>
                                            <?php echo htmlspecialchars($degree); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php 
                                        $institution = formatInstitutionName($alumni['institution'] ?? '');
                                        if (!empty($institution)): 
                                    ?>
                                        <div class="alumni-card-entry__text">
                                            <i class="fas fa-university"></i>
                                            <?php echo htmlspecialchars($institution); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="alumni-card-entry__footer">
                                        <?php
                                            $admissionYear = $alumni['eduAdmissionYear'] ?? null;
                                            if ($admissionYear) {
                                                echo '<div style="margin-bottom:6px;color:#4c1d95;font-weight:600;">Admission Year ' . htmlspecialchars($admissionYear) . '</div>';
                                            }
                                            $linkedin = formatLinkedInUrl($alumni['linkedin'] ?? '');
                                        ?>
                                        <?php if (!empty($linkedin)): ?>
                                            <a href="<?php echo htmlspecialchars($linkedin); ?>" target="_blank" rel="noopener noreferrer" class="table-link" data-linkedin="<?php echo htmlspecialchars($linkedin); ?>">
                                                View LinkedIn Profile
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <script src="table-view.js"></script>
</body>
</html>

