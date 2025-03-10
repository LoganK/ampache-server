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

namespace Ampache\Module\Api\Method\Api5;

use Ampache\Config\AmpConfig;
use Ampache\Repository\Model\Catalog;
use Ampache\Repository\Model\Share;
use Ampache\Repository\Model\User;
use Ampache\Module\Api\Api5;
use Ampache\Module\System\Session;

/**
 * Class ShareDelete5Method
 */
final class ShareDelete5Method
{
    public const ACTION = 'share_delete';

    /**
     * share_delete
     * MINIMUM_API_VERSION=420000
     *
     * Delete an existing share.
     *
     * @param array $input
     * filter = (string) UID of share to delete
     * @return boolean
     */
    public static function share_delete(array $input): bool
    {
        if (!AmpConfig::get('share')) {
            Api5::error(T_('Enable: share'), '4703', self::ACTION, 'system', $input['api_format']);

            return false;
        }
        if (!Api5::check_parameter($input, array('filter'), self::ACTION)) {
            return false;
        }
        $user      = User::get_from_username(Session::username($input['auth']));
        $object_id = $input['filter'];
        if (in_array($object_id, Share::get_share_list($user))) {
            if (Share::delete_share($object_id, $user)) {
                Api5::message('share ' . $object_id . ' deleted', $input['api_format']);
                Catalog::count_table('share');
            } else {
                /* HINT: Requested object string/id/type ("album", "myusername", "some song title", 1298376) */
                Api5::error(sprintf(T_('Bad Request: %s'), $object_id), '4710', self::ACTION, 'system', $input['api_format']);
            }
        } else {
            /* HINT: Requested object string/id/type ("album", "myusername", "some song title", 1298376) */
            Api5::error(sprintf(T_('Not Found: %s'), $object_id), '4704', self::ACTION, 'filter', $input['api_format']);
        }

        return true;
    } // share_delete
}
