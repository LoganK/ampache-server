<?php
/*
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
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
 */

declare(strict_types=1);

namespace Ampache\Repository;

use Ampache\Config\AmpConfig;
use Ampache\Module\System\Core;
use Ampache\Module\System\Dba;
use Ampache\Repository\Model\Catalog;

final class AlbumRepository implements AlbumRepositoryInterface
{
    /**
     * This returns a number of random albums
     *
     * @return int[] Album ids
     */
    public function getRandom(
        int $userId,
        ?int $count = 1
    ): array {
        $results = [];

        if (!$count) {
            $count = 1;
        }

        $sql  = "SELECT DISTINCT `album`.`id` FROM `album` WHERE `album`.`catalog` IN (" . implode(',', Catalog::get_catalogs('', $userId)) . ") ";

        $rating_filter = AmpConfig::get_rating_filter();
        if ($rating_filter > 0 && $rating_filter <= 5 && $userId > 0) {
            $sql .= "AND" . sprintf(
                " `album`.`id` NOT IN (SELECT `object_id` FROM `rating` WHERE `rating`.`object_type` = 'album' AND `rating`.`rating` <=%d AND `rating`.`user` = %d) ",
                $rating_filter,
                $userId
            );
        }
        $sql .= sprintf(
            'ORDER BY RAND() LIMIT %d',
            $count
        );
        $db_results = Dba::read($sql);

        while ($row = Dba::fetch_assoc($db_results)) {
            $results[] = (int)$row['id'];
        }

        return $results;
    }

    /**
     * This returns a number of random album_disks
     *
     * @return int[] AlbumDisk ids
     */
    public function getRandomAlbumDisk(
        int $userId,
        ?int $count = 1
    ): array {
        $results = [];

        if (!$count) {
            $count = 1;
        }

        $sql  = "SELECT DISTINCT `album_disk`.`id` FROM `album_disk` LEFT JOIN `album` ON `album`.`id` = `album_disk`.`album_id` WHERE `album_disk`.`catalog` IN (" . implode(',', Catalog::get_catalogs('', $userId)) . ") ";

        $rating_filter = AmpConfig::get_rating_filter();
        if ($rating_filter > 0 && $rating_filter <= 5 && $userId > 0) {
            $sql .= sprintf(
                    "AND `album`.`id` NOT IN (SELECT `object_id` FROM `rating` WHERE `rating`.`object_type` = 'album' AND `rating`.`rating` <=%d AND `rating`.`user` = %d) ",
                    $rating_filter,
                    $userId
                );
        }
        $sql .= sprintf(
            'ORDER BY RAND() LIMIT %d',
            $count
        );
        $db_results = Dba::read($sql);

        while ($row = Dba::fetch_assoc($db_results)) {
            $results[] = (int)$row['id'];
        }

        return $results;
    }

    /**
     * gets songs from this album
     *
     * @return int[] Album ids
     */
    public function getSongs(
        int $albumId
    ): array {
        $userId     = (!empty(Core::get_global('user'))) ? Core::get_global('user')->id : null;
        $sql        = "SELECT `song`.`id` FROM `song` WHERE `song`.`album` = ? AND `song`.`catalog` IN (" . implode(',', Catalog::get_catalogs('', $userId)) . ") ORDER BY `song`.`disk`, `song`.`track`, `song`.`title`";
        $db_results = Dba::read($sql, [$albumId]);

        $results = [];
        while ($row = Dba::fetch_row($db_results)) {
            $results[] = (int) $row['0'];
        }

        return $results;
    }

    /**
     * gets songs from this album_disk id
     *
     * @return int[] Song ids
     */
    public function getSongsByAlbumDisk(
        int $albumDiskId
    ): array {
        $userId = (!empty(Core::get_global('user'))) ? Core::get_global('user')->id : null;
        $sql    = "SELECT `song`.`id` FROM `song` LEFT JOIN `album_disk` ON `album_disk`.`album_id` = `song`.`album` AND `album_disk`.`disk` = `song`.`disk` WHERE `album_disk`.`id` = ? AND `album_disk`.`catalog` IN (" . implode(',', Catalog::get_catalogs('', $userId)) . ")";
        if (AmpConfig::get('catalog_filter') && !empty(Core::get_global('user')) && Core::get_global('user')->id > 0) {
            $sql .= "AND" . Catalog::get_user_filter('song', Core::get_global('user')->id) . " ";
        }
        $sql .= "ORDER BY `song`.`disk`, `song`.`track`, `song`.`title`";
        $db_results = Dba::read($sql, [$albumDiskId]);

        $results = [];
        while ($row = Dba::fetch_row($db_results)) {
            $results[] = (int) $row['0'];
        }

        return $results;
    }

    /**
     * gets a random order of songs from this album
     *
     * @return int[] Album ids
     */
    public function getRandomSongs(
        int $albumId
    ): array {
        $sql = (AmpConfig::get('catalog_disable'))
            ? "SELECT `song`.`id` FROM `song` LEFT JOIN `catalog` ON `catalog`.`id` = `song`.`catalog` WHERE `song`.`album` = ? AND `catalog`.`enabled` = '1' "
            : "SELECT `song`.`id` FROM `song` WHERE `song`.`album` = ? ";
        if (AmpConfig::get('catalog_filter') && !empty(Core::get_global('user')) && Core::get_global('user')->id > 0) {
            $sql .= "AND" . Catalog::get_user_filter('song', Core::get_global('user')->id) . " ";
        }
        $sql .= 'ORDER BY RAND()';
        $db_results = Dba::read($sql, [$albumId]);

        $results = [];
        while ($row = Dba::fetch_row($db_results)) {
            $results[] = (int) $row['0'];
        }

        return $results;
    }

    /**
     * gets a random order of songs from this album group
     *
     * @return int[] Album ids
     */
    public function getRandomSongsByAlbumDisk(
        int $albumDiskId
    ): array {
        $sql = (AmpConfig::get('catalog_disable'))
            ? "SELECT `song`.`id` FROM `song` LEFT JOIN `album_disk` ON `album_disk`.`album_id` = `song`.`album` AND `album_disk`.`disk` = `song`.`disk` LEFT JOIN `catalog` ON `catalog`.`id` = `song`.`catalog` WHERE `album_disk`.`id` = ? AND `catalog`.`enabled` = '1' "
            : "SELECT `song`.`id` FROM `song` LEFT JOIN `album_disk` ON `album_disk`.`album_id` = `song`.`album` AND `album_disk`.`disk` = `song`.`disk` WHERE `album_disk`.`id` = ? ";
        if (AmpConfig::get('catalog_filter') && !empty(Core::get_global('user')) && Core::get_global('user')->id > 0) {
            $sql .= "AND" . Catalog::get_user_filter('song', Core::get_global('user')->id) . " ";
        }
        $sql .= 'ORDER BY RAND()';
        $db_results = Dba::read($sql, [$albumDiskId]);

        $results = [];
        while ($row = Dba::fetch_row($db_results)) {
            $results[] = (int) $row['0'];
        }

        return $results;
    }

    /**
     * Deletes the album entry
     */
    public function delete(
        int $albumId
    ): bool {
        $result = Dba::write(
            'DELETE FROM `album` WHERE `id` = ?',
            [$albumId]
        );

        return $result !== false;
    }

    /**
     * Cleans out unused albums
     */
    public function collectGarbage(): void
    {
        // delete old mappings or bad ones
        Dba::write("DELETE FROM `album_map` WHERE `object_id` NOT IN (SELECT `id` FROM `artist`);");
        Dba::write("DELETE FROM `album_map` WHERE `album_map`.`album_id` NOT IN (SELECT DISTINCT `song`.`album` FROM `song`);");
        Dba::write("DELETE FROM `album_map` WHERE `album_map`.`album_id` IN (SELECT `album_id` FROM (SELECT DISTINCT `album_map`.`album_id` FROM `album_map` LEFT JOIN `artist_map` ON `artist_map`.`object_type` = `album_map`.`object_type` AND `artist_map`.`artist_id` = `album_map`.`object_id` AND `artist_map`.`object_id` = `album_map`.`album_id` WHERE `artist_map`.`artist_id` IS NULL AND `album_map`.`object_type` = 'album') AS `null_album`);");
        // delete the albums that don't have any songs left
        Dba::write("DELETE FROM `album` WHERE `album`.`id` NOT IN (SELECT DISTINCT `song`.`album` FROM `song`) AND `album`.`id` NOT IN (SELECT DISTINCT `album_id` FROM `album_map`);");
        // delete old album_disks that shouldn't exist
        Dba::write("DELETE FROM `album_disk` WHERE `album_id` NOT IN (SELECT `id` FROM `album`);");
    }

    /**
     * Get time for an album disk by album.
     */
    public function getAlbumDuration(int $albumId): int
    {
        $db_results = Dba::read(
            'SELECT `time` FROM `album` WHERE `album`.`id` = ?',
            [$albumId]
        );

        $results = Dba::fetch_assoc($db_results);

        return (int) $results['time'];
    }

    /**
     * Get play count for an album disk by album id.
     */
    public function getAlbumPlayCount(int $albumId): int
    {
        $db_results = Dba::read(
            'SELECT `total_count` FROM `album` WHERE `album`.`id` = ?',
            [$albumId]
        );

        $results = Dba::fetch_assoc($db_results);

        return (int) $results['total_count'];
    }

    /**
     * Get song count for an album disk by album id.
     */
    public function getSongCount(int $albumId): int
    {
        $db_results = Dba::read(
            'SELECT `song_count` FROM `album` WHERE `album`.`id` = ?',
            [$albumId]
        );

        $results = Dba::fetch_assoc($db_results);

        return (int) $results['song_count'];
    }

    /**
     * Get distinct artist count for an album disk by album id.
     */
    public function getArtistCount(int $albumId): int
    {
        $db_results = Dba::read(
            'SELECT `artist_count` FROM `album` WHERE `album`.`id` = ?',
            [$albumId]
        );

        $results = Dba::fetch_assoc($db_results);

        return (int) $results['artist_count'];
    }

    /**
     * Get distinct artist count for an album array.
     */
    public function getArtistCountGroup(array $albumArray): int
    {
        $idlist     = '(' . implode(',', $albumArray) . ')';
        $sql        = "SELECT COUNT(DISTINCT(`song`.`artist`)) AS `artist_count` FROM `song` LEFT JOIN `catalog` ON `catalog`.`id` = `song`.`catalog` WHERE `catalog`.`enabled` = '1' AND `song`.`album` IN $idlist;";
        $db_results = Dba::read($sql);
        $results    = Dba::fetch_assoc($db_results);

        return (int) $results['artist_count'];
    }

    /**
     * gets the album ids that the artist is a part of
     * Return Album or AlbumDisk based on album_group preference
     *
     * @return int[]
     */
    public function getByArtist(
        int $artistId,
        ?int $catalog = null,
        bool $group_release_type = false
    ): array {
        $userId        = (!empty(Core::get_global('user'))) ? Core::get_global('user')->id : null;
        $catalog_where = "AND `album`.`catalog` IN (" . implode(',', Catalog::get_catalogs('', $userId)) . ")";
        if ($catalog !== null) {
            $catalog_where = "AND `album`.`catalog` = '" . Dba::escape($catalog) . "'";
        }
        $original_year = AmpConfig::get('use_original_year') ? "IFNULL(`album`.`original_year`, `album`.`year`)" : "`album`.`year`";
        $sort_type     = AmpConfig::get('album_sort');
        $showAlbum     = AmpConfig::get('album_group');
        switch ($sort_type) {
            case 'year_asc':
                $sql_sort = "$original_year ASC";
                break;
            case 'year_desc':
                $sql_sort = "$original_year DESC";
                break;
            case 'name_asc':
                $sql_sort = "`album`.`name` ASC";
                break;
            case 'name_desc':
                $sql_sort = "`album`.`name` DESC";
                break;
            default:
                $sql_sort = "`album`.`name`" . ", $original_year";
        }

        $sql        = ($showAlbum)
            ? "SELECT DISTINCT `album`.`id`, `album`.`release_type`, `album`.`mbid` FROM `album` LEFT JOIN `album_map` ON `album_map`.`album_id` = `album`.`id` WHERE `album_map`.`object_id` = ? $catalog_where GROUP BY `album`.`id`, `album`.`release_type`, `album`.`mbid` ORDER BY $sql_sort"
            : "SELECT DISTINCT `album_disk`.`id`, `album_disk`.`disk`, `album`.`name`, `album`.`release_type`, `album`.`mbid`, $original_year FROM `album_disk` LEFT JOIN `album` ON `album`.`id` = `album_disk`.`album_id` LEFT JOIN `album_map` ON `album_map`.`album_id` = `album`.`id` " . $catalog_join . " WHERE `album_map`.`object_id` = ? $catalog_where GROUP BY `album_disk`.`id`, `album_disk`.`disk`, `album`.`name`, `album`.`release_type`, `album`.`mbid`, $original_year ORDER BY $sql_sort, `album_disk`.`disk`";
        $db_results = Dba::read($sql, array($artistId));
        $results    = array();
        while ($row = Dba::fetch_assoc($db_results)) {
            if ($group_release_type) {
                // We assume undefined release type is album
                $rtype = $row['release_type'] ?? 'album';
                if (!isset($results[$rtype])) {
                    $results[$rtype] = array();
                }
                $results[$rtype][] = (int)$row['id'];

                $sort = (string)AmpConfig::get('album_release_type_sort');
                if ($sort) {
                    $results_sort = array();
                    $asort        = explode(',', $sort);

                    foreach ($asort as $rtype) {
                        if (array_key_exists($rtype, $results)) {
                            $results_sort[$rtype] = $results[$rtype];
                            unset($results[$rtype]);
                        }
                    }

                    $results = array_merge($results_sort, $results);
                }
            } else {
                $results[] = (int)$row['id'];
            }
        }

        return $results;
    }

    /**
     * gets the album ids that the artist is a part of
     * Return Album only
     *
     * @return int[]
     */
    public function getAlbumByArtist(
        int $artistId,
        ?int $catalog = null,
        bool $group_release_type = false
    ): array {
        $userId        = (!empty(Core::get_global('user'))) ? Core::get_global('user')->id : null;
        $catalog_where = "AND `album`.`catalog` IN (" . implode(',', Catalog::get_catalogs('', $userId)) . ")";
        if ($catalog !== null) {
            $catalog_where .= " AND `album`.`catalog` = '" . Dba::escape($catalog) . "'";
        }
        $original_year = AmpConfig::get('use_original_year') ? "IFNULL(`album`.`original_year`, `album`.`year`)" : "`album`.`year`";
        $sort_type     = AmpConfig::get('album_sort');
        switch ($sort_type) {
            case 'year_asc':
                $sql_sort = "$original_year ASC";
                break;
            case 'year_desc':
                $sql_sort = "$original_year DESC";
                break;
            case 'name_asc':
                $sql_sort = "`album`.`name` ASC";
                break;
            case 'name_desc':
                $sql_sort = "`album`.`name` DESC";
                break;
            default:
                $sql_sort = "`album`.`name`" . ", $original_year";
        }

        $sql        = "SELECT DISTINCT `album`.`id`, `album`.`release_type`, `album`.`mbid` FROM `album` LEFT JOIN `album_map` ON `album_map`.`album_id` = `album`.`id` WHERE `album_map`.`object_id` = ? $catalog_where GROUP BY `album`.`id`, `album`.`release_type`, `album`.`mbid` ORDER BY $sql_sort";
        $db_results = Dba::read($sql, array($artistId));
        $results    = array();
        while ($row = Dba::fetch_assoc($db_results)) {
            if ($group_release_type) {
                // We assume undefined release type is album
                $rtype = $row['release_type'] ?? 'album';
                if (!isset($results[$rtype])) {
                    $results[$rtype] = array();
                }
                $results[$rtype][] = (int)$row['id'];

                $sort = (string)AmpConfig::get('album_release_type_sort');
                if ($sort) {
                    $results_sort = array();
                    $asort        = explode(',', $sort);

                    foreach ($asort as $rtype) {
                        if (array_key_exists($rtype, $results)) {
                            $results_sort[$rtype] = $results[$rtype];
                            unset($results[$rtype]);
                        }
                    }

                    $results = array_merge($results_sort, $results);
                }
            } else {
                $results[] = (int)$row['id'];
            }
        }

        return $results;
    }

    /**
     * gets the album ids that the artist is a part of
     * Return AlbumDisk only
     *
     * @return int[]
     */
    public function getAlbumDiskByArtist(
        int $artistId,
        ?int $catalog = null,
        bool $group_release_type = false
    ): array {
        $userId        = (!empty(Core::get_global('user'))) ? Core::get_global('user')->id : null;
        $catalog_where = "AND `album_disk`.`catalog` IN (" . implode(',', Catalog::get_catalogs('', $userId)) . ")";
        if ($catalog !== null) {
            $catalog_where .= "AND `album_disk`.`catalog` = '" . Dba::escape($catalog) . "'";
        }
        $original_year = AmpConfig::get('use_original_year') ? "IFNULL(`album`.`original_year`, `album`.`year`)" : "`album`.`year`";
        $sort_type     = AmpConfig::get('album_sort');
        switch ($sort_type) {
            case 'year_asc':
                $sql_sort = "$original_year ASC";
                break;
            case 'year_desc':
                $sql_sort = "$original_year DESC";
                break;
            case 'name_asc':
                $sql_sort = "`album`.`name` ASC";
                break;
            case 'name_desc':
                $sql_sort = "`album`.`name` DESC";
                break;
            default:
                $sql_sort = "`album`.`name`, $original_year";
        }

        $sql        = "SELECT DISTINCT `album_disk`.`id`, `album_disk`.`disk`, `album`.`name`, `album`.`release_type`, `album`.`mbid`, $original_year FROM `album_disk` LEFT JOIN `album` ON `album`.`id` = `album_disk`.`album_id` LEFT JOIN `album_map` ON `album_map`.`album_id` = `album`.`id` WHERE `album_map`.`object_id` = ? $catalog_where GROUP BY `album_disk`.`id`, `album_disk`.`disk`, `album`.`name`, `album`.`release_type`, `album`.`mbid`, $original_year ORDER BY $sql_sort, `album_disk`.`disk`";
        $db_results = Dba::read($sql, array($artistId));
        $results    = array();
        while ($row = Dba::fetch_assoc($db_results)) {
            if ($group_release_type) {
                // We assume undefined release type is album
                $rtype = $row['release_type'] ?? 'album';
                if (!isset($results[$rtype])) {
                    $results[$rtype] = array();
                }
                $results[$rtype][] = (int)$row['id'];

                $sort = (string)AmpConfig::get('album_release_type_sort');
                if ($sort) {
                    $results_sort = array();
                    $asort        = explode(',', $sort);

                    foreach ($asort as $rtype) {
                        if (array_key_exists($rtype, $results)) {
                            $results_sort[$rtype] = $results[$rtype];
                            unset($results[$rtype]);
                        }
                    }

                    $results = array_merge($results_sort, $results);
                }
            } else {
                $results[] = (int)$row['id'];
            }
        }

        return $results;
    }

    /**
     * gets the album id has the same artist and title
     *
     * @return int[]
     */
    public function getByName(
        string $name,
        int $artistId
    ): array {
        $sql        = "SELECT `album`.`id` FROM `album` WHERE (`album`.`name` = ? OR LTRIM(CONCAT(COALESCE(`album`.`prefix`, ''), ' ', `album`.`name`)) = ?) AND `album`.`album_artist` = ?";
        $params     = array($name, $name, $artistId);
        $db_results = Dba::read($sql, $params);
        $results    = array();
        while ($row = Dba::fetch_assoc($db_results)) {
            $results[] = (int)$row['id'];
        }

        return $results;
    }

    /**
     * gets the album id that is part of this mbid_group
     *
     * @return int[]
     */
    public function getByMbidGroup(
        string $mbid
    ): array {
        $sql        = "SELECT `album`.`id` FROM `album` WHERE `album`.`mbid_group` = ?";
        $db_results = Dba::read($sql, array($mbid));
        $results    = array();
        while ($row = Dba::fetch_assoc($db_results)) {
            $results[] = (int)$row['id'];
        }

        return $results;
    }
}
