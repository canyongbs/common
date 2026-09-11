<?php

/*
<COPYRIGHT>

    Copyright 2026 Canyon GBS LLC. All rights reserved.

</COPYRIGHT>
*/

describe('helpers declared later', function () {
    it('uses a helper declared after the registration', function () {
        expect(laterHelper())->toBe('later');
    });
});

function laterHelper(): string
{
    return 'later';
}
-----
<?php

/*
<COPYRIGHT>

    Copyright 2026 Canyon GBS LLC. All rights reserved.

</COPYRIGHT>
*/

$laterHelper = function (): string {
    return 'later';
};

describe('helpers declared later', function () use ($laterHelper) {
    it('uses a helper declared after the registration', function () use ($laterHelper) {
        expect($laterHelper())->toBe('later');
    });
});
