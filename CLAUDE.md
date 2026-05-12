<!-- claude-memory:start -->

# Claude Memory System

This project uses **claudememory** — a dual-layer semantic index over Git commit history for Claude Code.

## Always Start Here

When beginning any task in this repository:

1. Call `latest_commits(5)` to understand what changed recently
2. Call `search_git_history(<relevant topic>)` before touching any module with history
3. After fixing a bug, call `bug_fix_history(<component>)` to check for prior regressions

## Available Skills

| Task | Skill |
|------|-------|
| Search commit history for a topic | `/claude-memory-search` |
| Index a new repository | `/claude-memory-index` |
| Debug why a component behaves a certain way | `/claude-memory-debug` |
| Check what's currently indexed | `/claude-memory-status` |

## MCP Tools Reference

| Tool | What it gives you | When to use |
|------|-------------------|-------------|
| `search_git_history(query, limit, category)` | Commits semantically related to a topic | Before editing any significant module |
| `latest_commits(limit)` | N most-recent indexed commits | Session start, before investigating regressions |
| `commits_touching_file(filename, limit)` | All commits that modified a file | Before editing a file — understand its history |
| `bug_fix_history(component, include_security)` | Bug/security fixes for a component | Before adding new code near known bug areas |
| `architecture_decisions(topic, limit)` | Refactors, migrations, design decisions | Understanding why code is structured a certain way |

## Proactive Usage Rules

**Always call before editing:**
```
commits_touching_file("PaymentService.php")  # know what's broken here before
bug_fix_history("auth")                       # avoid re-introducing fixed bugs
```

**Always call at session start:**
```
latest_commits(10)   # what changed while you were away?
```

**Always call when confused about design:**
```
architecture_decisions("state machine")  # why was this abstraction introduced?
search_git_history("why was X removed")
```

## Category Filter Values

Use `category=` in `search_git_history()` to narrow results:

| Category | Matches |
|----------|---------|
| `fix`    | Bug fixes, hotfixes, patches |
| `feat`   | New features |
| `security` | Security-related changes |
| `refactor` | Code refactors |
| `migration` | Database/schema migrations |
| `arch`   | Architecture decisions |
| `perf`   | Performance improvements |

<!-- claude-memory:end -->

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **laravel-seo-indexing** (576 symbols, 1511 relationships, 33 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

> If any GitNexus tool warns the index is stale, run `npx gitnexus analyze` in terminal first.

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `gitnexus_impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `gitnexus_detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `gitnexus_query({query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `gitnexus_context({name: "symbolName"})`.

## When Debugging

1. `gitnexus_query({query: "<error or symptom>"})` — find execution flows related to the issue
2. `gitnexus_context({name: "<suspect function>"})` — see all callers, callees, and process participation
3. `READ gitnexus://repo/laravel-seo-indexing/process/{processName}` — trace the full execution flow step by step
4. For regressions: `gitnexus_detect_changes({scope: "compare", base_ref: "main"})` — see what your branch changed

## When Refactoring

- **Renaming**: MUST use `gitnexus_rename({symbol_name: "old", new_name: "new", dry_run: true})` first. Review the preview — graph edits are safe, text_search edits need manual review. Then run with `dry_run: false`.
- **Extracting/Splitting**: MUST run `gitnexus_context({name: "target"})` to see all incoming/outgoing refs, then `gitnexus_impact({target: "target", direction: "upstream"})` to find all external callers before moving code.
- After any refactor: run `gitnexus_detect_changes({scope: "all"})` to verify only expected files changed.

## Never Do

- NEVER edit a function, class, or method without first running `gitnexus_impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `gitnexus_rename` which understands the call graph.
- NEVER commit changes without running `gitnexus_detect_changes()` to check affected scope.

## Tools Quick Reference

| Tool | When to use | Command |
|------|-------------|---------|
| `query` | Find code by concept | `gitnexus_query({query: "auth validation"})` |
| `context` | 360-degree view of one symbol | `gitnexus_context({name: "validateUser"})` |
| `impact` | Blast radius before editing | `gitnexus_impact({target: "X", direction: "upstream"})` |
| `detect_changes` | Pre-commit scope check | `gitnexus_detect_changes({scope: "staged"})` |
| `rename` | Safe multi-file rename | `gitnexus_rename({symbol_name: "old", new_name: "new", dry_run: true})` |
| `cypher` | Custom graph queries | `gitnexus_cypher({query: "MATCH ..."})` |

## Impact Risk Levels

| Depth | Meaning | Action |
|-------|---------|--------|
| d=1 | WILL BREAK — direct callers/importers | MUST update these |
| d=2 | LIKELY AFFECTED — indirect deps | Should test |
| d=3 | MAY NEED TESTING — transitive | Test if critical path |

## Resources

| Resource | Use for |
|----------|---------|
| `gitnexus://repo/laravel-seo-indexing/context` | Codebase overview, check index freshness |
| `gitnexus://repo/laravel-seo-indexing/clusters` | All functional areas |
| `gitnexus://repo/laravel-seo-indexing/processes` | All execution flows |
| `gitnexus://repo/laravel-seo-indexing/process/{name}` | Step-by-step execution trace |

## Self-Check Before Finishing

Before completing any code modification task, verify:
1. `gitnexus_impact` was run for all modified symbols
2. No HIGH/CRITICAL risk warnings were ignored
3. `gitnexus_detect_changes()` confirms changes match expected scope
4. All d=1 (WILL BREAK) dependents were updated

## Keeping the Index Fresh

After committing code changes, the GitNexus index becomes stale. Re-run analyze to update it:

```bash
npx gitnexus analyze
```

If the index previously included embeddings, preserve them by adding `--embeddings`:

```bash
npx gitnexus analyze --embeddings
```

To check whether embeddings exist, inspect `.gitnexus/meta.json` — the `stats.embeddings` field shows the count (0 means no embeddings). **Running analyze without `--embeddings` will delete any previously generated embeddings.**

> Claude Code users: A PostToolUse hook handles this automatically after `git commit` and `git merge`.

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->
