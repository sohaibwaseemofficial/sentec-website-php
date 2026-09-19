# Azure Deployment Guide for Student Account

This guide will help you deploy the SENTEC website to Azure using Azure for Students account.

## Prerequisites

- Azure for Students account (get it at: https://azure.microsoft.com/free/students/)
- Git installed on your local machine
- Your project pushed to GitHub

## Step 1: Get Azure for Students

1. Go to [Azure for Students](https://azure.microsoft.com/free/students/)
2. Sign in with your student email
3. Verify your student status
4. You'll get $100 free credit (no credit card required!)

## Step 2: Create Azure Web App

### Option A: Using Azure Portal (Recommended for beginners)

1. **Login to Azure Portal**
   - Go to [portal.azure.com](https://portal.azure.com)
   - Sign in with your student account

2. **Create Web App**
   - Click "Create a resource"
   - Search for "Web App" and select it
   - Click "Create"

3. **Configure Web App**
   - **Subscription**: Azure for Students
   - **Resource Group**: Create new → "sentec-rg"
   - **Name**: `sentec-web` (or your preferred name - must be unique)
   - **Publish**: Code
   - **Runtime stack**: PHP 8.2 (or 8.1/8.0)
   - **Operating System**: Windows
   - **Region**: Choose closest to you (e.g., East US)
   - **Pricing Plan**: F1 (Free tier) - perfect for students!

4. **Click "Review + Create"** then **"Create"**

### Option B: Using Azure CLI

```bash
# Login to Azure
az login

# Create resource group
az group create --name sentec-rg --location eastus

# Create App Service Plan (Free tier)
az appservice plan create --name sentec-plan --resource-group sentec-rg --sku F1

# Create Web App
az webapp create --name sentec-web --resource-group sentec-rg --plan sentec-plan --runtime "PHP|8.2"
```

## Step 3: Configure MySQL Database

### Option A: Azure Database for MySQL (Flexible Server)

1. In Azure Portal, click "Create a resource"
2. Search for "Azure Database for MySQL Flexible Server"
3. Configure:
   - **Resource Group**: sentec-rg
   - **Server name**: sentec-mysql-server
   - **Region**: Same as your web app
   - **MySQL version**: 8.0
   - **Compute + Storage**: Burstable, B1ms (cheapest option)
   - **Admin username**: sentecadmin
   - **Password**: Create a strong password
4. **Networking**: 
   - Allow access from Azure services: Yes
   - Add current client IP address: Yes
5. Click "Review + Create" then "Create"

### Option B: Use ClearDB (Free MySQL add-on)

1. In your Web App, go to "MySQL In App" (simpler, free option)
2. Turn it ON
3. This creates a local MySQL database for your app

## Step 4: Configure Environment Variables in Azure

1. Go to your Web App in Azure Portal
2. Navigate to **Settings** → **Configuration**
3. Under **Application settings**, click **"+ New application setting"**
4. Add each variable from your `.env` file:

```
DB_HOST = your-mysql-server.mysql.database.azure.com
DB_USERNAME = sentecadmin
DB_PASSWORD = your-password
DB_NAME = sentec
RECAPTCHA_SITE_KEY = your-site-key
RECAPTCHA_SECRET_KEY = your-secret-key
ADMIN_EMAIL = your-email@example.com
FROM_EMAIL = no-reply@sentec.live
FROM_NAME = SENTEC
```

5. Click **"Save"** at the top

## Step 5: Deploy Your Code

### Option A: Deploy from GitHub (Recommended)

1. In your Web App, go to **Deployment** → **Deployment Center**
2. Select **Source**: GitHub
3. Sign in to GitHub
4. Select:
   - **Organization**: Your GitHub username
   - **Repository**: SENTEC
   - **Branch**: main
5. Click **"Save"**
6. Azure will automatically deploy your code!

### Option B: Deploy using Git

```bash
# Get your deployment credentials
az webapp deployment list-publishing-credentials --name sentec-web --resource-group sentec-rg

# Add Azure remote to your local repository
cd d:\shayan\wamp64\www\azuredeploy\SENTEC
git remote add azure https://sentec-web.scm.azurewebsites.net:443/sentec-web.git

# Push to Azure
git push azure main
```

### Option C: Deploy using VS Code

1. Install "Azure App Service" extension in VS Code
2. Sign in to Azure
3. Right-click your Web App
4. Select "Deploy to Web App"
5. Choose your project folder

## Step 6: Import Database

1. **Export your local database**:
   ```bash
   # From your local MySQL
   # The sentec.sql file is already in your project
   ```

2. **Import to Azure MySQL**:
   
   Using MySQL Workbench or command line:
   ```bash
   mysql -h your-server.mysql.database.azure.com -u sentecadmin -p sentec < sentec.sql
   ```

   Or use Azure Portal:
   - Go to your MySQL server
   - Use Query editor
   - Paste and run the SQL from `sentec.sql`

## Step 7: Configure Custom Domain (Optional)

1. In your Web App, go to **Settings** → **Custom domains**
2. Click **"+ Add custom domain"**
3. Follow the instructions to:
   - Add DNS records to your domain provider
   - Validate domain ownership
   - Bind the domain

## Step 8: Enable HTTPS/SSL

1. In your Web App, go to **Settings** → **TLS/SSL settings**
2. **HTTPS Only**: Turn ON
3. **Minimum TLS Version**: 1.2
4. For custom domain, add SSL certificate:
   - Use free managed certificate
   - Or upload your own

## Step 9: Test Your Deployment

1. Visit your site: `https://sentec-web.azurewebsites.net`
2. Test all functionality:
   - Contact form
   - Event registration
   - Admin panel
   - Gallery
   - Database connections

## Monitoring and Logs

### View Logs:
1. Go to **Monitoring** → **Log stream**
2. See real-time logs from your application

### Application Insights (Optional):
1. Enable Application Insights for detailed monitoring
2. Track performance, errors, and usage

## Cost Management for Students

- **Free tier (F1)**: Perfect for development and small projects
- **MySQL Flexible Server**: Use Burstable B1ms (cheapest)
- **Or use MySQL In App**: Completely free but limited
- Monitor your spending: Set up budget alerts in Azure Portal

## Troubleshooting

### Common Issues:

1. **Database Connection Errors**:
   - Check firewall rules in MySQL server
   - Verify connection string in App Settings
   - Ensure SSL connection if required

2. **500 Internal Server Error**:
   - Check PHP error logs in Log Stream
   - Verify .env variables are set in App Settings
   - Check file permissions

3. **Files Not Uploading**:
   - Adjust upload limits in `.user.ini`
   - Check storage quota

4. **Environment Variables Not Loading**:
   - Azure uses App Settings instead of `.env` file
   - Update `env_loader.php` to use Azure App Settings

## Azure Student Resources

- **Free Credits**: $100/year (renews if you're still a student)
- **Free Services**: Many services have free tiers
- **Learning**: Microsoft Learn modules for Azure
- **Support**: Azure documentation and community forums

## Important Files for Azure Deployment

- `.deployment` - Tells Azure how to deploy
- `deploy.cmd` - Deployment script
- `web.config` - IIS/Azure configuration
- `.user.ini` - PHP settings
- `.gitignore` - Prevents sensitive files from being uploaded

## Security Checklist

- ✅ Environment variables in Azure App Settings (not in code)
- ✅ `.env` file in `.gitignore` 
- ✅ HTTPS enabled
- ✅ MySQL firewall configured
- ✅ Strong admin passwords
- ✅ Keep Azure credentials secure

## Next Steps

1. Set up automated backups for database
2. Configure staging slots for testing
3. Set up CI/CD pipeline with GitHub Actions
4. Enable Application Insights for monitoring
5. Configure custom domain and SSL

## Useful Commands

```bash
# View Web App details
az webapp show --name sentec-web --resource-group sentec-rg

# View logs
az webapp log tail --name sentec-web --resource-group sentec-rg

# Restart Web App
az webapp restart --name sentec-web --resource-group sentec-rg

# Open Web App in browser
az webapp browse --name sentec-web --resource-group sentec-rg
```

## Support

- Azure Documentation: https://docs.microsoft.com/azure
- Azure for Students: https://azure.microsoft.com/free/students/
- Azure Support: https://portal.azure.com → Help + Support

---

**Good luck with your deployment! 🚀**
