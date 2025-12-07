<?php
/**
 * Serve Google Drive documents directly
 * Downloads from Drive using service account and serves to browser
 * No local storage needed - serves on-demand
 */

session_start();
require_once __DIR__ . '/../connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die('Access denied');
}

// Get Drive URL from request
$driveUrl = isset($_GET['url']) ? urldecode($_GET['url']) : '';

if (empty($driveUrl)) {
    http_response_code(400);
    die('No document URL provided');
}

// Configuration
$credentialsPath = __DIR__ . '/../credentials/alumni-service.json';

try {
    // Load Google API client
    $vendorAutoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($vendorAutoload)) {
        throw new Exception('Composer dependencies not installed');
    }
    require_once $vendorAutoload;
    
    // Extract file ID from Drive URL
    $fileId = '';
    if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $driveUrl, $matches)) {
        $fileId = $matches[1];
    } elseif (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $driveUrl, $matches)) {
        $fileId = $matches[1];
    } elseif (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $driveUrl, $matches)) {
        $fileId = $matches[1];
    }
    
    if (empty($fileId)) {
        throw new Exception('Invalid Drive URL');
    }
    
    // Initialize Google Drive API client
    $client = new Google_Client();
    $client->setApplicationName('TechVyom Alumni Management');
    $client->setScopes([
        Google_Service_Sheets::SPREADSHEETS,
        'https://www.googleapis.com/auth/drive.readonly'
    ]);
    $client->setAuthConfig($credentialsPath);
    $client->setAccessType('offline');
    
    // Create Drive service
    $driveService = new Google_Service_Drive($client);
    
    // Get file metadata
    $file = $driveService->files->get($fileId, ['fields' => 'name,mimeType']);
    $fileName = $file->getName();
    $mimeType = $file->getMimeType();
    
    // Download file content
    if (strpos($mimeType, 'application/vnd.google-apps') === 0) {
        // Google Workspace files - export as PDF
        $response = $driveService->files->export($fileId, 'application/pdf', ['alt' => 'media']);
        $mimeType = 'application/pdf';
        $fileName = preg_replace('/\.[^.]+$/', '', $fileName) . '.pdf';
    } else {
        // Regular files - download directly
        $response = $driveService->files->get($fileId, ['alt' => 'media']);
    }
    
    // Set headers and serve file
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    
    // Output file content
    echo $response->getBody()->getContents();
    exit();
    
} catch (Exception $e) {
    http_response_code(500);
    $errorMessage = $e->getMessage();
    
    // Check if it's a Drive API not enabled error
    if (strpos($errorMessage, 'Google Drive API has not been used') !== false || 
        strpos($errorMessage, 'SERVICE_DISABLED') !== false ||
        strpos($errorMessage, 'accessNotConfigured') !== false) {
        
        // Extract project ID from error if available
        $projectId = '854210745903'; // Default from error message
        if (preg_match('/project[_\s]+(\d+)/i', $errorMessage, $matches)) {
            $projectId = $matches[1];
        }
        
        $enableUrl = "https://console.developers.google.com/apis/api/drive.googleapis.com/overview?project={$projectId}";
        
        die('
        <html>
        <head><title>Google Drive API Not Enabled</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; max-width: 800px; margin: 0 auto; }
            .error-box { background: #fee; border: 2px solid #f00; padding: 20px; border-radius: 8px; }
            .error-title { color: #c00; font-size: 24px; margin-bottom: 15px; }
            .error-message { color: #333; margin-bottom: 20px; line-height: 1.6; }
            .action-button { display: inline-block; background: #4285f4; color: white; padding: 12px 24px; 
                            text-decoration: none; border-radius: 4px; font-weight: bold; margin: 10px 0; }
            .action-button:hover { background: #357ae8; }
            .instructions { background: #f9f9f9; padding: 15px; border-left: 4px solid #4285f4; margin-top: 20px; }
            .instructions ol { margin: 10px 0; padding-left: 25px; }
            .instructions li { margin: 8px 0; }
        </style>
        </head>
        <body>
            <div class="error-box">
                <div class="error-title">⚠️ Google Drive API Not Enabled</div>
                <div class="error-message">
                    The Google Drive API needs to be enabled for your project before documents can be viewed.
                </div>
                <div style="text-align: center;">
                    <a href="' . htmlspecialchars($enableUrl) . '" target="_blank" class="action-button">
                        🔗 Enable Google Drive API Now
                    </a>
                </div>
                <div class="instructions">
                    <strong>Steps to fix:</strong>
                    <ol>
                        <li>Click the button above (or <a href="' . htmlspecialchars($enableUrl) . '" target="_blank">this link</a>)</li>
                        <li>Click <strong>"ENABLE"</strong> on the Google Cloud Console page</li>
                        <li>Wait 1-2 minutes for activation</li>
                        <li>Refresh this page and try viewing the document again</li>
                    </ol>
                    <p style="margin-top: 15px; color: #666;">
                        <strong>Project ID:</strong> ' . htmlspecialchars($projectId) . '<br>
                        <strong>Service Account:</strong> alumni-data-sync@alumni-476208.iam.gserviceaccount.com
                    </p>
                </div>
            </div>
        </body>
        </html>
        ');
    }
    
    // Check if it's a "file not found" error (usually means not shared with service account)
    if (strpos($errorMessage, 'File not found') !== false || 
        strpos($errorMessage, 'notFound') !== false ||
        strpos($errorMessage, '404') !== false) {
        
        // Extract file ID if available
        $fileId = '';
        if (preg_match('/File not found:\s*([a-zA-Z0-9_-]+)/', $errorMessage, $matches)) {
            $fileId = $matches[1];
        }
        
        die('
        <html>
        <head><title>Document Not Accessible</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; max-width: 800px; margin: 0 auto; }
            .error-box { background: #fff3cd; border: 2px solid #ffc107; padding: 20px; border-radius: 8px; }
            .error-title { color: #856404; font-size: 24px; margin-bottom: 15px; }
            .error-message { color: #333; margin-bottom: 20px; line-height: 1.6; }
            .action-button { display: inline-block; background: #4285f4; color: white; padding: 12px 24px; 
                            text-decoration: none; border-radius: 4px; font-weight: bold; margin: 10px 0; }
            .action-button:hover { background: #357ae8; }
            .instructions { background: #f9f9f9; padding: 15px; border-left: 4px solid #4285f4; margin-top: 20px; }
            .instructions ol { margin: 10px 0; padding-left: 25px; }
            .instructions li { margin: 8px 0; }
            .file-id { background: #e9ecef; padding: 8px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
            .service-account { background: #e7f3ff; padding: 10px; border-radius: 4px; margin: 15px 0; }
        </style>
        </head>
        <body>
            <div class="error-box">
                <div class="error-title">📄 Document Not Accessible</div>
                <div class="error-message">
                    The document cannot be accessed because it is not shared with the service account.
                </div>
                ' . ($fileId ? '<div class="file-id"><strong>File ID:</strong> ' . htmlspecialchars($fileId) . '</div>' : '') . '
                <div class="instructions">
                    <strong>How to fix this:</strong>
                    <ol>
                        <li>Open the document in Google Drive using this link:<br>
                            <a href="https://drive.google.com/file/d/' . htmlspecialchars($fileId ?: 'FILE_ID') . '/view" target="_blank" style="color: #4285f4;">
                                https://drive.google.com/file/d/' . htmlspecialchars($fileId ?: 'FILE_ID') . '/view
                            </a>
                        </li>
                        <li>Click the <strong>"Share"</strong> button (top right)</li>
                        <li>Add this email address:<br>
                            <div class="service-account">
                                <strong>alumni-data-sync@alumni-476208.iam.gserviceaccount.com</strong>
                            </div>
                        </li>
                        <li>Set permission to <strong>"Viewer"</strong></li>
                        <li>Click <strong>"Send"</strong></li>
                        <li>Come back here and refresh the page</li>
                    </ol>
                    <p style="margin-top: 15px; color: #666;">
                        <strong>Tip:</strong> If you have many documents, share the entire folder instead of individual files.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ');
    }
    
    // For other errors, show the error message
    die('Error loading document: ' . htmlspecialchars($errorMessage));
}

