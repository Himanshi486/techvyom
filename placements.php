<?php
session_start();
include 'connect.php';

// Load helper functions for better column mapping and formatting
require_once __DIR__ . '/admin/sheets_helper.php';
require_once __DIR__ . '/format_helpers.php';

$placedAlumni = [];

// Fetch placed alumni data from Google Sheets - only approved entries with work experience
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
                
                // Find column indices using helper functions for precise matching
                $statusColIndex = getColumnIndex($headers, ['Status', 'status']);
                $workExpColIndex = getColumnIndex($headers, [
                    'Are you currently working or have work experience?',
                    'are you currently working or have work experience',
                    'currently working or have work experience',
                    'work experience'
                ]);
                $nameColIndex = getColumnIndex($headers, ['Full Name', 'full name', 'name']);
                $emailColIndex = getColumnIndex($headers, ['Email Address', 'email address', 'email']);
                
                // Company: "Currently working with Organisation/Company" - prioritize exact match
                $companyColIndex = false;
                foreach ($headers as $idx => $header) {
                    $headerNormalized = strtolower(trim($header));
                    if (stripos($headerNormalized, 'currently working with organisation') !== false ||
                        stripos($headerNormalized, 'currently working with company') !== false) {
                        $companyColIndex = $idx;
                        break; // Take first match, prioritizing "Currently working with Organisation/Company"
                    }
                }
                
                // Role: "Current Job Title/ Designation" - prioritize exact match
                $roleColIndex = false;
                foreach ($headers as $idx => $header) {
                    $headerNormalized = strtolower(trim($header));
                    if (stripos($headerNormalized, 'current job title') !== false ||
                        stripos($headerNormalized, 'job title') !== false && stripos($headerNormalized, 'designation') !== false) {
                        $roleColIndex = $idx;
                        break; // Take first match, prioritizing "Current Job Title/ Designation"
                    }
                }
                
                $locationColIndex = getColumnIndex($headers, [
                    'Location of Current Job',
                    'location of current job',
                    'location'
                ]);
                $linkedinColIndex = getColumnIndex($headers, ['LinkedIn Profile', 'linkedin profile', 'linkedin']);
                
                // Year columns
                foreach ($headers as $idx => $header) {
                    $headerLower = strtolower(trim($header));
                    if (stripos($headerLower, 'year of admission') !== false && stripos($headerLower, 'spm') !== false) {
                        $yearAdmissionColIndex = $idx;
                    }
                    if (stripos($headerLower, 'year of passing') !== false && stripos($headerLower, 'spm') !== false) {
                        $yearPassingColIndex = $idx;
                    }
                }
                
                // Process rows - only approved entries with work experience
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
                    
                    // Check if has work experience
                    $hasWorkExp = false;
                    if ($workExpColIndex !== false && isset($row[$workExpColIndex])) {
                        $workExpValue = trim(strtolower((string)$row[$workExpColIndex]));
                        if (stripos($workExpValue, 'yes') !== false || $workExpValue === '1') {
                            $hasWorkExp = true;
                        }
                    }
                    
                    // Also check if they have company/organisation data (fallback)
                    if (!$hasWorkExp && $companyColIndex !== false && isset($row[$companyColIndex])) {
                        $companyValue = trim((string)$row[$companyColIndex]);
                        if (!empty($companyValue)) {
                            $hasWorkExp = true;
                        }
                    }
                    
                    if (!$hasWorkExp) {
                        continue; // Skip entries without work experience
                    }
                    
                    // Extract data using helper functions for reliable mapping
                    $name = mapValue($headers, $row, ['Full Name', 'full name', 'name']);
                    $email = mapValue($headers, $row, ['Email Address', 'email address', 'email']);
                    
                    // Company: Must match "Currently working with Organisation/Company" exactly
                    $company = mapValue($headers, $row, [
                        'Currently working with Organisation/Company',
                        'currently working with organisation/company',
                        'currently working with organisation company',
                        'Currently working with Organisation',
                        'currently working with organisation',
                        'Currently working with Company',
                        'currently working with company'
                    ]);
                    
                    // Role: Must match "Current Job Title/ Designation" exactly
                    $role = mapValue($headers, $row, [
                        'Current Job Title/ Designation',
                        'current job title/ designation',
                        'current job title designation',
                        'Current Job Title',
                        'current job title',
                        'Job Title/ Designation',
                        'job title designation',
                        'Designation'
                    ]);
                    
                    $location = mapValue($headers, $row, ['Location of Current Job', 'location of current job', 'location']);
                    $linkedin = mapValue($headers, $row, ['LinkedIn Profile', 'linkedin profile', 'linkedin']);
                    
                    // Year columns (fallback to direct access if helper didn't find)
                    $yearAdmission = ($yearAdmissionColIndex !== false && isset($row[$yearAdmissionColIndex])) ? trim($row[$yearAdmissionColIndex]) : '';
                    $yearPassing = ($yearPassingColIndex !== false && isset($row[$yearPassingColIndex])) ? trim($row[$yearPassingColIndex]) : '';
                    
                    // Only add if we have at least a name
                    if (!empty($name)) {
            $placedAlumni[] = [
                            'name' => $name,
                            'company' => formatCompanyName($company),
                            'role' => $role,
                            'location' => $location,
                            'admissionYear' => !empty($yearAdmission) && is_numeric($yearAdmission) ? (int)$yearAdmission : null,
                            'passingYear' => !empty($yearPassing) && is_numeric($yearPassing) ? (int)$yearPassing : null,
                            'linkedin' => $linkedin,
                            'experience' => null,
                            'package' => null
                        ];
                    }
                }
                
                // Sort by passing year descending, then name ascending
                usort($placedAlumni, function($a, $b) {
                    if ($a['passingYear'] != $b['passingYear']) {
                        return ($b['passingYear'] ?? 0) - ($a['passingYear'] ?? 0);
                    }
                    return strcmp($a['name'], $b['name']);
                });
        }
    }
} catch (Exception $e) {
        error_log('Error fetching placements data from Google Sheets: ' . $e->getMessage());
    $placedAlumni = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placements | TechVyom Alumni</title>
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
                        <a class="nav-item active" href="placements.php">
                            <i class="fas fa-briefcase nav-icon"></i>
                            <span class="nav-label">Placements</span>
                        </a>
                        <a class="nav-item" href="higher-studies.php">
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
                            <h3><i class="fas fa-briefcase"></i> Alumni Placements</h3>
                            <p>Verified alumni currently excelling in their professional journeys.</p>
                        </div>
                        <div class="table-actions">
                            <button class="view-toggle-btn" type="button" data-view-target="placements" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="view-toggle-menu" data-view-menu="placements">
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
                    <div class="table-responsive is-visible" data-view-target="placements" data-view="table">
                        <table class="alumni-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Company</th>
                                    <th>Designation</th>
                                    <th>Batch</th>
                                    <th>LinkedIn</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($placedAlumni) === 0): ?>
                                    <tr>
                                        <td colspan="6" class="table-empty">No placement records available yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($placedAlumni as $alumni): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(formatName($alumni['name'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars(formatDisplayValue(formatCompanyName($alumni['company'] ?? ''))); ?></td>
                                            <td><?php echo htmlspecialchars(formatDisplayValue(formatRole($alumni['role'] ?? ''))); ?></td>
                                            <td>
                                                <?php
                                                    $batchText = formatBatch($alumni['admissionYear'] ?? null, $alumni['passingYear'] ?? null);
                                                    echo htmlspecialchars(formatDisplayValue($batchText));
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
                    <div class="alumni-card-list" data-view-target="placements" data-view="cards" hidden>
                        <?php if (count($placedAlumni) === 0): ?>
                            <div class="alumni-card-empty">No placement records available yet.</div>
                        <?php else: ?>
                            <?php foreach ($placedAlumni as $alumni): ?>
                                <article class="alumni-card-entry">
                                    <div class="alumni-card-entry__header">
                                        <span class="alumni-card-entry__title"><?php echo htmlspecialchars(formatName($alumni['name'] ?? '')); ?></span>
                                        <?php 
                                            $batchText = formatBatch($alumni['admissionYear'] ?? null, $alumni['passingYear'] ?? null);
                                            if (!empty($batchText)): 
                                        ?>
                                            <span class="alumni-card-entry__badge">Batch <?php echo htmlspecialchars($batchText); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                        $role = formatRole($alumni['role'] ?? '');
                                        $company = formatCompanyName($alumni['company'] ?? '');
                                        $roleText = '';
                                        if (!empty($role) && !empty($company)) {
                                            $roleText = $role . ' at ' . $company;
                                        } elseif (!empty($role)) {
                                            $roleText = $role;
                                        } elseif (!empty($company)) {
                                            $roleText = $company;
                                        }
                                    ?>
                                    <?php if (!empty($roleText)): ?>
                                        <div class="alumni-card-entry__text">
                                            <i class="fas fa-briefcase"></i>
                                            <?php echo htmlspecialchars($roleText); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="alumni-card-entry__footer">
                                        <?php
                                            if (!empty($batchText)) {
                                                echo '<div style="margin-bottom:6px;color:#4c1d95;font-weight:600;">Batch ' . htmlspecialchars($batchText) . '</div>';
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

