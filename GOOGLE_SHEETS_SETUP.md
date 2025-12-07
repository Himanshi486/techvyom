# Google Sheets API Integration Setup Guide

This guide will help you set up the Google Sheets API integration for the TechVyom Alumni Management system.

## Prerequisites

1. PHP 7.4 or higher
2. Composer installed on your system
3. Google Cloud Platform account
4. Service account JSON key file

## Step 1: Install Composer Dependencies

Run the following command in your project root directory:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/techvyom
composer install
```

This will install the Google API PHP client library (`google/apiclient`).

## Step 2: Create Google Cloud Project & Service Account

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the **Google Sheets API**:
   - Navigate to "APIs & Services" > "Library"
   - Search for "Google Sheets API"
   - Click "Enable"

4. Create a Service Account:
   - Go to "APIs & Services" > "Credentials"
   - Click "Create Credentials" > "Service Account"
   - Give it a name (e.g., "techvyom-sheets-service")
   - Click "Create and Continue"
   - Skip optional steps and click "Done"

5. Create a Key for the Service Account:
   - Click on the created service account
   - Go to "Keys" tab
   - Click "Add Key" > "Create new key"
   - Choose "JSON" format
   - Download the JSON file

## Step 3: Place Credentials File

1. Place the downloaded JSON file in the `credentials/` folder
2. Rename it to `alumni-service.json`

**File location:** `/Applications/XAMPP/xamppfiles/htdocs/techvyom/credentials/alumni-service.json`

**Note:** The `.htaccess` file in the credentials folder prevents direct access to JSON files for security.

## Step 4: Share Google Sheet with Service Account

1. Open your Google Sheet: `1y2BTTfKBrokY4syfNL9qHbX0gTIsnopAznnanpRlPr4`
2. Click the "Share" button
3. Copy the service account email address from your JSON file (it looks like: `your-service@project-id.iam.gserviceaccount.com`)
4. Paste it in the "Share" dialog
5. Give it "Editor" permissions
6. Click "Send"

**Important:** The sheet must be shared with the service account email, otherwise the API won't have access.

## Step 5: Add Status Column to Your Sheet

Your Google Sheet should have a "Status" column. This column will be used to track approval status:

- Empty or any value other than "approved" = Pending approval
- "approved" = Already approved (won't show in import page)

If you don't have a Status column, add it to your sheet. The system will automatically detect it.

## Step 6: Verify Configuration

Configuration values are set in:
- `admin/import_sheet.php`
- `admin/approve.php`

**Current settings:**
- Spreadsheet ID: `1y2BTTfKBrokY4syfNL9qHbX0gTIsnopAznnanpRlPr4`
- Sheet Name: `Sheet1`
- Credentials Path: `credentials/alumni-service.json`

If you need to change these, update them in both files.

## Step 7: Test the Integration

1. Log in to the admin panel
2. Navigate to Dashboard
3. Click "Import from Google Sheets"
4. You should see unapproved entries from your Google Sheet
5. Click "Approve" on any entry to:
   - Insert it into the MySQL database
   - Update the Status column to "approved"

## Troubleshooting

### Error: "Credentials file not found"
- Ensure `alumni-service.json` is placed in the `credentials/` folder
- Check file permissions (should be readable by PHP)

### Error: "Access denied" or "Permission denied"
- Ensure the Google Sheet is shared with the service account email
- Verify the service account has "Editor" permissions

### Error: "Google Sheets API not enabled"
- Go to Google Cloud Console
- Enable the Google Sheets API for your project

### Error: "Composer not found"
- Install Composer: https://getcomposer.org/download/
- Or install dependencies manually if needed

### No data showing
- Check if there are any rows with status != 'approved'
- Verify the Sheet Name is correct (default: "Sheet1")
- Check browser console for JavaScript errors

## Security Notes

1. The `credentials/` folder is protected by `.htaccess` to prevent direct access
2. Only logged-in admins can access the import pages
3. Never commit the credentials JSON file to version control
4. Keep your service account credentials secure

## File Structure

```
techvyom/
├── admin/
│   ├── import_sheet.php      # Display unapproved entries
│   └── approve.php           # Process approval & insert to DB
├── credentials/
│   ├── .htaccess            # Security protection
│   └── alumni-service.json  # Service account credentials (you need to add this)
├── vendor/                  # Composer dependencies (generated)
└── composer.json            # Dependency definition
```

## Support

For issues or questions, check:
1. Google Sheets API documentation
2. Error messages in the admin panel
3. PHP error logs

