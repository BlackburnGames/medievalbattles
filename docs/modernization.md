# Modernization

Medieval Battles v6 was written in 2003 for PHP 4 and released publicly for
historical purposes. The work in progress is a staged modernization: get it
running under test first, port it mechanically, then rewrite it structurally.

The ordering is deliberate. Every stage before the rewrite exists to make the
rewrite reviewable — a change to 2003 code is only safe if something can tell
you the behaviour did not move.

## Phase 1 — a test-covered baseline (done)

A running stack on PHP 5.6 in Docker, plus the characterization suite described
in [testing.md](testing.md). Nothing about the game's behaviour was changed.
Three latent fatals were fixed to make the app boot at all; they are listed
under "Fixed once" in [porting-notes.md](porting-notes.md).

## Phase 2 — the mechanical port (done)

The sources now **run on PHP 8.3**, and the full suite is green on both 8.3 and
5.6 against the same golden master. What was removed, roughly in order:

- `mysql_*` → `mysqli_*`, with `mb_db_result()` standing in for
  `mysql_result()`'s two-argument form (see the trap writeup in
  [porting-notes.md](porting-notes.md)).
- Bare array keys (`$row[email]`) and bare word constants (`$x = golems`),
  fixed with the tokenizer rather than sed.
- `ereg_replace()` → `preg_replace()`.
- `session_register()` → `app/include/session.php` writing `$_SESSION`
  directly.
- `register_globals` → `mb_input()`. The 315 reads that depended on injection
  now name their parameter explicitly, and the shim is switched off.

Two supporting pieces landed alongside it: the manual was rebuilt from the
engine ([game-manual.md](game-manual.md)), and the compatibility layer was
emptied down to the one disabled block that remains.

## Phase 3 — the structural and security rewrite

In progress. The known work, in no committed order:

- ~~**SQL injection.**~~ **The audit is at zero: 161 entries down to none.** All ~1300 queries
  are built by string interpolation with no escaping. This is the reason the
  app must not be exposed to a network. `tests/sql-injection-audit.php` finds
  the reachable half of it — request input that survives into a
  `mysqli_query()` — and baselines it in `tests/sql-injection.txt` as a third
  ratchet.

  The fix is `mb_sql_str()` and `mb_sql_int()` in `app/include/db.php`, and
  which one a site needs is the interesting part. The audit records the quoting
  context because escaping only helps inside quotes: `WHERE topicid=$topicid`
  is interpolated bare, so no amount of escaping closes it and the value has to
  be cast instead.

  Prepared statements are the better answer and are not this change. Escaping
  every site can be checked by a suite that proves behaviour did not move;
  rewriting 1300 interpolations into bind calls cannot.

  The last 36 were not a random 36, and none of the three groups was closed by
  escaping alone:

  - **26 were in `barter.php` and `guildbarter.php`**, which were dead —
    `barter.php` died at line 17 and had since 2003. Rewriting an unreachable
    page is a guess, so the barter system was **switched back on and put on the
    crawl first**; see below.
  - **9 were in `app/css/admin/`**, which no test touched. That area is now
    `app/admin/`, has a real gate, and is crawled; see below.
  - **1 was `preferences.php`'s `$email`**, and it was the honest one. A
    player-chosen address is written to six tables and then assigned to
    `$email` — the name that ~1300 `WHERE email='$email' AND pw='$pw'` clauses
    interpolate. Escaping 1300 call sites was never the move, so the boundary
    moved instead. `app/include/credentials.php` says what an address and a
    password hash may be, and three places enforce it: `checksignup.php` (whose
    own address check had been commented out since 2003, so *any* string could
    be stored), `preferences.php`, and `include/session.php`, which discards a
    credential that fails on the way back out. That makes all 1300
    interpolations safe by construction and keeps them safe as they are
    rewritten. `preferences.php`'s own twenty are escaped as well, because a
    page that changes the value should not lean on a rule enforced elsewhere.

    It costs something and the cost is stated in that file: an apostrophe is
    legal in a real address and `o'brien@example.com` is now refused. That
    restriction can be lifted the day those queries bind their parameters.

  Two shapes of false positive turned up often enough to name. Both are one
  variable meaning two things, which a flow-insensitive audit cannot tell apart
  and neither can a reader — so both were fixed by renaming rather than
  suppressed:

  - **A flag and a value.** `wconstruct.php` reads `mb_input('longsword')` only
    to test `IsSet()`, then forty lines later assigns the same name the build
    time in ticks, and that is what the `UPDATE` writes. Twenty-two entries.
  - **A request id and a computed one.** `gc.php`'s `$gid` was both the guild
    being messaged and the id of the guild being created.

  And one shape of false *negative*, which matters more: `extract()` on a
  database row. It silently replaced the request value with a column of the same
  name, so four forum listings looked like they interpolated a request parameter
  when they did not — and it hid a genuine second-order injection in
  `S_VMESS.php` at the same time. All thirteen calls are gone.
- ~~**Unescaped output.**~~ **The audit is at zero: 29 entries down to none.**
  Every template echoes user data raw — there is no escaping helper anywhere in
  the 2003 sources, and the pages are one long `echo "<html> ... $var ..."` per
  branch. `tests/xss-audit.php` finds the reachable half of it and baselines it
  in `tests/xss.txt` as a fourth ratchet.

  It shares the SQL audit's taint tracker, which moved to `tests/taint-lib.php`
  rather than being copied: a source or a sanitizer that one audit knows about
  and the other does not is a false clean bill of health.

  The fix is `mb_h()` and `mb_attr()` in `app/include/html.php`, and as with
  `mb_sql_str()` / `mb_sql_int()`, which one a site needs is the interesting
  part. The audit records the HTML context because escaping only helps inside
  quotes here too: `<font class=$x>` is a **bare** attribute, and a bare
  attribute value ends at the first space, so `x onerror=alert(1)` breaks out
  carrying no character `htmlspecialchars()` would touch. `mb_attr()` returns
  the value escaped *and* quoted for exactly that reason. This codebase writes
  almost every attribute bare.

  Context is accumulated across the file rather than per statement, because the
  tag a value lands in is routinely opened somewhere else: `topic.php` emits an
  `<input ... value="` as inline HTML and the short-tag echo that follows
  carries no literal text of its own. Per statement that scores as element
  text — the mild answer, and the wrong one. It reclassified five entries.

  What the 29 turned out to be:

  - **Nine were `$snum`, the settlement number**, in six near-identical copies
    of the settlement viewer plus `settlement.php` and `S_SET.php`. It is
    range-checked and `mb_sql_int()`ed into every query, then echoed raw — and
    in `S_SET.php` into a bare `href`. The range check is no protection:
    `"<script>"` is not less than 1, so the "does not exist" branch echoes it.
    The six copies are a merge candidate on the barter boards' precedent.
  - **Eight were the four topic pages**, and all eight were one variable
    meaning two things — the same shape the SQL work named. `$topic` and
    `$message` are read from the request and then reused for the row being
    rendered, and the reply form at the bottom prefills from whichever won.
    The row values are `$r_*` now, seeded from the request so the empty-thread
    case still prefills what it always did.
  - **Seven were `equip.php`** telling you which weapon has not been created
    yet, in the words you chose.
  - The rest were `checksignup.php` reflecting a taken name back, `gc.php`'s
    guild name, `S_PREF.php`'s MSN field, `barter_handler.php`'s unit type, and
    one boolean.

  The forum post body is the one value still echoed as markup, because it is
  stored as markup on purpose: the forum has always allowed `<i>` and `<b>`.
  That is closed at the boundary instead. `strip_tags($message, "<i>,<b>")` is
  the wrong tool for an allow-list — an allowed tag keeps its attributes, so
  `<i onmouseover=alert(1)>` passed through whole. `mb_post_html()` escapes
  everything and restores exactly the two bare tags, which an attribute makes
  not match.

  Same two limits the SQL audit carries, and they matter more here. It is
  **first-order and per-file**, so the stored XSS — an empire name, a guild
  name, a message — is invisible to it. That is the larger half and the more
  dangerous one, so it has its own list rather than being left implied.

  `--stored` takes a row read back out of the database as a source too, and
  baselines separately in `tests/xss-stored.txt` so the first-order list keeps
  meaning what it says. It opened at **116 entries across 50 files**, which is
  the whole of it — every echo in the app that renders something a player
  typed.

  **All 24 attribute-context entries are closed; the 92 in element text are
  not.** The attributes came first because that is the half escaping alone
  cannot fix, and because they concentrate the genuinely dangerous shapes:

  - `S_SET.php` rendered `<img src=$settlepic>` bare, from a URL a settlement
    leader types in — the same shape as `gc.php`'s guild flag, which had
    already been closed. `S_SINFO.php` echoed the same URL, the settlement name
    and the leader's notice back into three single-quoted form fields, where
    one apostrophe closes the attribute.
  - Seven copies of `<option value=$row[0]>$row[1]` — the empire pickers on the
    attack, intel, message, kick and government pages.
  - Every listing's action link: the forum thread links, the barter Barter/End
    pair, the guild accept/reject pair, the settlement and guild member links.

  What is left is 92 element-text entries, and they are not simply 92 `mb_h()`
  calls. Some of those values are stored as markup on purpose and would
  double-escape: the forum post body, and the guild name and info, which
  `gc.php` runs through `htmlspecialchars()` on the way in. Closing them means
  deciding per column whether the stored value is text or markup and unwinding
  the 2003 escape-on-input where it is the latter. That is the next piece of
  work, and it is a data decision rather than a mechanical one.

  One pre-existing mismatch found and left alone: `gc.php` stores the
  HTML-escaped guild name in `guild.gname` and the raw one in `user.guild` on
  the two adjacent statements that create a guild, so a name containing `&` or
  `<` never matches itself again.
- **Passwords.** Unsalted MD5, denormalized into four tables alongside the
  email that identifies the row.
- ~~**Error handling.**~~ **Done.** `mysqli_report()` is back on as
  `MYSQLI_REPORT_ERROR` (warnings, not exceptions), the smoke crawl and the tick
  both fail on a query error, and `tests/broken-queries.txt` is empty. Going
  further, to `STRICT` and exceptions, needs the ~1300 call sites to check their
  return values first. Getting there meant fixing what it surfaced —

  - **Research had never worked.** The tick adds points to `r1pts`–`r18pts` in
    one `UPDATE`, but the schema stopped at `r14pts`. A MySQL `UPDATE` is
    atomic, so the statement was rejected whole and the fourteen columns that
    did exist were not written either — assigning sages did nothing, for
    anyone, ever.
  - **Troops sent out were lost permanently.** The returning-army slot and its
    timer are cleared by separate statements, and the restore that moves the
    army back into `military` is a third. Two of the three name
    `golem1`–`golem4`, which `returntbl` did not have, so they failed while the
    timer beside them kept counting down — and once it passed 1 the
    `WHERE time1 = 1` release could never match again.
  - **The guild flag was not a cut feature, just an unsaveable one.** Only the
    column was missing. It is also escaped now, which it never was.
  - The tick ran a phantom first pass for userid 0, whose empty interpolations
    MySQL rejected as syntax errors.

  The first three shared a cause: an `UPDATE` naming a column that `db.sql` did
  not have, against code that checks no return value. The engine was right and
  the schema was short — so the fix was almost entirely `db.sql`.
- **The bug list.** `tests/register-globals.txt` and `$GAMEDATA['quirks']` are
  both inventories, kept in a form that shrinks as they are fixed. Treat the
  first as suspects rather than defects — it is per-file and flow-insensitive,
  so a variable assigned in one file and read in another looks unassigned.
- **Authorization.** **The forum moderation pages are done; the rest is not.**
  Nothing checks that you own what you are acting on beyond the queries' own
  `WHERE` clauses.

  `gl-delposts.php` and `sl-delposts.php` were the clearest cases and are
  fixed: neither checked that the caller led anything, and `gl-delposts.php`
  deleted purely on the topicid in the URL, so any logged-in player could
  remove any guild's threads by guessing. Both now refuse a non-leader and
  scope every delete to the caller's own guild or settlement.

  They were also the only two pages where a guard could be *verified*, because
  closing the coverage gap put them on the crawl. Anywhere the suite still does
  not reach, adding a check is a guess about what the check should permit.

  ~~**The admin area has no gate at all.**~~ **Done.** It was under
  `app/css/admin/` — a stylesheet directory — and `adminlogin.php` compared
  against a hardcoded `mako` / `quickshot` and, on success, printed a link to
  `main.php`. That was the entire mechanism: no session was written and nothing
  in the area asked whether one existed, so every page served to anyone who
  typed the URL. It is `app/admin/` now, and:

  - `app/admin/include/auth.php` holds one credential, read from
    `MB_ADMIN_USER` / `MB_ADMIN_PASSWORD` and defaulting to the historical pair
    so nothing that worked stops working. A match records the session and
    regenerates its id; `mb_admin_require()` answers 403 to everything else.
  - Every page calls it — through `include/igtop.php` for the eight that have
    it, and directly in `gameconfig.php` and `newgame.php`, which do not.
    Those two were the ones with no check at all, which is not a coincidence.
  - The smoke crawl has an admin phase, and its first pass asserts that every
    entry point refuses both a stranger **and a logged-in player** — a gate
    keyed on the wrong session slot passes without that second arm.
    `$ADMIN_PAGES` is checked against `glob(app/admin/*.php)`, so a new page
    added and not gated fails the run.

  This does not make the area safe to expose. What is behind the gate still
  interpolates, still echoes raw, and still deletes the world from one link.

- ~~**Two blind spots in `register-globals-audit.php`,**~~ **both fixed.** Each
  hid a read the Phase 2 port then missed:

  - ~~It skipped every name beginning with `_`~~ to avoid reporting the
    superglobals, and caught `$_mace` with it. **Fixed** — it names the nine
    superglobals now. That read being missing left the Mace button inert, and
    with it the six priest weapons that check the Mace as a prerequisite.
  - ~~It credits a shared include for a name even in files that do not include
    it.~~ **Fixed** — it resolves includes per file now. `adminlogin.php` read
    `$pw`, which `include/session.php` assigns, but that file included nothing
    except `request.php`, so the read was suppressed and the port missed it.

    Two things had to be right for the per-file form to be usable at all.

    **A file that is only ever included sees its includer's scope.** Half the
    codebase is a panel template under `include/` that renders `$race` and `$gp`
    while including nothing itself; scoring those against their own closure
    reported 345 new entries, all noise. A file's visible set is the union of
    the include closures of every file that reaches it, itself among them —
    which is why `adminlogin.php` is still reported and `S_ARMOR.php` is not.

    **Includes here are CWD-relative, and the CWD is the requested page's
    directory.** That is what lets `app/admin/include/igtop.php` say
    `include("connect.php")` and reach the admin one, while
    `app/admin/dusers.php` says `include("include/igtop.php")` and also reaches
    the admin one rather than the game's. `mb_resolve_include()` tries the entry
    page's directory, then the including file's, then the app root.

    It also follows `include($mb_panel)` by looking up the literal `.php`
    strings assigned to that name in the same file. Without it, `barter.php`'s
    two board templates looked like entry points reading `$db` and `$ename` out
    of nowhere.

    The ratchet grew by three, the only time it has: `attack.php`,
    `attackm.php` and `attackr.php` read `$gid` to file guild news, and only
    `commong.php` assigns it. None of the three includes it, so the attacker's
    guild has never heard about an attack — and `$tgid`, the defender's, was
    already on the list because nothing anywhere assigns it. Both sides of the
    feature are dead, both are guarded by `if($x[0] != "")` so nothing is
    written wrong, and repairing them means deciding what the feature should do
    rather than restoring a read.

    One entry came off instead of on. `input.posts.php` stamped a new
    settlement forum topic with `$datestamp`, which is `commong.php`'s — the
    guild half — while this page includes `common.php`. Topics were stored with
    an empty date and displayed one. The reply below it, and both branches of
    `sl-input.posts.php` writing the same two tables, have always used `$clock`.

    The login itself was repaired earlier, and that repair is worth reading,
    because restoring the missing read was **not** enough.
    `adminlogin.php` now includes `auth.php`, which includes `session.php`,
    which sets `$pw` from the session — so reading the form into `$pw` would
    have been silently overwritten by the logged-out empty string. Two
    different secrets shared one name, which is what made the read look
    satisfied to the audit in the first place. The posted password is
    `$admin_pw` now, and the collision is gone rather than sequenced around.
- **Invert the manual dependency** so the engine reads `$GAMEDATA` rather than
  its inline literals.

### The barter system, switched back on

`barter.php` opened with an echo and a `die()` above every other statement in
the file — a 2003 notice saying the barter would return in v6 — and
`guildbarter.php` carried its own copy announcing that the guild barter would
not. Nothing below either line had run since. That is why the two files held 26
of the last 36 injection entries: real code, unreachable, and not worth
escaping blind.

They are one file now. `app/include/barter_handler.php` holds the board and the
two pages are three lines each, because they were 430-line copies of each other
that had drifted:

- `guildbarter.php` checked `$archer` where it meant `$archers`, so the guild
  board never verified you owned the archers you were listing.
- Neither buy handler scoped its lookup to its own board. `S_BARTER.php` lists
  `page != 'guild'` and `guild_barter.php` lists `page = 'guild'`, but both
  handlers matched on `barterid` alone — so a guild listing's id passed to
  `barter.php` sold it to anyone, skipping the same-guild check that is the
  entire point of the guild board.
- `include/guild_barter.php`'s **Barter** and **End** links both pointed at
  `barter.php`, which is how you would have found that out.

And what the handler itself was doing:

- The type was validated on the add branch and not at all on the buy branch, so
  a listing whose type matched none of the ten `if`s transferred nothing, was
  paid for anyway, and was still deleted and announced as a sale.
- The **Scientist** it offered has not existed for a long time. The game calls
  that unit a **Sage** everywhere current — `functions.php` reads
  `military.sages` into `$sages` — while `$scientists` is set by nothing, so
  the sale was gated on 0 and a bought one landed in a column nothing reads.
  `military` still has both.
- The archery requirement on the add branch tested `$b_type`, which is the type
  of the listing being *bought* and is not set there.
- The "fill every field in" check read `$gp`, the seller's own gold, where
  `$cost` belongs, so it never looked at the price field.
- The seller's news line interpolated `$b_method`, which never existed, and
  quoted a different number from the buyer's copy.

The stock movement was three ten-way switches per page — sixty near-identical
`UPDATE`s across the pair, each its own chance to interpolate something
unescaped. It is one table and one function, which is what made escaping this
reviewable rather than sixty separate judgement calls.

Both boards are on the crawl. Listing, buying and cancelling are driven
explicitly rather than followed, because buying and cancelling are GET links
that consume the row they point at.

### Prerequisite: coverage — done

The smoke crawl reached 39 of 69 top-level scripts when Phase 3 opened. It
reaches 58, and every one of the eleven it does not reach has a reason recorded
in `$UNREACHABLE` that is checked on each run. It also covers all ten admin
pages and both barter boards, neither of which is in that count — the
denominator is `app/*.php`. Rewriting a page the suite does not visit is not a
port, it is a guess, so this came first — and it kept paying out, because each
page that came into the net arrived with defects attached.

Three steps got there:

1. Seeding the guild, settlement-leader and forum fixtures, and crawling as a
   rank-and-file member as well as a leader.
2. Seeding a message, so `vmessages.php` stopped rendering an empty table. Its
   loop — two queries per row — had never executed once.
3. Driving the forms. The crawler follows links, so every POST handler sat
   outside the net. `postForm()` now submits both arms of the four forum thread
   handlers, and the moderation handlers are called directly.

The remaining eleven are three includes, three POST handlers with no safe
fixture (`checksignup.php` creates a user from `rand()`), `disband.php` and
`logout.php` (both destroy what the run depends on), one dead file
(`scoreboard.php`), and the two-step account activation flow. `guildbarter.php`
came off the list when the barter system was switched back on.

Two things learned doing it.

**Destructive requests have to run before the crawl, not after.**
`govt.php:20` clears `sl` for every member of a settlement on each visit and
re-elects whoever holds the most votes, and nobody in the fixture votes — so
the crawler merely opening the page deposes the tester, and anything afterwards
that needs settlement leadership is refused. The same reasoning puts the barter
buy and cancel calls before the crawl: they need gold and iron, and the crawl
spends both.

**A crawl that follows links needs to know where it is allowed to go.**
`extractLinks()` normalises every href to a path from the docroot, so the admin
navbar's relative `href="main.php"` walked the admin pass straight out into the
game as a client with no player session — and quietly pulled `activate_code.php`
into coverage with a broken query attached. `crawl()` takes a path prefix now.
The admin phase is the one exception to the rule above: it runs dead last,
because driving `gameconfig.php`'s bulk actions is the only way to learn that
thirty of its statements named tables which have never existed in this schema,
and it leaves the fixture spent.
