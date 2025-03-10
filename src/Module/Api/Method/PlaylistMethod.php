<?php

/*
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 *  LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright 2001 - 2022 Ampache.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=0);

namespace Ampache\Module\Api\Method;

use Ampache\Repository\Model\Playlist;
use Ampache\Repository\Model\Search;
use Ampache\Repository\Model\User;
use Ampache\Module\Api\Api;
use Ampache\Module\Api\Json_Data;
use Ampache\Module\Api\Xml_Data;
use Ampache\Module\Authorization\Access;
use Ampache\Module\System\Session;

/**
 * Class PlaylistMethod
 * @package Lib\ApiMethods
 */
final class PlaylistMethod
{
    public const ACTION = 'playlist';

    /**
     * playlist
     * MINIMUM_API_VERSION=380001
     *
     * This returns a single playlist
     *
     * @param array $input
     * filter = (string) UID of playlist
     * @return boolean
     */
    public static function playlist(array $input): bool
    {
        if (!Api::check_parameter($input, array('filter'), self::ACTION)) {
            return false;
        }
        $user      = User::get_from_username(Session::username($input['auth']));
        $object_id = $input['filter'];

        $playlist = ((int) $object_id === 0)
            ? new Search((int) str_replace('smart_', '', $object_id), 'song', $user)
            : new Playlist((int) $object_id);
        if (!$playlist->id) {
            /* HINT: Requested object string/id/type ("album", "myusername", "some song title", 1298376) */
            Api::error(sprintf(T_('Not Found: %s'), $object_id), '4704', self::ACTION, 'filter', $input['api_format']);

            return false;
        }
        if (!$playlist->type == 'public' && (!$playlist->has_access($user->id) && !Access::check('interface', 100, $user->id))) {
            Api::error(T_('Require: 100'), '4742', self::ACTION, 'account', $input['api_format']);

            return false;
        }

        ob_end_clean();
        switch ($input['api_format']) {
            case 'json':
                echo Json_Data::playlists(array($object_id), $user, false, false);
                break;
            default:
                echo Xml_Data::playlists(array($object_id), $user);
        }

        return true;
    }
}
