<?php

namespace App\GraphQL\Resolvers;

use App\Models\Beatmap;
use App\Models\User;
use App\Models\UserProfileCustomization;

class UserResolver
{
    protected $userProfileCustomization = [];

    public function getDefaultGroup(User $user)
    {
        return $user->defaultGroup();
    }

    public function getCommentsCount(User $user)
    {
        return $user->comments()->withoutTrashed()->count();
    }

    public function getCover(User $user)
    {
        return $this->userProfileCustomization($user)->cover();
    }

    public function getKudosu(User $user)
    {
        return [
            'total' => $user->osu_kudostotal,
            'available' => $user->osu_kudosavailable,
        ];
    }

    public function getProfileOrder(User $user)
    {
        return $this->userProfileCustomization($user)->extras_order;
    }

    public function getActiveTournamentBanner(User $user)
    {
        return $user->profileBanners()->active();
    }

    public function getBadges(User $user)
    {
        return $user->badges()->orderBy('awarded', 'DESC')->get();
    }

    public function getPage(User $user)
    {
        if ($user->userPage !== null) {
            return [
                'html' => $user->userPage->bodyHTML(['withoutImageDimensions' => true, 'modifiers' => ['profile-page']]),
                'raw' => $user->userPage->bodyRaw,
            ];
        } else {
            return ['html' => '', 'raw' => ''];
        }
    }

    public function getPreviousUsernames(User $user)
    {
        return $user->previousUsernames()->unique()->values();
    }

    public function getRankHistory(User $user, array $args)
    {
        return $user->rankHistories()
            ->where('mode', Beatmap::modeInt($args["mode"] ?? $user->playmode))
            ->first();
    }

    public function getStatistics(User $user, array $args)
    {
        return $user->statistics($args["mode"] ?? $user->playmode);
    }

    public function getAchievements(User $user)
    {
        return $user->userAchievements()->orderBy('date', 'desc')->get();
    }

    private function userProfileCustomization(User $user): UserProfileCustomization
    {
        if (!isset($this->userProfileCustomization[$user->getKey()])) {
            $this->userProfileCustomization[$user->getKey()] = $user->userProfileCustomization ?? $user->userProfileCustomization()->make();
        }

        return $this->userProfileCustomization[$user->getKey()];
    }
}
