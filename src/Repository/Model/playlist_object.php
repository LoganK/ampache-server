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
 *
 */

namespace Ampache\Repository\Model;

use Ampache\Module\Authorization\Access;
use Ampache\Module\Util\InterfaceImplementationChecker;
use Ampache\Module\Util\ObjectTypeToClassNameMapper;
use Ampache\Config\AmpConfig;
use Ampache\Module\System\Core;
use Ampache\Module\Util\Ui;

/**
 * playlist_object
 * Abstracting out functionality needed by both normal and smart playlists
 */
abstract class playlist_object extends database_object implements library_item
{
    // Database variables
    /**
     * @var integer $id
     */
    public $id;
    /**
     * @var string $name
     */
    public $name;
    /**
     * @var integer $user
     */
    public $user;
    /**
     * @var string $user
     */
    public $username;
    /**
     * @var string $type
     */
    public $type;
    /**
     * @var string $link
     */
    public $link;
    /**
     * @var bool $has_art
     */
    public $has_art;
    /**
     * @var string $f_link
     */
    public $f_link;
    /**
     * @var string $f_type
     */
    public $f_type;
    /**
     * @var string $f_name
     */
    public $f_name;

    /**
     * @return array
     */
    abstract public function get_items();

    /**
     * format
     * This takes the current playlist object and gussies it up a little bit so it is presentable to the users
     * @param boolean $details
     */
    public function format($details = true)
    {
        // format shared lists using the username
        $this->f_name = (($this->user == Core::get_global('user')->id))
            ? scrub_out($this->name)
            : scrub_out($this->name . " (" . $this->username . ")");
        $this->f_type = ($this->type == 'private') ? Ui::get_icon('lock', T_('Private')) : '';
        $this->get_f_link();
    } // format

    /**
     * does the item have art?
     * @return bool
     */
    public function has_art()
    {
        if (!isset($this->has_art)) {
            $this->has_art = ($this instanceof Search)
                ? Art::has_db($this->id, 'search')
                : Art::has_db($this->id, 'playlist');
        }

        return $this->has_art;
    }

    /**
     * has_access
     * This function returns true or false if the current user
     * has access to this playlist
     * @param integer $user_id
     * @return boolean
     */
    public function has_access($user_id = null)
    {
        if (Access::check('interface', 100)) {
            return true;
        }
        if (!Access::check('interface', 25)) {
            return false;
        }
        // allow the owner
        if (($this->user == Core::get_global('user')->id) || ($this->user == $user_id)) {
            return true;
        }

        return false;
    } // has_access

    /**
     * @param string $filter_type
     * @return array
     */
    public function get_medias($filter_type = null)
    {
        $medias = $this->get_items();
        if ($filter_type) {
            $nmedias = array();
            foreach ($medias as $media) {
                if ($media['object_type'] == $filter_type) {
                    $nmedias[] = $media;
                }
            }
            $medias = $nmedias;
        }

        return $medias;
    }

    /**
     * Get item keywords for metadata searches.
     * @return array
     */
    public function get_keywords()
    {
        return array();
    }

    /**
     * @return string
     */
    public function get_fullname()
    {
        $show_fullname = AmpConfig::get('show_playlist_username');
        $my_playlist   = $this->user == Core::get_global('user')->id;
        $this->f_name  = ($my_playlist || !$show_fullname)
            ? $this->name
            : $this->name . " (" . $this->username . ")";

        return $this->f_name;
    }

    /**
     * Get item link.
     * @return string
     */
    public function get_link()
    {
        // don't do anything if it's formatted
        if (!isset($this->link)) {
            $web_path   = AmpConfig::get('web_path');
            $this->link = ($this instanceof Search)
                ? $web_path . '/smartplaylist.php?action=show_playlist&playlist_id=' . $this->id
                : $web_path . '/playlist.php?action=show_playlist&playlist_id=' . $this->id;
        }

        return $this->link;
    }

    /**
     * Get item link.
     * @return string
     */
    public function get_f_link()
    {
        // don't do anything if it's formatted
        if (!isset($this->f_link)) {
            $link_text    = scrub_out($this->get_fullname());
            $this->f_link = '<a href="' . $this->get_link() . '" title="' . $link_text . '">' . $link_text . '</a>';
        }

        return $this->f_link;
    }

    /**
     * @return null
     */
    public function get_parent()
    {
        return null;
    }

    /**
     * @return array
     */
    public function get_childrens()
    {
        return $this->get_items();
    }

    /**
     * Search for direct children of an object
     * @param string $name
     * @return array
     */
    public function get_children($name)
    {
        debug_event('playlist_object.abstract', 'get_children ' . $name, 5);

        return array();
    }

    /**
     * @return integer
     */
    public function get_user_owner()
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function get_default_art_kind()
    {
        return 'default';
    }

    /**
     * @return mixed|null
     */
    public function get_description()
    {
        return null;
    }

    /**
     * display_art
     * @param integer $thumb
     * @param boolean $force
     * @param boolean $link
     */
    public function display_art($thumb = 2, $force = false, $link = true)
    {
        if (AmpConfig::get('playlist_art') || $force) {
            $add_link  = ($link) ? $this->get_link() : null;
            $list_type = ($this instanceof Search)
                ? 'search'
                : 'playlist';
            Art::display($list_type, $this->id, $this->get_fullname(), $thumb, $add_link);
        }
    }

    /**
     * gather_art
     */
    public function gather_art($limit): array
    {
        $medias   = $this->get_medias();
        $count    = 0;
        $images   = array();
        $title    = T_('Playlist Items');
        $web_path = AmpConfig::get('web_path');
        shuffle($medias);
        foreach ($medias as $media) {
            if ($count >= $limit) {
                return $images;
            }
            if (InterfaceImplementationChecker::is_library_item($media['object_type'])) {
                if (!Art::has_db($media['object_id'], $media['object_type'])) {
                    $class_name = ObjectTypeToClassNameMapper::map($media['object_type']);
                    $libitem    = new $class_name($media['object_id']);
                    $parent     = $libitem->get_parent();
                    if ($parent !== null) {
                        $media = $parent;
                    }
                }
                $art = new Art($media['object_id'], $media['object_type']);
                if ($art->has_db_info()) {
                    $link     = $web_path . "/image.php?object_id=" . $media['object_id'] . "&object_type=" . $media['object_type'];
                    $images[] = ['url' => $link, 'mime' => $art->raw_mime, 'title' => $title];
                }
            }
            $count++;
        }

        return $images;
    }

    /**
     * get_catalogs
     *
     * Get all catalog ids related to this item.
     * @return integer[]
     */
    public function get_catalogs()
    {
        return array();
    }
} // end playlist_object.class
