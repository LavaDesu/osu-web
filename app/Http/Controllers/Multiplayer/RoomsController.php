<?php

/**
 *    Copyright 2015-2018 ppy Pty. Ltd.
 *
 *    This file is part of osu!web. osu!web is distributed with the hope of
 *    attracting more community contributions to the core ecosystem of osu!.
 *
 *    osu!web is free software: you can redistribute it and/or modify
 *    it under the terms of the Affero GNU General Public License version 3
 *    as published by the Free Software Foundation.
 *
 *    osu!web is distributed WITHOUT ANY WARRANTY; without even the implied
 *    warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *    See the GNU Affero General Public License for more details.
 *
 *    You should have received a copy of the GNU Affero General Public License
 *    along with osu!web.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace App\Http\Controllers\Multiplayer;

use App\Http\Controllers\Controller as BaseController;
use App\Libraries\Multiplayer\Mod;
use App\Models\Beatmap;
use App\Models\Multiplayer\PlaylistItem;
use App\Models\Multiplayer\Room;
use Auth;
use Carbon\Carbon;
use DB;
use Request;

class RoomsController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $rooms = Room::active()
            ->orderBy('id', 'DESC');

        if (Request::has('owned')) {
            $rooms->startedBy(Auth::user());
        }

        if (Request::has('participated')) {
            // TODO: this
        }

        return json_collection(
            $rooms
                ->with('host')
                ->with('playlist.beatmap.beatmapset')
                ->get(),
            'Multiplayer\Room',
            [
                'host',
                'playlist.beatmap.beatmapset'
            ]
        );
    }

    public function leaderboard($roomId)
    {
        return Room::findOrFail($roomId)->topScores();
    }

    public function show($room_id)
    {
        return json_item(
            Room::where('id', $room_id)
                ->with('host')
                ->with('playlist.beatmap.beatmapset')
                ->firstOrFail(),
            'Multiplayer\Room',
            [
                'host',
                'playlist.beatmap.beatmapset',
            ]
        );
    }

    public function store()
    {
        $currentUser = Auth::user();
        $hasActiveRooms = Room::active()->startedBy($currentUser)->exists();
        if ($hasActiveRooms) {
            abort(403, 'number of simultaneously active rooms reached');
        }

        foreach (['name', 'playlist'] as $field) {
            if (!Request::has($field) || !present(Request::input($field))) {
                abort(422, "field '{$field}' required");
            }

            $$field = Request::input($field);
        }

        if (!is_array($playlist) || empty($playlist)) {
            abort(422, "field 'playlist' cannot be empty");
        } else {
            $playlistBeatmaps = array_map(function ($item) {
                if (isset($item['beatmap_id'])) {
                    return $item['beatmap_id'];
                } else {
                    abort(422, "playlist item missing field 'beatmap_id'");
                }
            }, $playlist);

            $beatmaps = Beatmap::whereIn('beatmap_id', $playlistBeatmaps)->get();

            $playlistItems = [];
            foreach ($playlist as $item) {
                foreach (['beatmap_id', 'ruleset_id'] as $field) {
                    if (!isset($item[$field]) || !present($item[$field])) {
                        abort(422, "playlist item missing field '{$field}'");
                    }
                }

                if (!$beatmaps->where('beatmap_id', $item['beatmap_id'])->first()) {
                    abort(422, "beatmap not found: {$item['beatmap_id']}");
                }

                $allowedMods = Mod::parseInputArray(
                    isset($item['allowed_mods']) ? $item['allowed_mods'] : [],
                    $item['ruleset_id']
                );
                $requiredMods = Mod::parseInputArray(
                    isset($item['required_mods']) ? $item['required_mods'] : [],
                    $item['ruleset_id']
                );

                $playlistItems[] = [
                    'beatmapId' => $item['beatmap_id'],
                    'rulesetId' => $item['ruleset_id'],
                    'allowedMods' => $allowedMods,
                    'requiredMods' => $requiredMods,
                ];
            }
        }

        if (Request::has('starts_at')) {
            $startTime = Carbon::parse(Request::input('starts_at'));
        } else {
            $startTime = Carbon::now();
        }

        if (Request::has('ends_at')) {
            $endTime = Carbon::parse(Request::input('ends_at'));

            if ($endTime->isBefore($startTime)) {
                abort(422, "'ends_at' cannot be before 'starts_at'");
            }
        } elseif (Request::has('duration')) {
            $endTime = $startTime->copy()->addMinutes(Request::input('duration'));
        } else {
            abort(422, "field 'duration' or 'ends_at' required");
        }

        if (Request::has('max_attempts')) {
            $maxAttempts = get_int(Request::input('max_attempts'));
            if ($maxAttempts < 1 || $maxAttempts > 32) {
                abort(422, "field 'max_attempts' must be between 1 and 32");
            }
        }

        $roomOptions = [
            'name' => $name,
            'user_id' => $currentUser->user_id,
            'starts_at' => $startTime,
            'ends_at' => $endTime,
            'max_attempts' => isset($maxAttempts) ? presence($maxAttempts) : null,
        ];

        $room = DB::transaction(function () use ($roomOptions, $playlistItems) {
            $room = new Room($roomOptions);
            $room->save();

            foreach ($playlistItems as $item) {
                try {
                    $playlistItem = new PlaylistItem();
                    $playlistItem->beatmap_id = $item['beatmapId'];
                    $playlistItem->ruleset_id = $item['rulesetId'];
                    $playlistItem->room()->associate($room);
                    $playlistItem->allowed_mods = $item['allowedMods'];
                    $playlistItem->required_mods = $item['requiredMods'];
                    $playlistItem->save();
                } catch (\Exception $e) {
                    abort(422, $e->getMessage());
                }
            }

            return $room;
        });

        return json_item(
            $room,
            'Multiplayer\Room',
            'playlist'
        );
    }
}
