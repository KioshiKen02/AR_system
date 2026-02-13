# Production Setup Guide (Windows / UniserverZ)

This guide covers setting up the **Queue Worker**, **Reverb (WebSocket)**, and **Task Scheduler** for a production environment using UniserverZ.

## Prerequisites
- Ensure **NSSM** (Non-Sucking Service Manager) is installed and added to your System PATH (as per the main README).
- Ensure your `.env` file is configured for production (`APP_ENV=production`, `APP_DEBUG=false`).

---

## 1. Setting up Reverb (WebSocket Server)

Reverb needs to run permanently as a background service.

1. Open **Command Prompt** as Administrator.
2. Install the service:
   ```cmd
   nssm install bilar_reverb
   ```
3. In the NSSM GUI:
   - **Path**: Click `...` and select your PHP executable (e.g., `C:\UniServerZ\core\php\php.exe`).
   - **Startup directory**: Select your project root folder (e.g., `C:\UniServerZ\www\bilar_breeder-ar-local`).
   - **Arguments**: 
     ```
     artisan reverb:start --host=0.0.0.0 --port=8081
     ```
     *(Note: `0.0.0.0` allows access from other computers in the Intranet. Change port `8081` if needed.)*
   - **Service name**: `bilar_reverb`
4. Click **Install service**.
5. Start the service:
   ```cmd
   nssm start bilar_reverb
   ```

---

## 2. Setting up Queue Worker

The Queue Worker processes background jobs (like generating reports).

1. Open **Command Prompt** as Administrator.
2. Install the service:
   ```cmd
   nssm install bilar_queue
   ```
3. In the NSSM GUI:
   - **Path**: Select your PHP executable (`C:\UniServerZ\core\php\php.exe`).
   - **Startup directory**: Select your project root folder.
   - **Arguments**: 
     ```
     artisan queue:work --sleep=3 --tries=3 --timeout=600 --max-jobs=1000 --max-time=3600
     ```
   - **Service name**: `bilar_queue`
4. Click **Install service**.
5. Start the service:
   ```cmd
   nssm start bilar_queue
   ```

---

## 3. Setting up the Scheduler (Task Scheduler)

The Laravel Scheduler handles automated tasks (like daily reports, cleanups, etc.). On Windows, we use **Windows Task Scheduler** instead of NSSM, because it needs to run **once every minute**, not run continuously.

1. Open **Task Scheduler** (Press `Win + R`, type `taskschd.msc`, press Enter).
2. Click **Create Task** (right sidebar).
3. **General Tab**:
   - Name: `BilarARScheduler`
   - Security options: Select **"Run whether user is logged on or not"** and **"Run with highest privileges"**.
4. **Triggers Tab**:
   - Click **New...**
   - Begin the task: **On a schedule**.
   - Select **One time** (start time: 1 minute from now).
   - Check **Repeat task every**: `1 minute`.
   - For a duration of: **Indefinitely**.
   - Click **OK**.
5. **Actions Tab**:
   - Click **New...**
   - Action: **Start a program**.
   - **Program/script**: `C:\UniServerZ\core\php\php.exe` (Adjust path to your PHP).
   - **Add arguments**: `artisan schedule:run`
   - **Start in**: `C:\UniServerZ\www\bilar_breeder-ar-local` (Your project folder).
   - Click **OK**.
6. Click **OK** to save. You may be asked for your Windows password.

---

## Verification

- **Reverb**: Open `http://YOUR_SERVER_IP:8081` in a browser. You should see a Reverb status page or 404 (but a connection is made).
- **Queue**: Check `storage/logs/laravel.log`. If reports are processing, it's working.
- **Scheduler**: Run `php artisan schedule:list` to see if tasks are running.
