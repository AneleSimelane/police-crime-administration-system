# Eswatini Police CAS — User Manual

**Crime Administration System (CAS)**  
Version 1.0 | Royal Eswatini Police Service

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Getting Started](#2-getting-started)
3. [User Roles & Permissions](#3-user-roles--permissions)
4. [Case Lifecycle Workflow](#4-case-lifecycle-workflow)
5. [Citizen Guide](#5-citizen-complainant-guide)
6. [Desk Officer Guide](#6-desk-officer-guide)
7. [Investigator Guide](#7-investigator-officer-guide)
8. [HOD Guide](#8-head-of-department-hod-guide)
9. [Admin Guide](#9-station-commander-admin-guide)
10. [System Modules Reference](#10-system-modules-reference)
11. [Emergency & Panic Alerts](#11-emergency--panic-alerts)
12. [FAQ & Troubleshooting](#12-faq--troubleshooting)

---

## 1. Introduction

The **Eswatini Police Crime Administration System (CAS)** is a web-based portal for reporting crimes, managing investigation dockets, and tracking cases from initial complaint through to court referral or closure.

The system supports Eswatini's administrative structure, including the four regions (Hhohho, Manzini, Shiselweni, Lubombo) and traditional authority details (Chief and Indvuna) for citizen profiles.

### Key Capabilities

- Online crime reporting with GPS location capture
- Emergency panic dispatch to nearest station
- Digital statement approval by complainants
- Evidence upload and docket management
- Inter-station case transfers
- PDF docket export and printing

### Access Requirements

- Modern web browser (Chrome, Firefox, Edge, Safari)
- Internet connection
- For GPS features: allow location access when prompted
- Citizens must register; police staff receive accounts from administration

---

## 2. Getting Started

### 2.1 First-Time System Setup (Administrators)

1. Install the system in your XAMPP `htdocs` folder and start Apache and MySQL.
2. Navigate to **Setup DB** on the landing page or visit `/initialize.php`.
3. Click **Setup & Seed Database** to create tables and demo accounts.
4. Proceed to the portal login once setup completes successfully.

### 2.2 Citizen Registration

1. From the landing page, click **Register Citizen Profile**.
2. Complete all required fields: name, email, password, phone, address, date of birth, and region.
3. Optionally provide traditional details (Chief, Indvuna), occupation, and employer information.
4. After registration, sign in with your email and password.

### 2.3 Logging In

1. Click **Access CAS Portal** or **Police Login** from the landing page.
2. Enter your registered email address and password.
3. Upon successful login, you are redirected to your role-specific dashboard.

### 2.4 Navigation

After login, use the **sidebar menu** on the left to access modules. The top bar shows your name, role, and a dark mode toggle.

---

## 3. User Roles & Permissions

| Role | Description | Primary Functions |
|------|-------------|-------------------|
| **Citizen** | Complainant / victim | Report cases, approve statements, track investigations, emergency alerts |
| **Desk Officer** | Station front desk | Register walk-ins, transcribe statements, handle emergency queue |
| **Officer** | Police investigator | Accept cases, diary notes, evidence, arrests and warrants |
| **HOD** | Head of Department | Assign investigators, review progress, court referral or closure |
| **Admin** | Station Commander | Force directory, inter-station transfers, station oversight |

Each role can only access modules and case data permitted for their station and assignment.

---

## 4. Case Lifecycle Workflow

1. **Case Reported** — Citizen or desk officer creates the case (`pending_approval`)
2. **Statement Transcribed** — Desk officer records the official statement
3. **Citizen Approval** — Complainant approves or rejects (`pending_assignment`)
4. **HOD Assignment** — Investigator assigned (`pending_investigator_acceptance`)
5. **Investigator Acceptance** — Officer accepts case (`under_investigation`)
6. **Active Investigation** — Diary notes, evidence, warrants, arrests
7. **Review & Closure** — Forward to court or close case

> **Important:** Investigators cannot add progress notes until the citizen approves the official statement.

---

## 5. Citizen (Complainant) Guide

### Reporting a New Crime

1. Dashboard → **Report New Crime Case**
2. Enter title, description, crime type, date, and station
3. Allow GPS or enter location manually
4. Optionally attach evidence (max 20 MB per file)
5. Save your case reference number

### Approving Your Statement

1. Open the case from **Cases / Dockets**
2. Review the transcribed statement
3. Click **Approve Statement** or **Reject** if corrections are needed

### Tracking Cases

View status, investigator assignment, and timeline updates from **Cases / Dockets**.

---

## 6. Desk Officer Guide

### Walk-in Complaints

Register complainant details and incident information. Unregistered citizens receive an account automatically.

### Transcribing Statements

Open the case, record the statement, and submit for citizen approval.

### Emergency Queue

High-priority panic alerts appear on the dashboard with a red banner — prioritize immediately.

---

## 7. Investigator (Officer) Guide

### Accepting Cases

Open assigned docket → **Accept Case** → status becomes `under_investigation`.

### Investigation Actions

- Add diary/progress notes
- Upload evidence (JPG, PNG, MP4, MOV, AVI, MP3, WAV, PDF, DOC, DOCX)
- Log warrants and arrests via suspect manager

### Exporting Dockets

**Compile & Export PDF Docket** → browser Print → Save as PDF.

---

## 8. Head of Department (HOD) Guide

- Assign cases from the **Docket Allocation Queue**
- Monitor department cases and investigator workload
- Review completed dockets and forward to court or close
- Access **Force Directory** for personnel overview

---

## 9. Station Commander (Admin) Guide

- Manage **Force Directory** — register officers and desk staff
- Review **Inter-Station Transfers** — approve or reject
- Monitor station analytics and court dates

---

## 10. System Modules Reference

| Module | Description |
|--------|-------------|
| Dashboard | Role-specific overview and quick actions |
| Cases / Dockets | Case creation and management |
| Evidence Files | Upload and browse evidence |
| Reports & Export | Printable reports and PDF dockets |
| Analytics | Crime statistics and trends |
| Force Directory | Police personnel (Admin, HOD, Officer) |
| Notifications | System alerts |
| My Profile | Personal information |
| Account Settings | Password and theme preferences |

---

## 11. Emergency & Panic Alerts

**For life-threatening emergencies, always call emergency services directly.**

1. Click **PANIC / EMERGENCY REPORT** (dashboard or landing page)
2. Allow GPS access for automatic station routing
3. Case is created with **High Priority** and **PANIC** flag

If GPS is unavailable, submit manually with location details.

---

## 12. FAQ & Troubleshooting

**Forgot password?** — Use Account Settings when logged in, or contact station admin.

**Access Denied?** — Your role lacks permission or the case is outside your station.

**Investigator can't add notes?** — Citizen must approve the statement first.

**Evidence file types?** — JPG, JPEG, PNG, MP4, MOV, AVI, MP3, WAV, PDF, DOC, DOCX (20 MB max).

**Save docket as PDF?** — Export docket → Print → Save as PDF.

**Dark mode?** — Toggle in the top navigation bar.

---

*© Royal Eswatini Police Service — Crime Administration System*
