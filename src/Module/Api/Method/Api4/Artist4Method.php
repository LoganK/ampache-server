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

namespace Ampache\Module\Api\Method\Api4;

use Ampache\Repository\Model\User;
use Ampache\Module\Api\Api4;
use Ampache\Module\Api\Json4_Data;
use Ampache\Module\Api\Xml4_Data;
use Ampache\Module\System\Session;

/**
 * Class Artist4Method
 */
final class Artist4Method
{
    public const ACTION = 'artist';

    /**
     * artist
     * MINIMUM_API_VERSION=380001
     *
     * This returns a single artist based on the UID of said artist
     *
     * @param array $input
     * filter  = (string) Alpha-numeric search term
     * include = (array) 'albums'|'songs' //optional
     * @return boolean
     */
    public static function artist(array $input): bool
    {
        if (!Api4::check_parameter($input, array('filter'), self::ACTION)) {
            return false;
        }
        $uid     = scrub_in($input['filter']);
        $user    = User::get_from_username(Session::username($input['auth']));
        $include = [];
        if (array_key_exists('include', $input)) {
            $include = (is_array($input['include'])) ? $input['include'] : explode(',', (string)$input['include']);
        }
        switch ($input['api_format']) {
            case 'json':
                echo Json4_Data::artists(array($uid), $include, $user);
                break;
            default:
                echo Xml4_Data::artists(array($uid), $include, $user);
        }

        return true;
    } // artist
}
