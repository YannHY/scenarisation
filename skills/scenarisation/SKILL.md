---
name: scenarisation
description: Create, validate, and prepare publication for pedagogical Scenarisation designs using the `scenarisation` CLI. Use for lessons, sequences, learning designs, instructional scenarios, Bloom outcomes, learning moments, digital competencies, or publishable `design.json` files. Do not use for development of the Scenarisation website itself.
---

# Scenarisation

## Goal

Help an educator create a complete, structured, importable Scenarisation `design.json` with the `scenarisation` CLI, not by hand-editing JSON. Ask the pedagogical questions first, make reasonable assumptions when safe, generate the design with CLI commands, validate it, then explain how to publish it.

## CLI Setup

Select the CLI by capability, not merely by whether a `scenarisation` command exists. The selected CLI must support the school-system catalog, the two `init` options, and explicit pedagogical choices for every activity.

First, when working inside a Scenarisation repository that contains `./bin/scenarisation`, probe that repository CLI:

```bash
./bin/scenarisation --version
./bin/scenarisation list school-systems
./bin/scenarisation list activity-options
./bin/scenarisation init --help
./bin/scenarisation add-activity --help
```

Use it as `SCENARISATION=./bin/scenarisation` when both catalog commands succeed, the `init` help includes `--school-system` and `--school-level`, and the activity help includes `--group`, `--teaching`, `--pacing`, `--mode`, `--evaluation`, and `--aias`. Prefer this repository CLI over a global installation because it matches the current project.

When no compatible repository CLI is available, probe the global command in the same way:

```bash
scenarisation --version
scenarisation list school-systems
scenarisation list activity-options
scenarisation init --help
scenarisation add-activity --help
```

Use it as `SCENARISATION=scenarisation` only when the same school and activity capability checks pass. A successful `scenarisation --help` alone is not sufficient.

If a global CLI exists but fails this capability check, do not use it and do not fall back to a reduced design format that omits `schoolSystem` or `schoolLevel`. Tell the user only that Scenarisation CLI must also be updated for compatibility, then ask for permission to run `scenarisation upgrade`. Do not expose the detailed capability-check output unless the update fails or the user asks for it. After explicit permission, run the upgrade and repeat the capability check. If it succeeds, use `SCENARISATION=scenarisation` and simply confirm that the skill and CLI are ready.

Do not modify the global CLI without explicit permission. If the user declines the upgrade, or if it cannot be completed, use a compatible local copy instead.

If `.tools/bin/scenarisation` already exists, apply the same capability check to it. Use it when compatible. Otherwise, download a current local copy:

```bash
mkdir -p .tools/bin
curl -fsSL https://raw.githubusercontent.com/YannHY/scenarisation/main/bin/scenarisation -o .tools/bin/scenarisation
chmod +x .tools/bin/scenarisation
./.tools/bin/scenarisation --version
./.tools/bin/scenarisation list school-systems
./.tools/bin/scenarisation list activity-options
./.tools/bin/scenarisation init --help
./.tools/bin/scenarisation add-activity --help
```

If `raw.githubusercontent.com` is blocked, use the environment’s web fetch/browser capability to retrieve:

```text
https://github.com/YannHY/scenarisation/blob/main/bin/scenarisation
```

Write the retrieved file to `.tools/bin/scenarisation`, make it executable, and use `./.tools/bin/scenarisation` for all later commands.

Once a compatible `.tools/bin/scenarisation` has been created, do not depend on the network again. If none of the candidates passes the capability check and a current local copy cannot be obtained, stop with an actionable explanation rather than generating an incomplete design.

## Ask Before Creating

Ask concise questions in French unless the user asks for English. Do not overload the user at the start.

Essential questions:

- subject or theme
- school system or classification, then the corresponding level, and target learners
- total duration
- activity mode of delivery: classroom-based, location-based, online, blended, or other
- group size
- teaching objectives: what the teacher wants to work on, transmit, or train
- expected learning outcomes: what learners should be able to do
- constraints: time, tools, assessment, institution, classroom setup
- desired level of detail

Complementary questions to ask only when useful:

- Bloom level for each outcome if known
- digital competencies to mobilize, if relevant
- imposed supports, works, resources, or tools

Distinguish teaching objectives from learning outcomes. If the user gives only teaching objectives, transform them into observable learning outcomes with action verbs and Bloom levels.

If information is missing, make reasonable assumptions instead of blocking, unless the assumption would be risky.

School-system handling:

- Treat Belgium as three distinct systems: French, Flemish, and German-speaking Communities.
- Treat England, Wales, Scotland, and Northern Ireland as distinct systems.
- Treat the European Schools as a transnational system and ISCED 2011 as an international classification, not as countries.
- If the user names only “Belgium” or “United Kingdom”, ask which community or nation unless the context makes it unambiguous.
- Never invent a level identifier. Use the CLI catalog commands below.

Duration handling:

- If duration is given in days, ask or explicitly propose a per-session duration before generating the full design.
- By default, for middle school/college, interpret `1 day` as `1 session of 55 minutes`, unless the user says otherwise.
- State the assumption clearly.

Before running the complete creation commands, briefly restate:

- subject
- school system or classification, level, and target learners
- total duration converted to minutes
- planned number of moments
- teaching objectives
- proposed Bloom outcomes
- main digital competencies, if any

## Create the Design

Use CLI commands to create and enrich `design.json`. Before creating many activities, inspect the available commands and accepted values:

```bash
$SCENARISATION --help
$SCENARISATION init --help
$SCENARISATION add-moment --help
$SCENARISATION add-activity --help
$SCENARISATION outcome --help
$SCENARISATION list types
$SCENARISATION list bloom
$SCENARISATION list competencies
$SCENARISATION list activity-options
$SCENARISATION list school-systems
$SCENARISATION list school-levels --system france
```

Create the file:

```bash
$SCENARISATION init design.json --title "TITLE" --lang fr --duration 90 --mode onsite --school-system france --school-level quatrieme --group-size 24 --description "DESCRIPTION" --objectives "TEACHING OBJECTIVES"
```

Add each moment:

```bash
$SCENARISATION add-moment design.json --title "MOMENT TITLE" --objectives "MOMENT OBJECTIVES" --intentions "PEDAGOGICAL CHOICES"
```

Add each activity:

```bash
$SCENARISATION add-activity design.json --moment 1 --type investigate --duration 15 --group subgroups --teaching guided --pacing sync --mode onsite --evaluation formative --aias 3 --competencies A1,P6 --description "ACTIVITY DESCRIPTION" --instructions "INSTRUCTIONS FOR STUDENTS"
```

## Make Pedagogical Choices for Every Activity

For every activity, explicitly determine and pass all six pedagogical parameters: `group`, `teaching`, `pacing`, `mode`, `evaluation`, and `aias`. Never rely on CLI defaults. Treat the six values as one coherent pedagogical configuration rather than independent metadata.

Base the choices on the activity objective, learner autonomy, required interactions, delivery constraints, accessibility and differentiation needs, expected evidence of learning, and the intended role of AI:

- `group`: use `whole` for shared instruction or synthesis, `subgroups` for interaction or co-production, and `individual` for personal appropriation, practice, or individual evidence
- `teaching`: use `directed` for explicit teaching, demonstration, or tight safety constraints; `guided` for scaffolded inquiry; `supported` for learner-led work with available teacher help; and `independent` only when learners can genuinely proceed autonomously
- `pacing`: use `sync` when shared interaction, immediate feedback, or coordination matters; use `async` when learners need flexible, self-paced work
- `mode`: align the activity with its real setting; use `onsite` for the classroom, `location-based` for another physical site, `online` for remote work, `blended` for a genuine combination, and `other` only when none fits
- `evaluation`: use `diagnostic` before learning to establish prerequisites, `formative` during learning when evidence informs feedback or regulation, `summative` at the end of a learning phase, `certificative` only for formally validated high-stakes evidence, and `none` only when no evidence is collected or judged
- `aias`: choose a level for every activity using AIAS 2.1: `1` means no AI; `2` allows AI for exploration, research, or planning while production remains autonomous; `3` makes AI a collaborator whose outputs the learner evaluates, modifies, and integrates; `4` fully integrates AI under the learner's critical direction and disciplinary expertise; `5` has the learner explore and co-design creative uses of AI. Use `not-applicable` only when the AIAS framework genuinely does not apply. Do not use `undecided` in a generated design.

Do not vary values merely to create visual diversity. Reuse a value when the pedagogical situation is unchanged and vary it when the learning progression justifies the change. Record a concise rationale in the activity `--notes` when the assessment, AIAS level, or configuration is not self-evident, and summarize the progression-level rationale in the moment `--intentions`.

Use only CLI-controlled values for controlled fields. Safe values:

- `school-system`: use an id returned by `list school-systems`
- `school-level`: use an id returned by `list school-levels --system SYSTEM_ID`
- `type`: `read`, `investigate`, `practice`, `produce`, `discuss`, `collaborate`
- `group`: `whole`, `subgroups`, `individual`
- `teaching`: `directed`, `guided`, `supported`, `independent`
- `pacing`: `sync`, `async`
- `mode`: `onsite`, `location-based`, `online`, `blended`, `other`
- `evaluation`: `none`, `diagnostic`, `formative`, `summative`, `certificative`
- `aias`: `1`, `2`, `3`, `4`, `5`, or `not-applicable`; never use `undecided` in a generated design
- `competencies`: short codes such as `A1`, `P6`, `C14`, comma-separated

Never put long natural-language text in controlled fields such as `--school-system`, `--school-level`, `--group`, `--teaching`, `--evaluation`, `--type`, or `--pacing`.

Use `--description` for the pedagogical description of the activity and `--instructions` for directions addressed directly to students. Put criteria, supports, teacher role, differentiation details, and other pedagogical detail in `--notes`, `--objectives`, or `--intentions` according to their scope.

Add Bloom outcomes:

```bash
$SCENARISATION outcome design.json --bloom understand --verb "Expliquer" --text "Expliquer le rôle d’un élément clé du thème étudié."
```

Allowed Bloom levels:

- `remember`
- `understand`
- `apply`
- `analyze`
- `evaluate`
- `create`

Recommended workflow:

1. Create `design.json` with `init`.
2. Add Bloom outcomes with `outcome`.
3. Add one moment and one complete activity to test the accepted CLI values.
4. If the command succeeds, add the remaining moments and activities.
5. If a command fails, explain why, correct the value, and retry.
6. Validate with `validate --strict-pedagogy`.
7. Run `prompt design.json`.

The design should include:

- clearly titled moments
- explicit pedagogical intentions
- varied activities
- realistic durations
- appropriate group modes
- an explicit, coherent teaching mode, pacing, delivery mode, evaluation mode, and AIAS choice for every activity
- diagnostic, formative, or summative assessment modes where relevant
- Bloom outcomes connected to the activities
- digital competencies where relevant
- descriptions detailed enough for a teacher to use

If the user asks to integrate digital work, propose pedagogically useful uses such as guided research, source checking, collaborative mapping, digital writing, file organization, revision, correction, controlled production, or controlled sharing.

## Validate and Report

Always validate:

```bash
$SCENARISATION validate design.json --strict-pedagogy
```

If validation fails, fix the design with CLI commands and validate again. Avoid manual JSON edits unless the CLI is genuinely impossible to use.

Generate the handoff prompt when useful:

```bash
$SCENARISATION prompt design.json
```

At the end, report:

- where `design.json` is
- CLI validation result
- school system or classification and level
- number of moments
- number of activities
- teaching objectives used
- Bloom outcomes created
- digital competencies used
- duration distribution
- distribution of group, teaching, pacing, delivery, evaluation, and AIAS choices
- assumptions made
- main commands executed

## Publication Guidance

Do not publish from a sandbox unless the user explicitly provides a CLI token and asks you to publish.

For normal use, tell the user to publish from their own Mac/terminal:

```bash
scenarisation validate ~/Desktop/design.json
scenarisation publish ~/Desktop/design.json
```

If the user explicitly gives a token and asks you to publish:

```bash
$SCENARISATION login
$SCENARISATION publish design.json
```

Never invent, request publicly, or print a token unless the user deliberately shares it for that session.
