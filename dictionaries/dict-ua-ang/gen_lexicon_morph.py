# -*- coding: utf-8 -*-
"""Controlled English morphology for campus anglicisms (whitelist only)."""
from __future__ import annotations

import re
from itertools import product

LETTER = re.compile(
    r"^[A-Za-z0-9\u00c1\u00c9\u00cd\u00d3\u00da\u00dc\u00d1"
    r"\u00e1\u00e9\u00ed\u00f3\u00fa\u00fc\u00f1"
    r"\u00c7\u00e7\u00d6\u00f6]+$"
)

WHITELIST = (
    "feedback", "deadline", "paper", "abstract", "poster", "workshop", "webinar",
    "brainstorm", "network", "coach", "mentor", "leader", "stakeholder",
    "benchmark", "brief", "handout", "checklist", "syllabus", "timeline",
    "milestone", "roadmap", "pitch", "startup", "freelance", "skill", "email",
    "stream", "podcast", "blog", "post", "link", "slide", "meeting", "call",
    "quiz", "assignment", "homework", "essay", "draft", "peer", "review",
    "plagiar", "cite", "reference", "keyword", "impact", "factor", "preprint",
    "dataset", "pipeline", "backlog", "sprint", "market", "rank", "cluster",
    "score", "test", "consult", "outsource", "lease", "factor", "hold",
    "dump", "lobby", "staff", "intern", "train", "fellow", "grant", "fund",
    "endow", "research", "method", "hypothesis", "manuscript", "submit",
    "publish", "conference", "symposium", "archive", "business", "manage",
    "brand", "customer", "client", "vendor", "partner", "sponsor", "sale",
    "recruit", "onboard", "upskill", "reskill", "mindset", "workflow",
    "dashboard", "analytic", "entrepreneur", "founder", "invest", "venture",
    "portfolio", "pivot", "scale", "growth", "conversion", "funnel", "landing",
    "website", "influencer", "follower", "engage", "click", "deploy", "debug",
    "commit", "branch", "merge", "issue", "ticket", "standup", "retro", "demo",
    "prototype", "mockup", "wireframe", "automate", "cloud", "server", "endpoint",
    "webhook", "query", "script", "container", "present", "communicate",
    "collaborate", "negotiate", "mediate", "facilitate", "campus", "tuition",
    "scholarship", "registrar", "transcript", "faculty", "department", "semester",
    "credit", "prerequisite", "elective", "major", "minor", "undergraduate",
    "graduate", "alumni", "proctor", "gamify", "hackathon", "bootcamp", "crowdfund",
    "crowdsource", "chatbot", "blockchain", "livestream", "openaccess",
)

SUFFIXES = (
    "s", "es", "ed", "ing", "er", "ers", "est", "ly", "ness", "ment", "ments",
    "tion", "tions", "ship", "ships", "able", "ible", "ize", "ise", "ized",
    "ised", "izing", "ising", "ful", "less",
)


def morph_tokens() -> set[str]:
    out: set[str] = set()
    for base, suf in product(WHITELIST, SUFFIXES):
        if base.endswith(suf):
            continue
        w = base + suf
        if 3 <= len(w) <= 45 and LETTER.fullmatch(w):
            out.add(w)
    return out
