<?php

use Carbon\CarbonImmutable;

test('a stored UTC time is shown in the display timezone', function () {
    config(['app.display_timezone' => 'Asia/Dhaka']);

    $this->blade('<x-local-time :at="$at" />', ['at' => CarbonImmutable::parse('2026-01-01 18:30:00', 'UTC')])
        ->assertSee('2 Jan 2026, 12:30 AM')
        ->assertSee('datetime="2026-01-01T18:30:00+00:00"', escape: false);
});
