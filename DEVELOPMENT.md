# Development Guide — CompletionCertificate

> Last reviewed: 2026-10-19
>
> This file is the repository-local development/status handoff for CompletionCertificate. The central Dolibarr engineering rules live in `vanyolai/dolibarr/docs/VANYOLAI_DEVELOPMENT_GUIDE.md`.

## Repository role

```text
canonical source: vanyolai/dolibarr-completioncertificate
source branch:    main
consumer:         vanyolai/dolibarr
consumer prefix:  htdocs/custom/completioncertificate
subtree mode:     UNSQUASHED
```

Normal development direction is repository -> tested `main` -> Dolibarr subtree -> integrated acceptance -> production.

Do not develop a second independent copy in the Dolibarr consumer.

## Current implementation state

Current module version: **0.5.6**.

Implemented capabilities include:

- completion certificates created from validated customer orders;
- dedicated order tab and native linked-object integration;
- line-based certified quantities or progress-percentage mode;
- protection against over-certification / progress above 100%;
- stored net certified amounts;
- draft, validated and canceled lifecycle;
- controlled reopening/cancellation behavior;
- native PDF generation and document block;
- native Dolibarr email composition;
- automatic attachment of the generated completion-certificate PDF to the email form;
- `formmail` hook enablement required for attachment integration;
- Hungarian and English translations.

Recent 0.5.6 work:
- `20a8d442fc77` — automatic PDF attachment to emails;
- `2e52035db212` — enable `formmail` hook and bump version to 0.5.6.

## Architectural rules

- Use the core customer order as the commercial source of truth.
- Use native related-object links rather than a parallel relation mechanism.
- Keep PDF generation inside the normal Dolibarr document-model lifecycle.
- Use native formmail/substitution/document behavior instead of a custom mailer.
- Keep business calculations in reusable object/service code, not page rendering.
- User-visible strings must use module/core language keys in both HU and EN.
- New UI must match normal Dolibarr card/tab/document conventions.

## Current acceptance focus

The 0.5.6 email path must be verified as a complete native flow:

1. generate/regenerate the current official certificate PDF;
2. open the standard Dolibarr email form;
3. verify the correct generated PDF is attached automatically;
4. cancel the email form without triggering attachment-validation side effects;
5. send and verify the delivered attachment;
6. check permissions and behavior for draft/validated/canceled certificates.

PDF regression checks must include:
- draft/status watermark remains visually above table/header layers;
- date and place rendering is not left unintentionally blank;
- certified net amount displays the order currency;
- translated status labels are rendered through `$langs->trans()`;
- page breaks, line wrapping, footer/header and linked-object blocks remain native-looking.

## Roadmap

### Milestone 1 — 0.5.6 integrated acceptance
- verify automatic PDF attachment end to end;
- verify cancel/back behavior in formmail;
- regression-test generation/re-generation and lifecycle statuses;
- confirm HU/EN translation coverage.

### Milestone 2 — PDF polish and deterministic metadata
- finish watermark/layer-order acceptance;
- verify place/date defaults and explicit overrides;
- verify amount/currency presentation;
- remove any remaining hard-coded UI strings.

### Milestone 3 — Email/template quality
- verify reusable Dolibarr email template behavior for completion certificates;
- keep substitutions in the standard substitution mechanism;
- ensure the generated official PDF is the attachment source of truth.

### Milestone 4 — release hardening
- review permissions and multientity behavior;
- add focused regression tests for lifecycle and totals;
- update README/version/changelog together;
- only then mark the milestone stable for the Dolibarr consumer.

## Subtree update

This module is intentionally **unsquashed**:

```bash
git subtree pull \
  --prefix=htdocs/custom/completioncertificate \
  https://github.com/vanyolai/dolibarr-completioncertificate.git \
  main \
  -m "Update CompletionCertificate subtree"
```

Do not add `--squash`.

## Definition of done for a feature

A CompletionCertificate change is not complete until:
- source repo code is committed and reviewed;
- PHP syntax and focused behavior checks pass;
- both HU/EN language files are updated where needed;
- PDF/email behavior is tested in an integrated Dolibarr 23 environment;
- this file is updated if project status or roadmap changes;
- the accepted source is pulled into the Dolibarr consumer with the exact subtree mode above.
