---
name: scenarisation-site
description: Build, maintain, review, and refine the Scenarisation website and its interface. Use for changes to the site’s PHP, HTML, CSS, JavaScript, administration and account pages, responsive behavior, accessibility, dark theme, or visual consistency. Do not use for creating pedagogical designs with the `scenarisation` CLI.
---

# Scenarisation Site

Maintain the Scenarisation web application while preserving its established architecture and visual direction.

## Project Conventions

- The application is server-rendered PHP with vanilla JavaScript and CSS; it has no compilation step.
- Reuse the shared navigation, footer, theme, account, and page-shell primitives before introducing page-specific variants.
- Keep database work compatible with both SQLite and MySQL unless the surrounding code explicitly targets one driver.
- Preserve the existing French/English interface mechanism when changing user-facing copy.
- When a linked CSS or JavaScript file uses a version query parameter, update that parameter after changing the asset so browsers receive the new version.

## Interface Visual Direction

Keep the interface crisp, restrained, predominantly neutral, and spacious.

- Never use blue-tinted or other colored background fills for decorative surfaces, including cards, badges, initials or avatars, icon containers, and empty states. Keep these backgrounds transparent, white, or neutral gray. Also avoid diffuse blue shadows, colored glows, ambient halos, and pulsing rings.
- Use white or neutral surfaces with thin, sharp borders. Add a restrained neutral shadow only when elevation is necessary for comprehension.
- Reserve blue for meaningful foreground accents such as icons, links, selected states, data visualization, and solid accessible focus outlines—not atmospheric decoration.
- Prefer flat, precise states over blurred or luminous effects in both light and dark themes.
- Avoid nested framed surfaces. When content already sits inside a bordered or rounded container, structure its subsections with spacing, headings, neutral background changes, or simple dividers instead of additional bordered and rounded containers. If inner cards are necessary, keep their parent visually open.
- Use generous, deliberate whitespace between major sections. The gap between separate sections must be visibly larger than spacing within a section; do not compress the layout merely to fit more content in one viewport.

## Verification

- Run `php -l` on every changed PHP file and run `git diff --check`.
- For layout changes, inspect the rendered page at desktop and mobile widths and verify the dark theme when the affected surface supports it.
- Check that keyboard interaction, focus treatment, labels, tab state, and responsive overflow remain usable when relevant.
