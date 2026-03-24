<?php

/*
<COPYRIGHT>

    Copyright © 2016-2026, Canyon GBS LLC. All rights reserved.

    Canyon GBS Common is licensed under the Elastic License 2.0. For more details,
    see https://github.com/canyongbs/common/blob/main/LICENSE.

    Notice:

    - You may not provide the software to third parties as a hosted or managed
      service, where the service provides users with access to any substantial set of
      the features or functionality of the software.
    - You may not move, change, disable, or circumvent the license key functionality
      in the software, and you may not remove or obscure any functionality in the
      software that is protected by the license key.
    - You may not alter, remove, or obscure any licensing, copyright, or other notices
      of the licensor in the software. Any use of the licensor’s trademarks is subject
      to applicable law.
    - Canyon GBS LLC respects the intellectual property rights of others and expects the
      same in return. Canyon GBS™ and Canyon GBS Common are registered trademarks of
      Canyon GBS LLC, and we are committed to enforcing and protecting our trademarks
      vigorously.
    - The software solution, including services, infrastructure, and code, is offered as a
      Software as a Service (SaaS) by Canyon GBS LLC.
    - Use of this software implies agreement to the license terms and conditions as stated
      in the Elastic License 2.0.

    For more information or inquiries please visit our website at
    https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/

namespace Illuminate\Database\Eloquent {

    use Illuminate\Database\Eloquent\Model;

    /**
     * @method $this archive()
     * @method $this unarchive()
     * @method $this withoutArchived()
     * @method $this onlyArchived()
     * @method $this withoutArchivedAndUnused()
     *
     * @template TModel of Model
     */
    class Builder {}
}

namespace Illuminate\Database\Eloquent\Relations {

    use Illuminate\Database\Eloquent\Collection;
    use Illuminate\Database\Eloquent\Model;

    /**
     * @method $this archive()
     * @method $this unarchive()
     * @method $this withoutArchived()
     * @method $this onlyArchived()
     * @method $this withoutArchivedAndUnused()
     *
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @extends Relation<TRelatedModel, TDeclaringModel, ?TRelatedModel>
     */
    class BelongsTo extends Relation {}

    /**
     * @method $this archive()
     * @method $this unarchive()
     * @method $this withoutArchived()
     * @method $this onlyArchived()
     * @method $this withoutArchivedAndUnused()
     *
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @extends Relation<TRelatedModel, TDeclaringModel, Collection<int, TRelatedModel>>
     */
    class BelongsToMany extends Relation {}

    /**
     * @method $this archive()
     * @method $this unarchive()
     * @method $this withoutArchived()
     * @method $this onlyArchived()
     * @method $this withoutArchivedAndUnused()
     *
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @extends Relation<TRelatedModel, TDeclaringModel, Collection<int, TRelatedModel>>
     */
    class HasMany extends Relation {}

    /**
     * @method $this archive()
     * @method $this unarchive()
     * @method $this withoutArchived()
     * @method $this onlyArchived()
     * @method $this withoutArchivedAndUnused()
     *
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @extends Relation<TRelatedModel, TDeclaringModel, ?TRelatedModel>
     */
    class HasOne extends Relation {}

    /**
     * @method $this archive()
     * @method $this unarchive()
     * @method $this withoutArchived()
     * @method $this onlyArchived()
     * @method $this withoutArchivedAndUnused()
     *
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @extends Relation<TRelatedModel, TDeclaringModel, Collection<int, TRelatedModel>>
     */
    class HasManyThrough extends Relation {}

    /**
     * @method $this archive()
     * @method $this unarchive()
     * @method $this withoutArchived()
     * @method $this onlyArchived()
     * @method $this withoutArchivedAndUnused()
     *
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @extends Relation<TRelatedModel, TDeclaringModel, ?TRelatedModel>
     */
    class HasOneThrough extends Relation {}

    /**
     * @method $this archive()
     * @method $this unarchive()
     * @method $this withoutArchived()
     * @method $this onlyArchived()
     * @method $this withoutArchivedAndUnused()
     *
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @extends Relation<TRelatedModel, TDeclaringModel, ?TRelatedModel>
     */
    class MorphOne extends Relation {}

    /**
     * @method $this archive()
     * @method $this unarchive()
     * @method $this withoutArchived()
     * @method $this onlyArchived()
     * @method $this withoutArchivedAndUnused()
     *
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @extends Relation<TRelatedModel, TDeclaringModel, Collection<int, TRelatedModel>>
     */
    class MorphMany extends Relation {}

    /**
     * @method $this archive()
     * @method $this unarchive()
     * @method $this withoutArchived()
     * @method $this onlyArchived()
     * @method $this withoutArchivedAndUnused()
     *
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @extends Relation<TRelatedModel, TDeclaringModel, Collection<int, TRelatedModel>>
     */
    class MorphToMany extends Relation {}

    /**
     * @method $this archive()
     * @method $this unarchive()
     * @method $this withoutArchived()
     * @method $this onlyArchived()
     * @method $this withoutArchivedAndUnused()
     *
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @extends Relation<TRelatedModel, TDeclaringModel, ?TRelatedModel>
     */
    class MorphTo extends Relation {}
}
