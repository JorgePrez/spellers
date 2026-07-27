# -*- coding: utf-8 -*-
"""Expand anglicism lexicon (university context seeds + morph). Orthography for ang."""
from __future__ import annotations

import re
import sys
from pathlib import Path

BASE = Path(__file__).resolve().parent
ROOT = BASE.parent
sys.path.insert(0, str(ROOT / "_shared"))
sys.path.insert(0, str(BASE))

from expand_engine import (  # noqa: E402
    finalize,
    morph_combo,
    tokens_from_escaped_block,
    write_expanded,
)

OUT = BASE / "source" / "external" / "expanded_ang.txt"
EXT = BASE / "source" / "external"

# Pattern for anglicisms: allow digits for B2B, SaaS, etc.
LETTER_ANG = re.compile(
    r"^[A-Za-z0-9\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1"
    r"\u00c7\u00e7\u00d6\u00f6]+$"
)

ROOTS = [
    "feedback", "deadline", "paper", "marketing", "briefing", "internship",
    "syllabus", "workshop", "webinar", "networking", "coaching", "mentoring",
    "stakeholder", "benchmark", "softskill", "elearning", "moodle", "canvas",
    "quiz", "midterm", "KPI", "ROI", "SaaS", "B2B", "B2C", "startup",
    "pitch", "freelance", "email", "online", "offline", "streaming", "podcast",
    "blog", "slide", "laptop", "wifi", "upload", "download", "meeting",
    "zoom", "teams", "assignment", "essay", "draft", "peer", "review",
    "plagiarism", "citation", "dataset", "sprint", "agile", "scrum", "kanban",
    "MVP", "OKR", "CRM", "ERP", "thesis", "proposal", "handout", "checklist",
    "roadmap", "milestone", "brainstorm", "leadership", "coworking", "scaleup",
    "openaccess", "preprint", "framework", "backend", "frontend", "fullstack",
    "deploy", "repository", "commit", "branch", "merge", "fork", "clone",
    "pipeline", "debug", "refactor", "workflow", "template", "dashboard",
    "interface", "user", "analytics", "metrics", "performance", "benchmark",
    "insight", "engagement", "outreach", "impact", "outcome", "assessment",
    "rubric", "grading", "peer", "self", "feedback", "portfolio", "showcase",
    "hackathon", "bootcamp", "skillset", "upskill", "reskill", "crossfunctional",
    "teamwork", "collaboration", "synergy", "leverage", "scalable", "sustainable",
    "disruptive", "innovative", "proactive", "responsive", "inclusive",
    "diversity", "equity", "inclusion", "mindset", "wellness", "burnout",
    "workload", "taskforce", "committee", "board", "council", "panel",
    "speaker", "keynote", "panelist", "moderator", "facilitator", "participant",
    "attendee", "registrant", "nominee", "awardee", "grantee", "scholar",
    "fellow", "researcher", "practitioner", "stakeholder", "policymaker",
    "decisionmaker", "influencer", "advocate", "champion", "ambassador",
    "entrepreneurship", "innovation", "incubator", "accelerator", "venture",
    "funding", "crowdfund", "sponsor", "partnership", "alliance", "consortium",
    "network", "platform", "ecosystem", "community", "membership", "subscription",
    "freemium", "paywall", "monetize", "revenue", "profit", "margin",
    "overhead", "budget", "forecast", "projection", "target", "goal",
    "objective", "deliverable", "output", "input", "outcome", "impact",
    "effectiveness", "efficiency", "productivity", "optimization", "automation",
    "digitization", "transformation", "disruption", "innovation", "iteration",
    "prototype", "pilot", "rollout", "launch", "release", "update",
    "upgrade", "migration", "legacy", "deprecate", "sunset", "phase",
    "stage", "tier", "level", "grade", "rank", "score",
    "rating", "ranking", "leaderboard", "gamification", "badge", "achievement",
    "certification", "accreditation", "compliance", "regulation", "standard",
    "protocol", "guideline", "policy", "procedure", "framework", "methodology",
    "approach", "strategy", "tactic", "technique", "practice", "implementation",
    "execution", "delivery", "fulfillment", "completion", "closure", "wrap",
    "debrief", "retrospective", "postmortem", "lesson", "learn", "takeaway",
    "insight", "finding", "discovery", "observation", "recommendation", "suggestion",
    "proposal", "plan", "roadmap", "timeline", "schedule", "agenda",
    "calendar", "event", "session", "module", "unit", "lesson",
    "chapter", "section", "segment", "part", "component", "element",
    "factor", "aspect", "dimension", "variable", "parameter", "attribute",
    "feature", "function", "capability", "capacity", "resource", "asset",
    "tool", "instrument", "device", "equipment", "facility", "infrastructure",
    "system", "network", "architecture", "design", "structure", "organization",
    "format", "layout", "style", "theme", "color", "font",
    "icon", "logo", "brand", "identity", "image", "visual",
    "graphic", "illustration", "diagram", "chart", "graph", "table",
    "spreadsheet", "document", "file", "folder", "directory", "archive",
    "backup", "restore", "recovery", "sync", "share", "collaborate",
    "edit", "comment", "annotate", "highlight", "bookmark", "tag",
    "label", "category", "classification", "taxonomy", "hierarchy", "structure",
    "link", "hyperlink", "URL", "website", "webpage", "homepage",
    "portal", "gateway", "hub", "node", "server", "client",
    "host", "domain", "subdomain", "endpoint", "API", "SDK",
    "plugin", "extension", "addon", "module", "package", "library",
    "dependency", "version", "release", "build", "compile", "runtime",
    "environment", "configuration", "setting", "preference", "option", "parameter",
    "default", "custom", "override", "inherit", "extend", "implement",
    "integrate", "embed", "inject", "import", "export", "transfer",
    "migrate", "convert", "transform", "parse", "format", "validate",
    "verify", "authenticate", "authorize", "encrypt", "decrypt", "secure",
    "firewall", "antivirus", "malware", "phishing", "spam", "hack",
    "breach", "vulnerability", "exploit", "patch", "fix", "bug",
    "error", "exception", "warning", "alert", "notification", "message",
    "log", "trace", "monitor", "track", "measure", "analyze",
    "report", "summary", "overview", "detail", "breakdown", "snapshot",
    "screenshot", "recording", "video", "audio", "image", "media",
    "content", "material", "resource", "asset", "library", "repository",
]

# Clean roots: remove duplicates and ensure valid
ROOTS = sorted({r for r in ROOTS if len(r) >= 2 and " " not in r})

# English morphological endings for anglicisms
ENG_SUFFIXES = [
    "ing", "er", "ers", "ed", "s", "es",
    "ment", "ments", "ship", "ships",
    "able", "ize", "ized", "izing",
    "ation", "ations",
]

SEED = r"""
feedback deadline paper marketing briefing internship
syllabus workshop webinar networking coaching mentoring
stakeholder benchmark softskill softskills elearning
moodle canvas quiz midterm KPI ROI SaaS B2B B2C
startup pitch freelance email online offline
streaming podcast blog slide laptop wifi
upload download meeting zoom teams
assignment essay draft peer review
plagiarism citation dataset sprint agile
scrum kanban MVP OKR CRM ERP
thesis proposal handout checklist roadmap
milestone brainstorm brainstorming leadership
coworking scaleup openaccess preprint
framework backend frontend fullstack
deploy deployment repository commit branch
merge fork clone pipeline debug
refactor workflow template dashboard
interface analytics metrics performance
insight engagement outreach impact
outcome assessment rubric grading
portfolio showcase hackathon bootcamp
skillset upskill reskill crossfunctional
teamwork collaboration synergy leverage
scalable sustainable disruptive innovative
proactive responsive inclusive diversity
equity inclusion mindset wellness burnout
workload taskforce committee board
keynote panelist moderator facilitator
"""

FORCE = [
    "feedback", "deadline", "syllabus", "workshop", "webinar",
    "networking", "coaching", "mentoring", "stakeholder",
    "benchmark", "softskill", "softskills", "elearning",
    "B2B", "B2C", "SaaS", "MVP", "KPI", "ROI", "OKR", "CRM", "ERP",
    "startup", "pitch", "freelance", "email", "online", "offline",
    "streaming", "podcast", "blog", "laptop", "wifi",
    "upload", "download", "meeting", "zoom", "teams",
    "assignment", "essay", "draft", "peer", "review",
    "plagiarism", "citation", "dataset", "sprint", "agile",
    "scrum", "kanban", "thesis", "proposal", "handout",
    "checklist", "roadmap", "milestone", "brainstorm",
    "brainstorming", "leadership", "coworking", "scaleup",
    "openaccess", "preprint", "marketing", "internship",
]

# DENY only clear Spanish typos (missing tilde)
DENY = {
    "tecnico", "imagenes", "credito", "codigo", "pagina",
    "numero", "graficos", "metodo", "analisis", "practica",
    "teorico", "politica", "politico", "economico", "juridico",
    "medico", "clinico", "diagnostico", "terapeutico",
    "hola", "cosas", "airway", "vaccine", "agenda",
}


def valid_word_ang(w: str, min_len: int = 2, max_len: int = 45) -> bool:
    """Valid word for anglicisms: allows digits for B2B, SaaS, etc."""
    w = w.strip()
    if not w or not (min_len <= len(w) <= max_len):
        return False
    if " " in w:
        return False
    # Allow alphanumeric with optional hyphens (for compound terms)
    if not re.match(r"^[A-Za-z0-9][A-Za-z0-9\-]*$", w):
        return False
    return True


def main() -> int:
    bag: set[str] = set()
    bag |= tokens_from_escaped_block(SEED)
    
    # Expand with English morphological endings
    for root in ROOTS:
        if not valid_word_ang(root):
            continue
        bag.add(root)
        for suffix in ENG_SUFFIXES:
            w = root + suffix
            if valid_word_ang(w):
                bag.add(w)
    
    # Filter valid words only
    bag = {w for w in bag if valid_word_ang(w)}
    
    # Use finalize with use_ang=True (allows English endings)
    bag, stats = finalize(bag, BASE, force_keep=FORCE, deny=DENY, use_ang=True)
    
    words = write_expanded(OUT, bag, "expanded_ang UA (generado; ortografia ang)")
    print(f"expanded_ang: {len(words)}  stats={stats}")
    
    # Check key terms
    for c in ("feedback", "deadline", "B2B", "SaaS", "brainstorming", "networking",
              "technico", "imagenes", "credito", "syllabus", "workshop"):
        print(f"  {c}: {c in bag}")
    
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
