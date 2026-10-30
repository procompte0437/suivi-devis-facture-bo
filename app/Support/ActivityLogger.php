<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ActivityLogger
{
    public static function log(?User $user, string $action, ?Model $subject, string $description, ?Carbon $at = null): void
    {
        $activity = new Activity;
        $activity->user_id = $user?->id;
        $activity->action = $action;
        $activity->description = $description;

        if ($subject) {
            $activity->subject_type = $subject->getMorphClass();
            $activity->subject_id = $subject->getKey();
        }

        if ($at) {
            $activity->created_at = $at;
            $activity->updated_at = $at;
        }

        $activity->save();
    }
}
