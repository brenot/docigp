<?php

namespace App\Listeners;

use App\Events\EntriesChanged;
use App\Events\EntryDeleted;

class OnEntryDeleted extends Listener
{
    /**
     * Handle the event.
     *
     * @param  EntryDeleted  $event
     * @return void
     */
    public function handle(EntryDeleted $event)
    {
        event(new EntriesChanged($event->entryId));
    }
}
