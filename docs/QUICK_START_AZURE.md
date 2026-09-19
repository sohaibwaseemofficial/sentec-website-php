# 🚀 Quick Start Guide - Azure Deployment

## For Students: Free Azure Deployment in 5 Steps!

### ✅ What You'll Need
- Student email address
- GitHub account
- 30 minutes of your time

---

## Step 1️⃣: Get Azure for Students (5 min)

1. Visit: **https://azure.microsoft.com/free/students/**
2. Click **"Activate now"**
3. Sign in with your student email
4. Verify your student status
5. 🎉 **You now have $100 free credit!** (No credit card needed)

---

## Step 2️⃣: Push Your Code to GitHub (2 min)

```bash
# If not already on GitHub, push your code
cd d:\shayan\wamp64\www\azuredeploy\SENTEC

# Add all files (except .env - it's already ignored!)
git add .

# Commit
git commit -m "Add Azure deployment configuration"

# Push to GitHub
git push origin main
```

**✅ Verify**: Your code is now on GitHub! The `.env` file is NOT uploaded (check your repo).

---

## Step 3️⃣: Create Azure Web App (10 min)

### Option A: Azure Portal (Easiest)

1. Go to **https://portal.azure.com**
2. Click **"+ Create a resource"**
3. Search for **"Web App"** → Select → Create

**Fill in the form:**
- **Subscription**: Azure for Students
- **Resource Group**: Click "Create new" → Name: `sentec-rg`
- **Name**: `sentec-web` (or any unique name)
- **Publish**: Code
- **Runtime**: PHP 8.2
- **OS**: Windows
- **Region**: East US (or closest to you)
- **Pricing**: F1 (Free) ⚡

4. Click **"Review + Create"** → **"Create"**
5. Wait 2-3 minutes for deployment

### Option B: Azure CLI (For Advanced Users)

```bash
az login
az group create --name sentec-rg --location eastus
az appservice plan create --name sentec-plan --resource-group sentec-rg --sku F1
az webapp create --name sentec-web --resource-group sentec-rg --plan sentec-plan --runtime "PHP|8.2"
```

---

## Step 4️⃣: Configure Database (5 min)

### Easy Option: MySQL In App (FREE)

1. In Azure Portal, go to your Web App
2. Left menu → **"MySQL In App"**
3. Toggle **ON**
4. Click **"Save"**
5. Note: Database will be at `localhost` with auto-generated credentials

### Advanced Option: Azure MySQL Flexible Server

1. Create resource → **"Azure Database for MySQL Flexible Server"**
2. Configure:
   - Resource Group: `sentec-rg`
   - Server name: `sentec-mysql`
   - Admin username: `sentecadmin`
   - Password: [Create strong password]
   - Compute: Burstable B1ms
3. Networking → Allow Azure services
4. Create

---

## Step 5️⃣: Configure & Deploy (8 min)

### A. Set Environment Variables

1. Go to your Web App
2. Left menu → **Configuration** → **Application Settings**
3. Click **"+ New application setting"** and add each:

```
Name: DB_HOST              Value: localhost (or your MySQL server)
Name: DB_USERNAME          Value: root (or your username)
Name: DB_PASSWORD          Value: [your password]
Name: DB_NAME              Value: sentec
Name: RECAPTCHA_SITE_KEY   Value: [your reCAPTCHA site key]
Name: RECAPTCHA_SECRET_KEY Value: [your reCAPTCHA secret key]
Name: ADMIN_EMAIL          Value: your-email@example.com
Name: FROM_EMAIL           Value: no-reply@sentec.live
Name: FROM_NAME            Value: SENTEC
```

4. Click **"Save"** at the top

### B. Deploy from GitHub

1. Left menu → **Deployment Center**
2. Source: **GitHub**
3. Sign in to GitHub
4. Select:
   - Organization: Your username
   - Repository: SENTEC
   - Branch: main
5. Click **"Save"**
6. Wait 2-5 minutes for deployment

### C. Import Database

**Using Azure Cloud Shell:**

1. Upload `sentec.sql` to Cloud Shell
2. Run:
```bash
mysql -h localhost -u root -p sentec < sentec.sql
```

**Or use MySQL Workbench:**
- Connect to your Azure MySQL server
- Import `sentec.sql`

---

## 🎉 Your Site is LIVE!

Visit: **https://sentec-web.azurewebsites.net**

### Test Everything:
- ✅ Homepage loads
- ✅ Contact form works
- ✅ Event registration works
- ✅ Admin panel: `https://sentec-web.azurewebsites.net/admin`

---

## 🔧 Optional Enhancements

### Enable HTTPS Only
1. Web App → **TLS/SSL settings**
2. Turn **ON** "HTTPS Only"

### Add Custom Domain
1. Web App → **Custom domains**
2. Follow instructions to add your domain

### Set Up Auto-Deploy (Already Done!)
- Every `git push` to main → Auto-deploys to Azure! 🚀

### Monitor Your App
1. Web App → **Monitoring** → **Log stream**
2. See real-time logs

---

## 💡 Tips for Success

### Budget Management
- Monitor spending: Portal → Cost Management
- Set budget alerts
- F1 tier = completely FREE!
- MySQL In App = FREE
- Total monthly cost = **$0** 💰

### Common Issues & Solutions

**Issue**: Database connection error  
**Fix**: Check App Settings have correct DB credentials

**Issue**: 500 Internal Server Error  
**Fix**: Check Log Stream for PHP errors

**Issue**: Environment variables not loading  
**Fix**: Ensure all variables are in App Settings (not .env on Azure)

**Issue**: File upload not working  
**Fix**: Check upload directory permissions

### Getting Help
- Azure documentation: https://docs.microsoft.com/azure
- Full guide: See `AZURE_DEPLOYMENT.md`
- GitHub Issues: Open an issue in your repo

---

## 📱 Next Steps

1. ✅ Test all features thoroughly
2. ✅ Change admin password
3. ✅ Add your real reCAPTCHA keys
4. ✅ Import your database
5. ✅ Set up monitoring
6. ✅ Add custom domain (optional)
7. ✅ Share your live site! 🌐

---

## 📊 What You Got for FREE

| Feature | Value | Cost |
|---------|-------|------|
| Web Hosting (F1) | 1GB RAM, 1GB storage | $0/month |
| MySQL Database | Local database | $0/month |
| SSL Certificate | Free managed SSL | $0/month |
| Auto Deployment | GitHub integration | $0/month |
| Monitoring | Application Insights | Free tier |
| **Total** | Professional hosting | **$0/month** |

Plus **$100 credit** for upgrades when needed!

---

## 🎓 Learning Resources

- [Azure for Students Docs](https://docs.microsoft.com/azure/education-hub/)
- [App Service Tutorial](https://docs.microsoft.com/azure/app-service/)
- [PHP on Azure](https://docs.microsoft.com/azure/app-service/quickstart-php)

---

**Congratulations! Your SENTEC website is now live on Azure! 🚀**

Need more help? Check the full guide: [AZURE_DEPLOYMENT.md](AZURE_DEPLOYMENT.md)
