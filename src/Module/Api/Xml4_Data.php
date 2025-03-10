<?php
declare(strict_types=0);
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
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
 *
 */
namespace Ampache\Module\Api;

use Ampache\Module\Util\ObjectTypeToClassNameMapper;
use Ampache\Repository\Model\Album;
use Ampache\Repository\Model\License;
use Ampache\Repository\Model\Preference;
use Ampache\Repository\Model\Shoutbox;
use Ampache\Repository\Model\Video;
use Ampache\Module\Playback\Stream;
use Ampache\Module\Util\Ui;
use Ampache\Config\AmpConfig;
use Ampache\Repository\Model\Art;
use Ampache\Repository\Model\Artist;
use Ampache\Repository\Model\Catalog;
use Ampache\Module\System\Core;
use Ampache\Repository\Model\Democratic;
use Ampache\Repository\AlbumRepositoryInterface;
use Ampache\Repository\SongRepositoryInterface;
use Ampache\Repository\Model\Playlist;
use Ampache\Repository\Model\Podcast;
use Ampache\Repository\Model\Podcast_Episode;
use Ampache\Repository\Model\Rating;
use Ampache\Repository\Model\Search;
use Ampache\Repository\Model\Share;
use Ampache\Repository\Model\Song;
use Ampache\Repository\Model\Tag;
use Ampache\Repository\Model\User;
use Ampache\Repository\Model\Useractivity;
use Ampache\Repository\Model\Userflag;

/**
 * Xml_Data Class
 *
 * This class takes care of all of the xml document stuff in Ampache these
 * are all static calls
 *
 */
class Xml4_Data
{
    // This is added so that we don't pop any webservers
    private static $limit  = 5000;
    private static $offset = 0;
    private static $type   = '';

    /**
     * constructor
     *
     * We don't use this, as its really a static class
     */
    private function __construct()
    {
        // Rien a faire
    } // constructor

    /**
     * set_offset
     *
     * This takes an int and changes the offset
     *
     * @param integer $offset Change the starting position of your results. (e.g 5001 when selecting in groups of 5000)
     */
    public static function set_offset($offset)
    {
        self::$offset = (int) $offset;
    } // set_offset

    /**
     * set_limit
     *
     * This sets the limit for any ampache transactions
     *
     * @param  integer $limit Set a limit on your results
     * @return boolean
     */
    public static function set_limit($limit)
    {
        if (!$limit) {
            return false;
        }

        self::$limit = (strtolower((string) $limit) == "none") ? null : (int) $limit;

        return true;
    } // set_limit

    /**
     * set_type
     *
     * This sets the type of Xml_Data we are working on
     *
     * @param    string    $type    Xml_Data type
     * @return    boolean
     */
    public static function set_type($type)
    {
        if (!in_array(strtolower($type), array('rss', 'xspf', 'itunes'))) {
            return false;
        }

        self::$type = $type;

        return true;
    } // set_type

    /**
     * error
     *
     * This generates a standard XML Error message
     *
     * @param    string    $code    Error code
     * @param    string    $string    Error message
     * @return    string    return error message xml
     */
    public static function error($code, $string)
    {
        $xml_string = "\t<error code=\"$code\"><![CDATA[" . $string . "]]></error>";

        return Xml_Data::output_xml($xml_string);
    } // error

    /**
     * success
     *
     * This generates a standard XML Success message
     *
     * @param    string    $string    success message
     * @return    string    return success message xml
     */
    public static function success($string)
    {
        $xml_string = "\t<success code=\"1\"><![CDATA[" . $string . "]]></success>";

        return Xml_Data::output_xml($xml_string);
    } // success

    /**
     * header
     *
     * This returns the header
     *
     * @param string $title
     * @return string return xml
     * @see _header()
     */
    public static function header($title = null)
    {
        return self::_header($title);
    } // header

    /**
     * footer
     *
     * This returns the footer
     *
     * @see    _footer()
     * @return    string    return xml
     */
    public static function footer()
    {
        return self::_footer();
    } // footer

    /**
     * tags_string
     *
     * This returns the formatted 'tags' string for an xml document
     * @input array $tags
     * @param $tags
     * @return string
     */
    private static function tags_string($tags)
    {
        $string = '';

        if (is_array($tags)) {
            $atags = array();
            foreach ($tags as $tag_id => $data) {
                if (array_key_exists($data['id'], $atags)) {
                    $atags[$data['id']]['count']++;
                } else {
                    $atags[$data['id']] = array(
                        'name' => $data['name'],
                        'count' => 1
                    );
                }
            }

            foreach ($atags as $tag => $data) {
                $string .= "\t<tag id=\"" . $tag . "\" count=\"" . $data['count'] . "\" ><![CDATA[" . $data['name'] . "]]></tag>\n";
            }
        }

        return $string;
    } // tags_string

    /**
     * output_xml_from_array
     * This takes a one dimensional array and creates a XML document from it. For use primarily by the ajax mojo
     * @param $array
     * @param boolean $callback
     * @param string $type
     * @return string
     */
    public static function output_xml_from_array($array, $callback = false, $type = '')
    {
        $string = '';

        // If we weren't passed an array then return
        if (!is_array($array)) {
            return $string;
        }

        // The type is used for the different XML docs we pass
        switch ($type) {
            case 'itunes':
                foreach ($array as $key => $value) {
                    if (is_array($value)) {
                        $value = xoutput_from_array($value, true, $type);
                        $string .= "\t\t<$key>\n$value\t\t</$key>\n";
                    } else {
                        if ($key == "key") {
                            $string .= "\t\t<$key>$value</$key>\n";
                        } elseif (is_int($value)) {
                            $string .= "\t\t\t<key>$key</key><integer>$value</integer>\n";
                        } elseif ($key == "Date Added") {
                            $string .= "\t\t\t<key>$key</key><date>$value</date>\n";
                        } elseif (is_string($value)) {
                            /* We need to escape the value */
                            $string .= "\t\t\t<key>$key</key><string><![CDATA[" . $value . "]]></string>\n";
                        }
                    }
                } // end foreach

                return $string;
            case 'xspf':
                foreach ($array as $key => $value) {
                    if (is_array($value)) {
                        $value = xoutput_from_array($value, true, $type);
                        $string .= "\t\t<$key>\n$value\t\t</$key>\n";
                    } else {
                        if ($key == "key") {
                            $string .= "\t\t<$key>$value</$key>\n";
                        } elseif (is_numeric($value)) {
                            $string .= "\t\t\t<$key>$value</$key>\n";
                        } elseif (is_string($value)) {
                            /* We need to escape the value */
                            $string .= "\t\t\t<$key><![CDATA[" . $value . "]]></$key>\n";
                        }
                    }
                } // end foreach

                return $string;
            default:
                foreach ($array as $key => $value) {
                    // No numeric keys
                    if (is_numeric($key)) {
                        $key = 'item';
                    }

                    if (is_array($value)) {
                        // Call ourself
                        $value = xoutput_from_array($value, true);
                        $string .= "\t<content div=\"$key\">$value</content>\n";
                    } else {
                        /* We need to escape the value */
                        $string .= "\t<content div=\"$key\"><![CDATA[" . $value . "]]></content>\n";
                    }
                    // end foreach elements
                }
                if (!$callback) {
                    $string = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n<root>\n" . $string . "</root>\n";
                }

                return UI::clean_utf8($string);
            }
    } // output_from_array

    /**
     * keyed_array
     *
     * This will build an xml document from a key'd array,
     *
     * @param  array $array (description here...)
     * @param  boolean $callback (don't output xml when true)
     * @param  string|boolean $object
     * @return string return xml
     */
    public static function keyed_array($array, $callback = false, $object = false)
    {
        $string = '';
        // Foreach it
        foreach ($array as $key => $value) {
            $attribute = '';
            // See if the key has attributes
            if (is_array($value) && isset($value['attributes'])) {
                $attribute = ' ' . $value['attributes'];
                $key       = $value['value'];
            }

            // If it's an array, run again
            if (is_array($value)) {
                $value = self::keyed_array($value, true);
                $string .= ($object) ? "<$object>\n$value\n</$object>\n" : "<$key$attribute>\n$value\n</$key>\n";
            } else {
                $string .= ($object) ? "\t<$object index=\"" . $key . "\">$value</$object>\n" : "\t<$key$attribute><![CDATA[" . $value . "]]></$key>\n";
            }
        } // end foreach

        if (!$callback) {
            $string = Xml_Data::output_xml($string);
        }

        return $string;
    } // keyed_array

    /**
     * indexes
     *
     * This takes an array of object_ids and return XML based on the type of object
     * we want
     *
     * @param  array   $objects Array of object_ids (Mixed string|int)
     * @param  string  $object_type 'artist'|'album'|'song'|'playlist'|'share'|'podcast'|'podcast_episode'|'video'
     * @param    User     $user
     * @param  boolean $full_xml whether to return a full XML document or just the node
     * @param  boolean $include include episodes from podcasts or tracks in a playlist
     * @return string  return xml
     */
    public static function indexes($objects, $object_type, $user, $full_xml = true, $include = false)
    {
        if ((count($objects) > self::$limit || self::$offset > 0) && (self::$limit && $full_xml)) {
            $objects = array_splice($objects, self::$offset, self::$limit);
        }
        $string = ($full_xml) ? "<total_count>" . count($objects) . "</total_count>\n": '';

        // here is where we call the object type
        foreach ($objects as $object_id) {
            switch ($object_type) {
                case 'artist':
                    if ($include) {
                        $string .= self::artists(array($object_id), array('songs', 'albums'), $user, false);
                    } else {
                        $artist = new Artist($object_id);
                        if (!isset($artist->id)) {
                            break;
                        }
                        $albums = static::getAlbumRepository()->getAlbumByArtist($object_id);
                        $string .= "<$object_type id=\"" . $object_id . "\">\n\t<name><![CDATA[" . $artist->get_fullname() . "]]></name>\n";
                        foreach ($albums as $album_id) {
                            if ($album_id > 0) {
                                $album = new Album($album_id);
                                $string .= "\t<album id=\"" . $album_id . '"><![CDATA[' . $album->get_fullname() . "]]></album>\n";
                            }
                        }
                        $string .= "</$object_type>\n";
                    }
                    break;
                case 'album':
                    if ($include) {
                        $string .= self::albums(array($object_id), array('songs'), $user, false);
                    } else {
                        $album = new Album($object_id);
                        $string .= "<$object_type id=\"" . $object_id . "\">\n\t<name><![CDATA[" . $album->get_fullname() . "]]></name>\n\t\t<artist id=\"" . $album->album_artist . "\"><![CDATA[" . $album->get_album_artist_fullname() . "]]></artist>\n</$object_type>\n";
                    }
                    break;
                case 'song':
                    $song = new Song($object_id);
                    $song->format();
                    $string .= "<$object_type id=\"" . $object_id . "\">\n\t<title><![CDATA[" . $song->title . "]]></title>\n\t<name><![CDATA[" . $song->get_fullname() . "]]></name>\n\t<artist id=\"" . $song->artist . "\"><![CDATA[" . $song->get_artist_fullname() . "]]></artist>\n\t<album id=\"" . $song->album . "\"><![CDATA[" . $song->get_album_fullname() . "]]></album>\n\t<albumartist id=\"" . $song->albumartist . "\"><![CDATA[" . $song->get_album_artist_fullname() . "]]></albumartist>\n\t<disk><![CDATA[" . $song->disk . "]]></disk>\n\t<track>" . $song->track . "</track>\n</$object_type>\n";
                    break;
                case 'playlist':
                    if ((int) $object_id === 0) {
                        $playlist = new Search((int) str_replace('smart_', '', (string) $object_id));
                        $playlist->format();

                        $playlist_name  = Search::get_name_byid(str_replace('smart_', '', (string) $object_id));
                        $playlist_user  = ($playlist->type !== 'public')
                            ? $playlist->username
                            : $playlist->type;
                        $last_count     = ((int)$playlist->last_count > 0) ? $playlist->last_count : 5000;
                        $playitem_total = ($playlist->limit == 0) ? $last_count : $playlist->limit;
                    } else {
                        $playlist = new Playlist($object_id);
                        $playlist->format();

                        $playlist_name  = $playlist->name;
                        $playlist_user  = $playlist->username;
                        $playitem_total = $playlist->get_media_count('song');
                    }
                    $songs = ($include) ? $playlist->get_items() : array();
                    $string .= "<$object_type id=\"" . $object_id . "\">\n\t<name><![CDATA[" . $playlist_name . "]]></name>\n\t<items>" . (int)$playitem_total . "</items>\n\t<owner><![CDATA[" . $playlist_user . "]]></owner>\n\t<type><![CDATA[" . $playlist->type . "]]></type>\n";
                    $playlist_track = 0;
                    foreach ($songs as $song_id) {
                        if ($song_id['object_type'] == 'song') {
                            $playlist_track++;
                            $string .= "\t\t<playlisttrack id=\"" . $song_id['object_id'] . "\">" . $playlist_track . "</playlisttrack>\n";
                        }
                    }
                    $string .= "</$object_type>\n";
                    break;
                case 'share':
                    $string .= self::shares($objects);
                    break;
                case 'podcast':
                    $podcast = new Podcast($object_id);
                    $podcast->format();
                    $string .= "<podcast id=\"$object_id\">\n\t<name><![CDATA[" . $podcast->get_fullname() . "]]></name>\n\t<description><![CDATA[" . $podcast->description . "]]></description>\n\t<language><![CDATA[" . $podcast->f_language . "]]></language>\n\t<copyright><![CDATA[" . $podcast->f_copyright . "]]></copyright>\n\t<feed_url><![CDATA[" . $podcast->feed . "]]></feed_url>\n\t<generator><![CDATA[" . $podcast->f_generator . "]]></generator>\n\t<website><![CDATA[" . $podcast->f_website . "]]></website>\n\t<build_date><![CDATA[" . $podcast->f_lastbuilddate . "]]></build_date>\n\t<sync_date><![CDATA[" . $podcast->f_lastsync . "]]></sync_date>\n\t<public_url><![CDATA[" . $podcast->link . "]]></public_url>\n";
                    if ($include) {
                        $episodes = $podcast->get_episodes();
                        foreach ($episodes as $episode_id) {
                            $string .= self::podcast_episodes(array($episode_id), $user, false);
                        }
                    }
                    $string .= "\t</podcast>\n";
                    break;
                case 'podcast_episode':
                    $string .= self::podcast_episodes($objects, $user);
                    break;
                case 'video':
                    $string .= self::videos($objects, $user);
                    break;
            }
        } // end foreach objects

        return Xml_Data::output_xml($string, $full_xml);
    } // indexes

    /**
     * licenses
     *
     * This returns licenses to the user, in a pretty xml document with the information
     *
     * @param    array    $licenses    (description here...)
     * @return    string    return xml
     */
    public static function licenses($licenses)
    {
        if ((count($licenses) > self::$limit || self::$offset > 0) && self::$limit) {
            $licenses = array_splice($licenses, self::$offset, self::$limit);
        }
        $string = "<total_count>" . count($licenses) . "</total_count>\n";

        foreach ($licenses as $license_id) {
            $license = new license($license_id);
            $string .= "<license id=\"$license_id\">\n\t<name><![CDATA[" . $license->name . "]]></name>\n\t<description><![CDATA[" . $license->description . "]]></description>\n\t<external_link><![CDATA[" . $license->external_link . "]]></external_link>\n</license>\n";
        } // end foreach

        return Xml_Data::output_xml($string);
    } // licenses

    /**
     * tags
     *
     * This returns tags to the user, in a pretty xml document with the information
     *
     * @param    array    $tags    (description here...)
     * @return    string    return xml
     */
    public static function tags($tags)
    {
        if ((count($tags) > self::$limit || self::$offset > 0) && self::$limit) {
            $tags = array_splice($tags, self::$offset, self::$limit);
        }
        $string = "<total_count>" . count($tags) . "</total_count>\n";

        foreach ($tags as $tag_id) {
            $tag    = new Tag($tag_id);
            $counts = $tag->count();
            $string .= "<tag id=\"$tag_id\">\n\t<name><![CDATA[" . $tag->name . "]]></name>\n\t<albums>" . (int) ($counts['album'] ?? 0) . "</albums>\n\t<artists>" . (int) ($counts['artist'] ?? 0) . "</artists>\n\t<songs>" . (int) ($counts['song'] ?? 0) . "</songs>\n\t<videos>" . (int) ($counts['video'] ?? 0) . "</videos>\n\t<playlists>" . (int) ($counts['playlist'] ?? 0) . "</playlists>\n\t<stream>" . (int) ($counts['live_stream'] ?? 0) . "</stream>\n</tag>\n";
        } // end foreach

        return Xml_Data::output_xml($string);
    } // tags

    /**
     * artists
     *
     * This takes an array of artists and then returns a pretty xml document with the information
     * we want
     *
     * @param array $artists (description here...)
     * @param array $include Array of other items to include
     * @param    User     $user
     * @param boolean $full_xml whether to return a full XML document or just the node
     * @return    string    return xml
     */
    public static function artists($artists, $include, $user, $full_xml = true)
    {
        if ((count($artists) > self::$limit || self::$offset > 0) && (self::$limit && $full_xml)) {
            $artists = array_splice($artists, self::$offset, self::$limit);
        }
        $string = ($full_xml) ? "<total_count>" . count($artists) . "</total_count>\n" : '';

        Rating::build_cache('artist', $artists);

        foreach ($artists as $artist_id) {
            $artist = new Artist($artist_id);
            if (!isset($artist->id)) {
                continue;
            }
            $artist->format();

            $rating      = new Rating($artist_id, 'artist');
            $user_rating = $rating->get_user_rating($user->getId());
            $flag        = new Userflag($artist_id, 'artist');
            $tag_string  = self::tags_string($artist->tags);

            // Build the Art URL, include session
            $art_url = AmpConfig::get('web_path') . '/image.php?object_id=' . $artist_id . '&object_type=artist&auth=' . scrub_out(Core::get_request('auth'));

            // Handle includes
            if (in_array("albums", $include)) {
                $albums = self::albums(static::getAlbumRepository()->getAlbumByArtist($artist_id), array(), $user, false);
            } else {
                $albums = $artist->album_count;
            }
            if (in_array("songs", $include)) {
                $songs = self::songs(static::getSongRepository()->getByArtist($artist_id), $user, false);
            } else {
                $songs = $artist->song_count;
            }

            $string .= "<artist id=\"" . $artist->id . "\">\n\t<name><![CDATA[" . $artist->get_fullname() . "]]></name>\n" . $tag_string . "\t<albums>" . $albums . "</albums>\n\t<albumcount>" . $artist->album_count . "</albumcount>\n\t<songs>" . $songs . "</songs>\n\t<songcount>" . $artist->song_count . "</songcount>\n\t<art><![CDATA[" . $art_url . "]]></art>\n\t<flag>" . (!$flag->get_flag($user->id, false) ? 0 : 1) . "</flag>\n\t<preciserating>" . $user_rating . "</preciserating>\n\t<rating>" . $user_rating . "</rating>\n\t<averagerating>" . (string) ($rating->get_average_rating() ?: null) . "</averagerating>\n\t<mbid><![CDATA[" . $artist->mbid . "]]></mbid>\n\t<summary><![CDATA[" . $artist->summary . "]]></summary>\n\t<time><![CDATA[" . $artist->time . "]]></time>\n\t<yearformed>" . $artist->yearformed . "</yearformed>\n\t<placeformed><![CDATA[" . $artist->placeformed . "]]></placeformed>\n</artist>\n";
        } // end foreach artists

        return Xml_Data::output_xml($string, $full_xml);
    } // artists

    /**
     * albums
     *
     * This echos out a standard albums XML document, it pays attention to the limit
     *
     * @param integer[] $albums (description here...)
     * @param array $include Array of other items to include
     * @param User $user
     * @param boolean $full_xml whether to return a full XML document or just the node
     * @return    string    return xml
     */
    public static function albums($albums, $include, $user, $full_xml = true)
    {
        if ($include == null || $include == '') {
            $include = array();
        }
        if (is_string($include)) {
            $include = explode(',', $include);
        }

        if ((count($albums) > self::$limit || self::$offset > 0) && (self::$limit && $full_xml)) {
            $albums = array_splice($albums, self::$offset, self::$limit);
        }
        $string = ($full_xml) ? "<total_count>" . count($albums) . "</total_count>\n" : '';

        Rating::build_cache('album', $albums);

        foreach ($albums as $album_id) {
            $album = new Album($album_id);
            if (!isset($album->id)) {
                continue;
            }
            $album->format();

            $rating      = new Rating($album_id, 'album');
            $user_rating = $rating->get_user_rating($user->getId());
            $flag        = new Userflag($album_id, 'album');

            // Build the Art URL, include session
            $art_url = AmpConfig::get('web_path') . '/image.php?object_id=' . $album->id . '&object_type=album&auth=' . scrub_out(Core::get_request('auth'));

            $string .= "<album id=\"" . $album->id . "\">\n\t<name><![CDATA[" . $album->get_fullname() . "]]></name>\n";

            // Do a little check for artist stuff
            if ($album->get_album_artist_fullname() != "") {
                $string .= "\t<artist id=\"$album->album_artist\"><![CDATA[" . $album->f_album_artist_name . "]]></artist>\n";
            } elseif ($album->artist_count != 1) {
                $string .= "\t<artist id=\"0\"><![CDATA[Various]]></artist>\n";
            } else {
                $string .= "\t<artist id=\"$album->album_artist\"><![CDATA[" . $album->get_artist_fullname() . "]]></artist>\n";
            }

            // Handle includes
            if (in_array("songs", $include) && isset($album->id)) {
                $songs = self::songs(static::getAlbumRepository()->getSongs($album->id), $user, false);
            } else {
                $songs = $album->song_count;
            }

            $string .= "\t<time>" . $album->total_duration . "</time>\n\t<year>" . $album->year . "</year>\n\t<tracks>" . $songs . "</tracks>\n\t<songcount>" . $album->song_count . "</songcount>\n\t<type>" . $album->release_type . "</type>\n\t<disk>" . $album->disk_count . "</disk>\n" . self::tags_string($album->tags) . "\t<art><![CDATA[" . $art_url . "]]></art>\n\t<flag>" . (!$flag->get_flag($user->getId(), false) ? 0 : 1) . "</flag>\n\t<preciserating>" . $user_rating . "</preciserating>\n\t<rating>" . $user_rating . "</rating>\n\t<averagerating>" . ($rating->get_average_rating() ?: null) . "</averagerating>\n\t<mbid><![CDATA[" . $album->mbid . "]]></mbid>\n</album>\n";
        } // end foreach

        return Xml_Data::output_xml($string, $full_xml);
    } // albums

    /**
     * playlists
     *
     * This takes an array of playlist ids and then returns a nice pretty XML document
     *
     * @param  array   $playlists Playlist id's to include
     * @param  User    $user
     * @return string  return xml
     */
    public static function playlists($playlists, $user)
    {
        if ((count($playlists) > self::$limit || self::$offset > 0) && self::$limit) {
            $playlists = array_slice($playlists, self::$offset, self::$limit);
        }
        $hide_dupe_searches = (bool)Preference::get_by_user($user->getId(), 'api_hide_dupe_searches');
        $string             = "<total_count>" . count($playlists) . "</total_count>\n";

        // Foreach the playlist ids
        foreach ($playlists as $playlist_id) {
            $playlist_names = array();
            /**
             * Strip smart_ from playlist id and compare to original
             * smartlist = 'smart_1'
             * playlist  = 1000000
             */
            if ((int) $playlist_id === 0) {
                $playlist = new Search((int) str_replace('smart_', '', (string) $playlist_id));
                if ($hide_dupe_searches && $playlist->user == $user->getId() && in_array($playlist->name, $playlist_names)) {
                    continue;
                }
                $object_type    = 'search';
                $art_url        = Art::url($playlist->id, $object_type, Core::get_request('auth'));
                $last_count     = ((int)$playlist->last_count > 0) ? $playlist->last_count : 5000;
                $playitem_total = ($playlist->limit == 0) ? $last_count : $playlist->limit;
            } else {
                $playlist       = new Playlist($playlist_id);
                $object_type    = 'playlist';
                $art_url        = Art::url($playlist_id, $object_type, Core::get_request('auth'));
                $playitem_total = $playlist->get_media_count('song');
                if ($hide_dupe_searches && $playlist->user == $user->getId()) {
                    $playlist_names[] = $playlist->name;
                }
            }
            $playlist_name = $playlist->get_fullname();
            $playlist_user = $playlist->username;
            $playlist_type = $playlist->type;

            $rating      = new Rating($playlist_id, $object_type);
            $user_rating = $rating->get_user_rating($user->getId());
            $flag        = new Userflag($playlist_id, $object_type);

            // Build this element
            $string .= "<playlist id=\"" . $playlist_id . "\">\n\t<name><![CDATA[" . $playlist_name . "]]></name>\n\t<owner><![CDATA[" . $playlist_user . "]]></owner>\n\t<items>" . (int)$playitem_total . "</items>\n\t<type>" . $playlist_type . "</type>\n\t<art><![CDATA[" . $art_url . "]]></art>\n\t<flag>" . (!$flag->get_flag($user->getId(), false) ? 0 : 1) . "</flag>\n\t<preciserating>" . $user_rating . "</preciserating>\n\t<rating>" . $user_rating . "</rating>\n\t<averagerating>" . (string) ($rating->get_average_rating() ?: null) . "</averagerating>\n</playlist>\n";
        } // end foreach

        return Xml_Data::output_xml($string);
    } // playlists

    /**
     * shares
     *
     * This returns shares to the user, in a pretty xml document with the information
     *
     * @param    array    $shares    (description here...)
     * @return    string    return xml
     */
    public static function shares($shares)
    {
        if ((count($shares) > self::$limit || self::$offset > 0) && self::$limit) {
            $shares = array_splice($shares, self::$offset, self::$limit);
        }
        $string = "<total_count>" . count($shares) . "</total_count>\n";

        foreach ($shares as $share_id) {
            $share = new Share($share_id);
            $string .= "<share id=\"$share_id\">\n\t<name><![CDATA[" . $share->getObjectName() . "]]></name>\n\t<user><![CDATA[" . $share->getUserName() . "]]></user>\n\t<allow_stream>" . (int) $share->allow_stream . "</allow_stream>\n\t<allow_download>" . (int) $share->allow_download . "</allow_download>\n\t<creation_date><![CDATA[" . $share->creation_date . "]]></creation_date>\n\t<lastvisit_date><![CDATA[" . $share->lastvisit_date . "]]></lastvisit_date>\n\t<object_type><![CDATA[" . $share->object_type . "]]></object_type>\n\t<object_id>" . $share->object_id . "</object_id>\n\t<expire_days>" . $share->expire_days . "</expire_days>\n\t<max_counter>" . $share->max_counter . "</max_counter>\n\t<counter>" . $share->counter . "</counter>\n\t<secret><![CDATA[" . $share->secret . "]]></secret>\n\t<public_url><![CDATA[" . $share->public_url . "]]></public_url>\n\t<description><![CDATA[" . $share->description . "]]></description>\n</share>\n";
        } // end foreach

        return Xml_Data::output_xml($string);
    } // shares

    /**
     * catalogs
     *
     * This returns catalogs to the user, in a pretty xml document with the information
     *
     * @param  integer[] $catalogs group of catalog id's
     * @return string return xml
     */
    public static function catalogs($catalogs)
    {
        if ((count($catalogs) > self::$limit || self::$offset > 0) && self::$limit) {
            $catalogs = array_splice($catalogs, self::$offset, self::$limit);
        }
        $string = "<total_count>" . count($catalogs) . "</total_count>\n";

        foreach ($catalogs as $catalog_id) {
            $catalog = Catalog::create_from_id($catalog_id);
            $catalog->format();
            $string .= "<catalog id=\"$catalog_id\">\n\t<name><![CDATA[" . $catalog->name . "]]></name>\n\t<type><![CDATA[" . $catalog->catalog_type . "]]></type>\n\t<gather_types><![CDATA[" . $catalog->gather_types . "]]></gather_types>\n\t<enabled>" . $catalog->enabled . "</enabled>\n\t<last_add><![CDATA[" . $catalog->f_add . "]]></last_add>\n\t<last_clean><![CDATA[" . $catalog->f_clean . "]]></last_clean>\n\t<last_update><![CDATA[" . $catalog->f_update . "]]></last_update>\n\t<path><![CDATA[" . $catalog->f_info . "]]></path>\n\t<rename_pattern><![CDATA[" . $catalog->rename_pattern . "]]></rename_pattern>\n\t<sort_pattern><![CDATA[" . $catalog->sort_pattern . "]]></sort_pattern>\n</catalog>\n";
        } // end foreach

        return Xml_Data::output_xml($string);
    } // catalogs

    /**
     * podcasts
     *
     * This returns podcasts to the user, in a pretty xml document with the information
     *
     * @param  array   $podcasts    (description here...)
     * @param  User    $user
     * @param  boolean $episodes include the episodes of the podcast // optional
     * @return string  return xml
     */
    public static function podcasts($podcasts, $user, $episodes = false)
    {
        if ((count($podcasts) > self::$limit || self::$offset > 0) && self::$limit) {
            $podcasts = array_splice($podcasts, self::$offset, self::$limit);
        }
        $string = "<total_count>" . count($podcasts) . "</total_count>\n";

        foreach ($podcasts as $podcast_id) {
            $podcast = new Podcast($podcast_id);
            $podcast->format();
            $rating      = new Rating($podcast_id, 'podcast');
            $user_rating = $rating->get_user_rating($user->getId());
            $flag        = new Userflag($podcast_id, 'podcast');
            $art_url     = Art::url($podcast_id, 'podcast', Core::get_request('auth'));
            $string .= "<podcast id=\"$podcast_id\">\n\t<name><![CDATA[" . $podcast->get_fullname() . "]]></name>\n\t<description><![CDATA[" . $podcast->description . "]]></description>\n\t<language><![CDATA[" . $podcast->f_language . "]]></language>\n\t<copyright><![CDATA[" . $podcast->f_copyright . "]]></copyright>\n\t<feed_url><![CDATA[" . $podcast->feed . "]]></feed_url>\n\t<generator><![CDATA[" . $podcast->f_generator . "]]></generator>\n\t<website><![CDATA[" . $podcast->f_website . "]]></website>\n\t<build_date><![CDATA[" . $podcast->f_lastbuilddate . "]]></build_date>\n\t<sync_date><![CDATA[" . $podcast->f_lastsync . "]]></sync_date>\n\t<public_url><![CDATA[" . $podcast->link . "]]></public_url>\n\t<art><![CDATA[" . $art_url . "]]></art>\n\t<flag>" . (!$flag->get_flag($user->getId(), false) ? 0 : 1) . "</flag>\n\t<preciserating>" . $user_rating . "</preciserating>\n\t<rating>" . $user_rating . "</rating>\n\t<averagerating>" . (string) ($rating->get_average_rating() ?: null) . "</averagerating>\n";
            if ($episodes) {
                $items = $podcast->get_episodes();
                if (count($items) > 0) {
                    $string .= self::podcast_episodes($items, $user, false);
                }
            }
            $string .= "\t</podcast>\n";
        } // end foreach

        return Xml_Data::output_xml($string);
    } // podcasts

    /**
     * podcast_episodes
     *
     * This returns podcasts to the user, in a pretty xml document with the information
     *
     * @param  integer[] $podcast_episodes Podcast_Episode id's to include
     * @param  User      $user
     * @param  boolean   $full_xml whether to return a full XML document or just the node
     * @return string    return xml
     */
    public static function podcast_episodes($podcast_episodes, $user, $full_xml = true)
    {
        if ((count($podcast_episodes) > self::$limit || self::$offset > 0) && (self::$limit && $full_xml)) {
            $podcast_episodes = array_splice($podcast_episodes, self::$offset, self::$limit);
        }
        $string = ($full_xml) ? "<total_count>" . count($podcast_episodes) . "</total_count>\n" : '';

        foreach ($podcast_episodes as $episode_id) {
            $episode = new Podcast_Episode($episode_id);
            $episode->format();
            $rating      = new Rating($episode_id, 'podcast_episode');
            $user_rating = $rating->get_user_rating($user->getId());
            $flag        = new Userflag($episode_id, 'podcast_episode');
            $art_url     = Art::url($episode->podcast, 'podcast', Core::get_request('auth'));
            $string .= "\t<podcast_episode id=\"$episode_id\">\n\t\t<title><![CDATA[" . $episode->get_fullname() . "]]></title>\n\t\t<name><![CDATA[" . $episode->get_fullname() . "]]></name>\n\t\t<description><![CDATA[" . $episode->f_description . "]]></description>\n\t\t<category><![CDATA[" . $episode->f_category . "]]></category>\n\t\t<author><![CDATA[" . $episode->f_author . "]]></author>\n\t\t<author_full><![CDATA[" . $episode->f_artist_full . "]]></author_full>\n\t\t<website><![CDATA[" . $episode->f_website . "]]></website>\n\t\t<pubdate><![CDATA[" . $episode->f_pubdate . "]]></pubdate>\n\t\t<state><![CDATA[" . $episode->f_state . "]]></state>\n\t\t<filelength><![CDATA[" . $episode->f_time_h . "]]></filelength>\n\t\t<filesize><![CDATA[" . $episode->f_size . "]]></filesize>\n\t\t<filename><![CDATA[" . $episode->f_file . "]]></filename>\n\t\t<mime><![CDATA[" . $episode->mime . "]]></mime>\n\t\t<public_url><![CDATA[" . $episode->link . "]]></public_url>\n\t\t<url><![CDATA[" . $episode->play_url('', 'api', false, $user->getId(), $user->streamtoken) . "]]></url>\n\t\t<catalog>" . $episode->catalog . "</catalog>\n\t\t<art><![CDATA[" . $art_url . "]]></art>\n\t\t<flag>" . (!$flag->get_flag($user->getId(), false) ? 0 : 1) . "</flag>\n\t\t<preciserating>" . $user_rating . "</preciserating>\n\t\t<rating>" . $user_rating . "</rating>\n\t\t<averagerating>" . (string) ($rating->get_average_rating() ?: null) . "</averagerating>\n\t\t<played>" . $episode->played . "</played>\n";
            $string .= "\t</podcast_episode>\n";
        } // end foreach

        return Xml_Data::output_xml($string, $full_xml);
    } // podcast_episodes

    /**
     * songs
     *
     * This returns an xml document from an array of song ids. (Spiffy isn't it!)
     * @param integer[] $songs
     * @param  User $user
     * @param boolean $full_xml
     * @return string return xml
     */
    public static function songs($songs, $user, $full_xml = true)
    {
        if ((count($songs) > self::$limit || self::$offset > 0) && (self::$limit && $full_xml)) {
            $songs = array_slice($songs, self::$offset, self::$limit);
        }
        $string = ($full_xml) ? "<total_count>" . count($songs) . "</total_count>\n" : '';

        Song::build_cache($songs);
        Stream::set_session(Core::get_request('auth'));

        $playlist_track = 0;

        // Foreach the ids!
        foreach ($songs as $song_id) {
            $song = new Song($song_id);

            // If the song id is invalid/null
            if (!$song->id) {
                continue;
            }

            $song->format();
            $tag_string  = self::tags_string(Tag::get_top_tags('song', $song_id));
            $rating      = new Rating($song_id, 'song');
            $user_rating = $rating->get_user_rating($user->getId());
            $flag        = new Userflag($song_id, 'song');
            $art_url     = Art::url($song->album, 'album', Core::get_request('auth'));
            $play_url    = $song->play_url('', 'api', false, $user->id, $user->streamtoken);
            $playlist_track++;

            $string .= "<song id=\"" . $song->id . "\">\n\t<title><![CDATA[" . $song->title . "]]></title>\n\t<name><![CDATA[" . $song->title . "]]></name>\n\t<artist id=\"" . $song->artist . "\"><![CDATA[" . $song->get_artist_fullname() . "]]></artist>\n\t<album id=\"" . $song->album . "\"><![CDATA[" . $song->get_album_fullname() . "]]></album>\n\t<albumartist id=\"" . $song->albumartist . "\"><![CDATA[" . $song->get_album_artist_fullname() . "]]></albumartist>\n\t<disk><![CDATA[" . $song->disk . "]]></disk>\n\t<track>" . $song->track . "</track>\n" . $tag_string . "\t<filename><![CDATA[" . $song->file . "]]></filename>\n\t<playlisttrack>" . $playlist_track . "</playlisttrack>\n\t<time>" . $song->time . "</time>\n\t<year>" . $song->year . "</year>\n\t<bitrate>" . $song->bitrate . "</bitrate>\n\t<rate>" . $song->rate . "</rate>\n\t<mode><![CDATA[" . $song->mode . "]]></mode>\n\t<mime><![CDATA[" . $song->mime . "]]></mime>\n\t<url><![CDATA[" . $play_url . "]]></url>\n\t<size>" . $song->size . "</size>\n\t<mbid><![CDATA[" . $song->mbid . "]]></mbid>\n\t<album_mbid><![CDATA[" . $song->album_mbid . "]]></album_mbid>\n\t<artist_mbid><![CDATA[" . $song->artist_mbid . "]]></artist_mbid>\n\t<albumartist_mbid><![CDATA[" . $song->albumartist_mbid . "]]></albumartist_mbid>\n\t<art><![CDATA[" . $art_url . "]]></art>\n\t<flag>" . (!$flag->get_flag($user->getId(), false) ? 0 : 1) . "</flag>\n\t<preciserating>" . $user_rating . "</preciserating>\n\t<rating>" . $user_rating . "</rating>\n\t<averagerating>" . (string) ($rating->get_average_rating() ?: null) . "</averagerating>\n\t<playcount>" . $song->played . "</playcount>\n\t<catalog>" . $song->catalog . "</catalog>\n\t<composer><![CDATA[" . $song->composer . "]]></composer>\n\t<channels>" . $song->channels . "</channels>\n\t<comment><![CDATA[" . $song->comment . "]]></comment>\n\t<license><![CDATA[" . $song->f_license . "]]></license>\n\t<publisher><![CDATA[" . $song->label . "]]></publisher>\n\t<language>" . $song->language . "</language>\n\t<replaygain_album_gain>" . $song->replaygain_album_gain . "</replaygain_album_gain>\n\t<replaygain_album_peak>" . $song->replaygain_album_peak . "</replaygain_album_peak>\n\t<replaygain_track_gain>" . $song->replaygain_track_gain . "</replaygain_track_gain>\n\t<replaygain_track_peak>" . $song->replaygain_track_peak . "</replaygain_track_peak>\n";
            if (Song::isCustomMetadataEnabled()) {
                foreach ($song->getMetadata() as $metadata) {
                    $meta_name = str_replace(array(' ', '(', ')', '/', '\\', '#'), '_', $metadata->getField()->getName());
                    $string .= "\t<" . $meta_name . "><![CDATA[" . $metadata->getData() . "]]></" . $meta_name . ">\n";
                }
            }
            foreach ($song->tags as $tag) {
                $string .= "\t<genre><![CDATA[" . $tag['name'] . "]]></genre>\n";
            }

            $string .= "</song>\n";
        } // end foreach

        return Xml_Data::output_xml($string, $full_xml);
    } // songs

    /**
     * videos
     *
     * This builds the xml document for displaying video objects
     *
     * @param array $videos (description here...)
     * @param User  $user
     * @return   string   return xml
     */
    public static function videos($videos, $user)
    {
        if ((count($videos) > self::$limit || self::$offset > 0) && self::$limit) {
            $videos = array_slice($videos, self::$offset, self::$limit);
        }
        $string = "<total_count>" . count($videos) . "</total_count>\n";

        foreach ($videos as $video_id) {
            $video = new Video($video_id);
            $video->format();
            $rating      = new Rating($video_id, 'video');
            $user_rating = $rating->get_user_rating($user->getId());
            $flag        = new Userflag($video_id, 'video');
            $art_url     = Art::url($video_id, 'video', Core::get_request('auth'));

            $string .= "<video id=\"" . $video->id . "\">\n\t<title><![CDATA[" . $video->title . "]]></title>\n\t<name><![CDATA[" . $video->title . "]]></name>\n\t<mime><![CDATA[" . $video->mime . "]]></mime>\n\t<resolution><![CDATA[" . $video->f_resolution . "]]></resolution>\n\t<size>" . $video->size . "</size>\n" . self::tags_string($video->tags) . "\t<time><![CDATA[" . $video->time . "]]></time>\n\t<url><![CDATA[" . $video->play_url('', 'api', false, $user->getId(), $user->streamtoken) . "]]></url>\n\t<art><![CDATA[" . $art_url . "]]></art>\n\t<flag>" . (!$flag->get_flag($user->getId(), false) ? 0 : 1) . "</flag>\n\t<preciserating>" . $user_rating . "</preciserating>\n\t<rating>" . $user_rating . "</rating>\n\t<averagerating>" . (string) ($rating->get_average_rating() ?: null) . "</averagerating>\n</video>\n";
        } // end foreach

        return Xml_Data::output_xml($string);
    } // videos

    /**
     * democratic
     *
     * This handles creating an xml document for democratic items, this can be a little complicated
     * due to the votes and all of that
     *
     * @param array $object_ids Object IDs
     * @param User $user
     * @return   string     return xml
     */
    public static function democratic($object_ids, $user)
    {
        $democratic = Democratic::get_current_playlist($user);
        $string     = '';

        foreach ($object_ids as $row_id => $data) {
            $className  = ObjectTypeToClassNameMapper::map($data['object_type']);
            $song       = new $className($data['object_id']);
            $song->format();

            // FIXME: This is duplicate code and so wrong, functions need to be improved
            $tag           = new Tag($song->tags['0']);
            $song->genre   = $tag->id;
            $song->f_genre = $tag->name;

            $tag_string = self::tags_string($song->tags);
            $rating     = new Rating($song->id, 'song');
            $art_url    = Art::url($song->album, 'album', Core::get_request('auth'));
            $play_url   = $song->play_url('', 'api', false, $user->id, $user->streamtoken);

            $string .= "<song id=\"" . $song->id . "\">\n\t<title><![CDATA[" . $song->title . "]]></title>\n\t<name><![CDATA[" . $song->title . "]]></name>\n\t<artist id=\"" . $song->artist . "\"><![CDATA[" . $song->get_artist_fullname() . "]]></artist>\n\t<album id=\"" . $song->album . "\"><![CDATA[" . $song->get_album_fullname() . "]]></album>\n\t<genre id=\"" . $song->genre . "\"><![CDATA[" . $song->f_genre . "]]></genre>\n" . $tag_string . "\t<track>" . $song->track . "</track>\n\t<time><![CDATA[" . $song->time . "]]></time>\n\t<mime><![CDATA[" . $song->mime . "]]></mime>\n\t<url><![CDATA[" . $play_url . "]]></url>\n\t<size>" . $song->size . "</size>\n\t<art><![CDATA[" . $art_url . "]]></art>\n\t<preciserating>" . ($rating->get_user_rating($user->id) ?: null) . "</preciserating>\n\t<rating>" . ($rating->get_user_rating($user->id) ?: null) . "</rating>\n\t<averagerating>" . ($rating->get_average_rating() ?: null) . "</averagerating>\n\t<vote>" . $democratic->get_vote($row_id) . "</vote>\n</song>\n";
        } // end foreach

        return Xml_Data::output_xml($string);
    } // democratic

    /**
     * user
     *
     * This handles creating an xml document for a user
     *
     * @param  User   $user User
     * @param  bool   $fullinfo
     * @return string return xml
     */
    public static function user(User $user, $fullinfo)
    {
        $user->format();
        $string = "<user id=\"" . (string) $user->id . "\">\n\t<username><![CDATA[" . $user->username . "]]></username>\n";
        if ($fullinfo) {
            $string .= "\t<auth><![CDATA[" . $user->apikey . "]]></auth>\n\t<email><![CDATA[" . $user->email . "]]></email>\n\t<access><![CDATA[" . (string) $user->access . "]]></access>\n\t<fullname_public><![CDATA[" . (string) $user->fullname_public . "]]></fullname_public>\n\t<validation><![CDATA[" . $user->validation . "]]></validation>\n\t<disabled><![CDATA[" . (string) $user->disabled . "]]></disabled>\n";
        }
        $string .= "\t<create_date><![CDATA[" . (string) $user->create_date . "]]></create_date>\n\t<last_seen><![CDATA[" . (string) $user->last_seen . "]]></last_seen>\n\t<link><![CDATA[" . $user->link . "]]></link>\n\t<website><![CDATA[" . $user->website . "]]></website>\n\t<state><![CDATA[" . $user->state . "]]></state>\n\t<city><![CDATA[" . $user->city . "]]></city>\n";
        if ($user->fullname_public || $fullinfo) {
            $string .= "\t<fullname><![CDATA[" . $user->fullname . "]]></fullname>\n";
        }
        $string .= "</user>\n";

        return Xml_Data::output_xml($string);
    } // user

    /**
     * users
     *
     * This handles creating an xml document for an user list
     *
     * @param    integer[]    $users    User identifier list
     * @return    string    return xml
     */
    public static function users($users)
    {
        $string = "<users>\n";
        foreach ($users as $user_id) {
            $user = new User($user_id);
            $string .= "<user id=\"" . (string) $user->id . "\">\n\t<username><![CDATA[" . $user->username . "]]></username>\n</user>\n";
        }
        $string .= "</users>\n";

        return Xml_Data::output_xml($string);
    } // users

    /**
     * shouts
     *
     * This handles creating an xml document for a shout list
     *
     * @param    integer[]    $shouts    Shout identifier list
     * @return    string    return xml
     */
    public static function shouts($shouts)
    {
        $string = "<shouts>\n";
        foreach ($shouts as $shout_id) {
            $shout = new Shoutbox($shout_id);
            $user  = new User($shout->user);
            $string .= "\t<shout id=\"" . $shout_id . "\">\n\t\t<date>" . $shout->date . "</date>\n\t\t<text><![CDATA[" . $shout->text . "]]></text>\n";
            if ($user->id) {
                $string .= "\t\t<user id=\"" . (string) $user->id . "\">\n\t\t\t<username><![CDATA[" . $user->username . "]]></username>\n\t\t</user>\n";
            }
            $string .= "\t</shout>\n";
        }
        $string .= "</shouts>\n";

        return Xml_Data::output_xml($string);
    } // shouts

    /**
     * timeline
     *
     * This handles creating an xml document for an activity list
     *
     * @param    integer[]    $activities    Activity identifier list
     * @return    string    return xml
     */
    public static function timeline($activities)
    {
        $string = "<timeline>\n";
        foreach ($activities as $activity_id) {
            $activity = new Useractivity($activity_id);
            $user     = new User($activity->user);
            $string .= "\t<activity id=\"" . $activity_id . "\">\n\t\t<date>" . $activity->activity_date . "</date>\n\t\t<object_type><![CDATA[" . $activity->object_type . "]]></object_type>\n\t\t<object_id>" . $activity->object_id . "</object_id>\n\t\t<action><![CDATA[" . $activity->action . "]]></action>\n";
            if ($user->id) {
                $string .= "\t\t<user id=\"" . (string) $user->id . "\">\n\t\t\t<username><![CDATA[" . $user->username . "]]></username>\n\t\t</user>\n";
            }
            $string .= "\t</activity>\n";
        }
        $string .= "</timeline>";

        return self::_header() . $string . self::_footer();
    } // timeline

    /**
     * _header
     *
     * this returns a standard header, there are a few types
     * so we allow them to pass a type if they want to
     *
     * @param string $title
     * @return string Header xml tag
     */
    private static function _header($title = null)
    {
        switch (self::$type) {
            case 'xspf':
                $header = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n<playlist version = \"1\" xmlns=\"http://xspf.org/ns/0/\">\n<title>" . ($title ?? T_("Ampache XSPF Playlist")) . "</title>\n<creator>" . scrub_out(AmpConfig::get('site_title')) . "</creator>\n<annotation>" . scrub_out(AmpConfig::get('site_title')) . "</annotation>\n<info>" . AmpConfig::get('web_path') . "</info>\n<trackList>\n";
                break;
            case 'itunes':
                $header = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!-- XML Generated by Ampache v." . AmpConfig::get('version') . " -->\n";
                break;
            case 'rss':
                $header = "<?xml version=\"1.0\" encoding=\"" . AmpConfig::get('site_charset') . "\" ?>\n <!-- RSS Generated by Ampache v." . AmpConfig::get('version') . " on " . date("r", time()) . "-->\n<rss version=\"2.0\">\n<channel>\n";
                break;
            default:
                $header = "<?xml version=\"1.0\" encoding=\"" . AmpConfig::get('site_charset') . "\" ?>\n<root>\n";
                break;
        } // end switch

        return $header;
    } // _header

    /**
     * _footer
     *
     * this returns the footer for this document, these are pretty boring
     *
     * @return    string    Footer xml tag
     */
    private static function _footer()
    {
        switch (self::$type) {
            case 'itunes':
                $footer = "\t\t</dict>\t\n</dict>\n</plist>\n";
                break;
            case 'xspf':
                $footer = "</trackList>\n</playlist>\n";
                break;
            case 'rss':
                $footer = "\n</channel>\n</rss>\n";
                break;
            default:
                $footer = "\n</root>\n";
                break;
        } // end switch on type

        return $footer;
    } // _footer

    /**
     * @deprecated
     */
    private static function getSongRepository(): SongRepositoryInterface
    {
        global $dic;

        return $dic->get(SongRepositoryInterface::class);
    }

    /**
     * @deprecated
     */
    private static function getAlbumRepository(): AlbumRepositoryInterface
    {
        global $dic;

        return $dic->get(AlbumRepositoryInterface::class);
    }
} // end Xml_Data.class
