# implement

Agent Skill for implementing tasks from `docs/tasks`, other sources or user requests.

## When to Use

Use this skill when:
- Implementing a task listed in `docs/tasks`.
- The user asks to implement a refactoring.
- The user references a specific pending task from the task list.
- The user requests a new feature or change that requires code implementation.

Do **not** use this skill when:
- The task is already marked as "Done" in the task list.
- The user asks a question about a task without requesting implementation.

## Workflow

Follow these steps for each task implementation. Repeat the cycle until all critical issues are resolved.

### 1. Read the task and guidelines

- Open mentioned file in `docs/tasks/` (if applicable) and identify the task to implement.
- Read `src/Agents.md` for implementation rules and architecture guidelines. **Follow all rules strictly.**
- Read `docs/nfr_requirements.md` for non-functional requirements if applicable.
- Read `docs/system_design.md` for system design decisions if applicable.

### 2. Implement the task

- Follow the task description and any previously discussed decisions.
- Adhere to all rules in `src/Agents.md`:
  - Modular monolith with bounded contexts
  - Layer responsibilities (Domain, Application, Infrastructure)
  - Cross-context boundaries via events and ID/Value Objects
  - Doctrine XML mappings only, no ORM annotations in Domain
  - Domain events, Outbox, AuditLog patterns
  - Application layer contracts (final readonly DTOs, ID-VO + scalars)
  - Domain types and errors (typed exceptions, ErrorType enum)
  - Transactions and execution flow (concurrent safety, locks)
- If you encounter questions or design decisions during implementation, **make the recommended decision and record it**. Keep a running list of all decisions made.

### 3. Write tests for new and changed functionality except for trivial getters/setters and infrastructure (3rd-party, DB) calls.

### 4. Run tests and static analysis

After implementation, run the following commands:

```bash
docker compose run --rm php-app vendor/bin/phpstan analyse --memory-limit 256M
docker compose run --rm php-app php vendor/bin/phpunit
docker compose run --rm php-app bin/console lint:container
docker compose run --rm php-app bin/console lint:translations --locale=uk
docker compose run --rm php-app bin/console lint:translations --locale=en
docker compose run --rm php-app bin/console lint:translations --locale=pl
docker compose run --rm php-app bin/console lint:translations --locale=ru
```

- If any check fails, fix the issues and re-run until **all pass**.
- Do not proceed to review until tests, static analysis, and container/translations lint are clean.

### 5. Review uncommitted changes

After tests pass, perform a thorough review of all uncommitted changes:

- Check for adherence to `src/Agents.md` rules.
- Check for code quality, naming consistency, and architecture compliance.
- Identify **critical issues** (must fix before marking task as done):
  - Rule violations in `src/Agents.md`
  - Broken tests or PHPStan errors
  - Architecture violations (e.g., cross-context dependency, Domain depends on Infrastructure)
  - Missing cascade/orphan-removal in Doctrine mappings
  - Incorrect exception handling
- Identify **minor observations** (nice to have, but not blocking):
  - Naming inconsistencies
  - Code style improvements
  - Missing tests for edge cases
  - Documentation updates

### 6. Fix critical issues

- Fix all critical issues identified in the review.
- If minor issues are found alongside critical ones, fix them too.
- Re-run tests and static analysis after fixes.
- Re-run review after fixes.

### 7. Repeat until clean

Continue the cycle: **fix → tests → review → fix** until:
- All PHPUnit tests pass.
- No PHPStan errors.
- Review finds no critical issues.

### 8. Update the task list

- Mark the completed task as "Done" in related file in `docs/tasks`.
- Add a brief description of what was implemented.

### 9. Report decisions

After completing the task, report all decisions made during implementation to the user. Include:
- Design choices and their rationale.
- Any deviations from the original plan.
- Known limitations or follow-up items.

## Important Notes

- **Always read `src/Agents.md` before implementing code changes.** It is the authoritative source for architecture rules.
- **Always run all checks after any code changes:** PHPUnit, PHPStan, `lint:container`, and `lint:translations`. Do not skip any of them.
- **The review step is mandatory** — do not skip it. A task is not complete until review finds no critical issues.
- **Critical issues must be fixed** before marking a task as done.
- **Keep a running list of decisions** made during implementation and report them at the end.
- **Propose short commit message** using conventional commit format (e.g., `feat:`, `fix:`, `refactor:`).
- **Do not commit changes** unless explicitly requested by the user.
