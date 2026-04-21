---
name: code-implementation-plan
description: 'Create a structured implementation plan with role-based task assignments (Engineer/Designer). Use this skill when the user wants to plan new features, refactoring, upgrades, design work, architecture changes, or any development task that benefits from breaking work into phases with clear ownership. Trigger whenever the user mentions implementation plans, project planning, task breakdowns, feature specs, or asks to organize development work — even if they do not explicitly say "plan".'
---

# Code Implementation Plan

## Primary Directive

Create a new implementation plan file for `${input:PlanPurpose}`. Output must be machine-readable, deterministic, and structured for autonomous execution by AI systems or humans.

## Core Requirements

- Generate fully executable implementation plans
- Use deterministic language with zero ambiguity
- Structure all content for automated parsing
- Ensure complete self-containment

## Phase Architecture

- Each phase must have measurable completion criteria
- Tasks within phases are executable in parallel unless dependencies are specified
- All task descriptions must include specific file paths, function names, and exact implementation details
- No task should require human interpretation or decision-making

## Role Assignments

Every task must be assigned to one of two roles. This clarifies ownership and enables parallel workstreams between engineering and design.

| Role | Tag | Scope |
|------|-----|-------|
| Engineer | `🔧 Engineer` | Code, logic, backend, API, database, infrastructure, testing |
| Designer | `🎨 Designer` | UI, UX, frontend components, styling, layout, visual assets |

When a task spans both roles (e.g., "build a form with validation"), split it into two tasks — one for each role. Prefer clarity over brevity.

## AI-Optimized Standards

- Use explicit, unambiguous language
- Structure content as machine-parseable formats (tables, lists)
- Include specific file paths and exact code references where applicable
- Define all variables, constants, and configuration values explicitly
- Use standardized prefixes: `REQ-`, `TASK-`, `CON-`, `DEP-`, etc.
- Include validation criteria that can be automatically verified

## Output File Specifications

- Save implementation plan files in `.docs/plans/` directory
- Use naming convention: `[purpose]-[component]-[version].md`
- Purpose prefixes: `upgrade|refactor|feature|data|infrastructure|process|architecture|design`
- Example: `upgrade-system-command-4.md`, `feature-auth-module-1.md`

## Status

Use one of these statuses in front matter (badge color in parentheses): `Completed` (brightgreen), `In progress` (yellow), `Planned` (blue), `Deprecated` (red), `On Hold` (orange).

## Mandatory Template

All plans must follow this template exactly. No placeholder text may remain in the final output.

```md
---
goal: [Concise title describing the plan's goal]
version: [Optional: e.g., 1.0]
date_created: [YYYY-MM-DD]
last_updated: [Optional: YYYY-MM-DD]
owner: [Optional: Team/Individual]
status: 'Completed'|'In progress'|'Planned'|'Deprecated'|'On Hold'
tags: [Optional: e.g., feature, upgrade, chore, architecture, migration, bug]
---

# Introduction

![Status: <status>](https://img.shields.io/badge/status-<status>-<status_color>)

[A short concise introduction to the plan and the goal it is intended to achieve.]

## 1. Requirements & Constraints

- **REQ-001**: Requirement 1
- **SEC-001**: Security Requirement 1
- **CON-001**: Constraint 1
- **GUD-001**: Guideline 1
- **PAT-001**: Pattern to follow 1

## 2. Implementation Steps

### Phase 1: [Phase Title]

- GOAL-001: [Describe the goal of this phase]

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-001 | Description of task 1 | 🔧 Engineer | ✅ | 2025-04-25 |
| TASK-002 | Description of task 2 | 🎨 Designer | | |
| TASK-003 | Description of task 3 | 🔧 Engineer | | |

### Phase 2: [Phase Title]

- GOAL-002: [Describe the goal of this phase]

| Task | Description | Assign | Completed | Date |
|------|-------------|--------|-----------|------|
| TASK-004 | Description of task 4 | 🎨 Designer | | |
| TASK-005 | Description of task 5 | 🔧 Engineer | | |
| TASK-006 | Description of task 6 | 🔧 Engineer | | |

## 3. Alternatives

- **ALT-001**: Alternative approach 1
- **ALT-002**: Alternative approach 2

## 4. Dependencies

- **DEP-001**: Dependency 1
- **DEP-002**: Dependency 2

## 5. Files

- **FILE-001**: Description of file 1
- **FILE-002**: Description of file 2

## 6. Testing

- **TEST-001**: Description of test 1
- **TEST-002**: Description of test 2

## 7. Risks & Assumptions

- **RISK-001**: Risk 1
- **ASSUMPTION-001**: Assumption 1

## 8. Related Specifications / Further Reading

[Link to related spec or documentation]
```
