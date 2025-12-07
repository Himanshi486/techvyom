# Credentials Folder

This folder contains sensitive credential files for API integrations.

## Files in this folder

- `alumni-service.json` - Google Sheets API service account credentials (you need to add this)

## Security

- All JSON and key files are protected by `.htaccess` and cannot be accessed directly via web browser
- Never commit credential files to version control (they should be in `.gitignore`)

## How to add credentials

1. Download your Google Cloud service account JSON key file
2. Place it in this folder
3. Rename it to `alumni-service.json`

See `GOOGLE_SHEETS_SETUP.md` in the project root for detailed setup instructions.

