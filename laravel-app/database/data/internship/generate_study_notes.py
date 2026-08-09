#!/usr/bin/env python3
"""Generate study_note text for every task in beyond_180_day_curriculum_seed.json."""

from __future__ import annotations

import json
import re
from pathlib import Path

SEED = Path(__file__).with_name("beyond_180_day_curriculum_seed.json")

PROGRAM_INTRO = {
    "NETWORKING": (
        "Beyond Networking internship notes prepare you to design, build, and verify "
        "reliable IP networks in authorized labs only."
    ),
    "CYBER_SECURITY": (
        "Beyond Cyber Security internship notes prepare you to defend systems ethically "
        "inside isolated labs—never attack production or third-party systems."
    ),
    "CLOUD_COMPUTING": (
        "Beyond Cloud Computing internship notes prepare you to deploy and operate cloud "
        "workloads using free-tier or Beyond-authorized accounts with cost controls."
    ),
    "ARTIFICIAL_INTELLIGENCE": (
        "Beyond Artificial Intelligence internship notes prepare you to apply AI methods "
        "responsibly, with clear documentation and human oversight."
    ),
    "MACHINE_LEARNING": (
        "Beyond Machine Learning internship notes prepare you to build reproducible ML "
        "pipelines from data preparation through evaluation."
    ),
    "DATA_SCIENCE": (
        "Beyond Data Science internship notes prepare you to clean, analyze, and communicate "
        "data-driven findings with professional evidence."
    ),
    "SOFTWARE_DEVELOPMENT": (
        "Beyond Software Development internship notes prepare you to ship maintainable "
        "software with clear requirements, version control, and tests."
    ),
    "LIVE_SOUND_ENGINEERING": (
        "Beyond Live Sound internship notes prepare you to run safe, clear audio for live "
        "events using Beyond gear and approved venues."
    ),
    "LIGHTING_ENGINEERING": (
        "Beyond Lighting Engineering internship notes prepare you to design and operate "
        "safe stage lighting with correct power, DMX, and rigging discipline."
    ),
    "SCREENS_VIDEO": (
        "Beyond Screens & Video internship notes prepare you to deliver reliable video "
        "and LED/projection systems for events and installations."
    ),
    "INTERCOM": (
        "Beyond Intercom internship notes prepare you to set up production communications "
        "with clear etiquette, correct cabling, and reliable partyline/matrix systems."
    ),
}

PROGRAM_CONCEPTS = {
    "NETWORKING": [
        "OSI/TCP-IP layering",
        "addressing and subnetting",
        "switching and VLANs",
        "routing and default gateways",
        "cabling and physical media",
        "DNS/DHCP services",
        "ACL and basic firewall rules",
        "documentation and change control",
    ],
    "CYBER_SECURITY": [
        "CIA triad",
        "least privilege",
        "asset inventory",
        "threat modeling",
        "hardening baselines",
        "logging and detection",
        "incident response basics",
        "ethical boundaries and authorization",
    ],
    "CLOUD_COMPUTING": [
        "IaaS/PaaS/SaaS",
        "regions and availability",
        "identity and access (IAM)",
        "virtual networks and security groups",
        "containers and images",
        "cost and free-tier limits",
        "infrastructure as code habits",
        "monitoring and backups",
    ],
    "ARTIFICIAL_INTELLIGENCE": [
        "problem framing",
        "data quality",
        "model vs heuristic systems",
        "evaluation metrics",
        "bias and privacy",
        "prompting and RAG concepts",
        "human-in-the-loop review",
        "safe lab experimentation",
    ],
    "MACHINE_LEARNING": [
        "train/validation/test splits",
        "features and labels",
        "overfitting vs underfitting",
        "classification and regression",
        "pipelines and transforms",
        "cross-validation",
        "model selection",
        "reproducible experiments",
    ],
    "DATA_SCIENCE": [
        "tidy data",
        "exploratory analysis",
        "cleaning and imputation",
        "joins and aggregations",
        "visualization honesty",
        "statistical intuition",
        "storytelling with evidence",
        "data ethics and PII handling",
    ],
    "SOFTWARE_DEVELOPMENT": [
        "requirements and acceptance criteria",
        "version control with Git",
        "HTML/CSS/JS or backend modules",
        "APIs and data models",
        "testing and debugging",
        "code readability",
        "deployment basics",
        "security hygiene for apps",
    ],
    "LIVE_SOUND_ENGINEERING": [
        "signal flow",
        "gain structure",
        "microphone choice and placement",
        "mixer routing",
        "EQ and dynamics",
        "monitoring and feedback control",
        "cable discipline",
        "hearing protection and electrical safety",
    ],
    "LIGHTING_ENGINEERING": [
        "fixture types and photometrics basics",
        "power calculation",
        "DMX addressing",
        "console programming",
        "focus and color",
        "cable and connector inspection",
        "working-at-height rules",
        "show file backups",
    ],
    "SCREENS_VIDEO": [
        "signal standards (HDMI/SDI)",
        "resolution and frame rate",
        "switchers and converters",
        "projector/LED setup",
        "EDID and scaling",
        "cable length and integrity",
        "content formatting",
        "lift/electrical safety",
    ],
    "INTERCOM": [
        "partyline vs matrix",
        "beltpacks and headsets",
        "call language and etiquette",
        "wired vs wireless links",
        "IFB and stage announce concepts",
        "power and cable checks",
        "channel assignment",
        "production safety communications",
    ],
}

PROGRAM_SAFETY = {
    "NETWORKING": (
        "Do not scan or reconfigure production networks. Use Beyond lab VLANs/VMs only. "
        "Record every IP/VLAN change. Ask a supervisor before touching shared infrastructure."
    ),
    "CYBER_SECURITY": (
        "Work only in isolated labs with written authorization. Never use offensive tools "
        "against live systems, classmates, or the internet. Report accidental exposure immediately."
    ),
    "CLOUD_COMPUTING": (
        "Stay within Beyond-approved free tiers. Set billing alerts. Destroy unused resources daily. "
        "Never store secrets in public repos or screenshots."
    ),
    "ARTIFICIAL_INTELLIGENCE": (
        "Do not upload private or client data to public AI tools. Prefer local/open models when "
        "possible. Disclose AI assistance in your report. Verify factual claims before submitting."
    ),
    "MACHINE_LEARNING": (
        "Treat datasets as sensitive unless marked public. Do not scrape personal data. "
        "Document train/test leakage risks and keep notebooks reproducible."
    ),
    "DATA_SCIENCE": (
        "Anonymize personal data. Do not invent statistics. Cite sources. Keep raw vs cleaned "
        "datasets clearly separated in your evidence pack."
    ),
    "SOFTWARE_DEVELOPMENT": (
        "Never commit passwords or API keys. Test on local/dev environments only. "
        "Get approval before deploying anything that others will use."
    ),
    "LIVE_SOUND_ENGINEERING": (
        "Wear hearing protection during loud checks. Inspect cables for damage. "
        "Do not overload circuits. Coil and label Beyond cables after use."
    ),
    "LIGHTING_ENGINEERING": (
        "Respect working-at-height rules and PPE. Calculate power before energizing. "
        "Never hang fixtures without approved hardware and a spotter when required."
    ),
    "SCREENS_VIDEO": (
        "Use approved lifts/stands. Secure cables to prevent trip hazards. "
        "Power down before reseating signal connectors on energized racks when instructed."
    ),
    "INTERCOM": (
        "Keep channels professional—no jokes during show-critical moments. "
        "Test backup talk paths. Never leave beltpacks unattended in public guest areas."
    ),
}

MODE_PROCEDURE = {
    "Orientation and setup": (
        "Today is orientation and environment setup. Confirm your workstation, accounts, "
        "and lab access. Install or verify only the tools listed for this day. Capture a "
        "baseline screenshot of the ready environment before you experiment further."
    ),
    "Guided practical": (
        "Today is a guided practical. Follow the step sequence in the Study Notes and "
        "instructions exactly once, then repeat once from memory. Pause at each checkpoint "
        "to verify expected outputs before continuing."
    ),
    "Independent build": (
        "Today is an independent build. Using yesterday’s concepts, produce a small working "
        "artifact without copying a walkthrough line-for-line. Prefer clarity and correctness "
        "over novelty. Record decisions that differ from the template approach."
    ),
    "Troubleshoot": (
        "Today is troubleshooting. Introduce or accept a controlled fault in the lab, then "
        "diagnose using a written hypothesis → test → result loop. Do not randomly change "
        "many settings at once; change one variable at a time and note the effect."
    ),
    "Document and improve": (
        "Today is documentation and improvement. Rewrite your procedure so another intern "
        "could reproduce it. Improve one weak point (naming, safety note, validation step, "
        "or performance). Attach before/after evidence."
    ),
    "Assessment and reflection": (
        "Today is assessment and reflection. Demonstrate the topic end-to-end, score yourself "
        "against the marking guide, and write an honest reflection: what worked, what failed, "
        "and what you would do differently on a live Beyond job."
    ),
}


def split_title(title: str) -> tuple[str, str]:
    if ":" in title:
        mode, topic = title.split(":", 1)
        return mode.strip(), topic.strip()
    return "Guided practical", title.strip()


def topic_key_terms(topic: str, code: str) -> list[str]:
    base = PROGRAM_CONCEPTS.get(code, PROGRAM_CONCEPTS["SOFTWARE_DEVELOPMENT"])
    words = [w for w in re.split(r"[^A-Za-z0-9]+", topic) if len(w) > 2]
    topic_terms = []
    for w in words[:8]:
        topic_terms.append(f"{w} in the context of {topic}")
    # mix program pillars with topic-specific phrases
    mixed = []
    for i, c in enumerate(base):
        if i < 4:
            mixed.append(c)
        else:
            mixed.append(c)
    # ensure topic words appear as headings in concepts
    unique = []
    for item in topic_terms[:4] + mixed[:6]:
        if item not in unique:
            unique.append(item)
    return unique[:10]


def topic_theory(topic: str, code: str, objective: str) -> str:
    pillars = PROGRAM_CONCEPTS.get(code, [])
    pillar_text = "; ".join(pillars[:5])
    return (
        f"Topic focus: {topic}.\n"
        f"This day links the program pillars ({pillar_text}) to the concrete objective:\n"
        f"“{objective}”\n\n"
        f"Read the topic as a professional skill, not a buzzword. Define the main terms in "
        f"your own words, then connect each term to a lab action you can demonstrate. "
        f"When the topic includes multiple ideas (for example data + model, or cable + signal), "
        f"separate them: first the concept, then the control you can measure, then the failure "
        f"mode you must avoid on a Beyond job site or lab."
    )


def self_checks(topic: str, mode: str) -> list[str]:
    return [
        f"Can you explain {topic} to a supervisor in under two minutes without reading notes?",
        f"What evidence proves you completed the {mode.lower()} correctly today?",
        "Which safety or ethics rule would you break if you rushed—and how will you prevent that?",
        "What would you check first if the demo failed five minutes before a review?",
    ]


def evidence_lines(submission: str) -> list[str]:
    sub = (submission or "").strip()
    lines = [
        "Collect evidence while you work—not at the end of the day.",
        "Keep filenames clear: day-number_topic_short-description.ext",
    ]
    if sub:
        lines.append(f"Required submission for this day: {sub}")
    else:
        lines.append("Submit screenshots/PDF plus a short technical explanation as instructed.")
    return lines


def build_study_note(code: str, task: dict) -> str:
    title = task.get("title") or "Internship day"
    mode, topic = split_title(title)
    objective = (task.get("objective") or "Complete the day objective safely and document the result.").strip()
    tools = (task.get("tools") or "Tools assigned by Beyond for this lab").strip()
    difficulty = (task.get("difficulty") or "foundation").strip()
    hours = task.get("estimated_hours", 8)
    day = task.get("day_number", "?")
    submission = task.get("submission") or task.get("submission_requirements") or ""

    intro = PROGRAM_INTRO.get(code, "Beyond internship study notes.")
    safety = PROGRAM_SAFETY.get(code, "Follow Beyond lab rules and ask your supervisor before changes.")
    procedure = MODE_PROCEDURE.get(mode, MODE_PROCEDURE["Guided practical"])
    terms = topic_key_terms(topic, code)
    theory = topic_theory(topic, code, objective)
    checks = self_checks(topic, mode)
    evidence = evidence_lines(submission)

    sections = [
        f"STUDY NOTES — Day {day}: {title}",
        "",
        intro,
        "",
        "1) DAY FOCUS",
        f"Mode: {mode}",
        f"Topic: {topic}",
        f"Difficulty: {difficulty} · Estimated time: {hours}h",
        f"Tools for this day: {tools}",
        "",
        "2) LEARNING OUTCOMES",
        f"- Meet the objective: {objective}",
        f"- Use the correct tools and lab setup for {topic}.",
        f"- Produce evidence that matches the submission requirements.",
        f"- Apply Beyond safety, ethics, and documentation standards.",
        "",
        "3) CORE CONCEPTS",
        theory,
        "",
        "Key terms and ideas to define in your report:",
    ]
    for t in terms:
        sections.append(f"- {t}")

    sections.extend(
        [
            "",
            "4) PRACTICAL PROCEDURE",
            procedure,
            "",
            "Recommended work sequence:",
            f"1. Read this entire note and the day objective for {topic}.",
            "2. Prepare the authorized lab / VM / equipment; photograph the starting state.",
            f"3. Execute the {mode.lower()} for {topic} using only approved tools.",
            "4. Verify results with at least one independent check (second measurement, test, or peer review).",
            "5. Package screenshots/PDF notes and write a short professional technical explanation.",
            "",
            "5) SAFETY, ETHICS, AND BEYOND LAB RULES",
            safety,
            "Keep time entries truthful. Ask your supervisor before changing production equipment "
            "or shared accounts. Work only in authorized labs, VMs, or equipment assigned by Beyond.",
            "",
            "6) SELF-CHECK QUESTIONS",
        ]
    )
    for i, q in enumerate(checks, 1):
        sections.append(f"{i}. {q}")

    sections.extend(["", "7) EVIDENCE CHECKLIST"])
    for e in evidence:
        sections.append(f"- {e}")

    sections.extend(
        [
            "",
            "Pass tip: supervisors look for correct technique, clear evidence, honest troubleshooting, "
            "and professional documentation—not perfect first attempts.",
        ]
    )
    return "\n".join(sections).strip() + "\n"


def rewrite_instructions(instructions: list) -> list:
    out = []
    for line in instructions or []:
        if not isinstance(line, str):
            out.append(line)
            continue
        updated = re.sub(
            r"Study the supplied note and",
            "Read the Study Notes on this page, then",
            line,
            flags=re.IGNORECASE,
        )
        updated = re.sub(
            r"Study the supplied note",
            "Read the Study Notes on this page",
            updated,
            flags=re.IGNORECASE,
        )
        updated = re.sub(
            r"the supplied note",
            "the Study Notes on this page",
            updated,
            flags=re.IGNORECASE,
        )
        out.append(updated)
    return out


def main() -> None:
    data = json.loads(SEED.read_text(encoding="utf-8"))
    count = 0
    for prog in data.get("programs", []):
        code = str(prog.get("code") or "").upper()
        for task in prog.get("tasks", []):
            task["study_note"] = build_study_note(code, task)
            task["instructions"] = rewrite_instructions(task.get("instructions") or [])
            count += 1
    SEED.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Wrote study_note for {count} tasks → {SEED}")


if __name__ == "__main__":
    main()
