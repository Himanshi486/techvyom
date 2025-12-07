# GitHub Setup Instructions

## ✅ Step 1: Create GitHub Repository

1. Go to [GitHub.com](https://github.com) and sign in
2. Click the **"+"** icon in the top right → **"New repository"**
3. Repository name: `techvyom` (or your preferred name)
4. Description: "TechVyom Alumni Management System"
5. Visibility: Choose **Public** or **Private**
6. **DO NOT** initialize with README, .gitignore, or license (we already have these)
7. Click **"Create repository"**

## ✅ Step 2: Connect Local Repository to GitHub

After creating the repository, GitHub will show you commands. Use one of these options:

### Option A: If repository is EMPTY (recommended)

Run these commands in your terminal:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/techvyom

# Add remote repository (replace YOUR_USERNAME with your GitHub username)
git remote add origin https://github.com/YOUR_USERNAME/techvyom.git

# Rename branch to main if needed (already done)
git branch -M main

# Push to GitHub
git push -u origin main
```

### Option B: If repository already exists on GitHub

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/techvyom

# Remove existing remote if any
git remote remove origin 2>/dev/null

# Add your GitHub repository
git remote add origin https://github.com/YOUR_USERNAME/techvyom.git

# Push to GitHub
git push -u origin main
```

## ✅ Step 3: Verify Upload

1. Visit your repository on GitHub: `https://github.com/YOUR_USERNAME/techvyom`
2. You should see all your files listed
3. Verify that sensitive files are NOT visible:
   - ❌ `credentials/alumni-service.json` (should NOT be there)
   - ❌ `credentials.json` (should NOT be there)
   - ❌ `connect.php` (should NOT be there)
   - ✅ `connect.php.example` (should be there)

## 🔐 Authentication

### Using Personal Access Token (Recommended)

GitHub no longer accepts passwords. You'll need a Personal Access Token:

1. Go to GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Click **"Generate new token"** → **"Generate new token (classic)"**
3. Give it a name: "TechVyom Project"
4. Select scopes: **repo** (all repo permissions)
5. Click **"Generate token"**
6. **Copy the token immediately** (you won't see it again!)
7. When prompted for password during `git push`, paste the token instead

### Alternative: SSH Key

1. Generate SSH key: `ssh-keygen -t ed25519 -C "your_email@example.com"`
2. Add to GitHub: Settings → SSH and GPG keys → New SSH key
3. Use SSH URL: `git@github.com:YOUR_USERNAME/techvyom.git`

## 📋 Quick Commands

```bash
# Check repository status
git status

# Check remote repository
git remote -v

# View commit history
git log --oneline

# Push changes
git push

# Pull changes (if working on multiple machines)
git pull
```

## ⚠️ Important Notes

1. **Never commit sensitive files:**
   - `credentials.json`
   - `credentials/alumni-service.json`
   - `connect.php` (actual database credentials)

2. **Use `connect.php.example`** as a template for deployment

3. **Always review** what you're committing:
   ```bash
   git status
   git diff
   ```

4. **Protected files** (already in .gitignore):
   - Credentials
   - Vendor directory
   - Uploads directory
   - Deployment files
   - Log files

## 🚀 Next Steps

After pushing to GitHub:
1. Update README.md with project information
2. Add repository description on GitHub
3. Consider adding GitHub Actions for CI/CD
4. Add collaborators if working in a team

---

**Ready to push?** Run the commands from Step 2 above!

