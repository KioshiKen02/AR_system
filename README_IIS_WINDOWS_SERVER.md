IIS DEPLOYMENT – WINDOWS SERVER 2019/2022
=========================================

This guide is specifically for deploying this Laravel application on **Windows Server 2019/2022** using IIS. The steps and UI match what you see in **Server Manager** and **IIS Manager** on Windows Server.

---

PREREQUISITES AND DOWNLOADS
---------------------------

**Operating system**
- Windows Server 2019 or Windows Server 2022

**Software you need**
- **IIS (Internet Information Services)**  
  - Built into Windows Server (no separate download)  
  - Enabled via **Server Manager → Add Roles and Features**

- **PHP for Windows (x64, Non-Thread-Safe build)**  
  - Download: https://windows.php.net/download/  
  - Use the **x64 Non Thread Safe** ZIP package (e.g. `php-8.2.x-nts-Win32-vs16-x64.zip`)  
  - Extract to a folder like `C:\php\php-8.2`

- **Microsoft Visual C++ Redistributable** (required by PHP)  
  - For PHP 8.x built with VS16:  
  - Download: https://aka.ms/vs/17/release/vc_redist.x64.exe  
  - Install the **x64** package

- **IIS URL Rewrite Module 2.1** (for Laravel pretty URLs)  
  - Download: https://www.iis.net/downloads/microsoft/url-rewrite  
  - Install `rewrite_amd64_en-US.msi`

- **Composer** (PHP dependency manager)  
  - Download: https://getcomposer.org/download/  
  - Install globally so `composer` is available in PowerShell / cmd

- **Node.js (LTS)** (for building frontend assets, optional on the server)  
  - Download: https://nodejs.org/  
  - Install the **LTS** version

> Note: You can build frontend assets on your dev machine and deploy the built files, so Node.js on the server is optional.

---

STEP 1 – ENABLE IIS USING SERVER MANAGER
----------------------------------------

1. Open **Server Manager**.
2. In the top-right, click **Manage → Add Roles and Features**.
3. In the wizard:
   - **Installation type**:  
     - Choose **Role-based or feature-based installation**.
   - **Server selection**:  
     - Select your local server.
4. On **Server Roles**:
   - Check **Web Server (IIS)**.
5. Under **Web Server (IIS) → Web Server → Management Tools**:
   - Enable **IIS Management Console**.
6. Under **Web Server (IIS) → Web Server → Application Development**:
   - Enable:
     - .NET Extensibility
     - **CGI**
     - ISAPI Extensions
     - ISAPI Filters
7. Under **Web Server (IIS) → Web Server → Security** (optional but useful):
   - Enable **Request Filtering**.
8. Complete the wizard and let Windows install IIS.

Verify:
- Open **IIS Manager** (`inetmgr`) from Start.
- Open a browser on the server and go to `http://localhost`. You should see the IIS welcome page.

---

STEP 2 – INSTALL PHP ON WINDOWS SERVER
--------------------------------------

Option A – Standalone PHP (recommended for IIS)

1. Download the **x64 Non Thread Safe** PHP ZIP from:  
   https://windows.php.net/download/
2. Extract the ZIP to a folder, for example:  
   `C:\php\php-8.2`
3. In `C:\php\php-8.2`, copy `php.ini-production` to `php.ini`.
4. Edit `C:\php\php-8.2\php.ini` and at minimum:
   - Set the extension directory:
     ```ini
     extension_dir = "ext"
     ```
   - Enable common extensions (remove `;` at the start), for example:
     ```ini
     extension=curl
     extension=mbstring
     extension=fileinfo
     extension=openssl
     extension=pdo_mysql
     extension=zip
     ```
5. Add PHP to the system `PATH`:
   - Right-click **This PC → Properties → Advanced system settings**.
   - Click **Environment Variables…**.
   - Under **System variables**, select `Path` → **Edit**.
   - Add: `C:\php\php-8.2`
   - Click **OK** to save.

Option B – Use Laragon’s PHP for CLI (what you already have)

- For **CLI commands** (`php artisan`, `composer`), you can continue using Laragon’s PHP.  
- For **IIS FastCGI**, it’s cleaner to point IIS to a standalone PHP like `C:\php\php-8.2\php-cgi.exe` as in Option A, but you can also point IIS to Laragon’s `php-cgi.exe` if desired.

---

STEP 3 – INSTALL URL REWRITE MODULE
-----------------------------------

1. Download URL Rewrite from:  
   https://www.iis.net/downloads/microsoft/url-rewrite
2. Run the MSI installer (`rewrite_amd64_en-US.msi`) and complete setup.
3. Close and reopen **IIS Manager** if it was open.

---

STEP 4 – CONFIGURE PHP WITH FASTCGI IN IIS
------------------------------------------

1. Open **IIS Manager** (`inetmgr`).
2. In the **Connections** panel, click the server node (top machine name).
3. In the middle panel, double-click **FastCGI Settings**.
4. Add a new **FastCGI Application**:
   - **Full Path**:  
     `C:\php\php-8.2\php-cgi.exe`
   - **Arguments**: leave empty
   - **Max Instances**: leave at `0` (IIS auto-manages)
5. Select the new FastCGI entry and click **Edit…**.
6. Click **Environment Variables…** and add:
   - Name: `PHPRC`  
     Value: `C:\php\php-8.2`
7. Click **OK** to save.

Next, add a handler mapping for `.php`:

1. Still with the server node selected, open **Handler Mappings**.
2. Click **Add Module Mapping…** on the right.
3. Fill:
   - **Request path**: `*.php`
   - **Module**: `FastCgiModule`
   - **Executable**: `C:\php\php-8.2\php-cgi.exe`
   - **Name**: `PHP_via_FastCGI`
4. Click **OK**. If prompted to create a FastCGI application, choose **Yes**.

---

STEP 5 – DEPLOY THE APPLICATION CODE
------------------------------------

Assuming the project is currently on the server at:

`C:\laragon\www\ar_system`

You can either keep it there or copy it to a dedicated IIS folder.

**Option A – Use the existing Laragon folder**

1. Keep the project where it is:  
   `C:\laragon\www\ar_system`
2. In IIS, the site **Physical path** will be:  
   `C:\laragon\www\ar_system\public`

**Option B – Copy to a dedicated IIS folder (recommended for clean server setup)**

1. Copy the entire project folder (excluding `node_modules` if desired) to:  
   `C:\inetpub\ar_system`
2. Ensure the Laravel **public** directory is the web root:  
   `C:\inetpub\ar_system\public`

The remaining steps are the same regardless of which path you choose; just substitute the correct root path where appropriate.

---

STEP 6 – INSTALL PHP DEPENDENCIES (COMPOSER)
--------------------------------------------

In **PowerShell** or **cmd**, from the project root:

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

If you build assets on the **server**:

```bash
cd C:\laragon\www\ar_system
# or: cd C:\inetpub\ar_system

npm install
npm run build
```

If you prefer to build on your **dev machine**:

1. Build locally on your development PC (Laragon environment).
2. Copy the built assets (typically in `public/` or `public/build`) to the server’s project `public` folder.
3. In that case, Node.js is not required on the server.

---

STEP 8 – CONFIGURE ENVIRONMENT (.env)
-------------------------------------

If you already have `.env` configured from Laragon, you can reuse it. Just confirm the values make sense for the server.

From the project root:

```bash
cd C:\laragon\www\ar_system
# or: cd C:\inetpub\ar_system
```

Check `.env`:

- `APP_NAME="Ar System"`
- `APP_ENV=local` (you can keep this while testing; later use `production`)
- `APP_DEBUG=true` (for initial testing; set to `false` in production)
- `APP_URL=http://172.16.42.112` (or your actual IIS URL)
- Database settings (`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)

Important:

- `APP_URL` should match how you access the site in the browser:
  - If you use the IP: `http://172.16.42.112`
  - If you use a hostname (via DNS): `http://your-domain`

After adjusting `.env`, run:

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
cd C:\laragon\www\ar_system
# or: cd C:\inetpub\ar_system

php artisan migrate --force
php artisan db:seed --force  # if you use seeders
```

---

STEP 10 – CREATE THE IIS SITE (WINDOWS SERVER UI)
-------------------------------------------------

1. Open **IIS Manager** on the server.
2. In the **Connections** panel, right-click **Sites → Add Website…**
3. Configure:
   - **Site name**: `ArSystem` (or any name)
   - **Physical path**:
     - `C:\laragon\www\ar_system\public`  
       or `C:\inetpub\ar_system\public`
   - **Binding**:
     - Type: `http`
     - IP address: `172.16.42.112` (or `All Unassigned` if only this app)
     - Port: `80`
     - Host name: leave empty for now (or set a DNS name if you have one)
4. Click **OK** to create the site.

Set default documents:

1. Select the new **ArSystem** site.
2. In the middle panel, double-click **Default Document**.
3. Ensure `index.php` is listed. If not:
   - Click **Add…**
   - Enter `index.php`
   - Move it near the top if needed.

Stop the Default Web Site (to avoid conflicts):

1. In **Sites**, click **Default Web Site**.
2. On the right, click **Stop**.

Ensure your new site is started:

1. Click the **ArSystem** site.
2. On the right, click **Start** if it’s not running.

---

STEP 11 – FOLDER PERMISSIONS (WINDOWS SERVER)
---------------------------------------------

IIS needs write access to Laravel’s storage and cache directories.

If your root is `C:\laragon\www\ar_system`:

1. Open an elevated **PowerShell** (Run as Administrator).
2. Grant **Modify** permissions to IIS_IUSRS:

```powershell
icacls "C:\laragon\www\ar_system\storage" /grant "IIS_IUSRS:(OI)(CI)M" /T
icacls "C:\laragon\www\ar_system\bootstrap\cache" /grant "IIS_IUSRS:(OI)(CI)M" /T
```

If your root is `C:\inetpub\ar_system`, use:

```powershell
icacls "C:\inetpub\ar_system\storage" /grant "IIS_IUSRS:(OI)(CI)M" /T
icacls "C:\inetpub\ar_system\bootstrap\cache" /grant "IIS_IUSRS:(OI)(CI)M" /T
```

This ensures IIS can write logs, sessions, and caches.

---

STEP 12 – LARAVEL-FRIENDLY URL REWRITE (web.config)
----------------------------------------------------

In the project’s `public` folder (`C:\laragon\www\ar_system\public` or `C:\inetpub\ar_system\public`), ensure `web.config` contains a Laravel rewrite rule:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <defaultDocument>
            <files>
                <add value="index.php" />
            </files>
        </defaultDocument>
        <rewrite>
            <rules>
                <rule name="Laravel" stopProcessing="true">
                    <match url="^(.*)$" ignoreCase="false" />
                    <conditions logicalGrouping="MatchAll">
                        <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
                    </conditions>
                    <action type="Rewrite" url="index.php/{R:1}" appendQueryString="true" />
                </rule>
            </rules>
        </rewrite>
    </system.webServer>
</configuration>
```

This makes IIS behave like Apache’s `.htaccess` for Laravel routes.

---

STEP 13 – VERIFY THE APPLICATION
--------------------------------

1. In **IIS Manager**, select the `ArSystem` site and click **Restart**.
2. Open a browser (on the server or a client on the same network) and navigate to:
   - `http://172.16.42.112/` (if using only IP)
   - or `http://your-domain/` (if DNS is configured)
3. You should see the Laravel application (login/landing).

If you see a 404 for `/arsystem`:
- That is the Laravel tenant prefix. The `web.config` rewrite rule must be present so `/arsystem` is handled by `index.php`.

If you see a 500 error:
- Check `storage/logs/laravel.log` on the server for the exact error.
- Verify:
  - Database credentials in `.env`.
  - Permissions on `storage` and `bootstrap/cache`.
  - PHP FastCGI path and handler mappings.

---

OPTIONAL – HTTPS (SSL) ON WINDOWS SERVER
----------------------------------------

To enable HTTPS:

1. In **IIS Manager**, click the server node.
2. Open **Server Certificates**.
3. Import or create a certificate (from a CA or self-signed).
4. Select the `ArSystem` site → **Bindings…**
5. Click **Add…**:
   - Type: `https`
   - IP address: `172.16.42.112` (or `All Unassigned`)
   - Port: `443`
   - Host name: your DNS name (if used)
   - SSL certificate: select your certificate
6. Update `.env`:

```env
APP_URL=https://your-domain
```

7. Optionally add a redirect rule in `web.config` to force HTTP to HTTPS.

