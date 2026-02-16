# Production Setup Guide (Windows / Laragon)

This guide covers setting up the **Queue Worker**, **Reverb (WebSocket)**, and **Task Scheduler** for a production environment using Laragon.

## Prerequisites
- Ensure **NSSM** (Non-Sucking Service Manager) is installed and added to your System PATH.
- Ensure your `.env` file is configured for production (`APP_ENV=production`, `APP_DEBUG=false`).

---

## 0. Handling Existing Laragon (Multiple PHP Versions)

If the server already has an older version of Laragon running other projects, **do not change the global PHP version**, as it might break older projects. Instead, you can run multiple PHP versions simultaneously:

1. **Add the New PHP Version**: 
   - Download the **Thread Safe** PHP version required (e.g., PHP 8.2 or 8.3) from [windows.php.net](https://windows.php.net/download/).
   - Extract it to `C:\laragon\bin\php\`.
   - **Tip**: You can rename the extracted folder to something shorter like `php-8.2.4` to make your configuration files easier to read.

2. **Isolate the PHP Version for This Project**:
   To keep old projects on their current PHP version while this project uses the new one, add a `FilesMatch` block inside your `<VirtualHost>` configuration:

   ```apache
   <VirtualHost *:81> 
       DocumentRoot "D:/laragon/www/ar_system/public/" 
       ServerName ar_system.test 
       ServerAlias *.ar_system.test 

       <Directory "D:/laragon/www/ar_system/public/"> 
           AllowOverride All 
           Require all granted 
       </Directory> 

       # ADD THIS SECTION TO USE A SPECIFIC PHP VERSION
       <FilesMatch "\.php$">
           SetHandler fcgid-script
           # Change the path below to your specific PHP version's php-cgi.exe
           FcgidWrapper "D:/laragon/bin/php/php-8.2.4/php-cgi.exe" .php
       </FilesMatch>
   </VirtualHost> 
   ```
   *(Note: Replace `php-8.x.x` with the actual folder name of your new PHP version.)*

3. **Background Services (NSSM)**:
   The **Queue** and **Reverb** services always use the specific PHP path you provide during installation. Even if Laragon is running an older PHP globally for the web server, your background services will correctly run on the newer PHP because you will point NSSM directly to `C:\laragon\bin\php\php-8.x.x\php.exe`.

---

## 1. Setting up Reverb (WebSocket Server)

Reverb needs to run permanently as a background service.

1. Open **Command Prompt** as Administrator.
2. Install the service:
   ```cmd
   nssm install ar_sys_reverb
   ```
3. In the NSSM GUI:
   - **Path**: Click `...` and select your PHP executable (e.g., `D:\laragon6\bin\php\php-8.2.4\php.exe`).
   - **Startup directory**: Select your project root folder (e.g., `D:\laragon6\www\ar_system`).
   - **Arguments**: 
     ```
     artisan reverb:start --host=0.0.0.0 --port=6001
     ```
   - **Service name**: `ar_sys_reverb`
4. Click **Install service**.
5. Start the service:
   ```cmd
   nssm start ar_sys_reverb
   ```

---

## 2. Setting up Queue Worker

1. Open **Command Prompt** as Administrator.
2. Install the service:
   ```cmd
   nssm install ar_sys_queue        
   ```
3. In the NSSM GUI:
   - **Path**: Select your Laragon PHP executable.
   - **Startup directory**: Select your project root folder.
   - **Arguments**: 
     ```
     artisan queue:work --sleep=3 --tries=3 --timeout=600
     ```
   - **Service name**: `ar_sys_queue`
4. Click **Install service**.
5. Start the service:
   ```cmd
   nssm start ar_sys_queue
   ```

---

## 3. Setting up the Scheduler

1. Open **Task Scheduler** (Press `Win + R`, type `taskschd.msc`).
2. Click **Create Task**.
3. **General Tab**:
   - Name: `ARSystemScheduler`
   - Select **"Run whether user is logged on or not"** and **"Run with highest privileges"**.
4. **Triggers Tab**:
   - New... -> Begin: **On a schedule** -> One time.
   - Check **Repeat task every**: `1 minute` for a duration of **Indefinitely**.
5. **Actions Tab**:
   - New... -> Action: **Start a program**.
   - **Program/script**: `C:\laragon\bin\php\php-8.2.4\php.exe`
   - **Add arguments**: `artisan schedule:run`
   - **Start in**: `C:\laragon\www\ar_system`      
6. Click **OK**.

---

## Automation (Optional but Recommended)

You can use the provided `setup-services.ps1` script in the root folder to automate these steps via PowerShell.

---

## 4. Database Transfer (MySQL/MariaDB)

To move your database from the old server (e.g., UniServerZ or another Laragon) to the new Laragon setup:

### A. Export from the Source Server
- Use `mysqldump` to generate a portable backup (recommended over GUI exports).

```cmd
"C:\Path\To\mysqldump.exe" -u root -p ^
  --databases bilar_breeder_ar ^
  --routines --triggers --events ^
  --single-transaction --hex-blob ^
  --default-character-set=utf8mb4 > C:\backup\bilar_ar_YYYYMMDD.sql
```
- If your source DB uses `utf8mb4_0900_ai_ci` and the target is MariaDB (which may not support 0900 collations), open the dump file and replace `utf8mb4_0900_ai_ci` with `utf8mb4_general_ci`.

### B. Create the Database on Laragon
- Start MySQL from Laragon.
- Create the database if it doesn’t exist:
```sql
CREATE DATABASE IF NOT EXISTS bilar_breeder_ar CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

### C. Import into Laragon
- Use the matching `mysql.exe` for your Laragon MySQL/MariaDB:
```cmd
"C:\laragon\bin\mysql\mysql-8.x.x\bin\mysql.exe" -u root -p < C:\backup\bilar_ar_YYYYMMDD.sql
```

### D. Create/Grant an Application User
- If you don’t want to use root, create an app user and grant privileges:
```sql
CREATE USER IF NOT EXISTS 'admin'@'localhost' IDENTIFIED BY 'FarMsTeaM';
GRANT ALL PRIVILEGES ON bilar_breeder_ar.* TO 'admin'@'localhost';
FLUSH PRIVILEGES;
```
- For remote access (optional), use `'admin'@'%'` and ensure Windows Firewall allows inbound port 3306 and MySQL is listening on the desired interface.

### E. Update Application Configuration
- Edit `.env` and set:
```
DB_DATABASE=bilar_breeder_ar
DB_USERNAME=admin
DB_PASSWORD=FarMsTeaM
DB_HOST=127.0.0.1
DB_PORT=3306
```
- Clear and cache config:
```cmd
php artisan optimize:clear
php artisan config:cache
```

### F. Verify
- Run a simple query or `php artisan migrate --force` to ensure connectivity.
- Check [config/database.php](file:///c:/laragon/www/bilar_breeder-ar-local/config/database.php) if custom settings are needed.
