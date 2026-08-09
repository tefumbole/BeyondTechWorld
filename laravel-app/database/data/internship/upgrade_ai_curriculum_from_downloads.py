#!/usr/bin/env python3
"""
Upgrade ARTIFICIAL_INTELLIGENCE tasks in beyond_180_day_curriculum_seed.json
from the Downloads-enhanced 180-day program JSON.

Keeps program code/version 1.0 so Laravel import updates the existing program row.
"""

from __future__ import annotations

import json
import re
from pathlib import Path

HERE = Path(__file__).resolve().parent
SEED = HERE / "beyond_180_day_curriculum_seed.json"
SOURCE = HERE / "handbooks" / "ai" / "source_upgrade" / "Beyond_AI_Internship_180_Day_Program.json"

DEFAULT_RUBRIC = {
    "technical_correctness": 40,
    "evidence_and_reproducibility": 20,
    "troubleshooting_or_reasoning": 15,
    "documentation": 15,
    "safety_security_professionalism": 10,
}


def rewrite_instruction(line: str) -> str:
    text = line
    text = re.sub(
        r"Study the supplied note and",
        "Read the Study Notes on this page (and the Day Student Handbook), then",
        text,
        flags=re.IGNORECASE,
    )
    text = re.sub(
        r"the supplied note",
        "the Study Notes on this page",
        text,
        flags=re.IGNORECASE,
    )
    return text


def compose_study_note(day: dict) -> str:
    tools = day.get("tools") or []
    tools_s = ", ".join(tools) if isinstance(tools, list) else str(tools)
    outcomes = day.get("learning_outcomes") or []
    concepts = day.get("core_concepts") or []
    procedure = day.get("practical_procedure") or []
    safety = day.get("safety_ethics_and_beyond_lab_rules") or []
    checks = day.get("self_check_questions") or []
    evidence = day.get("evidence_checklist") or []
    report = day.get("report_sections") or []

    lines = [
        f"STUDY NOTES — Day {day['day']}: {day.get('mode')}: {day.get('topic')}",
        "",
        "Beyond Artificial Intelligence internship notes prepare you to apply "
        f"**{day.get('topic')}** responsibly, with clear documentation and human oversight.",
        "",
        "1) DAY FOCUS",
        f"Mode: {day.get('mode')}",
        f"Topic: {day.get('topic')}",
        f"Phase: {day.get('phase')}",
        f"Difficulty: {day.get('difficulty')} · Estimated time: {day.get('estimated_time_hours')}h",
        f"Tools for this day: {tools_s}",
        f"Objective: {day.get('objective')}",
        f"Prerequisite: {day.get('prerequisite')}",
        "",
        "2) LEARNING OUTCOMES",
    ]
    for o in outcomes:
        lines.append(f"- {o}")

    lines.extend(
        [
            "",
            "3) CORE CONCEPTS",
            f"Topic focus: {day.get('topic')}. Read the topic as a professional skill, not a buzzword. "
            "For every concept, state the concept, the observable control, the evidence, and the failure mode to avoid.",
            "",
            "Key terms and ideas to define in the report:",
        ]
    )
    for c in concepts:
        lines.append(f"- {c}")

    lines.extend(["", "4) PRACTICAL PROCEDURE", f"Prerequisite: {day.get('prerequisite')}", ""])
    for i, step in enumerate(procedure, 1):
        lines.append(f"{i}. {rewrite_instruction(str(step))}")

    lines.extend(["", "5) SAFETY, ETHICS, AND BEYOND LAB RULES"])
    for s in safety:
        lines.append(f"- {s}")
    lines.append(
        "Keep time entries truthful. Ask your supervisor before changing production equipment "
        "or shared accounts. Work only in authorized labs, VMs, or equipment assigned by Beyond."
    )

    lines.extend(["", "6) SELF-CHECK QUESTIONS"])
    for i, q in enumerate(checks, 1):
        lines.append(f"{i}. {q}")

    lines.extend(["", "7) EVIDENCE CHECKLIST AND REPORT"])
    lines.append(f"Required submission: {day.get('required_submission')}")
    for ev in evidence:
        if isinstance(ev, dict):
            fn = ev.get("filename") or "evidence.file"
            req = ev.get("requirement") or ""
            lines.append(f"- {fn}: {req}")
        else:
            lines.append(f"- {ev}")
    if report:
        lines.append("")
        lines.append("Report sections to include:")
        for r in report:
            lines.append(f"- {r}")
    lines.extend(
        [
            "",
            "Pass tip: supervisors look for correct technique, clear evidence, honest troubleshooting, "
            "and professional documentation—not perfect first attempts.",
        ]
    )
    return "\n".join(lines).strip() + "\n"


def map_day(day: dict) -> dict:
    mode = (day.get("mode") or "Guided laboratory").strip()
    topic = (day.get("topic") or f"Day {day.get('day')}").strip()
    procedure = [rewrite_instruction(str(s)) for s in (day.get("practical_procedure") or [])]
    if not procedure:
        procedure = [
            "Read the Study Notes on this page and the Day Student Handbook.",
            "Complete the practical objective in an authorized Beyond lab only.",
            "Capture evidence and write the technical report.",
        ]
    tools = day.get("tools") or []
    tools_s = ", ".join(tools) if isinstance(tools, list) else str(tools)

    return {
        "day_number": int(day["day"]),
        "track": "artificial_intelligence",
        "title": f"{mode}: {topic}",
        "estimated_hours": float(day.get("estimated_time_hours") or 8),
        "difficulty": day.get("difficulty") or "foundation",
        "tools": tools_s,
        "objective": day.get("objective") or f"Complete {topic} safely and document the result.",
        "instructions": procedure,
        "submission": day.get("required_submission")
        or "Evidence pack, independent verification, and short technical report.",
        "marking_guide": DEFAULT_RUBRIC,
        "pass_mark": 60,
        "requires_supervisor_approval": True,
        "study_note": compose_study_note(day),
        # retain upgrade metadata for audits (ignored by importer if unused)
        "phase": day.get("phase"),
        "day_code": day.get("day_code"),
        "prerequisite": day.get("prerequisite"),
    }


def main() -> None:
    source = json.loads(SOURCE.read_text(encoding="utf-8"))
    days = source.get("days") or []
    if len(days) != 180:
        raise SystemExit(f"Expected 180 days in source, got {len(days)}")

    nums = sorted(int(d["day"]) for d in days)
    if nums != list(range(1, 181)):
        raise SystemExit("Source day numbers are not sequential 1–180")

    seed = json.loads(SEED.read_text(encoding="utf-8"))
    mapped = [map_day(d) for d in sorted(days, key=lambda x: int(x["day"]))]

    found = False
    for prog in seed["programs"]:
        if str(prog.get("code") or "").upper() == "ARTIFICIAL_INTELLIGENCE":
            found = True
            # Keep version 1.0 so updateOrCreate matches existing DB program
            prog["version"] = "1.0"
            prog["name"] = "Artificial Intelligence"
            prog["task_count"] = 180
            prog["description"] = (
                source.get("purpose")
                or "Prepare interns to frame, build, evaluate, secure, document, deploy, "
                "and defend AI systems responsibly under human oversight."
            )
            prog["tasks"] = mapped
            break
    if not found:
        raise SystemExit("ARTIFICIAL_INTELLIGENCE program not found in seed")

    # validate
    for t in mapped:
        if not (t.get("study_note") or "").strip():
            raise SystemExit(f"Empty study_note day {t['day_number']}")
        if not t.get("instructions"):
            raise SystemExit(f"Empty instructions day {t['day_number']}")

    SEED.write_text(json.dumps(seed, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Updated AI tasks in {SEED}")
    print(f"Day 1 title: {mapped[0]['title']}")
    print(f"Day 2 title: {mapped[1]['title']}")
    print(f"Day 120 title: {mapped[119]['title']}")
    print(f"Day 1 study_note chars: {len(mapped[0]['study_note'])}")
    print(f"Day 1 instruction steps: {len(mapped[0]['instructions'])}")


if __name__ == "__main__":
    main()
