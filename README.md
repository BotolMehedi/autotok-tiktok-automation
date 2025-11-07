<h1 align="center">AUTOTOK
</h1>

<div align="center">

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](LICENSE)
[![TikTok API](https://img.shields.io/badge/TikTok-API-EE1D52?style=for-the-badge&logo=tiktok&logoColor=white)](https://developers.tiktok.com/)

*AUTOTOK is a full-stack web application that automates TikTok video uploads and scheduling through the official TikTok API. Built with PHP, MySQL. Ultimate tool for content creators to schedule multiple daily posts, manage bulk uploads*


</div>

---

## 🚀 Features

- [x] TikTok OAuth 2.0 Authentication
- [x] Multi-Account Management
- [x] Bulk Video Upload
- [x] Smart Scheduling
- [x] Random Caption Selection
- [x] Automatic Token Refresh
- [x] Manual Upload
- [x] Activity Logging
- [x] Export Logs
- [x] Real-time statistics on Dashboard
- [x] CSRF token protection
- [x] Session-based authentication


## 🏗️ Built With

- PHP 8.0+
- MySQL 5.7+
- HTML5, TailwindCSS, Vanilla JavaScript
- PHP Extensions (PDO, PDO_MySQL, cURL, JSON, mbstring, fileinfo)
- TikTok V2 OAuth API
- Cron-based scheduling system

---

## 🔧 Installation

I’m not going to tell you how to install it. If you can’t figure it out yourself, then fcuk u🖕This script is only for those who know what they’re doing😪

## Configure Environment

Copy the example environment file:

```
cp .env.example .env
```

Edit `.env` with your configuration:

```
DB_HOST=localhost
DB_NAME=AUTOTOK_db
DB_USER=your_database_username
DB_PASS=your_database_password

TIKTOK_CLIENT_KEY=your_tiktok_client_key
TIKTOK_CLIENT_SECRET=your_tiktok_client_secret
TIKTOK_REDIRECT_URI=https://yourdomain.com/callback.php

APP_URL=https://yourdomain.com
APP_NAME=AUTOTOK
APP_EMAIL=hello@example.com
TIMEZONE=UTC

SESSION_LIFETIME=86400
ENCRYPTION_KEY=generate_random_32_character_key

MAX_VIDEO_SIZE=524288000
ALLOWED_VIDEO_TYPES=mp4

CRON_TIME_TOLERANCE=3
```

## Permissions

```
chmod 755 videos/ captions/ logs/
chmod 644 .env
chmod 644 config.php
```

## Cron Job

- Go to Cron Jobs in cPanel
- Command: `/usr/bin/php /home/username/public_html/AUTOTOK/cron_upload.php`
- Interval: Every 5 minutes (`*/5 * * * *`)

---

## 🎯 Usage

### Step 1: Access the Application

Navigate to your installation URL: `https://yourdomain.com`

### Step 2: Authorize with TikTok

1. Click the **"Authorize with TikTok"** button
2. Login to your TikTok account
3. Grant the required permissions
4. You'll be redirected to the dashboard

### Step 3: Add New Profile

1. Click **"Add New"** button
2. Fill in the form:
   - **TikTok Username**: Profile identifier
   - **Upload Videos**: Select multiple MP4 files
   - **Captions**: Enter JSON array of captions
     ```
     ["Amazing content! 🔥", "Check this out! 🎬", "New video! ✨"]
     ```
   - **Schedule Times**: Set up to 3 daily posting times (e.g., 10:00, 15:00, 21:00)
3. Click **"Add Profile"**
4. Wait for upload to complete

### Step 4: Authorize Each Profile

1. Each new profile requires individual TikTok authorization
2. Click the **"Authorize"** button next to the profile
3. Login with the specific TikTok account
4. Status will change to "Authorized" ✅

### Step 5: Sit Back and Relax

Videos will automatically post at your scheduled times. Monitor activity in the Logs section.

### Manual Upload

Need to post immediately? Click the **▶** button next to any authorized profile.

---

## 🔑 How to Get TikTok API Credentials

### Step 1: Create TikTok Developer Account

1. Visit [TikTok for Developers](https://developers.tiktok.com/)
2. Click **"Get Started"** or **"Register"**
3. Login with your TikTok account
4. Complete the developer registration

### Step 2: Create an App

1. Go to **"My Apps"** in the developer portal
2. Click **"Create App"**
3. Fill in the required information:
   - **App Name**: AUTOTOK
   - **Description**: Automated video scheduling application
   - **Category**: Media & Entertainment

### Step 3: Configure Permissions

Enable these scopes/permissions:
- ✅ `user.info.basic` - Get user profile information
- ✅ `video.upload` - Upload videos
- ✅ `video.publish` - Publish videos to TikTok

### Step 4: Set Redirect URI

In the app settings, add your callback URL:
```
https://yourdomain.com/callback.php
```

**Important**: 
- Use HTTPS (required by TikTok)
- Match exactly with `.env` configuration
- Include the full path including `/callback.php`

### Step 5: Copy Credentials

1. Find **Client Key** and **Client Secret** in your app dashboard
2. Copy them to your `.env` file.

### Step 6: Production or Sandbox

For production use, you may need to submit your app for TikTok review. So you can use/test using Sandbox Credentials

---

## 🎯 Feature Requests

Have an idea? We'd love to hear it! Here are some planned features:

### 💡 Request a Feature

1. Check if the feature is already requested in [Issues](../../issues)
2. If not, create a new issue with the `enhancement` label
3. Describe your feature in detail:
   - **Use case**: Why you need it
   - **Expected behavior**: How it should work
   - **Examples**: Similar implementations (if any)

---

## 🤝 Contributing

Contributions are what make the open-source community amazing! Any contributions you make are **greatly appreciated**.

### How to Contribute

1. **Fork the Project**
2. **Clone Your Fork**
3. **Create a Feature Branch**
4. **Make Your Changes**
5. **Commit Your Changes**
6. **Push to Your Fork**
7. **Open a Pull Request**

### Testing

Before submitting:
- [ ] Test on PHP 8.0+
- [ ] Test on desktop and mobile browsers
- [ ] Verify no PHP errors or warnings
- [ ] Check database migrations work correctly
- [ ] Test with actual TikTok API (sandbox account)

---

<div align="center">

[Star](https://github.com/BotolMehedi/autotok-tiktok-automation/stargazers) | [Issue](https://github.com/BotolMehedi/autotok-tiktok-automation/issues) | [Discussion](https://github.com/BotolMehedi/autotok-tiktok-automation/discussions)

</div>

***

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

**TL;DR:** You can use this freely, modify it, sell it, whatever. Just don't blame me if something breaks!😪

***

## ⚠️ Disclaimer

This tool is created for educational and personal use purposes only.
Always comply with TIKTOK Terms of Service and API usage policies.
The creator is not responsible for any misuse, API violations, or account suspensions.
By using this project, you agree that you are doing so at your own risk.

***

## 🙏 Special Thanks

<div align="center"><i>
A massive shoutout to <b>Claude Sonnet</b> for being the ultimate coding buddy who never complained about my debugging sessions & somehow understood my terrible broken English texts😅🙏
</i></div>

***

<div align="center">

### 🌟 Star this repo if you find it helpful!

[Portfolio](https://mehedi.fun) | [Email](mailto:hello@mehedi.fun) | [Github](https://github.com/BotolMehedi)

**Made with ❤️ and lots of 💦 by [BotolMehedi](https://github.com/BotolMehedi)**

</div>