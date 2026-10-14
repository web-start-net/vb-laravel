# AI Developer Test Log & Autonomous Validation Audit

## Overview
This document tracks automated evaluations of fresh AI agents tasked with building complete production add-ons using **only** `AGENTS.md` and repository resources, with zero prior conversation history.

---

## Test Run 1: Thread Rating Add-on (`wsratings`)

### Objective
Tasked subagent with building a thread ratings add-on with star ratings (1–5), API endpoints (`rate` and `get`), database migration, model, template hook, settings, and phrases under `examples/ratings`.

### Observations & Discoveries
1. **Error Handling Convention**:
   - Returning `['error' => '...']` causes vBulletin's API gateway to treat the request as HTTP 200 with normal output.
   - Standardized on throwing `throw new \vB_Exception_Api('phrase_key')` to allow vB's native API error formatter to return standard `{"errors":[["phrase_key"]],"userid":...}` and appropriate status codes.
2. **Template Hook Variable Scope**:
   - Found that when vBulletin includes template hooks via `{vb:hook <name>}`, if `<arguments />` is empty in the product XML, `vB5_Template_Runtime::buildVars()` returns an empty array, stripping all variables.
   - Updated `Compiler.php` to automatically inspect `data/vb6-hooks.json` and serialize argument mappings (e.g. `conversation`, `nodeid`, `page`) into `<arguments>` so variables are automatically available in template hooks without developer boilerplate.
3. **vB6 Layout Modernization**:
   - Modern vB6 thread pages use `display_contenttype_threadview_header` and `display_contenttype_threadview_footer`, executing `conversation_post_above` and `conversation_post_below` directly on each thread and post.
   - Embedded rating interactive widget into `conversation_post_above.html` with hover star effects, live POST submission to `/ajax/api/wsratings.rating/rate`, and instant feedback.

### Verification Result
- `php bin/vbl validate examples/ratings`: **PASS** (0 errors)
- `php bin/vbl build examples/ratings`: **PASS** (0 errors)
- `tests/ratings-live-verify.php`: **PASS** (100% on live vB6 forum at `http://127.0.0.1/vbtest/`)

---

## Test Run 2: Post Thanks Add-on (`wsthanks`)

### Objective
Subagent spawned with fresh context and zero history. Prompted to build `examples/thanks` (`wsthanks`) with:
- `vb-addon.json`
- `src/ThanksServiceProvider.php`
- `database/migrations/2026_10_14_000000_create_wsthanks_thanks_table.php` (`id`, `nodeid`, `userid`, `created_at`, unique `[nodeid, userid]`)
- `src/Models/Thank.php`
- `src/Api/ThankController.php` with `get(int $nodeid)` and `toggle(int $nodeid)` (throws `\vB_Exception_Api('no_permission')`)
- `config/settings.php` and `lang/en.php`
- `templates/hooks/conversation_post_below.html` with AJAX thank toggle and counter

### Friction & Bugs Discovered
1. **`\v` Escape Sequence in Generator Heredoc**:
   - **Issue**: `src/Dev/Generator.php` line 271 contained `throw new \vB_Exception_Api('item_not_found');` inside an unquoted heredoc (`<<<PHP ... PHP;`). In PHP, `\v` in double-quoted heredocs evaluates to vertical tab (ASCII 11), generating invalid syntax.
   - **Fix**: Escaped as `\\vB_Exception_Api` in `Generator.php`.
2. **Manifest Schema vs. Validator Flexibility**:
   - `schemas/vb-addon.schema.json` allows either `title` or `name` via `anyOf`, while `Validator.php` permits either. Provided both `"name"` and `"title"` in manifests to maximize clarity.
3. **Template Hook Variable Access**:
   - In `conversation_post_below.html`, post ID is accessed safely via:
     `{vb:set postNodeId, {vb:if "!empty($conversation['nodeid'])", {vb:raw conversation.nodeid}, {vb:raw nodeid}}}`

### Verification Result
- `php bin/vbl validate examples/thanks`: **PASS** (0 errors)
- `php bin/vbl build examples/thanks`: **PASS** (0 errors)
- `tests/cli-generators-test.php`: **PASS** (100%)
- `tests/thanks-live-verify.php`: **PASS** (verified on live vB6 forum at `http://127.0.0.1/vbtest/`)
  - Datastore hook registration: **PASS**
  - Toggle ON / Toggle OFF count and boolean state: **PASS**
  - Guest access denial: **PASS**
  - HTTP AJAX POST CSRF/Guest rejection: **PASS**
  - Live page rendering: **PASS**
