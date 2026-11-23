# Claude instructions for `certificateelement_enrolstart`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding
style, CI gates, lang-string rules, the `mdl` environment, git rules) — do not
repeat them here. This file keeps only what is true for this plugin, as rules
with a one-line reason each; keep it under ~250 lines, because every session
and every subagent pays for it on every cache write. Measurements, incidents
and the long version of a gotcha go to `docs/` (export-ignored) with a pointer
from the rule.

Plugin context: a **certificateelement** subplugin of `tool_certificate` ("Enrolment start date") that
prints the date the learner's enrolment in the course started. It owns no table: the date is
frozen into `tool_certificate_issues.data` when a certificate is issued. Supports Moodle **5.3 only**
(`$plugin->requires = 2026100500`, `$plugin->supported = [503, 503]`), because the certificate
fork is mounted on the 5.3 stacks only. CI is `uaiblaine/moodle-workflows`, one job in
`.github/workflows/ci.yml` — **update it when `supported` changes**. The plugin installs at
`admin/tool/certificate/element/enrolstart`, inside the certificate tool's own tree.

## Agent orchestration budget (fleet rule, repeated here on purpose)

Section 6 of `~/dev/CLAUDE.md` (`moodle-dev/CLAUDE.fleet.md`) is the authority and says why.
This short copy reaches sessions that do not load that file: cloud sessions and checkouts
outside `~/dev`. Every subagent gets the model and effort of its role from its agent
definition, and none runs on the session model.

| Role | model | effort | agent |
|---|---|---|---|
| Mechanical sweeps, greps, renames, counts, log reading | `haiku` | `medium` | `fleet-sweeper` |
| Checklists against evidence (handoff counts, spec lines against a sweep log, lang lockstep) | `haiku` | `high` | `fleet-checker` |
| Readers, measurers, graders | `sonnet` | `medium` | `fleet-reader` |
| Refuters and verifiers of a blocking finding | `sonnet` | `high` | `fleet-verifier` |
| Well-scoped implementation (established cause, settled design, written recipe) | `sonnet` | `medium` | `fleet-fixer` |
| Non-trivial implementation (open design, several files, long tasks) | `opus` | `high` | `fleet-implementer` |
| Consolidators, critics, estimators, ADR and documentation drafters | `opus` | `high` | `fleet-synthesist` |

- Launch the `Agent` tool with `subagent_type: "fleet-*"`; it has no `effort` parameter, so
  the role's effort comes from that definition (`mdl claude-setup` installs them). Where they
  are not installed, pass `model`. Aliases only; never `fable`; `xhigh` only for a long-horizon
  implementer whose prompt says why; never `xhigh`/`max` on Sonnet or Haiku.
- No long command inside a subagent: `mdl ci --matrix`, `mdl mutate` and Behat run from the
  main session in a background Bash command; a subagent runs the fast gate its prompt names and
  reports the command with its counts.
- Workflows only on the user's opt-in, every `agent()` with `agentType: 'fleet-*'`, under 10
  agents. Advisor off by default.

## Commands

```sh
mdl ci moodle-certificateelement_enrolstart --branch MOODLE_503_STABLE --php 8.4            # one leg
mdl ci moodle-certificateelement_enrolstart --branch MOODLE_503_STABLE --php 8.4 --db mariadb
mdl phpunit m503 certificateelement_enrolstart    # once the plugin is mounted on the stack
```

## Code layout

```
classes/element.php          the element: its one field (date format), the PDF and editor rendering
classes/local/enrolment.php  the rule: which date an enrolment started, and which enrolment answers
classes/local/snapshot.php   the date frozen into an issue: capture(), read(), start_of()
classes/observer.php         certificate_issued -> snapshot::capture()
db/events.php                the one observer
```

## Versions

`$plugin->version` counts in the Moodle 5.3 namespace (`20261005XX`), as every branch-per-version
plugin of the fleet does (`~/dev/CLAUDE.md`, "XMLDB, upgrades, privacy, versioning").

## Architecture gotchas

- **The date is written when the certificate is issued and read from the issue ever after.** A
  template lives in the system or a category context and knows no course; the issue row does
  (`courseid`). `\tool_certificate\template::issue_certificate()` inserts the issue, triggers
  `certificate_issued`, reads the issue again and only then builds the PDF, so the observer's write
  is what the first PDF prints. Never compute the date in `render()` for an issue that has the key.
- **The key is present on every issue made for a course, and 0 means "looked, found none".**
  `snapshot::start_of()` falls back to the live enrolments only when the key is absent (an issue
  older than the plugin). Writing nothing for "no enrolment" would let an enrolment made later date
  a certificate issued before it.
- **`timestart` is 0 for most methods, on purpose in core** (`enrol_plugin::enrol_user()` documents
  it as unknown): cohort, database, LDAP, category, and `enrol_try_internal_enrol()` for a course
  creator. `timecreated` stands in, as in core's `completion_criteria_duration::cron()`. A restore
  keeps `timestart` and recreates the row, so `timecreated` is then the day of the restore; there
  is no better source (`course_completions.timeenrolled` has no writer in core 5.3).
- **Only `status = ENROL_USER_ACTIVE` rows are read.** An `enrol_apply` application is a suspended
  row created at application time with `timestart = 0`; counting it would print the day the person
  applied. Approval sets `timestart`.
- **The first PDF is not always built from the frozen date.** `issue_certificate()` releases the
  issuer's lock before the event, so a concurrent view of the activity can build the file first,
  and an issuer may build the file before it triggers the event: `render()` then reads live,
  judged at the issue's `timecreated`, which is the same date at that moment. A regeneration that
  loaded the row before the observer wrote and saves after it drops the key
  (`create_issue_file()` writes its in-memory `data` back).
- **A data request does not export the date** (README, "Data stored"): `tool_certificate`'s provider
  exports custom fields, never `issues.data`. Open, the owner's call; the fix belongs in the fork.
- **An observer that throws is swallowed by core's event manager** (a `debugging()` call), so a
  failure here never stops a certificate from being issued; it only leaves the issue without a key.
  Only an `\Exception`: an `\Error` is not caught and would stop the issue.

## Testing notes

- Issue through the certificate tool's generator with a course id
  (`$generator->issue($template, $user, null, [], 'mod_coursecertificate', $courseid)`), after
  `redirectMessages()`: the issue sends a notification. The observer runs in the test, so the row
  read back already carries the key; a test under `redirectEvents()` would see none.
- `enrol_user()` stamps `timecreated` with the wall clock; tests set the column afterwards.
- The PDF is asserted through a mock of `\pdf::writeHTMLCell()`, fifth argument, as the certificate
  tool's own element tests do.

## When in doubt

Follow the patterns in existing files. The codebase is internally
consistent — if a new file feels like it matches no existing shape,
re-examine the approach.
