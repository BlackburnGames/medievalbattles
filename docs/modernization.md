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

- **SQL injection.** **The audit reached zero, 161 entries down to none — and
  then reopened at 11, because it had only ever been reading half the sinks.**
  It matched the `mysqli_query` token, and sixty calls in seven files use the
  object form, `$db->query()`. Those cluster in exactly the pages a visitor
  reaches before logging in: `checksignup.php`, `checklogin.php` and
  `activate_account.php`. So the zero was true of every authenticated page and
  silent about the three that need no account at all — `checksignup.php`
  interpolates the address into a `SELECT` twenty lines before it validates it.
  `mb_is_query_sink()` matches both forms now, and **the 11 are closed**:

  - **Signup** escapes every value once, near the top. The interesting part is
    the empire name, for the same reason the address was: it is read back on
    every page and interpolated bare at forty-odd `WHERE ename='$ename'` sites.
    Those were safe by accident — a quote broke signup's `INSERT`, so no such
    name could be stored — and escaping the `INSERT` removes the accident.
    `mb_valid_ename()` in `credentials.php` states the rule instead, and
    signup is the only place an empire is ever named. Race and class are held
    to `$GAMEDATA`'s keys.
  - **Activation had never parsed.** `check` is a reserved word and both of
    its statements named it bare, so the `SELECT` failed, `$validate` was null,
    and the loose `!=` answered "activated" to a request carrying no code at
    all. It casts the id, escapes the code, backticks the column and compares
    with `hash_equals()` now. Signup still stores `check='2'`, so accounts are
    born activated and the page changes nothing a player sees; it is driven by
    hand, not by the crawl, which cannot sign up.
  - **Login** escapes the address it records and the empire name it reads back.

  **The second order has its own list now, and it is at zero too.**
  `--stored` takes a row read back out of the database as a source, the way
  the output audit's does; it opened at 36. Most were ids and counts, cast.
  One was the reason to build it: **a guild name was a stored injection any
  player could fire.** `gc.php` took any fifteen characters and copied the
  name into every member's `user.guild`, and from there it was interpolated
  bare — `$empireguild` at eighteen sites, three of them writes. A guild
  called `a' OR 'a'='a`, once deleted, ran
  `UPDATE user SET guild='None' WHERE guild='a' OR 'a'='a'` and took every
  guild's members away; that was driven by hand and it did. The fix is the
  empire-name fix again — `mb_valid_gname()` at the one place a guild is
  named — plus escaping all eighteen, because `$empireguild` comes from
  `functions.php` and the per-file audit could not see any of them. The
  AIM field was the other free-text value going back into a `SELECT`.

  All ~1300 queries
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

  The forum post body was the one value still echoed as markup, because it was
  stored as markup. That is fixed by not storing markup at all — see
  "Rich text, stored as text" below.

  Same two limits the SQL audit carries, and they matter more here. It is
  **first-order and per-file**, so the stored XSS — an empire name, a guild
  name, a message — is invisible to it. That is the larger half and the more
  dangerous one, so it has its own list rather than being left implied.

  `--stored` takes a row read back out of the database as a source too, and
  baselines separately in `tests/xss-stored.txt` so the first-order list keeps
  meaning what it says. It opened at **116 entries across 50 files**, which is
  the whole of it — every echo in the app that renders something a player
  typed.

  **Both lists are now at zero: 116 stored entries down to none.** The
  attributes came first because that is the half escaping alone cannot fix, and
  because they concentrate the genuinely dangerous shapes:

  - `S_SET.php` rendered `<img src=$settlepic>` bare, from a URL a settlement
    leader types in — the same shape as `gc.php`'s guild flag, which had
    already been closed. `S_SINFO.php` echoed the same URL, the settlement name
    and the leader's notice back into three single-quoted form fields, where
    one apostrophe closes the attribute.
  - Seven copies of `<option value=$row[0]>$row[1]` — the empire pickers on the
    attack, intel, message, kick and government pages.
  - Every listing's action link: the forum thread links, the barter Barter/End
    pair, the guild accept/reject pair, the settlement and guild member links.

  The remaining 88 were element text, and closing them was mechanical once the
  values that used to be markup were text — `mb_h()` at every site, in one
  sweep. Two entries are worth reading anyway, because neither was closed by
  escaping and both were the shape the SQL work already named:

  - **`S_VMESS.php`'s `$link` is markup in one branch and a bare name in the
    other.** It starts as the sender's empire name and is replaced by an anchor
    when that sender still has a settlement. Escaping it at the echo would have
    escaped the anchor; it is escaped where it is built, at both ends, and the
    anchor's `href` was bare and is now `mb_attr()`.
  - **`gameconfig.php`'s `$online` was a ternary on a database column** whose
    two branches are both constants. The audit cannot see that a tainted
    condition produces an untainted value, and neither can a reader at a glance.
    It is an `if` with a literal on each side now, which is how the same marker
    is written everywhere else in the codebase.

  One entry came off the list without being touched at all. `snews.php` renders
  through `mb_news_html()`, which escapes both of its arguments — the text via
  `mb_rich()`, the category against a whitelist — so it belongs on the audit's
  sanitizer list, and the entry was the audit not knowing that yet.

  A good many of the 88 were numbers and dates rather than anything a player
  types — 24 of them were one weapon countdown per weapon in `S_WEP.php` — so
  the list was always longer than the risk in it. They are escaped anyway: a
  reader cannot tell which columns are safe by looking, which is the same
  argument that put `mb_sql_str()` at every query site rather than the
  interesting ones.

### Rich text, stored as text

The escaping above made the stored HTML *safe*. It was still stored HTML, which
is the actual defect: the database held presentation, and every reader had to
know which columns were markup and which were not. Three things were tangled
together under that heading, and only one of them was rich text.

**Most of it was not rich text at all.** `htmlspecialchars()` on the way in was
2003's stand-in for output escaping. With `mb_h()` at the other end it is pure
downside, and it was breaking two things outright:

- **Passwords.** `preferences.php` stored `md5(htmlspecialchars($newpw))` while
  `checksignup.php` and `checklogin.php` hash the raw string.
  `credentials.php` constrains the *hash*, not the password, so nothing stopped
  it: changing your password to anything containing `& < > " '` stored the
  digest of a value login could never reproduce, and locked the account.
- **Guild identity.** `gc.php` wrote the escaped name to `guild.gname` and the
  raw one to `user.guild` on adjacent statements, so a guild called `Ale & Axe`
  never matched itself and its members belonged to a guild that did not exist.

`S_SINFOS.php` was the worst of it — seven `strip_tags()`, six
`htmlspecialchars()` and **ninety `str_replace()`** calls over six fields. The
`str_replace()` list could not have worked: it looked for `<script>` and
`onmouseover` in a string `htmlspecialchars()` had already turned into
`&lt;script&gt;`. What it *did* do was corrupt legitimate input, stripping its
needles out of the middle of the value — a settlement named `Wheightsville` was
stored as `Wsville`, because `height` was on the list. All 103 lines are gone,
replaced for the two URL fields by `mb_safe_url()`, which checks the scheme.
That is the thing the list was reaching for and never achieved:
`href="javascript:alert(1)"` runs however well the value is escaped.

**The forum and message bodies are the real rich text**, and they are stored as
exactly what the player typed. `mb_rich()` does everything at render: escape,
then break, then translate `[b]` and `[i]`. Brackets rather than angle brackets,
and the ordering is the reason — `mb_h()` runs first, so a player who types
`<b>` sees it as text, while `[` and `]` are not HTML metacharacters and come
through escaping untouched. The markup cannot be forged, because forging it
would mean emitting a character the escaper has already dealt with.

**The news lines were not rich text either — they were events.** All 51 sites
built a finished string like

```
"<font class=yellow>$ename ($setid) successfully attacked
 $evu[ename] ($evu[setid]) and gained $landgain land</font>"
```

and stored it, with two player-chosen empire names interpolated and nothing
escaping them. That was the last stored-XSS vector in the game and the one no
amount of output escaping could have closed, because the column genuinely did
contain HTML.

The colour was never decoration — `snews.php` prints a legend for it, so it is
a category. It has its own `class` column now, the `news` column holds plain
text, and `mb_news_html()` puts them back together at render time. The three
writers live in `app/include/news.php`; `mb_news_guild()` fixed a call site in
`guildconfig.php` that was inserting into a column named `guildid`, which the
schema has never had, so "X has been removed from the guild" never appeared.

**None of this needed a migration.** `db/seed.sql` contains no markup in any
column and there is no other corpus, which is the fact that made doing it now
rather than later obviously right.

~~**The suite reaches none of the 51 news writers.**~~ **It does now**, in the
two halves the gap actually had.

The crawl files real rows: thirty-two of the fifty-one sites are in the three
attack pages, and none had ever run, because launching an attack means naming a
target and a number of units and the crawler only follows links. Two attacks are
driven by hand — a land raid that wins and a resource raid that loses, which is
both outcome branches — and the rendered lines are then asserted on `snews.php`
and `main.php?pageid=news`, sentence and category colour separately. Asserting
matters more here than anywhere else in the crawl: every news `INSERT` in the
game is fired unchecked, so a row that is never written produces no diagnostic
at all.

It found one immediately. `attackr.php` and `attackm.php` filed the defender's
settlement news against `$tsetid`, a name nothing in either file assigns — both
were on `tests/register-globals.txt` as dead reads — so all four rows went to
setid 0 and no settlement has ever been told that one of its members was raided.
`attack.php` writes the same sentence against `$evu['setid']`, which is what
says what the value should have been. The ratchet is down to 159.

What the crawl still cannot do is choose an empire name, and an empire name that
is an XSS payload is the case this conversion exists for — the fixture's names
are what the tick golden master is computed from. So the round trip is driven
directly in `tests/news-render.php`: the three writers, then the renderer, on
the rows read back out of the database. It asserts that text survives unchanged,
that the category is in its own column, that the payload renders inert, that
`[i]Medieval style[/i]` italicises while a typed `<i>` does not, and that an
unknown category falls back to `yellow` rather than landing raw in the unquoted
attribute.
- ~~**Passwords.**~~ **Done.** Unsalted MD5, denormalized into six tables
  alongside the email that identifies the row. It is bcrypt now —
  `app/include/password.php` — and the interesting part is what did *not* have
  to change with it.

  **The hash has two jobs and only one of them was broken.** It protects the
  account, and it is also the bearer token: `$_SESSION['pw']` holds the stored
  hash and ~1300 queries filter `WHERE email='$email' AND pw='$pw'`. Rewriting
  that second job means touching every file in the game. But what those queries
  need is equality against the stored string, which a bcrypt hash satisfies
  exactly as an MD5 digest did — so the token stayed, and the digest under it
  changed. Nothing above `password.php` knows which format it is holding.

  **`PASSWORD_BCRYPT`, not `PASSWORD_DEFAULT`.** The hash is interpolated bare
  into those 1300 queries and validated on the way out of the session by
  `credentials.php`, so its alphabet is load-bearing: bcrypt emits only
  `[./A-Za-z0-9$]`, none of which means anything inside a SQL string literal.
  `PASSWORD_DEFAULT` is documented to change, and the argon2 formats it may
  change to contain `=`, `,` and `+`. Pinning it keeps `credentials.php`
  asserting a fact rather than a hope.

  **Legacy rows still log in, once.** There is exactly one corpus of this
  game's data and it is not this repo's fixture, so a login that stopped
  working for every existing account would be a behaviour change nothing here
  could catch. An MD5 row verifies, is rehashed, and is written back through
  the same six `UPDATE`s `preferences.php` has always run — which is why those
  moved into `password.php`. Two copies of a six-table list is how one of them
  ends up short, and a short one is invisible: the session token keeps matching
  `user` while the table it missed goes quietly empty. The fixture keeps two of
  its three accounts on MD5 so that path runs on every crawl.

  Three things came off with it:

  - **`==` on two digests.** `checklogin.php` compared the stored hash to the
    computed one with `==`, which compares numeric-looking strings as numbers —
    and a hex digest is numeric-looking whenever it contains no letters, so
    every `0e…`-shaped digest equalled every other one. It is `hash_equals()`
    on the legacy arm and `password_verify()` on the other.
  - **The activation code was the password digest.** `checksignup.php` stored
    `$pw` as `emailvalidate.code` and mailed it in the activation link, so the
    URL carried the exact string that `WHERE … AND pw='$pw'` accepts as the
    credential. It generates its own random code now — which it had to, since
    the column is `varchar(50)` and a bcrypt hash is 60.
  - **A dead check in `preferences.php`.** The account-deletion branch hashed
    the typed password and *then* tested it against `""`, and `md5("")` is not
    the empty string, so "you must specify a password" could never appear. Both
    branches refuse the deletion; only one of them says why.

  The address check moved to the front of `checklogin.php` at the same time.
  It is the one credential site reached by an unauthenticated POST, so the
  address goes into `WHERE email='$email'` having never been through
  `session.php`'s rule — and since signup and preferences both refuse to store
  an address this would reject, checking it here can only refuse a login that
  could not have matched a row.

  ~~What is still true: the hash is a bearer token with no expiry, no rotation
  and no CSRF protection around it.~~ **Done**, as its own change — see
  "Sessions" below. The hash is still in six places; that is the schema's,
  and it does not reach the browser.
- ~~**Sessions.**~~ **Done.** The hash never left the server — it lives in the
  session file — so the token anyone could steal was the session id, and the
  2003 code protected it with nothing. Every session now starts in
  `app/include/session.php`, which `checklogin.php` used to bypass with a bare
  `session_start()`:

  - **Fixation.** Strict mode refuses an id the server did not issue, and a
    login takes a new one. On the old code a login under an id an attacker
    chose kept it, and the attacker's copy was logged in.
  - **The cookie** is `HttpOnly` and `SameSite=Strict`, and `Secure` over
    HTTPS. PHP below 7.3 has no `samesite` parameter, so the attribute rides
    on the path there; the 5.6 image was built once to see it arrive.
  - **Expiry.** Two hours idle or twenty-four since login. PHP's collector
    was the only expiry there was, at its default 24 minutes and with a
    one-in-a-hundred chance of running on any given request.
  - **Logout ends the session** — data, file and cookie. It used to unset two
    keys and leave the session standing.
  - **Revocation.** Once per request the session's address and hash are
    checked against the account. A session whose password was changed from
    elsewhere, or whose account is gone, is logged out rather than left
    rendering empty pages — which is what makes a password change end the
    account's other sessions.
  - **CSRF.** Nearly every action here is a GET link, so form tokens would
    have left most of the surface open. Instead a request whose
    `Sec-Fetch-Site` says another site made it gets no session at all: it
    cannot read, act, log in or log out. A browser too old to send the header
    is covered by the `SameSite=Strict` cookie, which it will not attach to a
    cross-site request either. What neither covers is a browser that does
    neither, which no current one is.

  `tests/session.php` drives every rule over HTTP with curl, because the
  crawl's client never sends the header, never reads cookie attributes and
  never reuses a session it is done with. Against the old code it fails 17
  of its 23 checks.
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
- **Authorization.** **The forums are done — both the moderation pages and the
  four post handlers. The rest is not.** Nothing outside the forums checks that
  you own what you are acting on beyond the queries' own `WHERE` clauses.

  `gl-delposts.php` and `sl-delposts.php` were the clearest cases and went
  first: neither checked that the caller led anything, and `gl-delposts.php`
  deleted purely on the topicid in the URL, so any logged-in player could
  remove any guild's threads by guessing. Both now refuse a non-leader and
  scope every delete to the caller's own guild or settlement.

  They were also the only two pages where a guard could be *verified*, because
  closing the coverage gap put them on the crawl. Anywhere the suite still does
  not reach, adding a check is a guess about what the check should permit.

  **The four post handlers were the same two holes, on the pages that write.**
  Both forums are one shared table apiece — `setforums` for every settlement,
  `guildthreads` for every guild — and which board a row is on is a column on
  it. So each handler had two questions to answer and answered neither:

  - **May the caller act on this surface?** `gl-inputposts.php` ran the
    guild-leader query and then never looked at the result, so the leader-only
    board took posts from any member. `sl-input.posts.php` did not ask at all,
    though `sl-forum.php` and `sl-delposts.php` both refuse a non-leader.
    `inputpostsg.php` never checked membership, so a player in no guild filed
    threads under the guildname `None`, on a board every other guildless player
    reads.
  - **Is the row they named on the caller's own board?** A reply takes its
    topicid straight off the request. `guildmsgs` has no guildname of its own,
    so a reply belongs to a guild only through the thread it hangs off — and
    both guild handlers matched on the id alone. Any player in any guild could
    post into any other guild's private thread by guessing, and `topicg.php`
    renders `guildmsgs WHERE topicid` unscoped, so it appeared there. Exactly
    the delete-on-the-id-alone shape `gl-delposts.php` carried.

  The settlement half was half-scoped: its `UPDATE`s said `AND setid` and the
  reply `INSERT` stamps the caller's own, so a foreign topicid wrote a row whose
  setid does not match its thread — and `topic.php` selects on both, so nothing
  can ever display it. It went nowhere rather than somewhere it should not,
  which is luck and not a check. It is refused now.

  **`sl.php` failed open, and so did `sl-forum.php`.** Both tested
  `$sl == 'no'`, but `user.sl` defaults to `''`, signup never sets it, and the
  only writer of `'no'` is `govt.php`'s election. So until somebody in a
  settlement opened that page, any member could rename the settlement and
  replace its picture and notice. The suite could not see it because the
  fixture seeded every non-leader `'no'`; PoorSerf carries the default now,
  and both pages call `mb_forum_require_sl()`, which asks `!= 'yes'`.

  All five rules live in `app/include/forum_guard.php`. Two of them were already
  written inline in the two moderation pages, which is one copy too many for a
  rule six pages share.

  **Guild membership was the same omission one page over.**
  `guildconfig.php`'s accept branch wrote `user.guild` from the `auserid` in the
  URL and never looked for a request row, so a leader could conscript any
  guildless empire in the game by typing an id. `include/guild_request.php`
  draws the Accept link only for rows matching `gl_userid='$userid'`, so the
  link was always honest and the handler behind it was not — and the reject
  branch beside it has always scoped its `DELETE` with `AND gl_userid`, which is
  what says this was an omission rather than a design. The join is a round trip
  on the crawl now: the refusal is asserted first, while PoorSerf genuinely has
  not applied, and the request that follows is filed through `gc.php` rather
  than seeded, so the accept is proved against a row the game itself wrote.

  **The reject branch had the scope and filed the news ahead of it.** Its
  `DELETE` said `AND gl_userid='$userid'`, which is what made it look fixed,
  but "You were rejected from" went into the `auserid`'s empire news one line
  earlier, so a leader could write that line into any empire's news by typing
  an id. The crawl could not see it because `guildconfig.php?reject` was in
  `$SKIP`, and because the row lands in the victim's news rather than the
  leader's response. It asks the accept branch's question first now, and both
  arms are driven before the join: the refusal is asserted on PoorSerf's own
  news page while no request exists, then a request filed through `gc.php` is
  genuinely rejected and its news line asserted.

  **Deleting an account left its vote behind.** `preferences.php` carried a
  copy of `attack.php`'s destroyed-empire cleanup, where `$empvalue` and
  `$empireattacked` name the empire being destroyed. Here the empire being
  destroyed is the caller, so neither name meant anything: the `votefor` was
  read off whatever userid the request supplied, and the `UPDATE` filtered on
  an `$empireattacked` nothing assigns, so it matched `ename=''`. The form sends
  no `empvalue`, so in practice the branch never ran, and whoever the deleted
  account voted for kept the vote — and possibly the settlement leadership —
  forever. The suite did not see it because nothing drove the deletion: it
  destroys the account. It decrements the caller's own `$votefor` now and reads
  nothing from the request, and the crawl spends `idle2@` on it as its last
  player step, after a vote cast through `govt.php`, with a hostile `empvalue`
  on the request. The same branch redirected with `header()` after `igtop.php`
  had rendered the page, which on a page with no output buffer could only warn
  and then fall through into the forms of an account that no longer exists; it
  stops with a link instead.

  **The combat pages never asked whether their target existed.** All five —
  the three army attacks, `scattack.php` and `intel.php` — fetch the empire
  named by `empvalue` and fight whatever comes back, and an id that names
  nobody comes back empty. `attack.php` then scored the target as land 0 and
  took the "destroyed" branch, whose cleanup is keyed on `$evu['userid']`: a
  `DELETE FROM guild WHERE owner=''` and an `UPDATE ... WHERE votefor=''`, run
  for an empire that was never there. The others won, raided, suicided or
  spied on nobody, and every one of them spent the attacker's general or units
  doing it. The crawl could not see this for the reason the forums could not:
  its only attacks named a real target, so the arm that should be refused was
  never driven. The three army pages share `include/attack/defANDoff.php`, so
  the check is there, ahead of `calculations.php`, which is what actually moves
  the troops; `intel.php` and `scattack.php` look the target up before counting
  units now, which is also what lets the crawl reach them with an account that
  holds no thieves. Two smaller holes went with it. `attack.php`'s "you sent
  nothing" check ended in `$uirongolem = ""` — an assignment, so it never fired,
  and an empty attack died dividing by zero — and `scattack.php` had no
  self-target check at all, which only the guild check had been covering, so a
  guildless empire could suicide on itself. All three are driven from the arm
  they refuse, before the two attacks, so a guard that leaks a general also
  fails the news assertions behind it.

  **The two ways an empire ends were two broken copies of one operation.**
  `preferences.php`'s account deletion was written from `attack.php`'s
  destroyed-empire branch — it still carried the attack's variable names — and
  by now each had lost something the other kept. The attack withdrew the
  destroyed empire's vote with `WHERE ename='$V_check'`, an array in a string,
  so it matched an empire called "Array"; and its `DELETE FROM militaryWHERE`
  never parsed, so the army row outlived the empire. The deletion looked up a
  led guild by `owner='$ename'` — `owner` holds a userid — deleted from
  `guildrequest`, which is not the table, never told the settlement it had
  lost a member, and finished with `DROP TABLE main` and `DROP TABLE msgs`, the
  per-guild tables of an older schema named from two variables nothing sets.
  Both call `mb_empire_remove()` in `app/include/empire.php` now, which does
  everything either copy got right, once, keyed on the userid. Neither path
  can be crawled whole, so `tests/empire-remove.php` drives it on scratch rows;
  the attack branch was also driven by hand, and on the old code it left the
  military row and the vote behind.

  **Looking at the government page could depose the settlement leader.**
  `govt.php` ran the election at the top of the page, on every view: it
  cleared every member's `sl`, then crowned the top vote-holder only if
  someone had a vote. In a settlement where nobody had voted — which is most
  of them, and the fixture — the first member to open the page deposed the
  leader, and a tie went to whichever row MySQL returned first. The vote
  counts themselves were read, adjusted and written back. The election is
  `mb_settlement_elect()` in `app/include/election.php` now and runs only when
  a count changes: in `govt.php`'s vote branches and in `mb_empire_remove()`.
  A tie keeps the sitting leader, and no votes at all means no leader. The
  counts move relative to what is stored. `accountDeletion()` asserts the
  tester still leads after the crawl has been through `govt.php`, which failed
  on the old code, and `tests/empire-remove.php` asserts the recount after a
  removal.

  **And a raid could field golems it did not own.** The form in
  `include/attack/table.php` offers both golem fields on all three attack
  pages, but only `attack.php` read and checked them. That alone would have
  meant golems were simply ignored on the other two — except that
  `include/attack/defANDoff.php` and `calculations.php` each read all six unit
  counts off the request themselves. So a resource raid could name 100,000
  golems, own none and research nothing, have every one counted into its
  attack power, and win; the stock they were taken from went to −100,000.
  The same copies undid the pages' comma-stripping, so what was checked and
  what was sent were not even the same number.

  The copies were the Phase 2 port's, and they were added to satisfy
  `register-globals-audit.php`, which could not see a page handing a value to
  its own include and reported every such read as a request parameter. Both
  are gone, the audit credits the includer now (see
  [testing.md](testing.md)), and `attackm.php` and `attackr.php` check golems
  as `attack.php` does. The counts are whole numbers from the moment they are
  read, and "you sent nothing" means nothing above zero — an army of `"0"`
  fields passed the old `== ""` test and divided by zero. The golems' own
  survivor formula is still wrong in two places, and is a combat question
  rather than this one: see `$GAMEDATA['quirks']`.

  **Every guard is driven from the arm it refuses.** That is what this needed
  and what the suite could not do before: the primary tester leads Testguild and
  settlement 1, so every request they make is allowed, and a gate that answers
  yes to everyone passes. `idle@` is inside both and leads neither — the case
  the role guards exist to stop — and `poor@`, in settlement 2 and no guild, is
  the outsider the scope guards exist to stop. `gl-inputposts.php`'s discarded
  check is the reason to insist on it: it had been passing this suite for as
  long as the suite had reached the page.

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
    `attackm.php` and `attackr.php` read `$gid` to file guild news, and the
    audit found it assigned only in `commong.php`, which none of them includes
    — and `$tgid`, the defender's, nowhere at all. This was recorded as both
    sides of the feature being dead. **It was wrong.**
    `include/attack/defANDoff.php`, which all three include, fetches both, and
    a raid driven by hand files the attacker's guild its news line. The audit
    could not see a value an include hands back to its page; it can now, and
    the six entries came off with it.

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
  its inline literals. **Started: the economy block is inverted.**

  `include/gamedata.php` was a one-way copy. Every value carries a `file:line`
  citation back to the engine and keeping the two in step was a review rule —
  and a rule is not a mechanism, so the manual could go stale between the change
  and the review that was supposed to catch it.

  The ten per-tick economy rates are the first block to go the other way:
  `update.php` reads them and carries no copies. The tick golden master is why
  this block came first — it computes every one of them, so a transcription
  error cannot pass, and the inversion is checkable rather than merely claimed.
  Setting `iron_per_mine` to 1.5 moves the tester's iron by 22 and fails the
  run, which is the proof that the engine is reading the file rather than
  including it and ignoring it.

  An inverted value's citation turns around with it: gamedata is the definition
  now, and the comment names the reader. Uninverted entries keep their `file:line`
  and the block says which is which.

  The tick's housekeeping constants went the same way — the message and news
  lifetime, the experience floor — and with them the settlement count, which is
  read by the strength loop and by the three range checks that carried the
  literal. `$GAMEDATA` arrives with `connect.php`, alongside `db.php`,
  `html.php`, `news.php` and `password.php`, for the reason those are there: it
  is the one include every reader already has.

  **Two of the timers cannot be inverted and the block says so.** The activation
  and inactivity clocks are schema column defaults; the tick only counts them
  down, so there is no literal in the engine to take away.

  Inverting the settlement count surfaced a disagreement, now recorded as a
  quirk. `db.sql` ships **ten** settlements, and `settlement.php` and the three
  attack drop-downs ask `max(setid)` — so they offer ten. The intel, messaging
  and settlement-attack range checks said 30, and the tick recalculates strength
  for ids 0 to 30. Settlements 11–30 are selectable on three screens and empty
  on all of them. Nothing here changed that; the number moved into one place and
  the contradiction became visible instead of being spread across four files.

  **It is resolved now, in the schema's favour**, because the schema is the
  only thing that can hold a settlement. The count is not a game constant and
  has left `$GAMEDATA`: `mb_settlement_count()` in
  `app/include/settlements.php` asks `max(setid)`, and all nine readers — the
  tick's strength loop, the three range checks, the three drop-downs, signup,
  `settlement.php` and the manual — ask it. The quirk is gone with the
  disagreement.

  The three range checks are driven from the crawl, both arms. They are forms,
  so the crawler never reached them, and the bound failing silent — refusing
  every settlement on a page that still renders — is the case worth a test.
  The refusal arm asks for settlement 11, the first past the schema's ten,
  which the old bound of 30 accepted on all three.

  **The score weights are next, and only eight of the fourteen.** The
  experience recalculator reads Land, Mountain, Recruit, Sage, Warrior, Priest,
  Wizard and Archer from `$GAMEDATA['exp_values']`. Those are the eight the
  fixture holds, so the tick multiplies each by a non-zero count, and each was
  checked on its own: raising any one by a single point fails the run — Land
  moves all three empires and both settlements, Sage moves the tester by 5. The
  other six — Thief, Explorer, Suicide Civilian, Catapult and both golems —
  are still literals in `update.php` and still cited. No fixture empire owns
  one, so the tick multiplies their weights by zero: setting each of them to 99
  in the engine passes the golden master untouched. Inverting them would be the
  claim this work exists to replace with a check, so they wait for a fixture
  that holds them, which means re-accepting the golden on purpose.

  Inverting the Sage weight surfaced a disagreement, recorded here rather than
  as a quirk. The manual says a sage is worth 10 points, and the tester holds
  eight, but the score never counts the sages you hold: it reads
  `military.scientists`, the unit's pre-rename column, which nothing writes —
  the same orphan the barter's Scientist landed in, below — plus the sages
  *assigned* to the first fourteen projects. Of the tester's 5,880 points, 50
  are sages, from the five on Fireball; the eight in reserve score nothing, and
  the two on Advanced Farming score nothing either, which is the existing quirk.
  Nothing here changed that.

  **Of the race and class modifiers, two are definitions: the Demon's gold and
  iron deltas.** Lowering either from 0.5 to 0.45 fails the run — the idle
  Demon's gold by 750, its iron by 2. Everything else in the tick's two
  modifier switches is a literal still, because the fixture cannot see it, and
  each of these was checked by perturbing the engine and watching the golden
  master pass:

  - **No class modifier is computed at all.** The tester's class is `Warrior`,
    which is not one of the seven — `signup.php` cannot produce it, so no
    `case` matches — the Demon is a Cleric, which has no economy case, and the
    Giant's Ranger lumber bonus multiplies a mill count of zero.
  - **The Demon's civilian delta is masked.** The Demon has 1,300 civilians in
    room for 1,000, so the overcrowding decay replaces `$civmod` outright and
    the delta never reaches the arithmetic; setting it to −0.50 passes.
  - **The Giant's food delta rounds away.** One farm at 0.5 or at 0.6 both round
    to one food, and so would anything below 1.0.
  - **The Human's explore rate needs explorers in the field**, and nobody has
    any, so the land gain is zero whatever the rate.

  All four want a fixture change, which moves the golden, which is a re-accept
  that has to be its own reviewed step rather than a side effect of this one.

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

**And a listing could be sold more than once.** Every branch read the listing
and both balances at page load, checked them, and then wrote absolute figures
computed from what it had read — `gp=$newgp`, `archers=$archers+$amount` — with
the `DELETE` that ended the listing at the bottom and its row count never
looked at. Two buyers in flight both passed every check. Twelve simultaneous
buys of one listing let between five and twelve through, and paid the seller
for several; cancels refunded the same way, and listing could put the same
hundred archers up twice.

The tables are MyISAM, so there is no transaction to reach for. What there is
instead is that one statement is atomic and says how many rows it touched, so
each branch now **claims before it moves**: the payment is taken only
`AND gp >= cost`, the listing is claimed by deleting it and checking that one
row went, and everything after is relative — `col = col + delta`. A buyer who
loses the claim is refunded.

The crawl cannot see a race, so `tests/barter-race.php` makes one.

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
