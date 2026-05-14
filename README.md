# Bot Rate Limiter

`bot-rate-limiter` is a lightweight PHP script that limits request rates for specific bots based on their User-Agent.

## What it does

- Monitors requests from configured bot identifiers.
- Tracks request timestamps in a local JSON log file.
- Applies per-bot request limits within a 60-second window.
- Returns `429 Too Many Requests` when a bot exceeds its limit.
- Writes blocked requests to a separate log file.

## Current default configuration

In `bot-rate-limiter.php`, the defaults are:

- Time window: `60` seconds
- `facebookexternalhit`: `10` requests/minute
- `openai`: `10` requests/minute
- Counter file: `bot_counter.txt`
- Block log file: `bot_blocked.txt`

## How it works

1. The script reads the incoming `HTTP_USER_AGENT`.
2. It matches the first configured bot string found in the User-Agent.
3. It loads stored timestamps for that bot.
4. It removes timestamps older than the configured time window.
5. If the limit is reached, it:
   - logs the blocked request,
   - responds with HTTP `429`,
   - sends `Retry-After: 60`.
6. Otherwise, it stores the current timestamp and allows the request.

## Notes

- File locking (`flock`) is used to reduce race conditions during concurrent requests.
- Only bots listed in `$bot_limits` are rate-limited.
- This script uses local files for storage, so file permissions must allow read/write access.
