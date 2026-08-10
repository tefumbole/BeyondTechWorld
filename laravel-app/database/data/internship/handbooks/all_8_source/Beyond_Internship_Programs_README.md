# Beyond Enterprise 180-Day Internship Program Files

Each corrected individual program package contains:

- `Beyond_<Program>_Internship_180_Day_Program.json` — complete machine-readable program input.
- `Beyond_<Program>_Internship_180_Day_Program.md` — readable seven-section notes for all 180 days.
- `Beyond_<Program>_Internship_180_Day_Index.csv` — plain tabular index.
- `Beyond_<Program>_Internship_180_Day_Workbook.xlsx` — formatted Excel workbook with Overview, Phases, and 180 Days sheets.
- `build_multi_180_day_internships.py` — source code that generates the eight program JSON, Markdown, CSV, and base packages.
- `build_internship_workbooks.mjs` — source code that generates the individual and master Excel workbooks.

## Recommended use

Use the JSON file for direct application import. Use the Excel workbook for management, scheduling, supervisor review, status tracking, and notes. The Markdown file is the readable curriculum reference.

The `Status` column in the Excel workbook accepts: Not Started, In Progress, Completed, Needs Review, or Blocked.

## Regeneration

The Python and JavaScript builders are included for auditability and future updates. They require the approved Codex/ChatGPT Work runtime dependencies used to produce the files. If adapting them to another environment, review dependencies and output paths before execution.

Never place real passwords, API keys, tokens, personal/client information, production identifiers, unsafe equipment details, or unauthorized evidence in the program files.
