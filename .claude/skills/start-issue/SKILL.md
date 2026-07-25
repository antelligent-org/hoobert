---
name: start-issue
description: Read a GitHub issue from this repo and offer to start work on it. Use when the user gives a GitHub issue URL (e.g. https://github.com/antelligent-org/hoobert/issues/2) or an issue number and wants to begin implementing the bug, feature, or task it describes.
---

# Start a GitHub issue

Fetch an issue from this repo on GitHub, summarize it, and help start work.

## Inputs

- **Issue URL or number** supplied by the user, e.g. `https://github.com/antelligent-org/hoobert/issues/2`, or just `#2`. The trailing integer is the issue number.
- **Access token (optional).** The repo is public, so the reads below work unauthenticated. A token only raises the rate limit (60 requests/hour anonymous, 5000 authenticated) and is required if the repo ever goes private. Use `$GITHUB_TOKEN` or `$GH_TOKEN` if either is exported; do not stop or prompt when both are unset.

## Fixed values

- Repo: `antelligent-org/hoobert`. The `origin` remote still reads `anu-rock/woobert`; GitHub redirects it, but call the API with the current name so no redirect hop is needed.
- API base: `https://api.github.com`
- Issue endpoint: `GET /repos/antelligent-org/hoobert/issues/:number` (https://docs.github.com/rest/issues/issues#get-an-issue)
- Comments endpoint: `GET /repos/antelligent-org/hoobert/issues/:number/comments` (https://docs.github.com/rest/issues/comments#list-issue-comments)

## Steps

1. **Parse the number** from the URL: the integer after `/issues/`. If the user gave a bare number, use it. If neither is present, ask.

2. **Set up auth, if any.** If `GITHUB_TOKEN` or `GH_TOKEN` is exported, pass it as `-H "Authorization: Bearer $GITHUB_TOKEN"`. If not, send the request without that header and move on. Never echo the token or interpolate its value into a visible command; reference the env var.

   `gh` is not installed on this machine. If it ever is, `gh issue view <n> --repo antelligent-org/hoobert --comments` collapses steps 3 and 4 into one call and handles auth itself. Prefer it when available.

3. **Fetch the issue.**

   ```bash
   curl -sS --fail-with-body -H "Accept: application/vnd.github+json" \
     "https://api.github.com/repos/antelligent-org/hoobert/issues/<N>"
   ```

   Failure modes: `404` means a wrong number, or a private repo with no token. `403` with `rate limit` in the body means the anonymous limit is spent, so retry with a token or wait. `401` means the token is bad or expired.

   **Check for a `pull_request` key in the response.** GitHub numbers issues and pull requests in one sequence, so `/issues/<N>` happily returns a PR. This repo's release-please PRs sit in that range. If the key is present, say the number is a PR rather than an issue and ask the user how to proceed.

4. **Fetch the comments.** Always do this. Comments routinely carry context that is not in the description: a second repro, a narrowed scope, a correction, an attachment. The issue JSON's `comments` field gives the count to expect.

   ```bash
   curl -sS --fail-with-body -H "Accept: application/vnd.github+json" \
     "https://api.github.com/repos/antelligent-org/hoobert/issues/<N>/comments?per_page=100"
   ```

   This endpoint returns human comments only. GitHub's automated entries (label changes, renames, cross-references) live on the separate timeline/events endpoints, so there is nothing to filter out. Read each comment's `body` and `user.login`.

5. **Summarize.** Combine the issue and its comments into a short summary, a few lines, not the raw payload:
   - Title and `#<number>`, linking `html_url`
   - `state` (with `state_reason` if closed), `labels[].name`, `assignees[].login`, `milestone.title`. Omit whichever are empty.
   - A 2 to 4 sentence digest of `body`: what the bug, feature, or task is, and the apparent acceptance criteria. Say so if the body is empty.
   - Anything the comments add or change. Fold material context (extra repros, scope changes, corrections) into the digest rather than dropping it. If a comment supersedes the description, the comment wins.

6. **Offer next steps.** Ask the user whether to:
   - **Create a plan**, researching the codebase and drafting an implementation plan for review (enter plan mode), or
   - **Jump to implementation**, starting the change directly.

   Wait for their choice before doing either.

7. **Create a feature branch.** Before the first commit, create a branch for this issue and do all the work there. Don't ask, and don't commit to `main`.

   This is the one place the repo departs from CLAUDE.md, which otherwise says to commit directly to `main`. Issue work branches so the tracker, the branch, and the merge commit line up, and so a half-finished series never sits on `main`. Everything else in CLAUDE.md still applies: the user reviews before every commit, you commit only when asked, and you never `--amend`.

   Name the branch `<type>/<issue-number>-<slug>`:
   - **`<type>`** is the conventional-commit type that fits the issue (`fix`, `feat`, `chore`, `refactor`, `docs`, `ci`), inferred from its labels and nature.
   - **`<issue-number>`** is the number from step 1.
   - **`<slug>`** is a short lowercase hyphenated label from the issue title, a few words, no punctuation.

   For example, issue 2 titled "Page context never reaches the model" becomes `fix/2-page-context-never-reaches-model`. Create it with `git checkout -b <branch>` and stay on it for the rest of the work.

8. **Verify with the real tools before committing.** This repo has no unit-test suite, so verification is the build plus the WordPress plugin checker plus a run against the local stack. Match the checks to what you touched:

   - **Front-end (`plugin/hoobert/src/**`)**: run `npm run build` inside `plugin/hoobert` and confirm it succeeds. `npm run lint:js` on top when the change is non-trivial.
   - **PHP (`plugin/hoobert/includes/**`, `hoobert.php`)**: no linter is wired up, so read the diff against the PHP rules in CLAUDE.md (ABSPATH guard, escaped output, `manage_woocommerce` on admin routes).
   - **Anything user-facing** (UI copy, `readme.txt`, settings screen, assets): run the plugin checker, which CLAUDE.md treats as the source of truth over eyeballing:

     ```bash
     docker compose run --rm --entrypoint wp wpcli plugin check hoobert --exclude-directories=node_modules,src,scripts,build
     ```

     Zero errors expected; the naming warnings are the only known ones.
   - **Behavioral changes**: exercise the journey in the local stack (`docker compose up -d`, then the command bar in wp-admin). Say what you ran and what you saw.

   If a check does not fit the change, skip it and say briefly why. Do not skip silently, and do not claim a check passed that you did not run.

9. **Close the issue from the commit.** Put the closing footer on **exactly one** commit, the one you make once you judge the work complete. Don't try to predict which commit is literally last; its position doesn't matter. GitHub scans commit messages landing on the default branch and auto-closes the issue if any one of them carries the keyword, so a single occurrence anywhere on the branch keeps the tracker in sync and repeating it is noise.

   The moment you decide the issue is resolved is also when you can judge the footer correctly: `Closes #<number>` if the work fully resolves the issue, `Refs #<number>` if it only partially addresses it (links without closing). Put it on its own line, before the `Co-Authored-By` trailer.

   Before wrapping up, check the branch carries it: `git log --grep='#<number>' <branch>`. If it is missing, add it to the next commit you make. **Never `--amend` to fix this**, per CLAUDE.md. If the work is already complete and no further commit is coming, tell the user so they can decide between a small follow-up commit and closing the issue by hand.

   Note that auto-close fires on push, not on the local merge. Until the merge commit reaches `origin/main`, the issue stays open.

10. **Merge into `main` with `--no-ff`.** When the user asks to merge, always create a merge commit, `git merge --no-ff <branch>`, never a fast-forward, even when the branch is a single commit that could fast-forward cleanly. The merge commit groups the issue's commits under one point on `main` and preserves which commits belonged to which issue. Let the message default (`Merge branch '<branch>'`). Delete the local branch afterward (`git branch -d <branch>`). Push only when the user asks.

## Code comments

Follow CLAUDE.md → *Code style*, which governs this repo generally. Two points bite hardest on issue work:

- **Never mention the issue number in a code comment** (`// #2`, "added for #2"). The branch and its merge commit already carry it.
- **Statement-level comments default to off.** Doc comments on functions, methods, and files are welcome; a gloss on a self-evident statement is noise. The bar is that omitting the context would severely hurt readability.

## Writing style

CLAUDE.md → *Writing style* governs anything user-facing you produce here, including the summary in step 5 and every commit message: **no em dashes**, short direct sentences.

## Notes

- Keep any token out of all output. Never paste it into a command the user can see resolved, and never include it in the summary.
- **Attachments** in an issue body or comment are plain public URLs on GitHub (`https://github.com/user-attachments/assets/<id>`, or older `.../files/<id>/<name>`), usually inside markdown image or link syntax. Fetch one with `curl -sSL "<url>" -o <dest>`; no auth header is needed, and no API detour like GitLab's uploads endpoint.
- Issue **numbers are per-repo and shared with pull requests**, unlike GitLab's separate IIDs. Step 3's `pull_request` check is what keeps the two apart.
- If the anonymous rate limit becomes a nuisance, `curl -sS https://api.github.com/rate_limit` shows what is left.
