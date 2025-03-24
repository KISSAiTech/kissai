<?php

// DevCode Begins
// Determine the server domain based on the environment
if (strpos($_SERVER['HTTP_HOST'] ?? '', '.local') !== false) {
    define('KISSAI_SERVER_DOMAIN', "https://hubkissaiio.local");
} else {
// DevCode Ends
    define('KISSAI_SERVER_DOMAIN', "https://hub.kissai.io");
// DevCode Begins
}
// DevCode Ends

class KissAi_API_Endpoints {
    const SERVER_DOMAIN = KISSAI_SERVER_DOMAIN;
    const SUPPORT = self::SERVER_DOMAIN . "/contact-us/"; // TODO: needs to update dynamically
    const VERIFY_EMAIL = self::SERVER_DOMAIN . "/sign-up/"; // TODO: needs to update dynamically
    const SIGN_IN = self::SERVER_DOMAIN . "/sign-in/"; // TODO: needs to update dynamically
    const SERVER = self::SERVER_DOMAIN . "/kissai/api/v1";
    const REGISTER = self::SERVER . "/register/";
    const USER = self::SERVER. "/user/";
    const USER_LOGIN = self::SERVER. "/user/login/";
    const REGISTER_USER = self::SERVER . "/register/user/";
    const ASSISTANT = self::SERVER . "/assistant/";
    const FILE = self::SERVER . "/file/";
    const MESSAGE = self::SERVER . "/message/";
    const KEY = self::SERVER . "/key/";
    const KEY_VALIDATE = self::SERVER . "/key/validate/";
    const TOKENIZER = self::SERVER . "/tokenizer/";
    const TOKEN_USAGE = self::SERVER . "/token/usage/";
    const UPDATER = self::SERVER . "/updater/";
    const PLUGIN = self::SERVER . "/plugin/";
}
