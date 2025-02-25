<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Libraries\Chat;

use App\Events\ChatMessageEvent;
use App\Models\Chat\Message;
use Laravel\Octane\Facades\Octane;

/**
 * Wrapper to avoid SerializableClosure when dispatching ChatMessageEvent to task workers
 */
class MessageTask
{
    public function __construct(public Message $message)
    {
    }

    public static function dispatch(Message $message)
    {
        Octane::tasks()->dispatch([new static($message)]);
    }

    public function __invoke()
    {
        event(new ChatMessageEvent($this->message));
    }
}
