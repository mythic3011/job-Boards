# Lessons Learned

## 1. Deployment and evidence should stay separate

One of the clearest lessons from this project is that deployment logic and security evidence collection should not be mixed together. The application deploy path should publish a stable target. Security scanners and public validation tools should run against that deployed target as separate evidence steps.

## 2. Runtime contracts need explicit ownership

Generated files, runtime env, secret material, and rendered config fragments all need a clear owner. When those responsibilities are explicit, debugging gets faster and verification becomes more trustworthy.

## 3. Observability is part of system design

Metrics, logs, dashboards, and access policy are not just add-ons. They shape how the system is operated, verified, and explained. Treating observability as a first-class layer made the repo much stronger as an engineering artifact.

## 4. Local convenience is not proof-grade evidence

Local bring-up paths are useful, but they do not automatically prove production-style behavior or blue-team VM constraints. Keeping that distinction visible made the project more honest and more technically defensible.

## 5. Documentation quality changes how the project is perceived

Without curation, a repo like this can look like a pile of scripts and contracts. With the right summary docs, the same repo becomes much easier to understand quickly.

## Summary

The biggest takeaway is that the strongest value of this project comes from system thinking:

- not only building an app
- but also shaping deployment
- observability
- security controls
- verification
- and evidence presentation as one coherent story
