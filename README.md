# 📌 Employee Timesheet Management System

A **role-based web application** for managing employee work hours, tracking timesheets, and handling approval workflows. Built using **PHP (PDO) and MySQL**, the system ensures secure data handling, structured workflows, and efficient reporting.

---

## 🚀 Features

- 🔐 **Authentication System**
  - Secure login & registration using password hashing
  - Session-based authentication

- 👥 **Role-Based Access Control (RBAC)**
  - Admin and Employee roles
  - Restricted access based on user permissions

- ⏱️ **Timesheet Management**
  - Add, edit, delete timesheet entries
  - Automatic calculation of working hours
  - Daily and weekly submission system

- 🔄 **Workflow & Status Handling**
  - Status tracking: `Pending`, `Approved`, `Rejected`
  - Weekly submission locks entries after submission

- 📊 **Dashboard & Analytics**
  - Employee: weekly hours, personal entries
  - Admin: total users, projects, pending approvals

- 🧾 **Reporting**
  - Print/export timesheets in PDF-friendly format

- 🛡️ **Security & Validation**
  - Input validation and sanitization
  - SQL injection protection using prepared statements

---

## 🛠️ Tech Stack

- **Frontend:** HTML, CSS, JavaScript  
- **Backend:** PHP (PDO)  
- **Database:** MySQL  
- **Server:** Apache (XAMPP / LAMP)

---

## 📁 Project Structure

```
employee-timesheet/
│── index.php
│── register.php
│── dashboard.php
│── add_timesheet.php
│── edit_timesheet.php
│── my_timesheets.php
│── timesheet_print.php
│── logout.php
│── db.sql
│
├── admin/
│   ├── admin_timesheets.php
│   ├── manage_employees.php
│   ├── manage_projects.php
│   └── reports.php
│
├── includes/
│   ├── db_connect.php
│   ├── header.php
│   └── footer.php
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
```

---

## 🧩 System Architecture

```
Frontend → PHP Backend → MySQL Database → Role-Based Workflow
```

---

## ⚙️ Installation & Setup

### 1. Clone the repository
```bash
git clone https://github.com/your-username/employee-timesheet.git
cd employee-timesheet
```

### 2. Setup database
- Create a MySQL database (e.g., `timesheet_db`)
- Import the `db.sql` file
- Update database credentials in `/includes/db_connect.php`

### 3. Run the project
- Start Apache & MySQL
- Open: http://localhost/employee-timesheet

---

## 🔑 Default Credentials (Demo Only)

| Role     | Email                 | Password |
|----------|----------------------|----------|
| Admin    | admin@company.com    | password |
| Employee | employee@company.com | password |

⚠️ Change credentials before production use.

---

## 👨‍💻 Author

Subhajit Bag
