"""Spider registry — discovers spiders so the CLI can list and run them by slug."""
from __future__ import annotations

from .base import Spider
from .spiders.demo import DemoSpider

# Register a spider here (or import and append) to make it runnable by slug.
_SPIDERS: dict[str, type[Spider]] = {
    DemoSpider.slug: DemoSpider,
}


def available() -> list[str]:
    return sorted(_SPIDERS)


def get(slug: str) -> Spider:
    try:
        return _SPIDERS[slug]()
    except KeyError:
        raise KeyError(f"unknown spider {slug!r}; available: {', '.join(available())}")


def all_spiders() -> list[Spider]:
    return [cls() for cls in _SPIDERS.values()]
