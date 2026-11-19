<?php

use App\Modules\Shared\Logging\ScrubSensitiveData;
use Illuminate\Support\Facades\Log;

it('scrubs every log channel that writes somewhere', function (string $channel) {
    $processors = Log::channel($channel)->getLogger()->getProcessors();

    expect(collect($processors)->contains(fn ($p) => $p instanceof ScrubSensitiveData))->toBeTrue();
})->with(['stack', 'single', 'daily', 'stderr', 'syslog', 'errorlog']);
