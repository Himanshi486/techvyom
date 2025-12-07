<?php
session_start();
include 'connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | TechVyom Alumni</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="styles-additional.css">
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
                        <a class="nav-item active" href="about-us.php">
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
            <!-- About Department Section -->
            <section class="dashboard-section" style="margin-bottom: 0; padding-top: 20px; padding-bottom: 40px;">
                <div class="container">
                    <div class="section-header" style="margin-bottom: 20px;">
                        <h2 class="section-title">About the Department</h2>
                        <p class="section-subtitle">
                            Learn about our Computer Science Department and our commitment to empowering women in technology.
                        </p>
                    </div>

                    <!-- Single Content Section -->
                    <div style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">
                        
                        <!-- Department Overview -->
                        <div style="margin-bottom: 30px;">
                            <h3 style="font-size: 1.75rem; color: #7c3aed; margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-university"></i>
                                Department Overview
                            </h3>
                            <p style="font-size: 1rem; line-height: 1.8; color: #4b5563; margin-bottom: 16px;">
                                The Computer Science Department at Shyama Prasad Mukherji College for Women is dedicated to academic excellence and innovation in technology. The department provides a nurturing and inclusive environment that encourages women to thrive and lead in the ever-evolving tech industry.
                            </p>
                            <p style="font-size: 1rem; line-height: 1.8; color: #4b5563;">
                                Our comprehensive 3-year B.Sc. (Hons.) Computer Science program is designed exclusively for women, blending strong theoretical foundations with hands-on practical experience. The curriculum equips students with the knowledge, skills, and confidence needed to excel in diverse technology-driven careers and to emerge as future leaders in the field.
                            </p>
                        </div>

                        <!-- TechVyom Society -->
                        <div style="margin-bottom: 30px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
                            <h3 style="font-size: 1.75rem; color: #7c3aed; margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-users"></i>
                                TechVyom Society
                            </h3>
                            <p style="font-size: 1rem; line-height: 1.8; color: #4b5563;">
                                The department's official society, <strong style="color: #7c3aed;">TechVyom</strong>, serves as a dynamic platform for students to explore and engage with the latest trends in technology. It organizes a wide range of events such as quizzes, workshops, seminars, hackathons, and networking sessions, fostering both technical proficiency and collaborative learning. Through these initiatives, students gain valuable exposure, build meaningful connections with industry professionals, and strengthen their overall professional development.
                            </p>
                        </div>

                        <!-- Faculty Members -->
                        <div style="margin-bottom: 30px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
                            <h3 style="font-size: 1.75rem; color: #7c3aed; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-chalkboard-teacher"></i>
                                Faculty Members
                            </h3>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #f3f4f6;">
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e5e7eb; color: #1f2937; font-weight: 600;">S.No.</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e5e7eb; color: #1f2937; font-weight: 600;">Name</th>
                                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e5e7eb; color: #1f2937; font-weight: 600;">Designation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 12px; color: #4b5563;">1</td>
                                            <td style="padding: 12px; color: #1f2937; font-weight: 500;">Dr. Jaya Gera</td>
                                            <td style="padding: 12px; color: #4b5563;">Associate Professor</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 12px; color: #4b5563;">2</td>
                                            <td style="padding: 12px; color: #1f2937; font-weight: 500;">Dr. Akanksha Bansal Chopra</td>
                                            <td style="padding: 12px; color: #4b5563;">Assistant Professor</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 12px; color: #4b5563;">3</td>
                                            <td style="padding: 12px; color: #1f2937; font-weight: 500;">Dr. Anuradha Singhal (TIC)</td>
                                            <td style="padding: 12px; color: #4b5563;">Assistant Professor</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 12px; color: #4b5563;">4</td>
                                            <td style="padding: 12px; color: #1f2937; font-weight: 500;">Ms. Mansi Sood</td>
                                            <td style="padding: 12px; color: #4b5563;">Assistant Professor</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 12px; color: #4b5563;">5</td>
                                            <td style="padding: 12px; color: #1f2937; font-weight: 500;">Ms. Sonia Kumari</td>
                                            <td style="padding: 12px; color: #4b5563;">Assistant Professor</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 12px; color: #4b5563;">6</td>
                                            <td style="padding: 12px; color: #1f2937; font-weight: 500;">Dr. Manish Kumar Singh</td>
                                            <td style="padding: 12px; color: #4b5563;">Assistant Professor</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 12px; color: #4b5563;">7</td>
                                            <td style="padding: 12px; color: #1f2937; font-weight: 500;">Ms. Kumari Seema Rani</td>
                                            <td style="padding: 12px; color: #4b5563;">Assistant Professor</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 12px; color: #4b5563;">8</td>
                                            <td style="padding: 12px; color: #1f2937; font-weight: 500;">Ms. Pratibha Yadav</td>
                                            <td style="padding: 12px; color: #4b5563;">Assistant Professor</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 12px; color: #4b5563;">9</td>
                                            <td style="padding: 12px; color: #1f2937; font-weight: 500;">Dr. Shweta Tyagi</td>
                                            <td style="padding: 12px; color: #4b5563;">Assistant Professor</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 12px; color: #4b5563;">10</td>
                                            <td style="padding: 12px; color: #1f2937; font-weight: 500;">Ms. Savita Devi</td>
                                            <td style="padding: 12px; color: #4b5563;">Assistant Professor</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 12px; color: #4b5563;">11</td>
                                            <td style="padding: 12px; color: #1f2937; font-weight: 500;">Mr. Lavkush Gupta</td>
                                            <td style="padding: 12px; color: #4b5563;">Assistant Professor</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 12px; color: #4b5563;">12</td>
                                            <td style="padding: 12px; color: #1f2937; font-weight: 500;">Ms. Geeta Arneja (Ad-hoc)</td>
                                            <td style="padding: 12px; color: #4b5563;">Assistant Professor</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div style="padding-top: 24px; border-top: 1px solid #e5e7eb;">
                            <h3 style="font-size: 1.75rem; color: #7c3aed; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-envelope"></i>
                                Contact Us
                            </h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px;">
                                <div>
                                    <h4 style="font-size: 1rem; color: #7c3aed; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-map-marker-alt"></i> Address
                                    </h4>
                                    <p style="line-height: 1.7; color: #4b5563; margin: 0;">
                                        Computer Science Department<br>
                                        Shyama Prasad Mukherji College for Women<br>
                                        Punjabi Bagh, Delhi - 110026<br>
                                        University of Delhi
                                    </p>
                                </div>
                                <div>
                                    <h4 style="font-size: 1rem; color: #7c3aed; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-envelope"></i> Email
                                    </h4>
                                    <p style="margin: 0;">
                                        <a href="mailto:spmctechvyom@gmail.com" style="color: #7c3aed; text-decoration: none; font-weight: 500;">
                                            spmctechvyom@gmail.com
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="script.js"></script>
</body>
</html>

