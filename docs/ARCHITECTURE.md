# System Architecture

## Overview
The Hygiene Checklist System is a Laravel-based web application designed to manage daily hygiene inspections, defect reporting, and compliance tracking for a manufacturing/industrial environment.

## Tech Stack
- **Framework:** Laravel 11.x
- **Frontend:** Blade Templates + Tailwind CSS + Vanilla JS
- **Database:** MySQL
- **PDF Generation:** dompdf
- **Image Processing:** Intervention Image

## Core Components

### 1. Data Models (`app/Models`)
*   **Structure:** `Department` -> `Location` (Area) -> `Machine`
*   **People:** `User` (System Users), `Employee` (Inspectees)
*   **Inspection:** 
    *   `InspectionSession`: The header record for a round of checks.
    *   `InspectionLog`: The individual check result (Pass/Fail) for a specific Checkpoint.
    *   `Checkpoint`: The master data of *what* to check.

### 2. Controllers (`app/Http/Controllers`)
*   **InspectionController:** Handles the operational flow (Start, Scan, Save, Finish). *Refactoring Target: Logic to be moved to InspectionService.*
*   **ReportController:** Statistics and PDF export. *Refactoring Target: Logic to be moved to ReportService.*
*   **CorrectiveActionController:** Manages the lifecycle of heavy defects (CARs).

### 3. Services (Proposed)
To maintain code quality, complex business logic is delegated to Service classes:

*   **`App\Services\InspectionService`:**
    *   Handle Session State Transitions (Start -> Finish -> Verify).
    *   Validate Rules (e.g., "Cannot verify if re-cleans are pending").
    *   Handle Complex Data saving (Transactions).
    
*   **`App\Services\ReportService`:**
    *   Encapsulate query logic for Dashboards (Offenders, Trends).
    *   Handle Scope logic (Isolated Department vs Global View).

## Role & Permission Matrix

| Role | Inspection | Verify | Approve | Manage Users | View Reports |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Admin** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Manager** | ❌ | ✅ | ✅ | ❌ | ✅ |
| **Supervisor**| ❌ | ✅ | ❌ | ❌ | ✅ (Own Dept) |
| **Inspector** | ✅ | ❌ | ❌ | ❌ | ❌ |

## Directory Structure
```
app/
├── Http/Controllers/   # Request Handling
├── Models/            # Eloquent ORM
├── Services/          # Business Logic (New)
└── Policies/          # Authorization Rules
docs/
├── WORKFLOW.md        # State Machine Definitions
└── ARCHITECTURE.md    # This file
```
