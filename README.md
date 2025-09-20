# GRCC-CBT-Software

A **Computer-Based Testing (CBT) Software** developed for the GRCC Mentorship School to manage student exams, generate results, transcripts, and certificates.

---

## 📌 About the Project

GRCC-CBT-Software is built using **plain Core PHP (no frameworks)**, with **HTML**, **Tailwind CSS**, and **Vanilla JavaScript**. It is designed to be lightweight, secure, and fully responsive across mobile, tablet, and desktop devices.

Developed with love by **Pinnacle Tech Hub**  
**Noah Abayomi**  
📞 +2347032078859

---

## ✨ Features

- Multiple course exams for students  
- Score recording, transcript, and CGPA calculation  
- Time-based exams with resume on logout/login  
- Admin controlled exam scheduling (open/close, set durations)  
- Customized certificate generation upon passing  
- Student portal for exam participation, result review, certificate download, and profile management  
- Admin & Superadmin portals for managing courses, exams, students, reports, and system settings  
- Modern UI/UX design with consistent color scheme and gradients  
- Secure practices: prepared statements (PDO), input validation, session & role checks, CSRF protection

---

## 📂 Project Structure

```
cbt/
│
├── index.php
├── login.php
├── register.php
│
├── core/                  # Core logic (auth, DB, helper functions, security)
├── config/                # Configuration files (constants, settings)
├── student/               # Student-facing pages (dashboard, exams, certificates, profile)
├── admin/                 # Admin-facing pages (course/exam management, reports)
├── superadmin/            # Superadmin-specific features (admin management, logs)
├── includes/              # Shared UI components (headers, footers, navs)
├── vendor/                # External libraries (e.g. Dompdf)
├── assets/                # Static assets: CSS, JS, images
└── storage/               # Generated certificates, transcripts, logs
```

---

## 🎨 UI / UX Design Guidelines

**Color Palette & Gradients**

- Primary (Brand Blue): `#2563EB` (Tailwind `blue-600`)  
- Secondary (Teal): `#14B8A6` (Tailwind `teal-500`)  
- Accent (Amber): `#F59E0B` (Tailwind `amber-500`)  
- Background Light: `#F9FAFB` (Tailwind `gray-50`)  
- Text Dark: `#111827` (Tailwind `gray-900`)  

**Gradients**

- Primary Gradient: `bg-gradient-to-r from-blue-600 to-teal-500`  
- Secondary Gradient: `bg-gradient-to-r from-teal-500 to-amber-500`  

**Styling Rules**

- Buttons: rounded-xl, bold text, gradient background, hover states (e.g. `hover:opacity-90`)  
- Cards/Panels: white background, soft shadow, rounded corners  
- Forms: minimal styling, focus ring (`focus:ring-2 focus:ring-blue-500`)  
- Tables: striped or border-separated rows, clean and readable  
- Navigation: sticky or fixed top nav with gradient background  

---

## 🛠 Technology Stack

- **Backend**: Core PHP (PDO for DB interactions)  
- **Frontend**: HTML5, Tailwind CSS, Vanilla JavaScript  
- **Database**: MySQL  
- **PDF Generation**: Dompdf for certificates & transcripts  
- **Security & Auth**: PHP sessions, role-based access control, CSRF tokens, input sanitization

---

## 👟 Installation & Setup

1. Clone the repo  
   ```bash
   git clone https://github.com/Pinnacle96/GRCC-CBT-Software.git
   ```

2. Set up your local web server (WAMP, XAMPP, or LAMP) and MySQL.

3. Import the database schema (use the provided SQL file or create tables for `users`, `courses`, `exams`, `results`, `logs`, etc.).

4. In `config/constants.php`, update your database credentials and other environment settings.

5. Ensure `vendor/` directory is installed (if using composer for Dompdf):  
   ```bash
   composer install
   ```

6. Set file/folder permissions as needed for `storage/` to allow writing certificates, transcripts, logs.

7. Access the app via browser:  
   ```
   http://localhost/GRCC-CBT-Software/index.php
   ```

---

## 🔐 Security Notes

- Use `password_hash()` / `password_verify()` for storing and checking passwords  
- Use prepared statements (PDO) for all database queries  
- Validate and sanitize all user inputs  
- Protect routes/pages based on user roles (student, admin, superadmin)  
- Implement CSRF token checks on forms  
- Escape output with `htmlspecialchars()` where needed

---

## 🚀 License

This project is proprietary to GRCC Mentorship School. Unauthorized distribution, copying, or modification is prohibited without permission.

---

## 🤝 Contact

**Pinnacle Tech Hub**  
**Noah Abayomi**  
📞 +2347032078859  
