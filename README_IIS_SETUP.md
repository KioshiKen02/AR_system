IIS DEPLOYMENT SETUP (WINDOWS)
===============================

This guide explains how to deploy this Laravel application on **IIS** step‑by‑step, including what to install and where to download it.

---

PREREQUISITES AND DOWNLOADS
---------------------------

**Operating system**
- Windows Server 2019/2022 or Windows 10/11 (Pro/Enterprise) with IIS support

**Software you need**
- **IIS (Internet Information Services)**  
  - Built into Windows (no separate download)  
  - Enabled via “Turn Windows features on or off”

- **PHP for Windows (x64, Non‑Thread‑Safe build)**  
  - Download: https://windows.php.net/download/  
  - Choose version compatible with your project (for example PHP 8.1 or 8.2)  
  - Use the **x64 Non Thread Safe** ZIP package (e.g. `php-8.2.x-nts-Win32-vs16-x64.zip`)

- **Microsoft Visual C++ Redistributable** (required by PHP)  
  - For PHP 8.x built with VS16:  
  - Download: https://aka.ms/vs/17/release/vc_redist.x64.exe 
  - Install the **x64** package matching your PHP build

- **IIS URL Rewrite Module 2.1** (required for pretty URLs)  
  - Download: https://www.iis.net/downloads/microsoft/url-rewrite  
  - Install `rewrite_amd64_en-US.msi` (for 64‑bit)

- **Composer** (PHP dependency manager)  
  - Download: https://getcomposer.org/download/  
  - Install globally so `composer` is available in PowerShell / cmd

- **Node.js (LTS)** (for building frontend assets)  
  - Download: https://nodejs.org/  
  - Install the **LTS** version

---

STEP 1 – ENABLE IIS AND REQUIRED FEATURES
-----------------------------------------

1. Open **Control Panel → Programs → Turn Windows features on or off**.
2. Check **Internet Information Services**.
3. Under **Web Management Tools**, enable:
   - IIS Management Console
4. Under **World Wide Web Services → Application Development Features**, enable:
   - .NET Extensibility
   - **CGI**
   - ISAPI Extensions
   - ISAPI Filters
5. Under **Security**, you can enable:
   - Request Filtering
6. Click **OK** and let Windows install IIS.

Verify:
- Open a browser and go to `http://localhost`. You should see the IIS welcome page.

---

STEP 2 – INSTALL PHP ON WINDOWS
-------------------------------

1. Download the **x64 Non Thread Safe** PHP ZIP from:  
   https://windows.php.net/download/
2. Extract the ZIP to a folder, for example:  
   `C:\php\php-8.2`
3. Copy `php.ini-production` to `php.ini` in the same folder.
4. Edit `C:\php\php-8.2\php.ini` and adjust at minimum:
   - `extension_dir = "ext"`
   - Enable common extensions (remove `;` at the start), for example:
     - `extension=curl`
     - `extension=mbstring`
     - `extension=fileinfo`
     - `extension=openssl`
     - `extension=pdo_mysql`
5. Add PHP folder to the **PATH** environment variable:
   - System Properties → Advanced → Environment Variables
   - Under “System variables”, edit `Path` and add: `C:\php\php-8.2`

---

STEP 3 – INSTALL URL REWRITE MODULE
-----------------------------------

1. Download the URL Rewrite Module from:  
   https://www.iis.net/downloads/microsoft/url-rewrite
2. Run the MSI installer and complete setup.
3. After installation, restart **IIS Manager** if it was open.

---

STEP 4 – CONFIGURE PHP WITH FASTCGI IN IIS
------------------------------------------

1. Open **IIS Manager** (`inetmgr`).
2. In the **Connections** panel, select the server node (top machine name).
3. Open **FastCGI Settings**.
4. Add a new **FastCGI Application**:
   - Full Path: `C:\php\php-8.2\php-cgi.exe`
   - Arguments: *(leave empty)*
   - Max Instances: default is fine
5. Open the new FastCGI entry’s **Environment Variables** and add:
   - Name: `PHPRC`  
     Value: `C:\php\php-8.2`
6. Click **OK** to save.

Next, add handler mapping:

1. Still in IIS Manager, select the server node (or the specific site later).
2. Open **Handler Mappings**.
3. Add Module Mapping:
   - Request path: `*.php`
   - Module: `FastCgiModule`
   - Executable: `C:\php\php-8.2\php-cgi.exe`
   - Name: `PHP_via_FastCGI`
4. When prompted to create a FastCGI application, choose **Yes** (if not already created).

---

STEP 5 – DEPLOY THE APPLICATION CODE
------------------------------------

Assuming the application is located at:

`C:\laragon\www\ar_system`

You have two options:

**Option A – Use the existing Laragon folder (simple, same machine)**

1. Keep the project where it is:  
   `C:\laragon\www\ar_system`
2. In IIS, set the site **Physical path** to:
   `C:\laragon\www\ar_system\public`

**Option B – Copy to a dedicated IIS folder (optional)**

If you want a separate “server” location (for example on another machine):

1. Copy the entire project folder (excluding `node_modules` if desired) to the server:
   - Example: `C:\inetpub\ar_system`
2. Ensure the Laravel **public** directory will be the web root:
   - Final public path: `C:\inetpub\ar_system\public`

---

STEP 6 – INSTALL PHP DEPENDENCIES (COMPOSER)
--------------------------------------------

In PowerShell (or cmd), from the project root (choose the path you are using):

```bash
# If using Laragon path
cd C:\laragon\www\ar_system

# OR, if you copied to inetpub
cd C:\inetpub\ar_system

composer install --no-dev --optimize-autoloader
```

This installs all Laravel and PHP dependencies required by the application.

---

STEP 7 – INSTALL AND BUILD FRONTEND ASSETS
------------------------------------------

From the same project root:

```bash
cd C:\inetpub\ar_system
npm install
npm run build
```

This builds production assets into `public/build` (or the configured output directory).

---

STEP 8 – CONFIGURE ENVIRONMENT (.env)
-------------------------------------

1. Copy `.env.example` to `.env` (if not already done):

   ```bash
   cd C:\inetpub\ar_system
   copy .env.example .env
   ```

2. Edit `C:\inetpub\ar_system\.env` and configure:
   - `APP_NAME=Ar System`
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL=http://your-domain-or-ip`
   - Database settings (`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)
   - Any tenant‑specific or API settings used by this project

3. Generate the application key:

   ```bash
   php artisan key:generate
   ```

4. Clear and cache configuration:

   ```bash
   php artisan config:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

---

STEP 9 – RUN DATABASE MIGRATIONS
--------------------------------

From the project root:

```bash
cd C:\inetpub\ar_system
php artisan migrate --force
```

Run any required seeders if applicable:

```bash
php artisan db:seed --force
```

---

STEP 10 – CREATE THE IIS SITE
-----------------------------

1. Open **IIS Manager**.
2. In the **Connections** panel, right‑click **Sites → Add Website…**
3. Configure:
   - **Site name**: `ArSystem` (or any name)
   - **Physical path**: `C:\inetpub\ar_system\public`
   - **Binding**:
     - Type: `http`
     - IP address: `All Unassigned` or specific server IP
     - Port: `80` (or other)
     - Host name: `your-domain` (if using DNS)
4. Click **OK** to create the site.

Set default documents:

1. With the new site selected, open **Default Document**.
2. Ensure `index.php` is listed and at the top (or add it if missing).

---

STEP 11 – FOLDER PERMISSIONS
----------------------------

IIS needs write access to Laravel’s storage and cache directories.

1. In File Explorer, go to `C:\inetpub\ar_system`.
2. Right‑click **storage** → Properties → Security → Edit.
3. Add:
   - `IIS_IUSRS`
   - (Optionally) the specific application pool identity (e.g. `IIS AppPool\ArSystem`)
4. Grant **Modify** and **Write** permissions to:
   - `storage`
   - `bootstrap/cache`

Read permissions:
- Ensure IIS users have **Read & Execute** access to the rest of the project.

---

STEP 12 – VERIFY THE APPLICATION
--------------------------------

1. Start/Restart the site in **IIS Manager**.
2. In a browser, navigate to:
   - `http://localhost` (if bound to port 80 without host header), or
   - `http://your-domain` (if you configured a host name and DNS).
3. You should see the Laravel application login/dashboard.

If you see a 500 error:
- Check the **Windows Event Viewer** and `storage/logs/laravel.log`.
- Verify:
  - Database credentials in `.env` are correct.
  - `storage` and `bootstrap/cache` permissions are correct.
  - PHP path and handler mappings are correct.

---

OPTIONAL – HTTPS (SSL) SETUP
----------------------------

To add HTTPS:

1. Install or import an SSL certificate in **IIS → Server Certificates**.
2. Add an **HTTPS binding** on the site:
   - Type: `https`
   - Port: `443`
   - Select the appropriate certificate.
3. Update `APP_URL=https://your-domain` in `.env`.

You can then optionally add URL Rewrite rules to redirect HTTP to HTTPS.
