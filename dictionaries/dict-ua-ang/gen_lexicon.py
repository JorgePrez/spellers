# -*- coding: utf-8 -*-
"""Curated university anglicisms / loanwords. ASCII-safe source."""
from __future__ import annotations

import codecs

B_CAMPUS = r"""
feedback deadline paper abstract poster workshop webinar
brainstorming networking coaching mentoring leadership
stakeholder benchmark briefing handout checklist
syllabus timeline milestone roadmap pitch
startup coworking freelance
soft softs skills softskills hardskills
"""

B_TECH = r"""
email online offline streaming podcast blog post link
slide slides powerpoint excel software hardware
laptop smartphone wifi password login logout
upload download meeting call zoom teams classroom
elearning blearning moodle canvas quiz
"""

B_ACADEMIC = r"""
midterm finals assignment homework essay draft
peer review plagiarism citation references
keywords impact factor openaccess preprint
dataset pipeline backlog sprint agile scrum kanban
MVP KPI ROI SWOT CRM ERP SaaS B2B B2C OKR
"""

B_HYBRIDS = r"""
marketing ranking clustering scoring testing consulting
outsourcing leasing factoring holding dumping
lobby lobbyist staff staffer internship trainee
fellow fellowship grant funding endowment
"""

B_MORE = r"""
brainstorm brainstorms brainstorming
network networker networking
coach coaches coaching
mentor mentors mentoring
leader leaders leadership
stake stakeholders stakeholder
benchmark benchmarks benchmarking
brief briefs briefing briefings
handout handouts checklist checklists
timeline timelines milestone milestones
roadmap roadmaps pitch pitches pitching
startup startups coworking freelance freelancing
softskill softskills hardskill hardskills
stream streams streaming streamer streamers
podcast podcasts podcaster podcasters
blogger bloggers blogging
poster posters workshop workshops webinar webinars
abstract abstracts keyword keywords
reference references citation citations
plagiarism plagiarisms reviewer reviewers reviewing
assignment assignments homework homeworks
essay essays draft drafts drafting
dataset datasets pipeline pipelines
backlog backlogs sprint sprints agile scrum kanban
openaccess preprint preprints
elearning blearning elearning blearning
classroom classrooms
password passwords login logins logout logouts
upload uploads downloading downloads downloader
meeting meetings caller callers calling
software hardware laptop laptops smartphone smartphones
powerpoint powerpoints excel excels
slide slides slideshow slideshows
link links linked linking
post posts posting posted
blog blogs blogging blogger bloggers
online offline email emails emailing
zoom teams moodle canvas quiz quizzes
midterm midterms final finals
factor factors impacting impacted impact
grant grants granting funded funding
fellow fellows fellowship fellowships
trainee trainees trainer trainers training
intern interns internship internships
staff staffer staffers
lobby lobbies lobbyist lobbyists lobbying
holding holdings leasing factoring outsourcing
consulting consultant consultants consultancy
testing tester testers tested
scoring scorer scorers scored
clustering cluster clusters clustered
ranking rankings ranked rank ranks
marketing marketer marketers marketed
dumping
endowment endowments
"""

BLOCKS = (B_CAMPUS, B_TECH, B_ACADEMIC, B_HYBRIDS, B_MORE)


def tokens(escaped_block: str) -> list[str]:
    return [t for t in codecs.decode(escaped_block, "unicode_escape").split() if t]


def all_block_tokens() -> list[str]:
    out: list[str] = []
    for b in BLOCKS:
        out.extend(tokens(b))
    return out
