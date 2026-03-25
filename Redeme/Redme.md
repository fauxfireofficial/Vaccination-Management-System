===========================================
     VACCINATION MANAGEMENT SYSTEM
          (0-18 Years Child Immunization)
===========================================

PROJECT OVERVIEW
===========================================
A complete web-based system for child immunization tracking.
Parents can register children, book appointments, and track 
vaccination history. Hospitals can manage appointments and 
update vaccination status. Admin has full control over users,
hospitals, and system settings.

FEATURES
===========================================
✅ User Registration with OTP Verification
✅ Role-based Login (Parent, Hospital, Admin)
✅ Child Profile Management
✅ Online Appointment Booking
✅ Appointment Status Tracking
✅ Vaccination Records
✅ AI-Based Vaccine Recommendations
✅ Reports Generation (CSV Export)
✅ Email Notifications (PHPMailer)
✅ Responsive Design (Bootstrap 5)

TECHNOLOGIES USED
===========================================
Frontend : HTML5, CSS3, JavaScript, Bootstrap 5
Backend  : PHP 8.2
Database : MySQL
Email    : PHPMailer
Server   : XAMPP / Apache

SYSTEM REQUIREMENTS
===========================================
Hardware:
- Processor: Pentium 166 MHz or better
- RAM: 128 MB or higher
- Hard Disk: 10 GB free space

Software:
- Windows / Linux OS
- XAMPP (PHP 8+, MySQL 5.7+)
- Web Browser (Chrome/Firefox/Edge)

INSTALLATION STEPS
===========================================
1. Install XAMPP on your computer
2. Copy project folder to: C:\xampp\htdocs\Vaccination_Management_System
3. Start Apache and MySQL from XAMPP Control Panel
4. Open phpMyAdmin: http://localhost/phpmyadmin
5. Create database: vaccination_db
6. Import database file: vaccination_db.sql
7. Open browser and go to: http://localhost/Vaccination_Management_System

DEFAULT LOGIN CREDENTIALS
===========================================
ADMIN ACCOUNT
Email: admin@vaccinecare.com
Password: Admin@123

PARENT ACCOUNT
Email: parent@test.com
Password: Parent@123

HOSPITAL ACCOUNT
Email: city@hospital.com
Password: Hospital@123

MODULES
===========================================
👑 ADMIN MODULE
   • Manage Users (Parents/Hospitals)
   • Manage Hospitals (Verify/Edit/Delete)
   • Manage Vaccines
   • View All Appointments
   • Generate Reports
   • System Settings

👪 PARENT MODULE
   • Register/Login with OTP
   • Add Children Profiles
   • Book Appointments
   • View Vaccination History
   • Download Certificates
   • AI Vaccine Assistant

🏥 HOSPITAL MODULE
   • Register/Login
   • View Appointments
   • Update Status (Pending/Confirmed/Completed)
   • Record Vaccinations
   • Hospital Profile

DATABASE TABLES
===========================================
- users          : All system users
- parents        : Parent additional info
- hospitals      : Hospital details
- children       : Children profiles
- vaccines       : EPI vaccine list
- appointments   : Booking records
- vaccination_records : Administered vaccines
- notifications  : System alerts
- activity_log   : User actions
- otp_verification : OTP codes
- settings       : System configuration

FILE STRUCTURE
===========================================
Vaccination_Management_System/
├── PHPMailer/
├── tcpdf/
├── Redme.text
├── about.php
├── admin_add_user.php
├── admin_dashboard.php
├── admin_profile.php
├── api_recommendation.php
├── book_appointment.php
├── certificate.php
├── child_details.php
├── config.php
├── contact.php
├── db_config.php
├── download_schedule.php
├── export_report.php
├── faq.php
├── footer.php
├── forgot_password.php
├── get_hospital_details.php
├── get_user.php
├── header.php
├── hospital_appointments.php
├── hospital_dashboard.php
├── hospital_profile.php
├── hospitals_list.php
├── index.php
├── login.php
├── logout.php
├── mail_config.php
├── manage_bookings.php
├── manage_children.php
├── manage_hospitals.php
├── manage_users.php
├── manage_vaccines.php
├── my_appointments.php
├── my_children.php
├── parent_dashboard.php
├── parent_profile.php
├── patients.php
├── privacy.php
├── register.php
├── reports.php
├── resend_otp.php
├── settings.php
├── show_recommendations.php
├── terms.php
├── update_status.php
├── vaccination_info.php
├── vaccination_records.php
├── vaccination_schedule.php
├── vaccine_assistant.php
└── vaccine_recommendation.php

# 💉 Vaccination Management System (0-18 Years)
**Live Demo:** [https://vaccination-management-system.page.gd](https://vaccination-management-system.page.gd)

---

## 📝 Project Overview
A complete web-based system for child immunization tracking. Parents can register children, book appointments, and track vaccination history. Hospitals can manage appointments and update vaccination status. Admin has full control over users, hospitals, and system settings.

## 🚀 Features
- ✅ **Role-based Dashboards:** Dedicated panels for Parent, Hospital, and Admin.
- ✅ **Security:** OTP Verification & Password Recovery.
- ✅ **AI Assistant:** AI-Based vaccine recommendations for children.
- ✅ **Reports:** CSV Export and automated Schedule PDF generation.
- ✅ **Notifications:** Email alerts via PHPMailer.

## 🛠️ Tech Stack
- **Frontend:** HTML5, CSS3, JS, Bootstrap 5
- **Backend:** PHP 8.2 (Core)
- **Database:** MySQL
- **Libraries:** PHPMailer, TCPDF (for PDF reports)

---

## 📸 Screenshots
| Home Page | Admin Dashboard | Parent Dashboard |
| :--- | :--- | :--- |
| ![Home](/Assets/Home%20Page.png) | ![Admin](/Assets/Admin%20Deshboard.png) | ![Parent](/Assets/Parent%20Deshboard.png) |

---

## ⚙️ Installation (Localhost)
1. Clone the repository to `C:\xampp\htdocs\`.
2. Start Apache and MySQL from XAMPP Control Panel.
3. Import `vaccination_db.sql` via phpMyAdmin.
4. Update `db_config.php` with your local credentials.
5. Visit `http://localhost/Vaccination_Management_System`.

## 👤 Developer Information
- **Name:** Muhammad Iqbal (Faux Fire)
- **Project:** E-Project 2026
- **Contact:** fauxfireofficial@gmail.com

DEVELOPER INFORMATION
===========================================
Name     : Muhammad Iqbal
Email    : fauxfireofficial@gmail.com
Project  : Vaccination Management System (E-Project)
Year     : 2026

===========================================
        THANK YOU FOR REVIEWING
===========================================
