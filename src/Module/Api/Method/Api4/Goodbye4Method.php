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

use Ampache\Module\Api\Api4;
use Ampache\Module\System\Dba;
use Ampache\Module\System\Session;

/**
 * Class Goodbye4Method
 */
final class Goodbye4Method
{
    public const ACTION = 'goodbye';

    /**
     * goodbye
     * MINIMUM_API_VERSION=400001
     *
     * Destroy session for auth key.
     *
     * @param array $input
     * auth = (string))
     * @return boolean
     */
    public static function goodbye(array $input): bool
    {
        if (!Api4::check_parameter($input, array('auth'), self::ACTION)) {
            return false;
        }
        // Check and see if we should destroy the api session (done if valid session is passed)
        if (Session::exists('api', $input['auth'])) {
            $sql = "DELETE FROM `session` WHERE `id` = ? AND `type` = 'api';";
            Dba::write($sql, array($input['auth']));

            debug_event(self::class, 'Goodbye Received from ' . filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) . ' :: ' . $input['auth'], 5);
            ob_end_clean();
            Api4::message('success', 'goodbye: ' . $input['auth'], null, $input['api_format']);

            return true;
        }
        ob_end_clean();
        Api4::message('error', 'failed to end session: ' . $input['auth'], '400', $input['api_format']);

        return false;
    } // goodbye
}
