<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This plugin is used to access s3 files
 *
 * @since Moodle 2.0
 * @package    s3_links
 * @copyright  2010 Dongsheng Cai {@link http://dongsheng.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot . '/repository/lib.php');
require_once($CFG->dirroot . '/repository/s3/S3.php');

if (!defined('CURL_SSLVERSION_TLSv1')) {
    define('CURL_SSLVERSION_TLSv1', 1);
}

/**
 * Repository class for Amazon S3 integration
 */
class s3_links extends repository {
    /** @var string Access key */
    protected $access_key;
    /** @var string Secret key */
    protected $secret_key;
    /** @var string Endpoint URL */
    protected $endpoint;
    /** @var object S3 client */
    protected $s;
    /** @var string AWS region */
    protected $region;

    /**
     * Constructor
     * @param int $repositoryid
     * @param object $context
     * @param array $options
     */
    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        global $CFG;
        parent::__construct($repositoryid, $context, $options);
        $this->access_key = get_config('s3', 'access_key');
        $this->secret_key = get_config('s3', 'secret_key');
        $this->endpoint = get_config('s3', 'endpoint');
        
        // Extract region from endpoint and set default if needed
        $this->region = $this->get_region_from_endpoint($this->endpoint);
        
        if ($this->endpoint === false) {
            $this->endpoint = 's3.amazonaws.com';
        }

        $this->s = new S3($this->access_key, $this->secret_key, false, $this->endpoint);
        $this->s->setExceptions(true);

        // Configure proxy if needed
        if (!empty($CFG->proxyhost)) {
            if (empty($CFG->proxyport)) {
                $proxyhost = $CFG->proxyhost;
            } else {
                $proxyhost = $CFG->proxyhost . ':' . $CFG->proxyport;
            }
            $proxytype = CURLPROXY_HTTP;
            $proxyuser = null;
            $proxypass = null;
            if (!empty($CFG->proxyuser) and !empty($CFG->proxypassword)) {
                $proxyuser = $CFG->proxyuser;
                $proxypass = $CFG->proxypassword;
            }
            if (!empty($CFG->proxytype) && $CFG->proxytype == 'SOCKS5') {
                $proxytype = CURLPROXY_SOCKS5;
            }
            $this->s->setProxy($proxyhost, $proxyuser, $proxypass, $proxytype);
        }
    }

    /**
     * Extract region from endpoint
     * @param string $endpoint
     * @return string
     */
    protected function get_region_from_endpoint($endpoint) {
        if (preg_match('/s3[.-]([^.]+)\.amazonaws\.com/', $endpoint, $matches)) {
            return $matches[1];
        }
        return 'ap-southeast-2'; // Default region
    }

    /**
     * Generate direct S3 URL
     * @param string $bucket
     * @param string $uri
     * @return string
     */
    protected function generate_direct_url($bucket, $uri) {
        $region = $this->region;
        if ($region === 'external-1') {
            $region = 'us-east-1';
        }
        return "https://{$bucket}.s3.{$region}.amazonaws.com/" . rawurlencode($uri);
    }

    /**
     * Extracts the Bucket and URI from the path
     * @param string $path
     * @return array
     */
    protected function explode_path($path) {
        $parts = explode('/', $path, 2);
        if (isset($parts[1]) && $parts[1] !== '') {
            list($bucket, $uri) = $parts;
        } else {
            $bucket = $parts[0];
            $uri = '';
        }
        return array($bucket, $uri);
    }

    /**
     * Get file listing
     * @param string $path
     * @param string $page
     * @return array
     */
    public function get_listing($path = '', $page = '') {
        global $OUTPUT;
        if (empty($this->access_key)) {
            throw new moodle_exception('needaccesskey', 's3_links');
        }

        $list = array();
        $list['list'] = array();
        $list['path'] = array(
            array('name' => get_string('pluginname', 's3_links'), 'path' => '')
        );

        $list['manage'] = false;
        $list['dynload'] = true;
        $list['nologin'] = true;
        $list['nosearch'] = true;
        $tree = array();

        if (empty($path)) {
            try {
                $buckets = $this->s->listBuckets();
            } catch (S3Exception $e) {
                throw new moodle_exception(
                    'errorwhilecommunicatingwith',
                    'repository',
                    '',
                    $this->get_name(),
                    $e->getMessage()
                );
            }
            foreach ($buckets as $bucket) {
                $folder = array(
                    'title' => $bucket,
                    'children' => array(),
                    'thumbnail' => $OUTPUT->image_url(file_folder_icon())->out(false),
                    'path' => $bucket
                );
                $tree[] = $folder;
            }
        } else {
            $files = array();
            $folders = array();
            list($bucket, $uri) = $this->explode_path($path);

            try {
                $contents = $this->s->getBucket($bucket, $uri, null, null, '/', true);
            } catch (S3Exception $e) {
                throw new moodle_exception(
                    'errorwhilecommunicatingwith',
                    'repository',
                    '',
                    $this->get_name(),
                    $e->getMessage()
                );
            }
            foreach ($contents as $object) {
                if (isset($object['prefix'])) {
                    $title = rtrim($object['prefix'], '/');
                } else {
                    $title = $object['name'];
                }

                if (strlen($uri) > 0) {
                    $title = substr($title, strlen($uri));
                    if (empty($title) && !is_numeric($title)) {
                        continue;
                    }
                }

                if (isset($object['prefix'])) {
                    $folders[] = array(
                        'title' => $title,
                        'children' => array(),
                        'thumbnail' => $OUTPUT->image_url(file_folder_icon())->out(false),
                        'path' => $bucket . '/' . $object['prefix']
                    );
                } else {
                    $files[] = array(
                        'title' => $title,
                        'size' => $object['size'],
                        'datemodified' => $object['time'],
                        'source' => $bucket . '/' . $object['name'],
                        'thumbnail' => $OUTPUT->image_url(file_extension_icon($title))->out(false)
                    );
                }
            }
            $tree = array_merge($folders, $files);
        }

        $list['list'] = $tree;
        
        // Build navigation trail
        if (!empty($path)) {
            $trail = '';
            $parts = explode('/', $path);
            if (count($parts) > 1) {
                foreach ($parts as $part) {
                    if (!empty($part)) {
                        $trail .= $part . '/';
                        $list['path'][] = array('name' => $part, 'path' => $trail);
                    }
                }
            } else {
                $list['path'][] = array('name' => $path, 'path' => $path);
            }
        }

        return $list;
    }

    /**
     * Get file
     * @param string $filepath
     * @param string $file
     * @return array
     */
    public function get_file($filepath, $file = '') {
        list($bucket, $uri) = $this->explode_path($filepath);
        try {
            $url = $this->generate_direct_url($bucket, $uri);
            return array(
                'url' => $url,
                'filepath' => $filepath,
                'filename' => basename($uri),
                'filesize' => $this->s->getObjectInfo($bucket, $uri)['size']
            );
        } catch (S3Exception $e) {
            throw new moodle_exception(
                'errorwhilecommunicatingwith',
                'repository',
                '',
                $this->get_name(),
                $e->getMessage()
            );
        }
    }

    /**
     * Get file source information
     * @param stdClass $filepath
     * @return string
     */
    public function get_file_source_info($filepath) {
        return 'Amazon S3 URL: ' . $filepath;
    }

    /**
     * Check login
     * @return bool
     */
    public function check_login() {
        return true;
    }

    /**
     * Check if global search is supported
     * @return bool
     */
    public function global_search() {
        return false;
    }

    /**
     * Get link
     * @param string $reference
     * @return string
     */
    public function get_link($reference) {
        list($bucket, $uri) = $this->explode_path($reference);
        try {
            return $this->generate_direct_url($bucket, $uri);
        } catch (S3Exception $e) {
            throw new moodle_exception(
                'errorwhilecommunicatingwith',
                'repository',
                '',
                $this->get_name(),
                $e->getMessage()
            );
        }
    }

    /**
     * Supported return types
     * @return int
     */
    public function supported_returntypes() {
        return FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE;
    }

    /**
     * Supported features
     * @return int
     */
    public function supported_features() {
        return FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE | FILE_CONTROLLED_LINK;
    }

    /**
     * Get type option names
     * @return array
     */
    public static function get_type_option_names() {
        return array('access_key', 'secret_key', 'endpoint', 'pluginname');
    }

    /**
     * Type config form
     * @param object $mform
     * @param string $classname
     */
    public static function type_config_form($mform, $classname = 'repository') {
        parent::type_config_form($mform);
        $strrequired = get_string('required');
        $endpointselect = array(
            "s3.amazonaws.com" => "s3.amazonaws.com",
            "s3-external-1.amazonaws.com" => "s3-external-1.amazonaws.com",
            "s3-us-west-2.amazonaws.com" => "s3-us-west-2.amazonaws.com",
            "s3-us-west-1.amazonaws.com" => "s3-us-west-1.amazonaws.com",
            "s3-eu-west-1.amazonaws.com" => "s3-eu-west-1.amazonaws.com",
            "s3.eu-central-1.amazonaws.com" => "s3.eu-central-1.amazonaws.com",
            "s3-eu-central-1.amazonaws.com" => "s3-eu-central-1.amazonaws.com",
            "s3-ap-southeast-1.amazonaws.com" => "s3-ap-southeast-1.amazonaws.com",
            "s3-ap-southeast-2.amazonaws.com" => "s3-ap-southeast-2.amazonaws.com",
            "s3-ap-northeast-1.amazonaws.com" => "s3-ap-northeast-1.amazonaws.com",
            "s3-sa-east-1.amazonaws.com" => "s3-sa-east-1.amazonaws.com"
        );
        
        $mform->addElement('text', 'access_key', get_string('access_key', 's3_links'));
        $mform->setType('access_key', PARAM_RAW_TRIMMED);
        $mform->addElement('text', 'secret_key', get_string('secret_key', 's3_links'));
        $mform->setType('secret_key', PARAM_RAW_TRIMMED);
        $mform->addElement('select', 'endpoint', get_string('endpoint', 's3_links'), $endpointselect);
        $mform->setDefault('endpoint', 's3.amazonaws.com');
        $mform->addRule('access_key', $strrequired, 'required', null, 'client');
        $mform->addRule('secret_key', $strrequired, 'required', null, 'client');
    }

    /**
     * Contains private data check
     * @return bool
     */
    public function contains_private_data() {
        return false;
    }
}