<?php

class API_Base {
    public static function is_successful($response) {
        if ($response != null) {
            if (isset($response['response'])) {
                if (wp_remote_retrieve_response_code($response) == 200) {
                    return true;
                }
            }
            if (isset($response['object'])) {
                return true;
            }
        }
        return false;
    }
    public static function get_latest_version($version1, $version2) {
        // Compare the two versions
        if (version_compare($version1, $version2, '>')) {
            return $version1; // $version1 is newer
        } elseif (version_compare($version1, $version2, '<')) {
            return $version2; // $version2 is newer
        } else {
            return $version1; // Both versions are equal, return either
        }
    }

}