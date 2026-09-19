# GitHub Actions Setup for Azure Deployment

This guide explains how to set up the automated CI/CD pipeline for deploying to Azure.

## 📋 What is GitHub Actions?

GitHub Actions automatically deploys your code to Azure every time you push to the `main` branch. No manual deployment needed!

## 🔧 Setup Instructions

### Step 1: Get Azure Publish Profile

1. **Login to Azure Portal**
   - Go to [portal.azure.com](https://portal.azure.com)
   - Navigate to your Web App (`sentec-web`)

2. **Download Publish Profile**
   - Click **"Get publish profile"** in the top menu
   - This downloads a file: `sentec-web.PublishSettings`

3. **Open the file** in a text editor
   - Copy ALL the content (it's XML format)

### Step 2: Add Secret to GitHub

1. **Go to your GitHub repository**
   - Navigate to: `https://github.com/MohammadShayan1/SENTEC`

2. **Open Settings**
   - Click **"Settings"** tab (top right)
   - In left sidebar, click **"Secrets and variables"** → **"Actions"**

3. **Create New Secret**
   - Click **"New repository secret"**
   - Name: `AZURE_WEBAPP_PUBLISH_PROFILE`
   - Value: Paste the entire content of the publish profile file
   - Click **"Add secret"**

### Step 3: Update Workflow File (If Needed)

The workflow file is at: `.github/workflows/azure-deploy.yml`

Make sure the `app-name` matches your Azure Web App name:

```yaml
- name: 'Deploy to Azure Web App'
  uses: azure/webapps-deploy@v2
  with:
    app-name: 'sentec-web'  # ⚠️ Change this to YOUR app name
    publish-profile: ${{ secrets.AZURE_WEBAPP_PUBLISH_PROFILE }}
    package: .
```

### Step 4: Test the Pipeline

1. **Make a small change** to any file
2. **Commit and push**:
   ```bash
   git add .
   git commit -m "Test auto-deployment"
   git push origin main
   ```

3. **Watch the deployment**:
   - Go to GitHub repository
   - Click **"Actions"** tab
   - You'll see the deployment running!

## 🎯 How It Works

```
┌─────────────┐
│  Git Push   │
│  to main    │
└──────┬──────┘
       │
       ▼
┌─────────────────┐
│ GitHub Actions  │
│ Triggered       │
└──────┬──────────┘
       │
       ├─► 1. Checkout code
       ├─► 2. Setup PHP 8.2
       ├─► 3. Deploy to Azure
       │
       ▼
┌─────────────────┐
│  Azure Web App  │
│  Updated!       │
└─────────────────┘
```

## 📝 Workflow Breakdown

```yaml
name: Deploy to Azure Web App

# Trigger: Runs on push to main branch
on:
  push:
    branches:
      - main
  workflow_dispatch:  # Also allows manual triggering

jobs:
  build-and-deploy:
    runs-on: ubuntu-latest
    
    steps:
    # 1. Get the code
    - name: 'Checkout GitHub Action'
      uses: actions/checkout@v3

    # 2. Setup PHP environment
    - name: 'Setup PHP'
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: mysqli, pdo, pdo_mysql, mbstring, xml, ctype, json, tokenizer
        
    # 3. Deploy to Azure
    - name: 'Deploy to Azure Web App'
      uses: azure/webapps-deploy@v2
      with:
        app-name: 'sentec-web'
        publish-profile: ${{ secrets.AZURE_WEBAPP_PUBLISH_PROFILE }}
        package: .
```

## ✅ Verification

After setup, every push to `main` will:
1. ✅ Trigger GitHub Actions
2. ✅ Build your application
3. ✅ Deploy to Azure
4. ✅ Update your live site

**Check deployment status:**
- GitHub: Repository → Actions tab
- Azure: Web App → Deployment Center → Logs

## 🔄 Manual Deployment (Optional)

You can also trigger deployment manually:

1. Go to GitHub repository → **Actions** tab
2. Select **"Deploy to Azure Web App"** workflow
3. Click **"Run workflow"**
4. Select `main` branch
5. Click **"Run workflow"**

## 🚫 Troubleshooting

### Issue: Workflow fails with authentication error
**Solution**: 
- Regenerate publish profile in Azure
- Update the secret in GitHub

### Issue: Deployment succeeds but site doesn't update
**Solution**: 
- Check Azure logs: Web App → Log Stream
- Verify App Settings are configured
- Check for PHP errors

### Issue: Workflow doesn't trigger on push
**Solution**: 
- Verify you pushed to `main` branch
- Check workflow file is in `.github/workflows/`
- Check workflow syntax (YAML format)

### Issue: Secret not found
**Solution**: 
- Verify secret name is exactly: `AZURE_WEBAPP_PUBLISH_PROFILE`
- Check secret is in repository settings (not organization)

## 🎨 Customize Workflow

### Deploy to Different Branches

Add more branches to trigger on:

```yaml
on:
  push:
    branches:
      - main
      - development
      - staging
```

### Add Testing Before Deployment

Add a test step:

```yaml
- name: 'Run Tests'
  run: |
    php vendor/bin/phpunit tests/
```

### Add Notifications

Get notified on Discord/Slack when deployment completes:

```yaml
- name: 'Notify Slack'
  if: success()
  uses: rtCamp/action-slack-notify@v2
  env:
    SLACK_WEBHOOK: ${{ secrets.SLACK_WEBHOOK }}
    SLACK_MESSAGE: 'Deployment successful! 🚀'
```

## 📊 Deployment History

View all deployments:
1. GitHub → Actions tab
2. See all workflow runs
3. Click any run for detailed logs

## 💰 Cost

GitHub Actions is **FREE** for public repositories!
- 2,000 minutes/month for private repos
- Unlimited for public repos

## 📚 Resources

- [GitHub Actions Documentation](https://docs.github.com/actions)
- [Azure Deploy Action](https://github.com/Azure/webapps-deploy)
- [Workflow Syntax](https://docs.github.com/actions/reference/workflow-syntax-for-github-actions)

## 🎉 Benefits

✅ **Automated deployment** - No manual work  
✅ **Version control** - Track all deployments  
✅ **Rollback capability** - Easy to revert  
✅ **Testing integration** - Run tests before deploy  
✅ **Notifications** - Get alerts on success/failure  
✅ **Zero downtime** - Smooth deployments  

---

**Your CI/CD pipeline is ready! Every push = automatic deployment! 🚀**
