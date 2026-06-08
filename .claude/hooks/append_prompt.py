#!/usr/bin/env python3
"""UserPromptSubmit hook — append every submitted prompt to prompts.md.

Deterministic, team-wide enforcement of the "log every prompt verbatim" rule
(see CLAUDE.md). Reads the hook JSON from stdin, appends the user's prompt to
prompts.md at the project root under a per-day "## YYYY-MM-DD" heading.

Guarantees:
  - ALWAYS exits 0 — a logging hiccup must never block the user's prompt.
  - Writes NOTHING to stdout — UserPromptSubmit stdout is injected into the
    model context, so silence keeps the context clean.
  - Append-only; never edits or rewrites existing entries.
"""
import sys
import os
import json
import datetime


def main() -> None:
    raw = sys.stdin.read()
    try:
        data = json.loads(raw) if raw.strip() else {}
    except Exception:
        data = {}

    prompt = (data.get("prompt") or "").rstrip("\n")
    if prompt.strip() == "":
        return  # nothing to log (e.g. slash-command-only input)

    # Project root: Claude Code sets CLAUDE_PROJECT_DIR; fall back to cwd.
    root = os.environ.get("CLAUDE_PROJECT_DIR") or os.getcwd()
    path = os.path.join(root, "prompts.md")

    today = datetime.date.today().isoformat()  # YYYY-MM-DD
    heading = "## " + today

    try:
        with open(path, "r", encoding="utf-8") as fh:
            existing = fh.read()
    except FileNotFoundError:
        existing = ""
    except Exception:
        existing = ""

    block = []
    # Add today's date heading only the first time we log today.
    if heading not in existing:
        block.append("")
        block.append(heading)
    block.append("")
    for line in prompt.split("\n"):
        block.append("> " + line if line != "" else ">")
    text = "\n".join(block) + "\n"

    try:
        with open(path, "a", encoding="utf-8") as fh:
            fh.write(text)
    except Exception:
        pass  # never block on a write failure


if __name__ == "__main__":
    try:
        main()
    except Exception:
        pass
    sys.exit(0)
