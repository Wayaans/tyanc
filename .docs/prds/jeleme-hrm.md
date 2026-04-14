# **1\. Introduction**

## **1.1  What is Jeleme?**

Jeleme is the official Human Resources Management (HRM) application within the Tyanc platform ecosystem. It is not a standalone product — it is purpose-built as one of the real business apps that plug into Tyanc, following the same architecture contract, RBAC model, approval infrastructure, and admin shell that all Tyanc apps share. Just as Tyanc documents describe an ERP or Tasks app running under their own prefixes, Jeleme runs under /jeleme/\* and registers its permission namespace as jeleme.\* inside Tyanc's centralized permission system.

| Tyanc Placement:  Jeleme lives at /jeleme/\* within the Tyanc admin shell. Its routes, permissions, sidebar menu, and app registry entry all follow the Tyanc new-app checklist defined in TYANC-AI.md. Approvals for all HR workflows are handled through Cumpu, Tyanc's approval workspace. |
| :---- |

## **1.2  Purpose of this PRD**

This document defines the full product scope, module and sub-module breakdown, feature specifications, inter-module data relationships, recruitment pipeline detail, permission namespaces, non-functional requirements, and technical integration notes for Jeleme v1.0. It is intended for the engineering, design, and product teams responsible for building Jeleme on top of the Tyanc platform.

## **1.3  Technology Stack**

| Layer | Technology |
| :---- | :---- |
| Backend Framework | Laravel 13 (PHP 8.5) |
| Frontend Framework | Inertia.js v3 \+ Vue 3 \+ TypeScript |
| UI Component System | shadcn-vue (consistent with all Tyanc apps) |
| RBAC / Permissions | Spatie Laravel Permission — permission namespace: jeleme.\* |
| Data Layer (DTO) | Spatie Laravel Data — typed payloads for all Jeleme actions |
| Approval Workflow | Cumpu (Tyanc's centralized approval workspace) |
| Admin Shell | Shared Tyanc admin shell with app switching sidebar |
| Build Tool | Vite (bun lockfile, vite.config.ts) |
| Database | MySQL / PostgreSQL (via Laravel Eloquent) |
| Queue / Jobs | Laravel Queue (payroll runs, bulk exports) |

## **1.4  Jeleme within Tyanc — Platform Integration**

Tyanc is the control plane. Every app built on top of it — including Jeleme — must register itself correctly so that platform-level governance (access control, app visibility, approval routing) works without exception. Jeleme satisfies this by:

* Registering jeleme as an app key in Tyanc's apps and app\_pages registry tables.

* Declaring its full permission surface in config/permission-sot.php under the jeleme namespace.

* Adding its sidebar navigation to config/sidebar-menu.php so it appears in the Tyanc admin shell.

* Using php artisan tyanc:permissions-sync to keep permissions in sync after every Jeleme release.

* Routing all approval-gated actions through Cumpu's review workflow, with Jeleme owning the actual business mutation.

* Keeping all Jeleme business logic under app/Actions/Jeleme/\* and all pages under resources/js/pages/jeleme/\*.

# **2\. Product Vision & Goals**

Jeleme aims to give any organization running on the Tyanc platform a complete, deeply integrated HR system that eliminates data silos between employee records, payroll, attendance, recruitment, and performance. Unlike bolt-on HR tools, Jeleme shares the same user session, role assignments, approval history, and notification infrastructure as every other Tyanc app, creating a unified operational experience for HR administrators, managers, and employees alike.

| Goal | Description |
| :---- | :---- |
| Single Source of Truth | One employee record drives payroll, attendance, leave, performance, and offboarding — no duplicate data entry. |
| End-to-End Recruitment | A structured 13-step pipeline tracks every applicant from headcount request to their first day, with status visible at every stage. |
| Compliance-Ready | Payroll tax (PPh 21), BPJS contributions, and labor law requirements are built-in, not afterthoughts. |
| Approval Governance | Every HR decision that requires authorization flows through Cumpu, providing a consistent, auditable approval chain. |
| Platform Cohesion | Jeleme feels like a natural extension of Tyanc — same UI patterns, same nav shell, same RBAC model, same approval system. |

# **3\. Stakeholders & User Roles**

Jeleme inherits Tyanc's RBAC infrastructure. The following roles are suggested as Jeleme-specific permission groups, registered under the jeleme.\* namespace. Tyanc administrators can create and assign these roles through the Tyanc role management interface.

| Role | Primary Responsibilities within Jeleme |
| :---- | :---- |
| HR Administrator | Full access to all Jeleme modules. Manages employee data, runs payroll, configures settings, and oversees the full recruitment pipeline. |
| HR Staff | Day-to-day HR operations: attendance, leave, onboarding, training enrollment, and recruitment pipeline management up to offer stage. |
| HR Manager | Approves payroll runs, signs off on job offers, reviews performance ratings, authorizes disciplinary actions, and acts as Cumpu reviewer for HR approvals. |
| Department Head / Manager | Views team attendance, approves leave requests for their team, participates in recruitment interviews, and sets team KPIs. |
| Employee (Self-Service) | Views own payslips, submits leave requests, checks attendance, updates personal info, views training schedule, and submits resignation. |
| Finance Staff | Read access to payroll summaries, payslip data, and BPJS/tax reports for accounting reconciliation. |
| Recruiter | Manages the full recruitment pipeline: job postings, applicant tracking, interview scheduling, and offer letter generation. |

# **4\. Complete Module & Sub-Module Map**

The following table represents the full scope of Jeleme v1.0. Every module listed below will be built as a discrete feature area within the Jeleme app, with its own routes (under /jeleme/\*), permission set (under jeleme.\*), and Inertia page components (under resources/js/pages/jeleme/\*).

| \# | Module | Sub-Modules |
| :---- | :---- | :---- |
| **1** | **Employee Management** | Employee Profile (personal, identity, photo) Employee Career History (internal promotions, transfers, demotions) Employee Contract Management (contract types, renewals, probation tracking) Employee Documents (upload & manage supporting documents) Employee Education History Employee Work History (previous employers) Employee Skills & Competencies Employee Family & Dependents Employee Emergency Contacts Employee Status Management (active, inactive, resigned, terminated) |
| **2** | **Organizational Structure** | Company / Legal Entity Management Department Management Division & Unit Management Job Position / Job Title Management Job Grade & Level Management Organization Chart (visual hierarchy) Reporting Line Configuration |
| **3** | **Recruitment** | Manpower Planning (headcount request) Job Requisition (MPP approval flow via Cumpu) Job Posting Management (internal & external boards) Applicant / Candidate Database Recruitment Pipeline Tracker (13-step multi-stage funnel) Application Stage: Administrative Selection Application Stage: HR Interview Application Stage: Written / Psychological Test Application Stage: User / Technical Interview Application Stage: Background Check Application Stage: Medical Examination Job Offer Letter Management Contract Negotiation & Signing Pre-Onboarding Checklist Recruitment Source Tracking (walk-in, referral, job portal, agency) Blacklist Management |
| **4** | **Onboarding** | Onboarding Checklist Template Task Assignment per Onboarding Step Equipment & Access Request Trigger Welcome Email & Orientation Schedule Buddy / Mentor Assignment Probation Period Tracking Onboarding Completion Sign-off |
| **5** | **Attendance & Time Management** | Work Schedule / Shift Configuration Shift Assignment per Employee Daily Attendance Records Late & Early Departure Tracking Attendance Correction Request Overtime Request & Approval (via Cumpu) Holiday Master Data Roster / Schedule Calendar Integration Placeholder (fingerprint / face recognition device) |
| **6** | **Leave Management** | Leave Type Configuration (annual, sick, maternity, paternity, unpaid, etc.) Leave Balance Management (accruals, carry-forward, expiry) Leave Request & Approval (via Cumpu) Leave Calendar (team view) Collective Leave Setting Leave Encashment Configuration Leave History per Employee |
| **7** | **Payroll Management** | Salary Structure & Grade Payroll Component Configuration (earnings & deductions) Monthly Payroll Run Allowance & Benefit Management Overtime Pay Calculation PPh 21 Tax Calculation (Indonesia) BPJS Ketenagakerjaan Configuration BPJS Kesehatan Configuration Payslip Generation & Distribution Payroll History & Audit Trail Mid-Month Advance (kasbon) Tracking |
| **8** | **Performance Management** | KPI / OKR Setting per Employee Performance Appraisal Period Configuration Self-Assessment Form Manager Assessment Form 360-Degree Feedback (peer review) Performance Rating Calibration Performance Improvement Plan (PIP) Performance History Timeline |
| **9** | **Training & Development** | Training Program Catalog Training Schedule & Session Management Training Enrollment & Approval (via Cumpu) Training Attendance Recording Post-Training Evaluation (Kirkpatrick Level 1 & 2\) Certification & License Tracking (with expiry alerts) Individual Development Plan (IDP) Training Cost Tracking |
| **10** | **Benefits & Compensation** | Benefit Plan Configuration Health Insurance Management BPJS Administration Dashboard Employee Loan Management Expense Reimbursement Management Benefit Entitlement per Job Grade |
| **11** | **Disciplinary Management** | Violation / Incident Report Warning Letter (SP1, SP2, SP3) Generation Disciplinary Action Recording Hearing / Investigation Notes Disciplinary History per Employee Grievance & Complaint Submission |
| **12** | **Offboarding & Separation** | Resignation Submission & Approval (via Cumpu) Termination Processing Retirement Management Exit Interview Form Clearance / Handover Checklist Final Settlement Calculation (uang pesangon, penghargaan masa kerja) Experience Letter / Work Reference Generation Alumni Database |
| **13** | **Reports & Analytics** | Headcount & Workforce Composition Report Employee Turnover Report Attendance & Leave Summary Report Payroll Summary Report Recruitment Funnel Report Training Effectiveness Report Performance Distribution Report Custom Report Builder Export to PDF / Excel |
| **14** | **Settings & Configuration** | Jeleme General Settings (company profile, fiscal year) Work Calendar & Holiday Configuration Email / Notification Template Management Custom Fields per Entity Approval Rule Configuration (delegates to Cumpu) Role & Permission Sync (delegates to Tyanc) Audit Log Viewer |

# **5\. Recruitment Module — 13-Step Pipeline**

Recruitment is one of the most critical and complex processes in Jeleme. Rather than a simple applicant list, Jeleme implements a structured, multi-stage pipeline where every candidate has a visible, trackable status at all times. HR staff, recruiters, and managers can see exactly which step a candidate is on, how many candidates are at each stage, and what the next action required is.

| Pipeline Visibility: Every candidate record shows a progress indicator — for example, "Step 6 of 13 — User Interview" — so any authorized user immediately knows where the applicant stands without needing to read through history logs. Stage transitions are timestamped and actor-attributed. |
| :---- |

## **5.1  Pipeline Stage Definitions**

The table below defines each of the 13 pipeline stages: the stage name, the responsible actor, the entry condition (what must be true before this stage begins), and the exit condition (what outcome moves the candidate forward or closes the application).

| Step | Stage / Phase | Actor | Entry Condition | Exit Condition |
| :---- | :---- | :---- | :---- | :---- |
| **1** | Manpower Request (MPP) | Dept. Head → HRM | Approved headcount need | MPP approved by HR Director via Cumpu |
| **2** | Job Requisition Creation | HR Admin | Approved MPP | Job requisition record created, job post drafted |
| **3** | Job Posting Publication | HR Admin | Approved job requisition | Vacancy published (internal board, portal, agency) |
| **4** | Application Received | Applicant / System | Job posting live | Candidate record created; status \= Applied |
| **5** | Administrative Screening | HR Admin | Application submitted | Candidate: Passed / Failed (docs & requirements check) |
| **6** | HR Interview (Initial) | HR Staff | Passed admin screening | Interview notes saved; candidate: Proceed / Rejected |
| **7** | Written / Psychological Test | HR Staff / Vendor | Passed HR interview | Test score recorded; candidate: Proceed / Rejected |
| **8** | User / Technical Interview | Dept. Head / User | Passed test stage | Technical assessment score; candidate: Proceed / Rejected |
| **9** | Background Check | HR Admin | Passed technical interview | BGC result: Clear / Flagged; candidate continues or halted |
| **10** | Medical Examination | Clinic / Vendor | Cleared background check | Medical result: Fit / Not Fit; candidate continues or rejected |
| **11** | Job Offer Letter | HR Manager | Medical cleared | Offer sent; candidate: Accepts / Declines / Negotiates |
| **12** | Contract Signing | HR Admin \+ Candidate | Offer accepted | Signed contract uploaded; start date confirmed |
| **13** | Pre-Onboarding Checklist | HR Admin \+ IT | Contract signed | Access, equipment, orientation ready; Onboarding module triggered |

## **5.2  Candidate Status at Every Step**

At each stage, a candidate can hold one of the following statuses. The system enforces that only valid status transitions are permitted, preventing accidental skips or backward movement without an authorized override.

| Status | Meaning |
| :---- | :---- |
| In Progress | Candidate is actively being processed at the current step. |
| Awaiting Schedule | Step is pending scheduling (e.g., interview date not yet set). |
| Awaiting Result | Step was executed; result not yet recorded (e.g., waiting for test scores). |
| Passed — Moving Forward | Candidate cleared this step and advances to the next. |
| Rejected at Step N | Candidate did not pass; application closed at this step. Reason recorded. |
| On Hold | Candidate paused at this step (e.g., waiting for a department budget decision). |
| Offer Declined | Applicable at Step 11 only. Candidate declined the offer; position may be re-posted. |
| Contract Signed | Terminal success state. Candidate is ready for onboarding. |
| Withdrawn | Candidate self-withdrew at any step. Application closed. |

## **5.3  Recruitment Sub-Module Features**

### **5.3.1  Manpower Planning (MPP)**

The HR and department heads collaborate on the Annual Manpower Plan. This plan defines the number of approved headcounts per department per period. Job requisitions created in Step 2 must reference an approved MPP line item; the system will not allow unauthorized vacancies.

### **5.3.2  Job Requisition & Approval**

A job requisition captures the position details, number of openings, expected salary range, required qualifications, and target start date. Before a vacancy can be published, the requisition must be approved through Cumpu by the HR Manager (and optionally the Finance head for budget validation). Jeleme creates the Cumpu approval request automatically upon requisition submission.

### **5.3.3  Job Posting**

Once approved, HR publishes the vacancy. Jeleme maintains an internal job board visible to employees (for internal mobility) and supports manual publication to external job portals. The posting records the source of each incoming application (walk-in, referral, LinkedIn, JobStreet, headhunter agency, etc.) for recruitment source analytics.

### **5.3.4  Blacklist Management**

Former employees or past applicants who are flagged (e.g., terminated for cause, fraudulent documents) can be added to the blacklist. When a new application is submitted, the system checks the applicant's identity (NIK, email) against the blacklist and alerts the recruiter before proceeding.

### **5.3.5  Recruitment-to-Employee Conversion**

This is the most important data flow in the entire recruitment module. Upon Step 13 completion (contract signed, pre-onboarding checklist done), Jeleme automatically creates a full Employee profile in the Employee Management module using all data already captured during recruitment: personal information, education, work history, and contract terms. Zero re-entry is required from HR.

# **6\. Inter-Module Data Relations**

One of Jeleme's core design principles is that each piece of HR data is entered exactly once and reused everywhere it is needed. The table below explicitly documents how each module depends on or feeds data into other modules, so that development teams understand which modules must be built first and how database relationships should be structured.

| Relationship | Data Flow & Dependency |
| :---- | :---- |
| Employee Mgmt → Payroll | Employee salary structure, grade, BPJS number, and tax ID are pulled from Employee profile into every payroll run. |
| Employee Mgmt → Attendance | Work schedule assignment per employee drives attendance calculation and overtime eligibility. |
| Employee Mgmt → Leave | Leave balance initialization is tied to employee hire date, leave type entitlement, and job grade. |
| Employee Mgmt → Performance | KPIs and appraisal cycles are linked to the employee's current position and reporting line from Org Structure. |
| Recruitment → Employee Mgmt | Upon contract signing, the candidate record is automatically promoted to a full Employee profile. No manual re-entry. |
| Recruitment → Onboarding | Completing Step 13 of the recruitment pipeline triggers the Onboarding module, pre-populating tasks and assignees. |
| Onboarding → Attendance | After onboarding sign-off, the employee's work schedule becomes active; attendance tracking starts from Day 1\. |
| Org Structure → All Modules | Department, job title, and reporting line are master data referenced by Attendance, Leave, Payroll, Performance, and Recruitment. |
| Leave → Payroll | Unpaid leave days, leave encashment, and deduction-on-absence feed directly into the monthly payroll run. |
| Attendance → Payroll | Late deductions, overtime calculations, and attendance allowances flow from Attendance records into Payroll processing. |
| Performance → Payroll | Performance rating can drive merit increment and bonus amounts in the Payroll component configuration. |
| Training → Performance | Completed IDP training items are linked to the employee's development goals in the Performance appraisal cycle. |
| Disciplinary → Employee Career | Issued warning letters (SP) are recorded in the Employee Career History as a permanent part of the HR record. |
| Offboarding → Payroll | Final settlement (pesangon, unused leave encashment, final salary) is calculated within the Payroll module on separation. |
| All Approvals → Tyanc/Cumpu | Leave requests, overtime, training enrollment, recruitment requisitions, and offboarding all route approvals through Cumpu, governed by Tyanc RBAC. |
| Reports → All Modules | The Reports module aggregates data from Employee, Attendance, Leave, Payroll, Recruitment, and Performance as read-only views. |

## **6.1  Master Data Dependencies**

The following entities are master data that must be configured before other modules can operate. Building and seeding these first is a hard dependency for Jeleme's initial setup.

* Company / Legal Entity — root of all multi-company data scoping.

* Departments and Positions — required before creating any employee record.

* Job Grade & Salary Structure — required before running any payroll.

* Work Schedule & Holiday Calendar — required before processing attendance or leave.

* Leave Type Configuration — required before any leave request can be submitted.

* Payroll Component Configuration — required before the first payroll run.

# **7\. Module Specifications**

## **7.1  Employee Management**

The Employee Management module is the spine of Jeleme. Every other module depends on the employee record as its primary entity. The employee profile is split into logical sections to keep the UI navigable while maintaining data completeness.

### **Key Screens**

* Employee List — searchable, filterable table with status, department, and position filters.

* Employee Profile — tabbed layout: Personal Info | Career | Documents | Family | Education | Work History | Skills | Emergency Contacts.

* Employee Contract Tab — shows all contract versions, renewal dates, probation end date, and contract type (permanent, contract, intern, outsource).

* Employee Career History Tab — every promotion, transfer, demotion, and title change with effective dates, recorded as an immutable timeline.

### **Business Rules**

* Employee ID (NIK Karyawan) is auto-generated based on company prefix \+ hire year \+ sequence.

* Probation end date is calculated automatically from hire date and the probation duration configured per employee type.

* Status transitions (e.g., active → resigned) are gated by an approval in Cumpu for all status changes that trigger payroll or benefit changes.

## **7.2  Organizational Structure**

The Org Structure module manages the company hierarchy that all other modules reference. It is intentionally kept as pure master data: no complex workflows, just clean CRUD with relationship enforcement.

* Companies can have multiple departments; departments can have sub-units.

* Each position is linked to a job grade, which links to a salary structure in Payroll.

* The Organization Chart renders a visual tree based on reporting line data, showing each employee's manager relationship.

* Changing a department or position triggers a Career History entry on the affected employee automatically.

## **7.3  Attendance & Time Management**

Attendance tracks daily in/out records for all employees. Jeleme does not bundle a time-clock device driver, but provides a clean integration placeholder for fingerprint or facial recognition hardware via a documented API endpoint that devices can POST to.

* Work schedules are assigned per employee or per department. Shift rotation schedules are supported.

* Late arrivals and early departures are automatically flagged based on the assigned schedule.

* Attendance correction requests go through Cumpu for manager approval.

* Overtime is request-based: the employee or manager submits an overtime plan in advance, which is approved through Cumpu before the overtime is worked.

## **7.4  Leave Management**

Leave is fully configurable. HR administrators define leave types, accrual rules, carry-forward policies, and expiry rules through the Leave Type Configuration screen. Leave balances are calculated per employee based on their hire date, leave type entitlement, and accrual schedule.

* Employees submit leave requests through their self-service portal; the request goes to Cumpu for line-manager approval.

* The team leave calendar shows who is on leave on any given day, helping managers make approval decisions.

* Unpaid leave days are automatically reported to the Payroll module for deduction in the next payroll run.

* Collective leave days (e.g., company-wide shutdown) can be set globally and appear on all employee leave calendars.

## **7.5  Payroll Management**

Payroll is a sensitive, transaction-critical module. Every payroll run must be atomic — if any calculation fails, the entire run rolls back. Jeleme never allows a partially committed payroll state.

### **Payroll Run Lifecycle**

* Draft — HR inputs the payroll period. The system auto-imports attendance data, leave deductions, overtime amounts, and approved allowances.

* Review — HR reviews the computed figures. Adjustments can be made with audit-trail justification.

* Approval — The payroll run requires HR Manager sign-off through Cumpu before any disbursement.

* Finalized — Once approved, payslips are generated and locked. No further edits are permitted on that run.

* Distributed — Payslips are distributed to employees via the self-service portal or email.

### **Indonesia-Specific Compliance**

* PPh 21 (income tax) calculation is built-in, supporting both NPWP and non-NPWP employees and the progressive tariff structure.

* BPJS Ketenagakerjaan (JHT, JKK, JKM, JP) and BPJS Kesehatan contribution rates are configurable and applied automatically per payroll run.

## **7.6  Performance Management**

Jeleme's Performance Management module supports a structured appraisal cycle with KPI/OKR setting, multi-source feedback, and calibration. It deliberately avoids complex point-based scoring systems that require lengthy setup and instead uses a straightforward rating scale (1–5 or Exceptional / Meets / Below) that HR teams can configure per appraisal period.

* KPIs are set at the start of each performance period by the employee and approved by their manager.

* Mid-year and year-end reviews include both self-assessment and manager assessment sections.

* 360 feedback is optional per role: peers and subordinates can be invited to provide ratings and comments.

* Performance Improvement Plans (PIP) are triggered manually by HR or a manager when an employee's rating falls below a configurable threshold.

* Final ratings feed into the merit increment calculation in the Payroll module and influence bonus computation.

## **7.7  Training & Development**

The Training module tracks all formal learning activities. It is closely linked to Performance Management through the Individual Development Plan (IDP), where development goals identified in a performance review are tracked as training enrollments.

* Training programs have types: internal, external, mandatory, and elective.

* Enrollment requires Cumpu approval for external trainings that involve a cost.

* Certification tracking stores credential documents, issue dates, and expiry dates; expiry alerts notify the employee and HR 30/60/90 days in advance.

* Post-training evaluations collect employee feedback (reaction) and knowledge check scores (learning), following Kirkpatrick Levels 1 and 2\.

## **7.8  Offboarding & Separation**

Offboarding is as structured as Onboarding. When an employee leaves — whether through resignation, termination, or retirement — Jeleme guides HR and the departing employee through a checklist to ensure clean handover, asset return, access revocation, and accurate final settlement.

* Resignation submissions go through Cumpu; the system captures the notice period and calculates the last working day.

* The clearance checklist assigns tasks to IT (access revocation), Finance (final expense settlement), and the employee's department (asset return, knowledge transfer).

* Final settlement calculates: remaining salary, unused annual leave encashment, severance pay (uang pesangon), and appreciation pay (uang penghargaan masa kerja) per Indonesian labor law.

* Upon offboarding completion, the employee status changes to Inactive and they are added to the Alumni database with their experience letter.

# **8\. Permission Namespace (jeleme.\*)**

All Jeleme permissions are registered in Tyanc's config/permission-sot.php under the jeleme namespace. The Tyanc CLI command php artisan tyanc:permissions-sync will seed these permissions into the database after every Jeleme release. Roles are then assigned permission groups through the Tyanc admin interface.

| Permission Group | Coverage |
| :---- | :---- |
| jeleme.employees.\* | View, create, update, delete employee records |
| jeleme.org.\* | Manage org structure (departments, positions, grades) |
| jeleme.recruitment.\* | Manage job requisitions, postings, and pipeline |
| jeleme.onboarding.\* | Manage onboarding checklists and task assignments |
| jeleme.attendance.\* | View and manage attendance records, shifts, overtime |
| jeleme.leave.\* | View, request, approve, and configure leave |
| jeleme.payroll.\* | Run payroll, view payslips, manage salary structures |
| jeleme.performance.\* | Set KPIs, conduct appraisals, manage PIP |
| jeleme.training.\* | Manage training programs, enrollment, and certification |
| jeleme.benefits.\* | Manage benefit plans, BPJS, loans, reimbursements |
| jeleme.disciplinary.\* | Issue and view warning letters, record disciplinary actions |
| jeleme.offboarding.\* | Manage resignations, terminations, final settlements |
| jeleme.reports.\* | View and export all HR reports |
| jeleme.settings.\* | Configure Jeleme settings, calendars, templates |

| Tyanc Rule: Jeleme is not fully deployed until all routes have a corresponding permission and those permissions are registered in config/permission-sot.php. A route without a permission breaks access control and app-page visibility in the Tyanc shell. Run tyanc:permissions-sync after every new Jeleme feature. |
| :---- |

# **9\. Approval Flows via Cumpu**

Jeleme delegates all multi-party authorization decisions to Cumpu, Tyanc's approval workspace. The following HR actions require an approval gate before the actual mutation is committed. Jeleme submits the approval request to Cumpu; Cumpu reviewers act on it; and upon approval, the requesting HR user re-triggers the same action once — Jeleme then commits the change.

| Action | Cumpu Reviewer | Jeleme Permission Governed |
| :---- | :---- | :---- |
| Submit Job Requisition | HR Manager \+ Finance Head | jeleme.recruitment.requisition.submit |
| Publish Job Posting | HR Manager | jeleme.recruitment.posting.publish |
| Issue Job Offer Letter | HR Manager | jeleme.recruitment.offer.send |
| Approve Leave Request | Direct Manager → HR | jeleme.leave.approve |
| Approve Overtime Request | Direct Manager | jeleme.attendance.overtime.approve |
| Finalize Payroll Run | HR Manager | jeleme.payroll.finalize |
| Enroll in External Training | Direct Manager \+ HR | jeleme.training.enroll |
| Issue Warning Letter (SP) | HR Manager \+ Legal | jeleme.disciplinary.sp.issue |
| Process Resignation | HR Manager | jeleme.offboarding.resignation.approve |
| Process Termination | HR Manager \+ Legal \+ Director | jeleme.offboarding.termination.approve |
| Change Employee Status | HR Manager | jeleme.employees.status.change |

# **10\. Non-Functional Requirements**

| Category | Requirement |
| :---- | :---- |
| Performance | All HR list pages must load within 2 seconds for up to 10,000 employee records. Payroll run for 1,000 employees must complete within 60 seconds. |
| Security | All data access is gated by Tyanc RBAC. Salary and personal data fields are restricted to explicitly granted roles. All mutations are audit-logged. |
| Scalability | Jeleme must support multi-company/multi-entity operation under a single Tyanc instance using entity-scoped data partitioning. |
| Reliability | Payroll data must never be partially committed. All payroll runs use database transactions; failures roll back completely. |
| Maintainability | All Jeleme code lives under app/Actions/Jeleme/\*, resources/js/pages/jeleme/\*, and follows the Tyanc architecture contract in TYANC-AI.md. |
| Audit & Compliance | Every create/update/delete on employee, payroll, and disciplinary records writes to an immutable audit log accessible via Settings \> Audit Log. |
| Accessibility | All Jeleme UI components follow WCAG 2.1 AA standards, consistent with the shadcn-vue patterns used across Tyanc. |
| Localization | Jeleme ships with Indonesian (id) as the primary locale and English (en) as secondary, using Laravel's lang/ system. |

# **11\. Technical Integration Notes**

## **11.1  New App Checklist (from Tyanc Documentation)**

Every new Tyanc app must satisfy the following checklist. Jeleme's build process must ensure all items are checked before any sprint is considered done.

* Add jeleme to config/permission-sot.php with all resources and action verbs.

* Add jeleme navigation to config/sidebar-menu.php.

* Seed the app registry: php artisan db:seed \--class=AppRegistrySeeder \--no-interaction

* Sync permissions: php artisan tyanc:permissions-sync

* Regenerate frontend route helpers: php artisan wayfinder:generate

* Ensure every route has a matching permission; no orphan routes.

* All Jeleme business logic goes under app/Actions/Jeleme/\*

* All Jeleme pages go under resources/js/pages/jeleme/\*

* All Jeleme data transfer objects (DTOs) use Spatie Laravel Data.

## **11.2  Database Design Principles**

* All Jeleme tables are prefixed with jlm\_ to avoid collision with other Tyanc apps.

* Multi-company support: every core table includes a company\_id foreign key for entity scoping.

* Soft deletes are enabled on all employee-related tables to support data retention requirements.

* Payroll run tables use database-level transactions and are append-only once finalized (no UPDATE, only INSERT of correction runs).

* Sensitive fields (salary amounts, tax IDs, bank accounts) must use Laravel's encrypted cast.

## **11.3  File Storage**

* Employee documents, payslips, offer letters, and training certificates are stored using Laravel's filesystem abstraction.

* All uploads go through Tyanc's shared file management infrastructure to ensure consistent storage, access control, and virus scanning.

* Generated PDFs (payslips, offer letters, warning letters, experience certificates) use Laravel's PDF generation pipeline and are stored alongside their parent record.

## **11.4  Notifications**

Jeleme uses Tyanc's shared notification infrastructure. All notification templates are configurable through Settings \> Notification Templates within Jeleme. Notifications are sent via the following channels:

* In-app notifications (through the Tyanc notification bell, visible in the shared admin shell).

* Email notifications (using Laravel Mail \+ configured SMTP/SES).

* WhatsApp / SMS integration is out of scope for v1.0 but the notification system is designed to be channel-extensible.

# **12\. Out of Scope for v1.0**

The following items are explicitly excluded from Jeleme v1.0 to keep the first release focused and deliverable within timeline.

* AI-powered resume screening or candidate scoring — all screening is manual.

* AI-generated performance review suggestions — all review content is human-authored.

* AI chatbot or virtual HR assistant.

* Native mobile application (Jeleme v1.0 is a responsive web app only).

* Direct job portal API integrations (JobStreet, LinkedIn) — posting to external boards is manual in v1.0.

* Fingerprint / biometric device driver — attendance device integration is via an open API endpoint; hardware driver is not bundled.

* LMS (Learning Management System) — Jeleme tracks training enrollment and attendance but does not host course content.

* Complex compensation benchmarking or salary survey tools.

* Payroll bank transfer automation — payslip generation is in scope; automated bank file generation is v2.

# **13\. Phased Release Plan**

| Phase | Target | Modules Delivered |
| :---- | :---- | :---- |
| **Phase 1** | Months 1–2 | Tyanc Integration Setup, Org Structure, Employee Management (core profile), Settings & Configuration |
| **Phase 2** | Months 3–4 | Attendance & Time Management, Leave Management, Onboarding Module |
| **Phase 3** | Months 5–6 | Full Recruitment Pipeline (all 13 steps), Employee → Staff Conversion |
| **Phase 4** | Months 7–8 | Payroll Management (PPh 21, BPJS, payslip generation), Benefits & Compensation |
| **Phase 5** | Months 9–10 | Performance Management, Training & Development |
| **Phase 6** | Months 11–12 | Disciplinary Management, Offboarding & Separation, Reports & Analytics, Final Hardening |

# **14\. Glossary**

| Term | Definition |
| :---- | :---- |
| Tyanc | The shared admin platform and control plane that all business apps (including Jeleme) are built on top of. |
| Cumpu | Tyanc's approval workspace. Handles review workflows, approval chains, and audit trails for all cross-module approval gates. |
| Jeleme | The HRM application within the Tyanc ecosystem. Runs at /jeleme/\* and uses the jeleme.\* permission namespace. |
| MPP | Manpower Plan. The annual headcount budget agreed between HR and department heads, which authorizes job requisitions. |
| RBAC | Role-Based Access Control. All Jeleme access is governed by Spatie Laravel Permission roles registered in Tyanc. |
| PPh 21 | Pajak Penghasilan Pasal 21\. Indonesian personal income tax withheld by the employer and calculated per payroll run. |
| BPJS | Badan Penyelenggara Jaminan Sosial. Indonesian social insurance (BPJS Ketenagakerjaan for employment, BPJS Kesehatan for health). |
| SP1/SP2/SP3 | Surat Peringatan (Warning Letter) 1, 2, and 3\. Formal disciplinary escalation under Indonesian labor law. |
| Pesangon | Severance pay paid to employees upon termination or resignation (after certain conditions), calculated under Indonesian Manpower Law. |
| IDP | Individual Development Plan. A documented learning and growth plan created during performance reviews and tracked via Training module. |
| Pipeline Tracker | The visual interface in the Recruitment module showing every candidate's current step (out of 13\) in the hiring process. |
| Onboarding Module | The module that activates after a candidate completes Step 13 of recruitment, guiding first-day setup and orientation tasks. |
| jlm\_ prefix | The database table prefix used by all Jeleme tables to avoid naming conflicts with other Tyanc apps. |

| Jeleme  —  Human Resources Management A Tyanc Platform App  |  PRD v1.0  |  April 2026  |  Confidential |
| :---: |
