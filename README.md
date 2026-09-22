# StudyMe AI-Powered Learning Platform

## 🚀 Overview
StudyMe is a complete e-learning and educational platform catering to Secondary, University, Tech/Coding, and Teacher professional development tracks.

---

## 🛠️ Database Setup & Import Options

### Option 1: phpMyAdmin (Recommended for XAMPP)
1. Open XAMPP Control Panel and start **Apache** and **MySQL**.
2. Navigate to [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
3. Click the **Import** tab.
4. Choose the file: `studyme.sql` (or `database/studyme.sql`).
5. Click **Import** at the bottom.

### Option 2: Automated Browser Installer / Migration
1. Ensure XAMPP Apache and MySQL are running.
2. Open your browser and navigate to:
   ```
   http://localhost/StudyMe/database/init_db.php
   ```
3. The script will automatically create the `studyme` database, build all 42 tables, and populate default seed data with real-time status output.

---

## 🔑 Default Login Credentials

> **Default Password for all accounts:** `Password123!`

| Role | Email | Features / Dashboard |
| :--- | :--- | :--- |
| **Super Admin** | `admin@studyme.ng` | Full platform control, analytics, financial records, subscriptions, approvals |
| **Teacher (Primary)** | `teacher@studyme.ng` | Course builder, assignments, quizzes, student grading, earnings wallet |
| **Teacher (Secondary)** | `alex@studyme.ng` | STEM course management, live interactions, student QA |
| **Student (Tech Track)** | `student@studyme.ng` | Tech courses, video player, quizzes, assignments, certificate generator |
| **Student (University Track)** | `amina@studyme.ng` | University faculty modules, past questions (WAEC/NECO/JAMB), discussion board |

---

## 💰 Official Pricing & Subscription Plans

| Tier / Category | Monthly Price | Description |
| :--- | :--- | :--- |
| **Tech Courses** | **₦10,000.00** | Full access to coding bootcamps, frontend, backend, mobile dev, and projects |
| **Secondary School** | **₦3,000.00** | WAEC, NECO, JAMB prep, Junior & Senior secondary curriculums |
| **University Track** | **₦5,000.00** | 11 Faculties, 45+ departmental modules, exam summaries, and study guides |
| **Teacher Development**| **₦4,000.00** | Pedagogy, lesson planning, digital classroom management, teacher certification |

---

## 👨‍🏫 Teacher Course Assignment & University Level Workflow

### 1. Dual Course Onboarding Options
When registering or accessing the Instructor Suite, teachers can either:
- **Select an Existing Course**: Browse the catalog of accredited Technology and University faculty modules via [`teacher/select-course.php`](file:///c:/xampp/htdocs/StudyMe/teacher/select-course.php) and claim / assign themselves as the official instructor.
- **Create a New Course**: Build a brand-new course curriculum via [`teacher/create-course.php`](file:///c:/xampp/htdocs/StudyMe/teacher/create-course.php), specifying the appropriate **University Level / Year** (100L / Year 1 through 600L / Year 6, or Postgraduate).

### 2. Strict Student Scoping & Isolation
- **Enrollment Tracking**: Every student enrollment records both `course_id` and `teacher_id` in `enrollments`.
- **Student View**: Students strictly see and attend lessons, videos, assignments, quizzes, and announcements belonging to their enrolled course and assigned teacher.
- **Teacher View**: Teachers only manage and grade students enrolled in their own courses.
- **Admin Control**: Super Admins maintain unrestricted platform-wide visibility and governance.

---

## 📊 Database Architecture (42 Tables Reconstructed)

1. **User Management & Roles**: `users`, `students`, `teachers`, `teacher_applications`, `password_resets`
2. **Academic Structure**: `departments`, `subjects`, `categories`
3. **Courses & Curriculum**: `courses`, `course_sections`, `lessons`, `lesson_progress`, `lesson_questions`, `lesson_question_replies`, `resources`
4. **Assessments & Certification**: `quizzes`, `questions`, `question_options`, `quiz_attempts`, `quiz_answers`, `assignments`, `assignment_submissions`, `certificates`, `course_reviews`
5. **Enrollments & Subscriptions**: `enrollments`, `subscription_plans`, `subscriptions`
6. **Finance & Wallets**: `payments`, `wallets`, `wallet_transactions`, `withdrawals`, `referrals`
7. **Communication & Logs**: `announcements`, `announcement_reads`, `notifications`, `activity_logs`, `contact_messages`
8. **Blog & Past Questions**: `blog_categories`, `blog_posts`, `past_questions`
9. **System & AI Settings**: `settings`, `ai_settings`

---

## 📁 Key File Locations
- Master SQL Import: [`studyme.sql`](file:///c:/xampp/htdocs/StudyMe/studyme.sql) & [`database/studyme.sql`](file:///c:/xampp/htdocs/StudyMe/database/studyme.sql)
- Schema DDL: [`database/schema.sql`](file:///c:/xampp/htdocs/StudyMe/database/schema.sql)
- Seed Data: [`database/seed.sql`](file:///c:/xampp/htdocs/StudyMe/database/seed.sql)
- Database Config: [`config/database.php`](file:///c:/xampp/htdocs/StudyMe/config/database.php)
- Auto-Migrator: [`database/init_db.php`](file:///c:/xampp/htdocs/StudyMe/database/init_db.php)
