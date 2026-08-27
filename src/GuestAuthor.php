<?php

namespace Streekomroep;

use Timber\Integration\CoAuthorsPlus\CoAuthorsPlusUser;

/**
 * Guest author that falls back to a Gravatar when no portrait is set.
 *
 * Owning the fallback here keeps every template on plain `author.avatar`.
 */
class GuestAuthor extends CoAuthorsPlusUser
{
    protected function init($coauthor = false)
    {
        parent::init($coauthor);

        // CoAuthorsPlusUser::init() drops user_email; the avatar fallback needs it.
        $this->user_email = $coauthor->user_email ?? $this->user_email;
    }

    public function avatar($args = null)
    {
        $avatar = parent::avatar($args);
        if ($avatar) {
            return $avatar;
        }

        return $this->user_email ? get_avatar_url($this->user_email, $args) : null;
    }
}
