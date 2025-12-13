<?php
session_start();

include __DIR__ . '/connect.php';



if (!function_exists('formatStringLabel')) {
    function formatStringLabel($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/\s+/', ' ', $value);
        return ucwords(strtolower($value));
    }
}

if (!function_exists('formatCompanyName')) {
    function formatCompanyName($name) {
        return formatStringLabel($name);
    }
}

if (!function_exists('formatProgramName')) {
    function formatProgramName($name) {
        $formatted = formatStringLabel($name);
        if ($formatted === '') {
            return '';
        }

        $normalized = strtolower(preg_replace('/[^a-z0-9\s]/i', ' ', $name));
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        $programMappings = [
            'computer science' => 'BSc Computer Science (Hons)',
            'bsc computer science h' => 'BSc Computer Science (Hons)',
            'bsc cs hons' => 'BSc Computer Science (Hons)',
            'bsc h computer science' => 'BSc Computer Science (Hons)',
            'bsc honours computer science' => 'BSc Computer Science (Hons)',
            'bsc computer science honours' => 'BSc Computer Science (Hons)',
            'bsc hons computer science' => 'BSc Computer Science (Hons)',
            'bsc computer science' => 'BSc Computer Science',
            'bsc pol science' => 'BSc Political Science',
            'political science' => 'BSc Political Science',
            'bsc pol science h' => 'BSc Political Science (Hons)',
        ];

        foreach ($programMappings as $key => $canonical) {
            if (strpos($normalized, $key) !== false) {
                return $canonical;
            }
        }

        return $formatted;
    }
}

// Load alumni data for PHP processing
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
        // Extract state - could be in position 1 or 2 (e.g., "City, State" or "City, State, Country")
        $state = '';
        if (isset($locationParts[1])) {
            $state = trim($locationParts[1]);
        } elseif (isset($locationParts[2])) {
            $state = trim($locationParts[2]);
        }
        
        // If state is still empty, try to extract from location string
        if (empty($state) && !empty($row['location'])) {
            // Map cities to states and try to match Indian state names in the location string
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
            
            // First try city mapping
            foreach ($cityToState as $city => $stateName) {
                if (stripos($locationLower, $city) !== false) {
                    $state = $stateName;
                    break;
                }
            }
            
            // If still empty, try direct state name match
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

        if ($hasEmployment) {
            $currentStatus = 'Working';
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
            $statusDetails = trim(($degree ? "Pursuing {$degree}" : '') . ($institution ? ($degree ? ' at ' : 'At ') . $institution : ''));
        }

        $alumniData[] = [
            'id' => (int)$row['id'],
            'name' => trim($row['name']),
            'year' => (int)$row['year'],
            'program' => $programName,
            'role' => $roleName ?: 'Alumni',
            'company' => $companyName,
            'location' => $row['location'] ?: '',
            'city' => $city,
            'state' => $state,
            'country' => 'India',
            'linkedin' => $row['linkedin'],
            'hasInterview' => false,
            'achievements' => [],
            'currentStatus' => $currentStatus,
            'statusDetails' => $statusDetails,
            'experienceYears' => $row['experience_years'] ?: '',
            'education' => [
                'degree' => $row['degree_name'] ?: '',
                'institution' => $row['institution_name'] ?: '',
                'university' => $row['university_name'] ?: '',
                'hasHigherEdu' => (bool)$row['has_higher_edu'],
                'admissionYear' => $row['admission_year'] ?? null
            ],
            'hasEmployment' => $hasEmployment,
            'hasHigherEducation' => $hasHigherEducation,
            'institutionName' => $row['institution_name'] ?: '',
            'universityName' => $row['university_name'] ?: '',
            'admissionYear' => $row['admission_year'] ?? null
        ];
        }
    }
} catch (Exception $e) {
    // Log error but don't break the page
    error_log('Error fetching alumni data: ' . $e->getMessage());
    // Fallback to empty array if database fails
    $alumniData = [];
}

// Calculate stats
$totalAlumni = count($alumniData);
$stateArray = array_filter(array_column($alumniData, 'state'), function($s) { return !empty(trim($s)); });
$states = count(array_unique($stateArray));
$companies = count(array_unique(array_filter(array_column($alumniData, 'company'))));

// Debug: Log data count (remove in production)
error_log("Alumni data fetched: " . $totalAlumni . " verified alumni records");
if ($totalAlumni > 0) {
    error_log("Sample alumni: " . $alumniData[0]['name'] . " (ID: " . $alumniData[0]['id'] . ")");
} else {
    error_log("WARNING: No verified alumni found in database!");
}
$alumniCompanies = array_values(array_unique(array_filter(array_map(function ($alumni) {
    return isset($alumni['company']) ? formatCompanyName($alumni['company']) : '';
}, $alumniData))));
sort($alumniCompanies);

$programOptionsMap = [];
foreach ($alumniData as $alumni) {
    $programKey = strtolower($alumni['program']);
    if ($programKey === '') {
        continue;
    }
    if (!isset($programOptionsMap[$programKey])) {
        $programOptionsMap[$programKey] = $alumni['program'];
    }
}
$programOptions = array_values($programOptionsMap);
natcasesort($programOptions);
$programOptions = array_values($programOptions);

$placedAlumni = array_values(array_filter($alumniData, function ($alumni) {
    if ($alumni['currentStatus'] !== 'Working') {
        return false;
    }
    $hasEmploymentFields = !empty($alumni['company']) || !empty($alumni['role']) || !empty($alumni['location']);
    return $hasEmploymentFields;
}));

$higherStudiesAlumni = array_values(array_filter($alumniData, function ($alumni) {
    if ($alumni['currentStatus'] !== 'Studying') {
        return false;
    }
    $edu = $alumni['education'] ?? [];
    $hasHigherFields = !empty($edu['degree']) || !empty($edu['institution']) || !empty($edu['university']);
    return $hasHigherFields;
}));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechVyom Department Website Design</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-additional.css">
    <link rel="stylesheet" href="styles-map.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="min-h-screen" style="background-color: #f8f6ff;">
        <!-- Navigation -->
        <nav class="nav-container" id="navigation">
            <div class="nav-content">
                <!-- College Header -->
                <div class="college-header">
                    <h2 class="college-title">SHYAMA PRASAD MUKHERJI COLLEGE FOR WOMEN</h2>
                    <p class="college-subtitle">UNIVERSITY OF DELHI</p>
                </div>
                
                <!-- Desktop Navigation -->
                <div class="desktop-nav">
                    <div class="nav-grid">
                        <button class="nav-item" data-section="home">
                            <i class="fas fa-home nav-icon"></i>
                            <span class="nav-label">Home</span>
                        </button>
                        <a class="nav-item" href="placements.php">
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
                    <button class="mobile-nav-item" data-section="home">
                        <i class="fas fa-home"></i>
                        Home
                    </button>
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

        <main style="padding-top: 0;">
            <!-- Hero Section -->
            <section id="home" class="hero-section" style="min-height: 100vh; padding-top: 160px;">
                <div class="hero-background">
                    <img src="https://images.unsplash.com/photo-1598187210738-6b10d7d0471a?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxjb21wdXRlciUyMHNjaWVuY2UlMjB0ZWNobm9sb2d5JTIwcHVycGxlfGVufDF8fHx8MTc1Njc4OTc4Mnww&ixlib=rb-4.0.3&q=80&w=1200" 
                         alt="Technology Background" class="hero-bg-image">
                </div>
                <div class="hero-content">
                    <div class="hero-text">
                        <h1 class="hero-title">TechVyom</h1>
                        <p class="hero-subtitle">Computer Science Department's Alumni Network & Showcase Portal</p>
                        <p class="hero-description">
                            Celebrating our B.Sc.(H) Computer Science alumni across the globe-
                            empowering women leaders in technology and inspiring the next generation.
                        </p>
                    </div>
                    <div class="hero-buttons">
                        <a class="hero-btn" href="alumni-network.php">Explore Alumni Network</a>
                        <a class="hero-btn" href="https://docs.google.com/forms/d/1cfaQNe2yflQ9nYiuCIGBwZcumGBawvGamtgAA_FQaM0/viewform" target="_blank" rel="noopener noreferrer">Alumni Update Form</a>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="script.js"></script>
</body>
</html>
