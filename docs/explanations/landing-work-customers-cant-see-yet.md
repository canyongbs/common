# Landing Work Customers Can't See Yet

[Reviewable Slices](./reviewable-slices.md) lets a large feature merge into `main` one slice at a time, long before it is finished. That only works if one rule holds: **nothing unfinished or breaking reaches a customer until it is ready to be activated.** The rule applies to all of our work, sliced or not. This page explains the ideas we use to keep it. The step-by-step rules and the checklist for each slice are in the [`hiding-unfinished-work`](../../.ai/skills/hiding-unfinished-work/SKILL.md) skill.

A slice may include customer-facing surface: a Filament page, an action, a form field, a widget, an API field. What it may not do is let a customer reach something that is not finished, or change behaviour customers already rely on before its replacement is switched on. We do not require every slice to be invisible, and we do not require a final "switch it on" slice on every stack. We require that whatever is visible works and is finished, and that whatever is not finished is unreachable. The ticket closes when the feature is available to customers, not when the first slice merges.

The rule is about the code, not the paperwork. Reviewers check it in the diff, and pull request descriptions do not need to explain how a slice follows it.

## Feature flags now do two jobs

Every gate in our applications reads a [Laravel Pennant](https://laravel.com/docs/pennant) feature flag: a class in `app/Features` that extends `App\Support\AbstractFeatureFlag` (or `LandlordAbstractFeatureFlag` for landlord-wide flags), created with `make:ff`. The mechanics are in the [`managing-feature-flags`](../../.ai/skills/managing-feature-flags/SKILL.md) skill.

Until now a flag has had one job: **protecting zero-downtime deploys**. Our code is released before its migrations finish running, tenant by tenant, so any code that depends on a schema or data change is gated behind a flag that the migration itself activates. That job does not change.

Slicing gives flags a second job: acting as a **release toggle**. A feature can now land over several pull requests behind a flag that stays **inactive** while the work is unfinished, then be switched on by a migration in the final slice. Mechanically nothing is new: the flag is still created with `make:ff`, still activated by a migration, and still removed by a cleanup pull request afterwards. What is new is that the activation may arrive several pull requests, and possibly several releases, after the flag was created. Two small additions support this:

- A cleanup task records whether it is **ready** to be actioned (see [Cleanup Tasks](./cleanup-tasks.md)), so a flag that later slices still depend on is not removed early. A feature may use more than one flag; its flags usually share one cleanup task, marked ready when the feature is finished.
- `ff:activate`, `ff:deactivate` and `ff:list` switch flags on and off by hand, so developers and reviewers can try unfinished work locally.

Our flags are stored per database. In Advising App and Aiding App (and Concourse once it launches) each tenant has its own database, so a flag's state is per tenant, and an activation migration activates it for every tenant as the migrations run. We do not switch features on for only some tenants.

## Three ways to hide unfinished work

Choose per slice, not per feature; a single stack often uses all three.

**Not wired yet.** Build the models, jobs, services and even the Filament components, but do not register the resource, add the navigation item, mount the route or expose the action until a later slice. Customers cannot reach code that nothing points at. This is Martin Fowler's [Keystone Interface](https://www.martinfowler.com/bliki/KeystoneInterface.html): the back end lands over several releases and the entry point is added last, like the keystone in an arch. It costs nothing and needs no cleanup, which makes it the right choice for background work, internal services, and features with a single entry point.

**Behind a feature flag.** Build and wire the customer-facing surface, but gate every way in on an inactive flag. This is the right choice when the user interface is part of understanding the concept and should be reviewed with its logic, when there are several entry points, or when the feature changes many places at once. It costs a cleanup pull request later. Fowler's [Feature Toggles](https://martinfowler.com/articles/feature-toggles.html) is the long version; the short version is that release toggles that outlive their release become debt, which is why every flag comes with a cleanup task.

**Branch by abstraction, and expand and contract.** When a slice changes existing behaviour rather than adding new behaviour, neither of the above hides it: the old path is live. Introduce an abstraction over the old implementation, route callers through it, build the new implementation behind it, switch, then remove the old path in a later slice ([Fowler: Branch by Abstraction](https://martinfowler.com/bliki/BranchByAbstraction.html)). For schema the same idea is expand and contract: add the new column or table, write to both, move the readers, stop writing the old one, and drop it in a later release.

## The keystone

The **keystone** is whatever makes the feature available to customers. It takes one of two shapes:

- **Wire the entry point.** Earlier slices left the surface unwired; the keystone registers the resource, adds the navigation item, mounts the route or exposes the action. It is usually tens of lines.
- **Activate the flag.** Earlier slices shipped their surface behind an inactive flag; the keystone is a migration that activates it, and it marks the flag's cleanup task ready.

The keystone normally goes in the slice that finishes the feature, usually the last concept slice, so the review that completes the feature also switches it on. That slice is where the whole feature is run end to end before customers get it. We avoid pull requests that only flip a switch: they cost a review and make a finished feature wait for it. A keystone gets its own last slice only when activation has to wait for something outside the stack, such as a product go-ahead or another ticket's work, or when the wiring or activation is complex enough to deserve its own review.

A stack of background work with no customer-facing change needs no keystone, and neither does work that sits behind another feature's flag, which that feature's own stack will activate.

## Holding is a separate decision

A stack can _land as approved_ or _hold and land together_ (see [Reviewable Slices](./reviewable-slices.md#landing-modes)). Landing as approved is the natural partner of flags and unwired slices: since nothing merged is reachable, there is usually no reason to hold. Hold only for timing, when product has not decided or a set of slices should be signed off as a whole. A hold never makes an unsafe slice safe: every slice must leave `main` working by itself, because a held stack is not guaranteed to land in one piece. Even in a held stack the flag stays inactive until the keystone. Holding is about **when code reaches `main`**; the flag is about **when customers reach the code**. Keep the two decisions separate.

## References

| Source                                                                                                                 | What it supports                                                                  |
| ---------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| [Laravel Pennant](https://laravel.com/docs/pennant)                                                                    | Class-based features, stores and activation.                                      |
| [Martin Fowler: Keystone Interface](https://www.martinfowler.com/bliki/KeystoneInterface.html)                         | Land the back end first and add the entry point last.                             |
| [Martin Fowler: Feature Toggles](https://martinfowler.com/articles/feature-toggles.html)                               | Release toggles, where to put toggle points, and why toggles must be short-lived. |
| [Martin Fowler: Branch by Abstraction](https://martinfowler.com/bliki/BranchByAbstraction.html)                        | Changing existing behaviour incrementally without a long-lived branch.            |
| [Martin Fowler: Patterns for Managing Source Code Branches](https://martinfowler.com/articles/branching-patterns.html) | Why landing early matters, and the hiding patterns side by side.                  |
| [Google Engineering Practices: Small CLs](https://google.github.io/eng-practices/review/developer/small-cls.html)      | Don't break the build: the system keeps working after each change lands.          |
