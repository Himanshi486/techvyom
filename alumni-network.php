<?php
session_start();
include 'connect.php';

// Load helper functions for formatting
require_once __DIR__ . '/format_helpers.php';

// Helper function to extract location components (city, state)
function extractLocationComponents($locationString) {
    $locationString = trim((string)$locationString);
    if (empty($locationString)) {
        return ['city' => '', 'state' => ''];
    }
    
    $locationParts = explode(',', $locationString);
            $city = isset($locationParts[0]) ? trim($locationParts[0]) : '';
            $state = '';
    
            if (isset($locationParts[1])) {
                $state = trim($locationParts[1]);
            } elseif (isset($locationParts[2])) {
                $state = trim($locationParts[2]);
            }

            // Enhanced state detection
    if (empty($state) && !empty($locationString)) {
                $cityToState = [
                    'delhi' => 'Delhi', 'new delhi' => 'Delhi',
                    'mumbai' => 'Maharashtra', 'pune' => 'Maharashtra', 'nagpur' => 'Maharashtra',
                    'bangalore' => 'Karnataka', 'bengaluru' => 'Karnataka', 'mysore' => 'Karnataka',
                    'hyderabad' => 'Telangana', 'warangal' => 'Telangana',
                    'chennai' => 'Tamil Nadu', 'coimbatore' => 'Tamil Nadu', 'madurai' => 'Tamil Nadu',
                    'kolkata' => 'West Bengal', 'howrah' => 'West Bengal',
                    'gurgaon' => 'Haryana', 'gurugram' => 'Haryana', 'faridabad' => 'Haryana', 'panchkula' => 'Haryana',
                    'noida' => 'Uttar Pradesh', 'lucknow' => 'Uttar Pradesh', 'kanpur' => 'Uttar Pradesh', 'meerut' => 'Uttar Pradesh'
                ];

                $indianStates = ['Haryana', 'Karnataka', 'Maharashtra', 'Tamil Nadu', 'West Bengal', 'Uttar Pradesh', 'Telangana', 'Rajasthan', 'Gujarat', 'Punjab', 'Madhya Pradesh', 'Bihar', 'Odisha', 'Andhra Pradesh', 'Kerala', 'Assam', 'Jharkhand', 'Chhattisgarh', 'Himachal Pradesh', 'Uttarakhand', 'Goa', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Sikkim', 'Tripura', 'Arunachal Pradesh', 'Delhi'];

        $locationLower = strtolower($locationString);

                foreach ($cityToState as $cityKey => $stateName) {
                    if (stripos($locationLower, $cityKey) !== false) {
                        $state = $stateName;
                        break;
                    }
                }

                if (empty($state)) {
                    foreach ($indianStates as $stateName) {
                        if (stripos($locationLower, strtolower($stateName)) !== false) {
                            $state = $stateName;
                            break;
                        }
                    }
                }
            }

    return ['city' => $city, 'state' => $state];
}

// Load alumni data from database - only verified alumni
$alumniData = [];
try {
    $sql = "SELECT
        ab.id,
        ab.full_name as name,
        ab.year_admission as admission_year,
        ab.year_passing as year,
        ab.course as program,
        ab.department,
        ab.linkedin_profile as linkedin,
        ae.employment_status,
        ae.organisation as company,
        ae.designation as role,
        ae.location,
        ae.experience_years,
        ae.placed_through_spm,
        ed.degree_name,
        ed.institution_name,
        ed.university_name,
        ed.has_higher_edu,
        ab.year_admission
    FROM alumni_basic ab
    LEFT JOIN alumni_employment ae ON ab.id = ae.alumni_id
    LEFT JOIN alumni_education ed ON ab.id = ed.alumni_id
    WHERE ab.verified = 1
    ORDER BY ab.year_passing DESC";

    $result = $conn->query($sql);
    if (!$result) {
        error_log('Database query failed: ' . $conn->error);
        $alumniData = [];
    } else {
        while ($row = $result->fetch_assoc()) {
            $locationParts = explode(',', $row['location'] ?? '');
            $city = isset($locationParts[0]) ? trim($locationParts[0]) : '';
            $state = '';
            if (isset($locationParts[1])) {
                $state = trim($locationParts[1]);
            } elseif (isset($locationParts[2])) {
                $state = trim($locationParts[2]);
            }
            
            // Enhanced state detection (same as index.php)
            if (empty($state) && !empty($row['location'])) {
                $cityToState = [
                    'delhi' => 'Delhi', 'new delhi' => 'Delhi',
                    'mumbai' => 'Maharashtra', 'pune' => 'Maharashtra', 'nagpur' => 'Maharashtra',
                    'bangalore' => 'Karnataka', 'bengaluru' => 'Karnataka', 'mysore' => 'Karnataka',
                    'hyderabad' => 'Telangana', 'warangal' => 'Telangana',
                    'chennai' => 'Tamil Nadu', 'coimbatore' => 'Tamil Nadu', 'madurai' => 'Tamil Nadu',
                    'kolkata' => 'West Bengal', 'howrah' => 'West Bengal',
                    'gurgaon' => 'Haryana', 'gurugram' => 'Haryana', 'faridabad' => 'Haryana', 'panchkula' => 'Haryana',
                    'noida' => 'Uttar Pradesh', 'lucknow' => 'Uttar Pradesh', 'kanpur' => 'Uttar Pradesh', 'meerut' => 'Uttar Pradesh'
                ];
                
                $indianStates = ['Haryana', 'Karnataka', 'Maharashtra', 'Tamil Nadu', 'West Bengal', 'Uttar Pradesh', 'Telangana', 'Rajasthan', 'Gujarat', 'Punjab', 'Madhya Pradesh', 'Bihar', 'Odisha', 'Andhra Pradesh', 'Kerala', 'Assam', 'Jharkhand', 'Chhattisgarh', 'Himachal Pradesh', 'Uttarakhand', 'Goa', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Sikkim', 'Tripura', 'Arunachal Pradesh', 'Delhi'];
                
                $locationLower = strtolower($row['location']);
                
                foreach ($cityToState as $cityName => $stateName) {
                    if (stripos($locationLower, $cityName) !== false) {
                        $state = $stateName;
                        break;
                    }
                }
                
                if (empty($state)) {
                    foreach ($indianStates as $stateName) {
                        if (stripos($locationLower, strtolower($stateName)) !== false) {
                            $state = $stateName;
                            break;
                        }
                    }
                }
            }

            $companyName = formatCompanyName($row['company'] ?? '');
            $roleName = trim($row['role'] ?? '');
            $programName = formatProgramName($row['program'] ?: 'BSc CS Hons');

            $hasEmployment = !is_null($row['employment_status']) ||
                !is_null($row['company']) ||
                !is_null($row['role']) ||
                !is_null($row['experience_years']);

            $hasHigherEducation = ($row['has_higher_edu'] == 1) ||
                !empty($row['degree_name']) ||
                !empty($row['institution_name']) ||
                !empty($row['university_name']);

            $currentStatus = '';
            $statusDetails = '';
            $displayLocation = '';

            if ($hasEmployment) {
                $currentStatus = ($row['placed_through_spm'] == 1) ? 'Placed' : 'Working';
                $displayLocation = $row['location'] ?? '';
                
                $details = [];
                if ($roleName !== '') {
                    $details[] = $roleName;
                }
                if ($companyName !== '') {
                    $details[] = $companyName;
                }
                if ($row['location']) {
                    $details[] = 'Location: ' . $row['location'];
                }
                $statusDetails = implode(' • ', array_filter($details));
            } elseif ($hasHigherEducation) {
                $currentStatus = 'Studying';
                $degree = $row['degree_name'] ?: '';
                $institution = $row['institution_name'] ?: $row['university_name'] ?: '';
                $displayLocation = $institution;
                $statusDetails = trim(($degree ? "Pursuing {$degree}" : '') . ($institution ? ($degree ? ' at ' : 'At ') . $institution : ''));
            }

            $locationData = extractLocationComponents($displayLocation);

            $alumniData[] = [
                'id' => (int)$row['id'],
                'name' => trim($row['name']),
                'email' => '',
                'year' => (int)$row['year'],
                'admissionYear' => (int)$row['admission_year'],
                'program' => $programName,
                'department' => $row['department'] ?? '',
                'role' => $roleName ?: '',
                'company' => $companyName,
                'location' => $displayLocation,
                'city' => $locationData['city'] ?: $city,
                'state' => $locationData['state'] ?: $state,
                'country' => 'India',
                'linkedin' => $row['linkedin'],
                'currentStatus' => $currentStatus,
                'statusDetails' => $statusDetails,
                'hasEmployment' => $hasEmployment,
                'hasHigherEducation' => $hasHigherEducation,
                'education' => [
                    'degree' => $row['degree_name'] ?: '',
                    'institution' => $row['institution_name'] ?: '',
                    'university' => $row['university_name'] ?: '',
                    'hasHigherEdu' => (bool)$row['has_higher_edu']
                ],
                'institutionName' => $row['institution_name'] ?: '',
                'universityName' => $row['university_name'] ?: ''
            ];
        }
    }
} catch (Exception $e) {
    error_log('Error fetching alumni data from database: ' . $e->getMessage());
    $alumniData = [];
}

// Calculate stats
$totalAlumni = count($alumniData);
$stateArray = array_filter(array_column($alumniData, 'state'), function($s) { return !empty(trim($s)); });
$states = count(array_unique($stateArray));
$companies = count(array_unique(array_filter(array_column($alumniData, 'company'))));

// Calculate placed and studying counts
$placedCount = 0;
$studyingCount = 0;
$workingCount = 0;
foreach ($alumniData as $alumni) {
    if ($alumni['currentStatus'] === 'Placed') {
        $placedCount++;
    } elseif ($alumni['currentStatus'] === 'Studying') {
        $studyingCount++;
    } elseif ($alumni['currentStatus'] === 'Working') {
        $workingCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Network | TechVyom Alumni</title>
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
                        <a class="nav-item" href="higher-studies.php">
                            <i class="fas fa-graduation-cap nav-icon"></i>
                            <span class="nav-label">Higher Studies</span>
                        </a>
                        <a class="nav-item active" href="alumni-network.php">
                            <i class="fas fa-network-wired nav-icon"></i>
                            <span class="nav-label">Explore Alumni</span>
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
            </div>
        </nav>

        <main style="padding-top: 160px; padding-bottom: 40px;">
            <!-- Interactive Map Section -->
            <section class="dashboard-section" style="margin-bottom: 0; padding-top: 20px; padding-bottom: 40px;">
                <div class="container">
                    <div class="section-header" style="margin-bottom: 20px;">
                        <h2 class="section-title">Alumni Network</h2>
                        <p class="section-subtitle">
                            Explore our global network of alumni and their achievements across the world.
                        </p>
                    </div>
                    <!-- Stats Cards -->
                    <div class="stats-grid" id="statsGrid" style="margin-bottom: 16px;">
                        <div class="stat-card">
                            <i class="fas fa-users stat-icon"></i>
                            <div class="stat-content">
                                <p class="stat-number" id="totalAlumni"><?php echo $totalAlumni; ?></p>
                                <p class="stat-label">Total Alumni</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-graduation-cap stat-icon"></i>
                            <div class="stat-content">
                                <p class="stat-number" id="studyingAlumni"><?php echo $studyingCount; ?></p>
                                <p class="stat-label">Studying</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-building stat-icon"></i>
                            <div class="stat-content">
                                <p class="stat-number" id="workingAlumni"><?php echo $workingCount; ?></p>
                                <p class="stat-label">Working</p>
                            </div>
                        </div>
                    </div>

                    <div class="map-container" id="mapContainer" style="margin-bottom: 0;">
                        <div class="map-card">
                            <div class="map-header">
                                <h3 class="map-title">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Alumni Locations Across India
                                </h3>
                                <p style="font-size: 14px; color: #6b7280; margin-top: 5px;">
                                    Showing where our verified alumni are currently working or studying
                                </p>
                            </div>
                            <div class="map-content">
                                <div class="map-wrapper">
                                    <div id="map" class="map"></div>
                                </div>
                                <div class="map-stats">
                                    <div class="map-stat-item">
                                        <div class="map-stat-dot"></div>
                                        <span class="map-stat-text">Total Alumni: <span id="mapTotalAlumni">0</span></span>
                                    </div>
                                    <div class="map-stat-info">
                                        States: <span id="mapStates">0</span> | Locations: <span id="mapCities">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="map-sidebar">
                            <div class="map-sidebar-card">
                                <div class="map-sidebar-header">
                                    <h3 class="map-sidebar-title">
                                        <i class="fas fa-users"></i>
                                        <span id="mapSidebarTitle">Select a Location</span>
                                    </h3>
                                </div>
                                <div class="map-sidebar-content" id="mapSidebarContent">
                                    <div class="map-sidebar-placeholder">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <p>Click on a location marker to view alumni details</p>
                                        <div class="map-sidebar-summary">
                                            <strong id="mapSidebarTotal"><?php echo $totalAlumni; ?></strong> total alumni across <strong id="mapSidebarCities">0</strong> cities
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Alumni Information Section -->
            <section class="alumni-table-section" style="margin-top: 0;">
                <div class="table-card">
                    <div class="table-header">
                        <div class="table-header-content">
                            <h3><i class="fas fa-users"></i> All Alumni</h3>
                            <p>Complete directory of verified alumni from our Computer Science Department.</p>
                        </div>
                        <div class="table-actions">
                            <button class="view-toggle-btn" type="button" data-view-target="alumni" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="view-toggle-menu" data-view-menu="alumni">
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
                    <div class="table-responsive is-visible" data-view-target="alumni" data-view="table">
                        <table class="alumni-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Company/Institution</th>
                                    <th>Location</th>
                                    <th>Batch</th>
                                    <th>LinkedIn</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($alumniData) === 0): ?>
                                    <tr>
                                        <td colspan="6" class="table-empty">No alumni records available yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($alumniData as $alumni): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars(formatName($alumni['name'] ?? '')); ?></strong></td>
                                            <td>
                                                <?php if ($alumni['currentStatus'] === 'Placed'): ?>
                                                    <span style="color: #10b981; font-weight: 600;">
                                                        <i class="fas fa-check-circle"></i> Placed
                                                    </span>
                                                <?php elseif ($alumni['currentStatus'] === 'Working'): ?>
                                                    <span style="color: #f59e0b; font-weight: 600;">
                                                        <i class="fas fa-briefcase"></i> Working
                                                    </span>
                                                <?php elseif ($alumni['currentStatus'] === 'Studying'): ?>
                                                    <span style="color: #3b82f6; font-weight: 600;">
                                                        <i class="fas fa-graduation-cap"></i> Studying
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #6b7280;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($alumni['currentStatus'] === 'Placed' || $alumni['currentStatus'] === 'Working'): ?>
                                                    <?php echo htmlspecialchars(formatDisplayValue(formatCompanyName($alumni['company'] ?? ''))); ?>
                                                <?php elseif ($alumni['currentStatus'] === 'Studying'): ?>
                                                    <?php 
                                                        $institution = formatInstitutionName($alumni['education']['institution'] ?? '');
                                                        $university = formatInstitutionName($alumni['education']['university'] ?? '');
                                                        echo htmlspecialchars(formatDisplayValue($institution ?: $university));
                                                    ?>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(formatDisplayValue(formatLocation($alumni['location'] ?? ''))); ?></td>
                                            <td>
                                                <?php
                                                    $batchText = formatBatch($alumni['admissionYear'] ?? null, $alumni['year'] ?? null);
                                                    echo htmlspecialchars(formatDisplayValue($batchText));
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $linkedin = formatLinkedInUrl($alumni['linkedin'] ?? '');
                                                    if (!empty($linkedin)): 
                                                ?>
                                                    <a href="<?php echo htmlspecialchars($linkedin); ?>" target="_blank" rel="noopener noreferrer" class="table-link">
                                                        <i class="fab fa-linkedin"></i> View Profile
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
                    <div class="alumni-card-list" data-view-target="alumni" data-view="cards" hidden>
                        <?php if (count($alumniData) === 0): ?>
                            <div class="alumni-card-empty">No alumni records available yet.</div>
                        <?php else: ?>
                            <?php foreach ($alumniData as $alumni): ?>
                                <article class="alumni-card-entry">
                                    <div class="alumni-card-entry__header">
                                        <span class="alumni-card-entry__title"><?php echo htmlspecialchars(formatName($alumni['name'] ?? '')); ?></span>
                                        <?php if ($alumni['currentStatus'] === 'Placed'): ?>
                                            <span class="alumni-card-entry__badge" style="background-color: #10b981;">Placed</span>
                                        <?php elseif ($alumni['currentStatus'] === 'Working'): ?>
                                            <span class="alumni-card-entry__badge" style="background-color: #f59e0b;">Working</span>
                                        <?php elseif ($alumni['currentStatus'] === 'Studying'): ?>
                                            <span class="alumni-card-entry__badge" style="background-color: #3b82f6;">Studying</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php 
                                        $statusDetails = trim($alumni['statusDetails'] ?? '');
                                        if (!empty($statusDetails)): 
                                    ?>
                                        <div class="alumni-card-entry__text">
                                            <?php if ($alumni['currentStatus'] === 'Placed' || $alumni['currentStatus'] === 'Working'): ?>
                                                <i class="fas fa-briefcase"></i>
                                            <?php else: ?>
                                                <i class="fas fa-graduation-cap"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($statusDetails); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php 
                                        $location = formatLocation($alumni['location'] ?? '');
                                        if (!empty($location)): 
                                    ?>
                                        <div class="alumni-card-entry__text">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($location); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="alumni-card-entry__footer">
                                        <?php
                                            $batchText = formatBatch($alumni['admissionYear'] ?? null, $alumni['year'] ?? null);
                                            if (!empty($batchText)) {
                                                echo '<div style="margin-bottom:6px;color:#4c1d95;font-weight:600;">Batch ' . htmlspecialchars($batchText) . '</div>';
                                            }
                                            $linkedin = formatLinkedInUrl($alumni['linkedin'] ?? '');
                                        ?>
                                        <?php if (!empty($linkedin)): ?>
                                            <a href="<?php echo htmlspecialchars($linkedin); ?>" target="_blank" rel="noopener noreferrer" class="table-link">
                                                <i class="fab fa-linkedin"></i> View LinkedIn Profile
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

    <!-- Alumni Data for JavaScript -->
    <script>
        window.alumniData = <?php echo json_encode($alumniData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        window.totalAlumni = <?php echo (int)$totalAlumni; ?>;
        window.totalStates = <?php echo (int)$states; ?>;
        window.totalCompanies = <?php echo (int)$companies; ?>;
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="script.js?v=<?php echo time(); ?>"></script>
    <script src="table-view.js"></script>
    <script>
        // Ensure map is initialized and updated on this page
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a bit for the page to fully render
            setTimeout(function() {
                if (typeof initializeMap === 'function') {
                    initializeMap();
                }
                // Invalidate map size to ensure it renders properly
                setTimeout(function() {
                    if (map) {
                        map.invalidateSize();
                    }
                    // Update map with alumni data
                    if (typeof updateMapWithAlumniData === 'function' && window.alumniData) {
                        updateMapWithAlumniData();
                    }
                }, 200);
            }, 100);
        });
    </script>
</body>
</html>
