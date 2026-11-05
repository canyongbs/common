---
name: hiding-unfinished-work
description: 'Use when a pull request adds or changes customer-facing surface (a Filament resource, page, action, form field, widget, navigation item, route, API field, notification or job trigger) for a feature that is not finished yet; when a feature is being delivered over several pull requests or a stack; when deciding whether to leave something unwired, gate it behind a feature flag, or use branch by abstraction; when one feature flag spans several pull requests; when writing or reviewing the keystone slice that wires up or activates a feature; or when testing a flag that is not activated yet. Covers choosing a hiding mechanism, where to gate, flags that span a stack (when to activate, cleanup task readiness, testing before activation), the keystone slice, and the per-slice checklist. Do not use for: the mechanics of flag classes, `make:ff` or activation migrations (use `managing-feature-flags`), cleanup task files (use `managing-cleanup-tasks`), or planning slices (use `planning-reviewable-slices`).'
user-invocable: false
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Hiding Unfinished Work

**Nothing unfinished may reach a customer.** A slice may add customer-facing surface as long as what customers can reach is finished and what is not finished is unreachable. The reasoning is in [Landing Work Customers Can't See Yet](https://github.com/canyongbs/common/blob/main/docs/explanations/landing-work-customers-cant-see-yet.md).

## 1. Choose how to hide it

| The slice…                                                                                | Use                                         |
| ----------------------------------------------------------------------------------------- | ------------------------------------------- |
| Adds something new with a single entry point, or adds background work only                | **Not wired yet**                           |
| Adds something new with several entry points, or its UI should be reviewed with its logic | **Behind a feature flag**                   |
| Changes behaviour customers already use                                                   | **Branch by abstraction / expand-contract** |
| Adds nothing a customer could reach                                                       | Nothing to hide; say so in the description  |

**Not wired yet:** build the code but do not register the Filament resource or page, add the navigation item, mount the route, or add the action to its page or table. A later slice (the keystone, or the concept slice that needs it) wires it in.

**Behind a feature flag:** gate **every** way in, in the same slice that adds it. Typical gate points:

- Filament resources and pages: `canAccess()` and `shouldRegisterNavigation()`.
- Actions, form fields, columns and widgets: `->visible(fn (): bool => SomeFeature::active())`.
- Routes and controllers: middleware or an early `abort_unless(SomeFeature::active(), 404)`.
- Policies, observers, listeners and scheduled work: an early return.
- Jobs: check before dispatching, and return early in `handle()` if the flag can change while the job is queued.
- API resources and fields: omit them while the flag is inactive.
- Blade: `@if (SomeFeature::active())` or Pennant's `@feature` directive.

**Branch by abstraction / expand-contract:** introduce an abstraction over the current behaviour (no behaviour change, its own slice), build the new implementation behind it gated by a flag, switch, then remove the old path in a later slice. For schema: add the new column or table, write to both, move readers, stop writing the old, and drop the old in a later release. Never rename or drop in the same deploy that introduces the replacement (see the `zero-downtime` guideline).

## 2. Flags that span a stack

- **Introduce the flag in the first slice that gates anything on it**, with `make:ff`. Later slices reuse it. Add a second flag only when parts of the feature must switch on independently, two concepts share no customer-facing surface, or an earlier gate must be retired while a later one stays; each extra flag is another activation and another cleanup.
- **Every slice gates its own edges.** A reviewer must be able to confirm from the slice alone that nothing it adds is reachable while the flag is inactive. "The flag check is in the next slice" fails review.
- **Do not activate the flag while the stack is open.** The migration that adds the feature's schema does **not** activate its release flag; the keystone slice's migration does. This is the one exception to "activate the flag in the migration that makes the change". Code that depends on the new schema is safe, because it is behind the same inactive flag. If a slice changes schema that **already-live** code relies on, that change still needs its own zero-downtime flag, activated in its own migration, as usual.
- **File the cleanup task with the flag**, in the slice that introduces it, with `ready: false`. Add to it in later slices if they create more cleanup. The keystone marks it `ready: true` (see `managing-cleanup-tasks`).
- **Interspersed cleanup is fine.** A slice in the middle of a stack may retire an earlier gate. It should contain only the removal and what the removal requires.

## 3. Test both states

- **Inactive:** prove the new surface is unreachable. Call `SomeFeature::deactivate()` at the start of these tests, even before the keystone exists, so they keep working once the keystone's migration activates the flag in the test database.
- **Active:** prove the feature works. **Until the keystone exists, call `SomeFeature::activate()` in these tests.** This is the exception to the "never activate a flag in a test" rule in `managing-feature-flags`: there is no activation migration yet to do it for you.
- **The keystone removes those `activate()` calls**, so the activation migration is what the tests rely on.
- **Locally:** turn the flag on with `ff:activate SomeFeature` to try or review the work, and `ff:deactivate SomeFeature` to turn it off (see `managing-feature-flags`).

## 4. The keystone slice

The keystone makes the feature available to customers, and is the last slice. It is one of:

- **Wiring:** register the resource or page, add the navigation item, mount the route, or add the action.
- **Activation:** a migration that activates the flag, written as described in `managing-feature-flags`.

In the same slice, mark the cleanup task `ready: true` and remove the tests' manual `activate()` calls. A keystone carries no new behaviour. Before it is approved, the feature must have been run end to end from the customer's entry point with the flag on. A stack with nothing customer-facing, or fully gated by a flag that is already active, needs no keystone; say so in the plan.

## 5. Checklist for each slice

Authors run this before marking a pull request ready; reviewers run it before approving.

- [ ] Every way in that this slice adds (page, action, route, API field, notification, job trigger) is unwired or gated on the feature's flag, **in this slice**.
- [ ] The flag is still inactive after this slice deploys; no migration here activates it unless this is the keystone.
- [ ] If this slice introduces the flag, it includes a cleanup task with `ready: false`.
- [ ] If this slice adds more cleanup to an existing flag, the cleanup task is updated.
- [ ] Tests cover the gated surface with the flag inactive (unreachable) and active (works).
- [ ] Nothing customers already use changes without an abstraction or expand step that keeps the old path working.
- [ ] If this is the keystone: it only wires or activates, the cleanup task is now `ready: true`, manual `activate()` calls are gone from tests, and the feature has been run end to end with the flag on.
- [ ] The description says how this slice keeps unfinished work from customers.

---

Related: `managing-feature-flags`, `managing-cleanup-tasks`, `planning-reviewable-slices`, `reviewing-reviewable-slices`.
