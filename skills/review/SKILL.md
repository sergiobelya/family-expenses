---
name: review
description: Agent Skill for reviewing project changes against the requirements and guidelines that apply to the current task.
---
# review

Agent Skill for reviewing project changes against the requirements and guidelines that apply to the current task. This skill is read-only: report findings and evidence; do not modify files or fix issues.

## When to Use

Use this skill when:
- The user asks for a review of a change, diff, pull request, or implementation.
- The implementation workflow reaches its review step.

Do not use it to implement requested fixes; report findings first, then make changes only if the user or an implementation workflow requests them.

## Workflow

### 1. Establish the review scope

- Use the latest user request as the current task. Identify its task/subtask and acceptance criteria, if provided.
- Review only changes that implement or affect that task. Do not report unrelated pre-existing issues or review unrelated working-tree changes.
- For an uncommitted change, inspect relevant staged, unstaged, and untracked files. Do not assume a summary or earlier review accurately represents the current diff.
- If the task boundary or required behavior is unclear and materially affects the review, ask a focused question rather than guessing.

### 2. Load the applicable requirements

- Read the task description and the relevant functional requirements/specification. Follow references to business requirements where needed.
- For application-code changes, read `src/Agents.md` and assess the relevant rules. Read the applicable sections of `docs/nfr_requirements.md` and `docs/system_design.md`.
- Use the repository's requirement documents as the source of truth. Apply only criteria relevant to the changed behavior; do not turn future-phase requirements or unrelated guidance into findings for this task.

### 3. Verify functional behavior

- Trace each in-scope acceptance criterion through the changed code and its callers. Confirm both the expected outcome and relevant failure/edge paths.
- Inspect tests as evidence: check whether they exercise the required behavior, authorization boundaries, and negative cases relevant to the change. A passing test does not by itself prove the requirement is met.
- Identify missing, contradicted, or only partially implemented requirements. Cite the requirement and the code/test evidence; distinguish a defect from behavior explicitly deferred to another task.

### 4. Review architecture and non-functional requirements

For relevant changes, verify the implementation against `src/Agents.md`, NFRs, and system design, including as applicable:
- Clean Architecture boundaries and inward dependency direction; bounded-context ownership and DDD consistency boundaries.
- Whether Presentation calls business repositories directly instead of going through Application commands, queries, or services; distinguish this from a narrowly scoped port for adapter-owned technical interaction state, not business data or workflow drafts (see `docs/system_design.md` §4 and `src/Agents.md` §5).
- SOLID, focused responsibilities, and extension points consistent with the project's design.
- Server-side authorization on every protected operation; current family membership and entity ownership must be revalidated. Family-scoped reads and writes must be scoped in repository/service queries, not trusted from active-family state or hidden UI.
- Idempotency and duplicate handling for retried updates/commands; correct transaction boundaries, audit records, concurrency controls, and recovery behavior.
- Input validation at the appropriate layer, typed error handling, persistence constraints, and sensitive-data handling.

These are review dimensions, not a blanket checklist: report only a concrete violation that applies to the change and can be supported by evidence. Do not treat a preferred refactoring or stylistic alternative as a defect.

### 5. Validate and report

- Review the actual diff and surrounding code. Use precise current file/line references and explain the impact and violated requirement/principle for each finding.
- Order actionable findings by severity (`P1` highest, then `P2`, `P3`). Keep suggestions and out-of-scope follow-ups separate from defects.
- Do not change files. Do not claim tests, static analysis, or runtime behavior were verified unless the commands were actually run and their results are available. Running checks is optional for review; if requested or needed, follow the repository's test instructions and environment requirements.
- Respond in the user's language. Start with findings, or explicitly state that no in-scope findings were found. Include a concise status for the current task's functional requirements (satisfied, partial, or not verified, with evidence) and state which checks were or were not run.

## Review Principles

- Prefer a small number of high-confidence, actionable findings over speculative concerns.
- Report bugs introduced by the change, and pre-existing issues only when they directly prevent the current task's requirements from working.
- Never infer missing behavior solely from absent tests; trace the implementation and state separately whether test coverage is missing.
- Do not expand the review into a general audit of the repository.
