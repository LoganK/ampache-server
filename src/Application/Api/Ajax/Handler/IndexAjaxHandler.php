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

declare(strict_types=0);

namespace Ampache\Application\Api\Ajax\Handler;

use Ampache\Module\Authorization\Access;
use Ampache\Module\Api\Ajax;
use Ampache\Config\AmpConfig;
use Ampache\Module\Statistics\Stats;
use Ampache\Module\Util\RequestParserInterface;
use Ampache\Repository\Model\Artist;
use Ampache\Repository\Model\Browse;
use Ampache\Repository\Model\Catalog;
use Ampache\Module\System\Core;
use Ampache\Repository\Model\Label;
use Ampache\Module\Util\Recommendation;
use Ampache\Repository\Model\Song;
use Ampache\Module\Util\SlideshowInterface;
use Ampache\Module\Util\Ui;
use Ampache\Repository\Model\Wanted;
use Ampache\Repository\AlbumRepositoryInterface;
use Ampache\Repository\LabelRepositoryInterface;
use Ampache\Repository\SongRepositoryInterface;
use Ampache\Repository\VideoRepositoryInterface;
use Ampache\Repository\WantedRepositoryInterface;

final class IndexAjaxHandler implements AjaxHandlerInterface
{
    private RequestParserInterface $requestParser;

    private SlideshowInterface $slideshow;

    private AlbumRepositoryInterface $albumRepository;

    private LabelRepositoryInterface $labelRepository;

    private SongRepositoryInterface $songRepository;

    private WantedRepositoryInterface $wantedRepository;

    private VideoRepositoryInterface $videoRepository;

    public function __construct(
        RequestParserInterface $requestParser,
        SlideshowInterface $slideshow,
        AlbumRepositoryInterface $albumRepository,
        LabelRepositoryInterface $labelRepository,
        SongRepositoryInterface $songRepository,
        WantedRepositoryInterface $wantedRepository,
        VideoRepositoryInterface $videoRepository
    ) {
        $this->requestParser    = $requestParser;
        $this->slideshow        = $slideshow;
        $this->albumRepository  = $albumRepository;
        $this->labelRepository  = $labelRepository;
        $this->songRepository   = $songRepository;
        $this->wantedRepository = $wantedRepository;
        $this->videoRepository  = $videoRepository;
    }

    public function handle(): void
    {
        $results = array();
        $action  = $this->requestParser->getFromRequest('action');
        $user    = Core::get_global('user');
        $moment  = (int) AmpConfig::get('of_the_moment');
        // filter album and video of the Moment instead of a hardcoded value
        if (!$moment > 0) {
            $moment = 6;
        }

        // Switch on the actions
        switch ($action) {
            case 'top_tracks':
                $artist          = new Artist($this->requestParser->getFromRequest('artist'));
                $object_ids      = $this->songRepository->getTopSongsByArtist($artist, (int)AmpConfig::get('popular_threshold', 10));
                $browse          = new Browse();
                $hide_columns    = array('cel_artist');
                $limit_threshold = AmpConfig::get('stats_threshold', 7);
                ob_start();
                require_once Ui::find_template('show_top_tracks.inc.php');
                $results['top_tracks'] = ob_get_clean();
                break;
            case 'random_albums':
                $albums = $this->albumRepository->getRandom(
                    $user->id,
                    $moment
                );
                if (count($albums) && is_array($albums)) {
                    ob_start();
                    require_once Ui::find_template('show_random_albums.inc.php');
                    $results['random_selection'] = ob_get_clean();
                } else {
                    $results['random_selection'] = '<!-- None found -->';

                    if (Access::check('interface', 75)) {
                        $catalogs = Catalog::get_catalogs();
                        if (count($catalogs) == 0) {
                            /* HINT: %1 and %2 surround "add a Catalog" to make it into a link */
                            $results['random_selection'] = sprintf(T_('No Catalog configured yet. To start streaming your media, you now need to %1$s add a Catalog %2$s'), '<a href="' . AmpConfig::get('web_path') . '/admin/catalog.php?action=show_add_catalog">', '</a>.<br /><br />');
                        }
                    }
                }
                break;
            case 'random_videos':
                $videos = $this->videoRepository->getRandom(
                    $user->id,
                    $moment
                );
                if (count($videos) && is_array($videos)) {
                    ob_start();
                    require_once Ui::find_template('show_random_videos.inc.php');
                    $results['random_video_selection'] = ob_get_clean();
                } else {
                    $results['random_video_selection'] = '<!-- None found -->';
                }
                break;
            case 'artist_info':
                if (AmpConfig::get('lastfm_api_key') && (array_key_exists('artist', $_REQUEST) || array_key_exists('fullname', $_REQUEST))) {
                    if (array_key_exists('artist', $_REQUEST)) {
                        $artist = new Artist($this->requestParser->getFromRequest('artist'));
                        $artist->format();
                        $biography = Recommendation::get_artist_info($artist->id);
                    } else {
                        $fullname  = $this->requestParser->getFromRequest('fullname');
                        $artist    = new Wanted(Wanted::get_wanted_by_name($fullname));
                        $biography = Recommendation::get_artist_info_by_name(rawurldecode($fullname));
                    }
                    ob_start();
                    require_once Ui::find_template('show_artist_info.inc.php');
                    $results['artist_biography'] = ob_get_clean();
                }
                break;
            case 'similar_artist':
                if (AmpConfig::get('show_similar') && array_key_exists('artist', $_REQUEST)) {
                    $artist = new Artist($this->requestParser->getFromRequest('artist'));
                    $artist->format();
                    $limit_threshold = AmpConfig::get('stats_threshold', 7);
                    $object_ids      = array();
                    $missing_objects = array();
                    if ($similars = Recommendation::get_artists_like($artist->id, 10, !AmpConfig::get('wanted'))) {
                        foreach ($similars as $similar) {
                            if ($similar['id']) {
                                $object_ids[] = $similar['id'];
                            } else {
                                $missing_objects[] = $similar;
                            }
                        }
                    }
                    ob_start();
                    require_once Ui::find_template('show_recommended_artists.inc.php');
                    $results['similar_artist'] = ob_get_clean();
                }
                break;
            case 'similar_songs':
                $artist     = new Artist($this->requestParser->getFromRequest('artist'));
                $similars   = Recommendation::get_artists_like($artist->id);
                $object_ids = array();
                if (!empty($similars)) {
                    foreach ($similars as $similar) {
                        if ($similar['id']) {
                            $similar_artist = new Artist($similar['id']);
                            // get the songs in a random order for even more chaos
                            $object_ids = array_merge($object_ids, $this->songRepository->getRandomByArtist($similar_artist));
                        }
                    }
                }
                // randomize and slice
                shuffle($object_ids);
                $object_ids      = array_slice($object_ids, 0, (int)AmpConfig::get('popular_threshold', 10));
                $browse          = new Browse();
                $hide_columns    = array();
                $limit_threshold = AmpConfig::get('stats_threshold', 7);
                ob_start();
                require_once Ui::find_template('show_similar_songs.inc.php');
                $results['similar_songs'] = ob_get_clean();
                break;
            case 'similar_now_playing':
                $media_id = $this->requestParser->getFromRequest('media_id');
                if (AmpConfig::get('show_similar') && isset($media_id) && array_key_exists('media_artist', $_REQUEST)) {
                    $artists = Recommendation::get_artists_like($this->requestParser->getFromRequest('media_artist'), 3, false);
                    $songs   = Recommendation::get_songs_like($media_id, 3);
                    ob_start();
                    require_once Ui::find_template('show_now_playing_similar.inc.php');
                    $results['similar_items_' . $media_id] = ob_get_clean();
                }
                break;
            case 'labels':
                if (AmpConfig::get('label') && array_key_exists('artist', $_REQUEST)) {
                    $labels     = $this->labelRepository->getByArtist((int)$this->requestParser->getFromRequest('artist'));
                    $object_ids = array();
                    if (count($labels) > 0) {
                        foreach ($labels as $labelid => $label) {
                            $object_ids[] = $labelid;
                        }
                    }
                    $browse = new Browse();
                    $browse->set_type('label');
                    $browse->set_simple_browse(false);
                    $browse->save_objects($object_ids);
                    $browse->store();
                    ob_start();
                    require_once Ui::find_template('show_labels.inc.php');
                    $results['labels'] = ob_get_clean();
                }
                break;
            case 'wanted_missing_albums':
                if (AmpConfig::get('wanted') && (array_key_exists('artist', $_REQUEST) || array_key_exists('artist_mbid', $_REQUEST))) {
                    if (array_key_exists('artist', $_REQUEST)) {
                        $artist = new Artist((int)$this->requestParser->getFromRequest('artist'));
                        if (!empty($artist->mbid)) {
                            $walbums = Wanted::get_missing_albums($artist);
                        } else {
                            debug_event('index.ajax', 'Cannot get missing albums: MusicBrainz ID required.', 3);
                        }
                    } elseif (array_key_exists('artist_mbid', $_REQUEST)) {
                        $walbums = Wanted::get_missing_albums(null, $_REQUEST['artist_mbid']);
                    } else {
                        $walbums = array();
                    }

                    ob_start();
                    require_once Ui::find_template('show_missing_albums.inc.php');
                    $results['missing_albums'] = ob_get_clean();
                }
                break;
            case 'add_wanted':
                if (AmpConfig::get('wanted') && array_key_exists('mbid', $_REQUEST)) {
                    $mbid = $this->requestParser->getFromRequest('mbid');
                    if (!array_key_exists('artist', $_REQUEST)) {
                        $artist_mbid = $_REQUEST['artist_mbid'];
                        $artist      = null;
                    } else {
                        $artist      = (int)$this->requestParser->getFromRequest('artist');
                        $aobj        = new Artist($artist);
                        $artist_mbid = $aobj->mbid;
                    }
                    $name = $this->requestParser->getFromRequest('name');
                    $year = $this->requestParser->getFromRequest('year');

                    if (!$this->wantedRepository->find($mbid, $user->id)) {
                        Wanted::add_wanted($mbid, $artist, $artist_mbid, $name, $year);
                        ob_start();
                        $walbum = new Wanted(Wanted::get_wanted($mbid));
                        $walbum->show_action_buttons();
                        $results['wanted_action_' . $mbid] = ob_get_clean();
                    } else {
                        debug_event('index.ajax', 'Already wanted, skipped.', 5);
                    }
                }
                break;
            case 'remove_wanted':
                if (AmpConfig::get('wanted') && array_key_exists('mbid', $_REQUEST)) {
                    $mbid    = $this->requestParser->getFromRequest('mbid');
                    $user_id = $user->has_access(75) ? null : $user->id;
                    $walbum  = new Wanted(Wanted::get_wanted($mbid));

                    $this->wantedRepository->deleteByMusicbrainzId($mbid, $user_id);
                    ob_start();
                    $walbum->accepted = false;
                    $walbum->id       = 0;
                    $walbum->show_action_buttons();
                    $results['wanted_action_' . $mbid] = ob_get_clean();
                }
                break;
            case 'accept_wanted':
                if (AmpConfig::get('wanted') && array_key_exists('mbid', $_REQUEST)) {
                    $mbid = $this->requestParser->getFromRequest('mbid');

                    $walbum = new Wanted(Wanted::get_wanted($mbid));
                    $walbum->accept();
                    ob_start();
                    $walbum->show_action_buttons();
                    $results['wanted_action_' . $mbid] = ob_get_clean();
                }
                break;
            case 'delete_play':
                Stats::delete((int)$_REQUEST['activity_id']);
                ob_start();
                show_now_playing();
                $results['now_playing'] = ob_get_clean();
                ob_start();
                $user_id   = $user->id ?? -1;
                $data      = Stats::get_recently_played($user_id, 'stream', 'song');
                $ajax_page = 'index';
                Song::build_cache(array_keys($data));
                require_once Ui::find_template('show_recently_played.inc.php');
                $results['recently_played'] = ob_get_clean();
                break;
            case 'reloadnp':
                ob_start();
                show_now_playing();
                $results['now_playing'] = ob_get_clean();
                ob_start();
                $user_id   = $user->id ?? -1;
                $data      = Stats::get_recently_played($user_id, 'stream', 'song');
                $ajax_page = 'index';
                Song::build_cache(array_keys($data));
                require_once Ui::find_template('show_recently_played.inc.php');
                $results['recently_played'] = ob_get_clean();
                break;
            case 'sidebar':
                switch ($_REQUEST['button']) {
                    case 'home':
                    case 'modules':
                    case 'localplay':
                    case 'player':
                    case 'preferences':
                        $button = $_REQUEST['button'];
                        break;
                    case 'admin':
                        if (Access::check('interface', 75)) {
                            $button = $_REQUEST['button'];
                        } else {
                            return;
                        }
                        break;
                    default:
                        return;
                } // end switch on button

                Ajax::set_include_override(true);
                ob_start();
                $_SESSION['state']['sidebar_tab'] = $button;
                require_once Ui::find_template('sidebar.inc.php');
                $results['sidebar-content'] = ob_get_contents();
                ob_end_clean();
                break;
            case 'slideshow':
                ob_start();
                $images = $this->slideshow->getCurrentSlideshow();
                if (count($images) > 0) {
                    $fsname = 'fslider_' . time();
                    echo "<div id='" . $fsname . "'>";
                    foreach ($images as $image) {
                        echo "<img src='" . $image['url'] . "' alt= '' onclick='update_action();' />";
                    }
                    echo "</div>";
                    $results['fslider'] = ob_get_clean();
                    ob_start();
                    echo '<script>';
                    echo "$('#" . $fsname . "').rhinoslider({
                    showTime: 15000,
                    effectTime: 2000,
                    randomOrder: true,
                    controlsPlayPause: false,
                    autoPlay: true,
                    showBullets: 'never',
                    showControls: 'always',
                    controlsMousewheel: false,
            });";
                    echo "</script>";
                }
                $results['fslider_script'] = ob_get_clean();
                break;
            case 'songs':
                $label_id = (int) ($_REQUEST['label']);

                ob_start();
                if ($label_id > 0) {
                    $label      = new Label($label_id);
                    $object_ids = $this->songRepository->getByLabel($label->name);

                    $browse = new Browse();
                    $browse->set_type('song');
                    $browse->set_simple_browse(false);
                    $browse->save_objects($object_ids);
                    $browse->store();

                    $hide_columns = array();
                    Ui::show_box_top(T_('Songs'), 'info-box');
                    require_once Ui::find_template('show_songs.inc.php');
                    Ui::show_box_bottom();
                }

                $results['songs'] = ob_get_contents();
                ob_end_clean();
                break;
            default:
                $results['rfc3514'] = '0x1';
                break;
        } // switch on action;

        // We always do this
        echo (string) xoutput_from_array($results);
    }
}
