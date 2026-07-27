# -*- coding: utf-8 -*-
"""Expanded curated university anglicisms (~2k-6k). ASCII-safe."""
from __future__ import annotations

import codecs

B_CAMPUS = r"""
feedback feedbacks deadline deadlines extension extensions
paper papers abstract abstracts poster posters
workshop workshops webinar webinars webcast webcasts webconference webconferences
brainstorm brainstorms brainstorming brainstormed brainstormer brainstormers
network networks networking networked networker networkers
coaching coach coaches coached coaching
mentoring mentor mentors mentored mentoring mentorship
leadership leader leaders leading
stakeholder stakeholders
benchmark benchmarks benchmarking benchmarked
briefing briefings brief briefs briefed
handout handouts checklist checklists
syllabus syllabi silabus timeline timelines
milestone milestones roadmap roadmaps
pitch pitches pitching pitched pitcher pitchers
startup startups scaleup scaleups
coworking coworker coworkers freelance freelancer freelancers freelancing
soft softs skill skills softskill softskills hardskill hardskills
brainstorming networking coaching mentoring leadership
"""

B_TECH = r"""
email emails emailing emailed
online offline streaming streamed stream streams streamer streamers
podcast podcasts podcaster podcasters podcasting
blog blogs blogger bloggers blogging blogged
post posts posting posted
link links linked linking hyperlink hyperlinks
slide slides slideshow slideshows deck decks
powerpoint powerpoints ppt ppts excel excels spreadsheet spreadsheets
software hardware firmware shareware freeware
laptop laptops notebook notebooks desktop desktops
smartphone smartphones tablet tablets wearable wearables
wifi wi-fi password passwords passphrase passphrases
login logins logout logouts logged logging
upload uploads uploaded uploading uploader uploaders
download downloads downloaded downloading downloader downloaders
meeting meetings meet meets met
call calls called calling caller callers
zoom teams skype meet google classroom classrooms
elearning elearning blearning blearning e-learning b-learning
moodle canvas blackboard edmodo schoology
quiz quizzes midterm midterms final finals
screenshot screenshots screen share screenshare
webcam webcams microphone microphones headset headsets
"""

B_ACADEMIC = r"""
assignment assignments assign assigned assigning
homework homeworks essay essays essayist essayists
draft drafts drafting drafted
peer peers review reviews reviewed reviewer reviewers reviewing
plagiarism plagiarisms plagiarize plagiarized plagiarizing plagiarizer
citation citations cite cited citing
reference references referenced referencing bibliography bibliographies
keyword keywords abstract abstracts
impact impacts impacted impacting
factor factors openaccess open access openaccess
preprint preprints postprint postprints
dataset datasets pipeline pipelines backlog backlogs
sprint sprints sprinting agile scrum kanban
MVP MVPs KPI KPIs ROI ROIs SWOT SWOTs
CRM CRMs ERP ERPs SaaS B2B B2C OKR OKRs
thesis theses dissertation dissertations
proposal proposals prospectus prospectuses
outline outlines outlining outlined
summary summaries summarizing summarized
appendix appendices annex annexes
footnote footnotes endnote endnotes
paraphrase paraphrases paraphrasing paraphrased
quotation quotations quote quotes quoted quoting
bibliography bibliographies
literature review literaturereview
peerreview peer review peerreviewed peer-reviewed
"""

B_HYBRIDS = r"""
marketing marketer marketers marketed marketing
ranking rankings ranked rank ranks
clustering cluster clusters clustered clustering
scoring score scores scored scorer scorers scoring
testing test tests tested tester testers testing
consulting consultant consultants consultancy consultancies
outsourcing outsource outsourced outsourcing
leasing lease leases leased leasing
factoring factor factors factored factoring
holding holdings dumping dump dumped dumping
lobby lobbies lobbyist lobbyists lobbying lobbied
staff staffer staffers staffed staffing
internship internships intern interns interned interning
trainee trainees trainer trainers training trained
fellow fellows fellowship fellowships
grant grants granted granting grantee grantees
funding funded funder funders fundraise fundraising
endowment endowments endowed endowing
marketing ranking clustering scoring testing consulting
outsourcing leasing factoring holding dumping
lobby lobbyist staff staffer internship trainee fellow fellowship grant funding endowment
"""

B_RESEARCH = r"""
research researcher researchers researching researched
methodology methodologies methodological
hypothesis hypotheses hypothetical
metadata manuscript manuscripts
submission submissions submitted submitting
reviewer reviewers reviewing reviewed
editor editors editorial editorship
publisher publishers publishing published
conference conferences proceedings
symposium symposia symposiums
peer-reviewed peerreviewed open-access openaccess
h-index hindex altmetrics altmetric
repository repositories archive archived archiving
replication replications replicable replicability
validity validities reliability reliabilities
triangulation triangulated triangulating
sampling samples sampled sampler samplers
survey surveys surveyed surveying
interview interviews interviewed interviewing interviewer interviewers
focus group focusgroup focusgroups
case study casestudy casestudies
longitudinal cross-sectional crosssectional
qualitative quantitative mixed-methods mixedmethods
randomized randomised randomization randomisation
control group controlgroup
variable variables dependent independent
correlation correlations correlational
regression regressions multivariate
significance significant statistically
p-value pvalue confidence interval
"""

B_BUSINESS = r"""
business businesses management manager managers managerial
leadership stakeholder stakeholders benchmark benchmarks
briefing briefings handout handouts checklist checklists
roadmap roadmaps timeline timelines milestone milestones
pitch pitches startup startups coworking freelance
consulting consultant consultants consultancy
branding brand brands branded branding
customer customers client clients vendor vendors
partner partners partnership partnerships
sponsor sponsors sponsorship sponsorships
sales salesperson salespersons
headhunting headhunter headhunters recruiting recruitment
onboarding offboarding upskilling reskilling
mindset skillset toolkit toolbox playbook
framework frameworks guideline guidelines template templates
workflow workflows dashboard dashboards analytics
entrepreneur entrepreneurs entrepreneurship
founder founders cofounder cofounders
investor investors investment investments
incubator incubators accelerator accelerators
venture ventures portfolio portfolios
valuation valuations captable equity stock stocks
shareholder shareholders boardroom boardrooms
pivot pivoted pivoting scale scaling scaled scalable
growth growthhacking traction churn retention
conversion conversions funnel funnels
landingpage landingpages homepage homepages website websites
"""

B_EDTECH = r"""
edtech proctoring proctored proctor proctors
gamification gamify gamified gamifying
microlearning bootcamp bootcamps hackathon hackathons
crowdfunding crowdsourcing crowdsource crowdsourced
blockchain chatbot chatbots
livestream livestreams livestreaming livestreamed
influencer influencers follower followers
engagement engagements reach impression impressions
click clicks clickthrough click-through CTR
CPM CPC CPA CPMs CPCs CPAs
SEO SEM SMM PPC
"""

B_IT = r"""
backend frontend fullstack full-stack devops
deploy deployment deployments deployed deploying
debug debugging debugger debugged
commit commits committed committing
repository repo repos branch branches merged merge merging
pullrequest pullrequests issue issues ticket tickets
standup standups retrospective retrospectives retro
demo demos demoed demoing prototype prototypes
mockup mockups wireframe wireframes
automation automate automated automating
chatgpt openai copilot github gitlab bitbucket
cloud cloudbased serverless microservice microservices
database databases endpoint endpoints webhook webhooks
api apis server servers caching cached cache
script scripts scripting scripted
query queries queried querying
container containers docker kubernetes
machinelearning deeplearning deep-learning deeplearning
datascience datawarehouse datawarehouses
"""

B_WRITING = r"""
brainstorming networking feedback deadline
writing writer writers writing
reading reader readers reading
listening listener listeners listening
speaking speaker speakers speaking
presentation presentations presenter presenters presenting
communication communications communicator communicators
collaboration collaborations collaborative collaboratively
teamwork teambuilding team-building
problem-solving problemsolving
critical thinking criticalthinking
decision-making decisionmaking
time management timemanagement
project management projectmanagement
conflict resolution conflictresolution
negotiation negotiations negotiator negotiators
mediation mediations mediator mediators
facilitation facilitations facilitator facilitators
"""

B_CAMPUS_LIFE = r"""
campus campuses dorm dorms dormitory dormitories
cafeteria cafeterias bookstore bookstores
tuition scholarship scholarships fellowship fellowships
registrar registrars transcript transcripts
dean deans provost provosts chancellor chancellors
faculty faculties department departments
semester semesters quarter quarters
syllabus credit credits prerequisite prerequisites
elective electives major majors minor minors
freshman sophomore junior senior undergrad undergraduate
graduate graduates postgraduate postgraduates
alumni alumna alumnus
"""

B_PROPER = r"""
LinkedIn Twitter Facebook Instagram TikTok YouTube
Spotify Netflix Amazon Microsoft Google Apple Meta OpenAI ChatGPT
Slack Trello Asana Jira Confluence Notion Evernote
Dropbox Drive OneDrive SharePoint Outlook Gmail
WhatsApp Telegram Discord
Turnitin Grammarly Zotero Mendeley EndNote RefWorks Overleaf
LaTeX Markdown Jupyter Colab Kaggle
TensorFlow PyTorch pandas numpy
JavaScript TypeScript Python Java Kotlin Swift
React Angular Vue Node Express Django Flask Spring Bootstrap Tailwind
Figma Sketch Canva
CEO CFO CTO COO CMO CIO CSO VP SVP EVP
HR PR IT QA QC R&D RnD EBITDA PnL LTV CAC
UX UI CX NPS CSAT GDPR HIPAA ISO IEEE ACM DOI ISBN ISSN ORCID
"""

EXPAND_BLOCKS = (
    B_CAMPUS,
    B_TECH,
    B_ACADEMIC,
    B_HYBRIDS,
    B_RESEARCH,
    B_BUSINESS,
    B_EDTECH,
    B_IT,
    B_WRITING,
    B_CAMPUS_LIFE,
    B_PROPER,
)


def expand_tokens() -> list[str]:
    out: set[str] = set()
    for b in EXPAND_BLOCKS:
        for t in codecs.decode(b, "unicode_escape").split():
            if t and " " not in t:
                out.add(t)
    return sorted(out)
