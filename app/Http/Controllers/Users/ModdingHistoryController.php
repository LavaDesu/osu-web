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

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Beatmap;
use App\Models\BeatmapDiscussion;
use App\Models\BeatmapDiscussionPost;
use App\Models\BeatmapDiscussionVote;
use App\Models\BeatmapsetEvent;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ModdingHistoryController extends Controller
{
    protected $section = 'user';

    public function index($id)
    {
        // FIXME: camelCase
        $current_action = 'beatmapset_activities';

        $user = User::lookup($id, 'id', true);

        if ($user === null || !priv_check('UserShow', $user)->can()) {
            abort(404);
        }

        $params = [
            'limit' => 10,
            'sort' => 'id-desc',
            'user' => $user->getKey(),
        ];

        $discussions = BeatmapDiscussion::search($params);
        $discussions['items'] = $discussions['query']->with([
                'user',
                'beatmapset',
                'startingPost',
            ])->get();

        $posts = BeatmapDiscussionPost::search($params);
        $posts['items'] = $posts['query']->with([
                'user',
                'beatmapset',
                'beatmapDiscussion',
                'beatmapDiscussion.beatmapset',
                'beatmapDiscussion.user',
                'beatmapDiscussion.startingPost',
            ])->get();

        $events = BeatmapsetEvent::search($params);
        $events['items'] = $events['query']->with(['user', 'beatmapset'])->get();

        $votes['items'] = BeatmapDiscussionVote::recentlyGivenByUser($user->getKey());
        $receivedVotes['items'] = BeatmapDiscussionVote::recentlyReceivedByUser($user->getKey());

        return view('users.beatmapset_activities', compact(
            'current_action',
            'discussions',
            'events',
            'posts',
            'user',
            'receivedVotes',
            'votes'
        ));
    }

    public function discussions()
    {
        $user = User::lookup(request('user'), 'id', true);

        if ($user === null || !priv_check('UserShow', $user)->can()) {
            abort(404);
        }

        priv_check('BeatmapDiscussionModerate')->ensureCan();

        $params = request();

        // for when the priv_check lock above is removed
        if (!priv_check('BeatmapDiscussionModerate')->can()) {
            $params['with_deleted'] = false;
        }

        $search = BeatmapDiscussion::search($params);
        $discussions = new LengthAwarePaginator(
            $search['query']->with([
                    'user',
                    'beatmapset',
                    'startingPost',
                ])->get(),
            $search['query']->realCount(),
            $search['params']['limit'],
            $search['params']['page'],
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $search['params'],
            ]
        );

        return view('beatmap_discussions.index', compact('discussions', 'search', 'user'));
    }

    public function events()
    {

    }

    public function posts()
    {

    }
}
