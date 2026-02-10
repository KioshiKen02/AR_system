# 🧾 AR System Documentation

## 📘 About AR System

The **Accounts Receivable (AR) System** is a financial management platform that facilitates:

- **Invoice Module** – Generating invoices for other income.  
- **Adjustment Module** – Modifying Sales and Charge Invoices to correct discrepancies or apply credit/debit memos.  
- **Payment Module** – Recording customer payments, where the associated Sales Invoices originate from the In-House Invoicing System.

---

## 🧩 Cloning this Project - Step-by-Step Guide

This guide will help future developers/programmers clone and set up this project with ease.  
While not perfect, it’s very helpful.

### **Step 1: Clone the Project**

1. Open the project repository.  
2. Click the **"Code"** option and copy the **SSH link**.  
3. In your terminal, run:

```bash
git clone git@github.com:IT-Sysdev-2023/bilar_breeder-ar-local.git

Step 2: Create Required Directories
If you encounter errors running the project:

Create sessions and views folders inside storage/framework/

It's okay if they're empty - just create them

Step 3: Handle Storage Issues
If you encounter errors in storage/app/public/images and similar directories:

Delete the .gitignore file

Run git add . and git push again

The project should now work on your local machine

Deployment Guide - Step by Step Setup
Step 1: Environment Configuration
Copy your updated local AR project and rename it to the new project name. Edit the .env file with the following changes:

env
APP_NAME="Bilar Breeder Local" 
# Change to project name (e.g., Cortes Piggery, Rizal Breeder, Marcela Ice Plant)

APP_ENV=local
# Change to: production

APP_DEBUG=true
# Change to: false

APP_URL=http://172.16.43.232:8080
# If 1 project on server: change IP, no port (unless port 80 used by other project)
# If multiple projects: change IP and add unique port

...Installation guide for upcoming developers cloning this project...
step 1: dont forget to create 'sessions, views' inside in the storage/framework path. Its okay if its empty...
 
---if ever there is error in the storage/app/public/images and more, just delete the git ignore file and git add and push again in the github
---- believe god above all ----
DB_DATABASE=bilar_breeder_ar
# Change to your preferred database name

DB_USERNAME=root
# Change to: admin

DB_PASSWORD=
# Set password: "FarMsTeaM"

SESSION_DOMAIN=172.16.43.232
# Change to server IP (no port, even for multiple projects)

REVERB_HOST="172.16.43.232"
# Change to server IP (no port, even for multiple projects)

REVERB_PORT=8081
# Choose unique reverb port (8080, 8081, 8082, etc.)

VITE_REVERB_HOST="172.16.43.232"
# Change to server IP (no port, even for multiple projects)

VITE_REVERB_PORT="8081"
# Choose unique reverb port (must match REVERB_PORT)
Step 2: Code Configuration
Search for "Bilar Breeder Local" in VS Code and add the new case to the switch case.

Important Notes:

Carefully review the code to avoid errors

In PHP files: $baseurl is used

In Vue files: baseurl is used (without $ sign)

For multiple projects on one server: add prefix to route middleware API in web.php

Communicate the API prefix to the invoicing team

<img width="371" height="431" alt="Code Configuration" src="https://github.com/user-attachments/assets/f29f7fc0-3b9b-49e3-ae14-89aaa77c4987" /> 
<img width="567" height="273" alt="Route Configuration" src="https://github.com/user-attachments/assets/f98d6248-2f47-40d3-8c14-daeea213a42f" />
Step 3: Install Dependencies
Navigate to the project folder in terminal and run:

bash
composer install --no-dev --optimize-autoloader
npm install
<img width="659" height="31" alt="Dependencies Installation" src="https://github.com/user-attachments/assets/756fed73-3134-463f-a607-2f2f0386b9af" />
Step 4: Copy to Server
Copy the project folder into the UniServerZ www folder (provided setup).

Location: Paste project inside the WWW folder of UniServerZ

<img width="681" height="203" alt="UniServerZ Folder Structure" src="https://github.com/user-attachments/assets/59566aa5-f3e6-4044-94f4-86bf11e4d828" />
Step 5: Archive UniServerZ
Archive the UniServerZ folder after adding your project.

<img width="337" height="409" alt="Archive UniServerZ" src="https://github.com/user-attachments/assets/b860d081-eac5-42cb-856e-66a6226f075e" />
Step 6: Prepare Installation Files
Copy these files to the server:

Archived UniServerZ

Node-v20

vc_redist

WinRAR

Step 7: Remote Desktop Setup
Use Remote Desktop Connection to access the server and paste copied files into the server's Downloads folder.

<img width="531" height="310" alt="Remote Desktop" src="https://github.com/user-attachments/assets/ea1a4d64-1853-410c-a240-307709880b30" />
Step 8: Install Required Software
Install in this order:

vc_redist

Node v20

WinRAR

Extract archived UniServerZ to Local Disk C

Step 9: Configure Virtual Hosts
Open Notepad as Administrator

Press Ctrl + O and navigate to: UniServerZ\core\apache2\conf\extra

Open httpd-vhosts.conf

<img width="645" height="62" alt="Virtual Hosts Path" src="https://github.com/user-attachments/assets/efeffa16-b9df-4043-8e75-ba18c81d97c3" />
Edit the configuration as shown below. Copy and paste these two blocks, changing the port and project folder name for multiple projects.

<img width="413" height="495" alt="Virtual Hosts Configuration" src="https://github.com/user-attachments/assets/46cfe54e-3abd-45f1-bdeb-a75c43b4f449" />
Step 10: Configure Multiple Projects (If Applicable)
For two or more projects on a single server:

Open Notepad and press Ctrl + O

Navigate to and open httpd.conf

Find Listen ${AP_PORT} and add Listen 8090 or Listen 8091 etc. (depending on ports used)

Save the file

<img width="662" height="161" alt="HTTPD Configuration" src="https://github.com/user-attachments/assets/e2e1c40e-03b6-43f8-8378-bc8e6febb938" /> <img width="620" height="343" alt="Listen Ports" src="https://github.com/user-attachments/assets/3cb91e2f-bd91-43d1-97a8-b9b5e8f31ab2" />
Step 11: Start Services
Open UniController as Administrator

Start both Apache and MySQL

Click "Server Console"

<img width="522" height="338" alt="UniController" src="https://github.com/user-attachments/assets/3d8a3ac7-0606-4ebd-b8c0-3080f3bfcb05" />
Step 12: Project Optimization
In Server Console, navigate to project folder:

bash
cd www
cd Project_Name
Run optimization commands:

bash
php artisan optimize:clear
If you encounter errors:

Go to bootstrap/cache/

Delete config, packages, and services folders

Run commands again:

bash
php artisan optimize:clear
php artisan config:cache
<img width="670" height="201" alt="Artisan Commands" src="https://github.com/user-attachments/assets/7b20ba21-1470-4be2-9028-847ff6f9f1a3" />
Step 13: Database and Build Setup
Run migrations:

bash
php artisan migrate
<img width="626" height="90" alt="Migration" src="https://github.com/user-attachments/assets/519189dd-5224-43ab-87d7-6d076af36666" />
If migration fails with rights error:

Open MySQL console in UniController

Run appropriate GRANT commands (replace bilar_breeder_ar_live with your database name)

Set up storage:

bash
rmdir /s /q public\storage
php artisan storage:link
Build assets:

bash
npm run dev
# Once successful, terminate with Ctrl+C
npm run build
Step 14: Firewall Configuration
Configure Windows Firewall to allow ports:

Go to Firewall → Advanced Settings

Click Inbound Rules → New Rule

Select "Port" and add:

Default port (80)

Additional ports used (8080, 8090, 8081, 8000, etc.)

Port 3306 for MySQL remote access

<img width="440" height="330" alt="Firewall Setup" src="https://github.com/user-attachments/assets/97410ff6-cabc-4049-a638-1822a22618f0" /> <img width="374" height="193" alt="Port Selection" src="https://github.com/user-attachments/assets/0855b967-78f1-4532-ab4d-018d7014b157" /> <img width="384" height="303" alt="Rule Completion" src="https://github.com/user-attachments/assets/90922177-3257-4be7-9b70-e3cc1586f8dd" />
Step 15: NSSM Setup for Background Services
Go to Environment System Variables

Click "Path" in System Variables section

Add NSSM path from UniServerZ folder (choose win64)

<img width="620" height="407" alt="Environment Variables" src="https://github.com/user-attachments/assets/4e72537f-3e0e-4bb7-9f56-53f3e4492245" /> <img width="652" height="94" alt="NSSM Path" src="https://github.com/user-attachments/assets/3b5680f2-84e5-43e5-ace2-a5d6528006b6" />
Step 16: Install Reverb and Queue Services
Open Command Prompt as Administrator.

For Reverb:

bash
nssm install project_name_reverb
Configure with:

Startup directory: Project directory

Arguments: Based on IP address and reverb port used

<img width="432" height="232" alt="Reverb Setup" src="https://github.com/user-attachments/assets/3788f7f0-fb3e-44b1-85c0-c2343b09961d" />
For Queue:

bash
nssm install project_name_queue
Configure with:

Startup directory: Project directory

Arguments:

bash
artisan queue:work --sleep=3 --tries=3 --timeout=600 --max-jobs=100 --max-time=3600
<img width="554" height="298" alt="Queue Setup" src="https://github.com/user-attachments/assets/1548cd8f-9323-4bf5-83bc-80f2c62bc58a" />
Step 17: Start Services
bash
nssm start project_name_reverb
nssm start project_name_queue
Step 18: Remote Database Access
Open MySQL console and run (replace with your details):

sql
GRANT ALL PRIVILEGES ON cortes_piggery_ar_live.* TO 'remote_root'@'your_pc_ip_address' IDENTIFIED BY 'FarMsTeaM';
<img width="643" height="76" alt="Database Access" src="https://github.com/user-attachments/assets/141e902f-3103-4e91-8bf9-be6bf397fa4f" />
Step 19: HeidiSQL Remote Access
Open HeidiSQL and click "Add New"

Configure connection:

Hostname/IP: Server's IP address

User: Username you created

Password: FarMsTeaM

Click "Open" to access database remotely

<img width="471" height="334" alt="HeidiSQL Configuration" src="https://github.com/user-attachments/assets/f8a3e7b2-d5ed-49a1-ae23-1c6bab064645" />
Step 20: Import Default Data
Import the provided SQL files:

users.sql

permissions.sql

## ⚠️ **Common Issues & Fixes**

### ❌ **1. Images Not Showing**

✔ Run:

```bash
php artisan storage:link
```

---

### ❌ **2. Report Progress Stuck at 100%**

This is caused by incorrect queue settings.

✔ Open `config/queue.php` and ensure:

```php
'default' => env('QUEUE_CONNECTION', 'database'),
```

And the **connection** is using **mysql**.

---

### ❌ **3. Report Generation Shows 403 Access Denied**

Example error:

> *ERROR 403 Access Denied – The gates are firmly shut…*

This happens when `storage:link` is not properly created.

✔ Solution:

```bash
php artisan storage:link
```

Make sure it runs **successfully**, and ensure:

* No `public/storage` folder existed before linking.
* Your generated files are saved under:

  ```
  storage/app/public/
  ```

---

### ❌ **4. Report Generation Failed to preview**

Example error:

> *ERROR 403 Access Denied – Failed to preview report*

This happens when `nssm reverb and queue` is not properly created.

✔ Solution:

Check again the nssm reverb the host and port must match on the server ip and the host must match on what is set in the reverb found in the .env file.
Then restart the nssm reverb and nssm queue that you setup.

---

### ❌ **5. The Pdf job report doesnt show error, and stuck in 98% or 100%**

✔ Solution: The problem is the job executed uses the default .env setup. It doesnt use the database set in the session. so that why the data dont match because the request use the session data and the job compare it to the .env database set.
So the solution is you must pass the session active database and use it in the controller and pass to job file to override the job to use the session database setup.

---
### ❌ **6. The report progress stop at 98%**

✔ Solution: There is error in generating the report in pdf file. check it and if you want to know the exact error check the laravel.log file of the project.
---

### ❌ **7. The report progress doesnt load**

✔ Solution: Check the console log by pressing f12, if there is broadcasting error then check the nssm in the server and restart it, or check the configuration if its correct.

---
## This are the credentials for all server filezilla username and password
*ALL UBAY SERVER
username : ar_ubay
password : ar_ubay2026 

*BILAR SERVER
username : ar_bilar
password : ar2026

*ICE AND PEANUT KISSES SERVER
username : ar_icepk
password : ar2026

*CORTES PIGGERY AND POULTRY SERVER
username : ar_cortes
password : ar2026

*CANHAYUPON SERVER
username : ar_canhayupon
password : ar2026

*BILAR HATCHERY SERVER
username : ar_hatchery
password : ar2026

*LAPSAON SERVER
username : ar_lapsaon
password : ar2026

*RIZAL BREEDER SERVER
username : ar_rizal
password : ar2026

## This setup is for giving access to database from centralized project using UniServerZ
# Please note that the commands there is just a sample just change the thing base on the user and the database name you created. All commands there is just a guide so please be watchful.

*First is that you must give access the first database or the main database that is setup on the project .env file

*The database is automatically created so youll need to do is to create access to specific user
Run this command for giving access to specific user
```bash
CREATE USER 'SampleUser'@'172.16.42.91' IDENTIFIED BY 'FarMsTeaM';
```
*That command create a user that is being recieve the access and its corresponding ip address and the password of the database to access
*Next is grant that user the access of the database
```bash
GRANT ALL PRIVILEGES ON sample_database.* TO 'SampleUser'@'172.16.42.91'; 
```
then run 
```bash
FLUSH PRIVILEGES;
```
finally run this command
```bash
GRANT ALL PRIVILEGES ON `sample_database`.* TO 'admin'@'localhost';
FLUSH PRIVILEGES;
```
showing all username that being given an access and privileges
```bash
SELECT user, host FROM mysql.user;
```
Thats all for the first and main database setup in the project .env file

*Next is to add more database on the same user
*First is creating new database, run this command
```bash
CREATE DATABASE second_sample_database;
```
then run this command
```bash
GRANT ALL PRIVILEGES ON second_sample_database.* TO 'SampleUser'@'172.16.42.91'; 
FLUSH PRIVILEGES;
```
finally run this command
```bash
GRANT ALL PRIVILEGES ON `second_sample_database`.* TO 'admin'@'localhost';
FLUSH PRIVILEGES;
```
Please update this documentation with any new server setups you create. These steps are very helpful for upcoming developers of this system.

Thank you and God bless on your journey...
